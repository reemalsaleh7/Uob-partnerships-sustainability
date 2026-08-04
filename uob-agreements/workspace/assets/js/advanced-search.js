(function (global) {
    'use strict';

    function normalize(value) {
        return String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replaceAll('_', ' ')
            .toLocaleLowerCase()
            .trim();
    }

    function tokenize(query) {
        const tokens = [];
        let token = '';
        let quoted = false;
        let escaped = false;

        for (const character of String(query || '').trim()) {
            if (escaped) {
                token += character;
                escaped = false;
                continue;
            }
            if (character === '\\') {
                escaped = true;
                continue;
            }
            if (character === '"') {
                quoted = !quoted;
                continue;
            }
            if (/\s/.test(character) && !quoted) {
                if (token) tokens.push(token);
                token = '';
                continue;
            }
            token += character;
        }

        if (escaped) token += '\\';
        if (token) tokens.push(token);
        return tokens;
    }

    function parseToken(rawToken) {
        let token = rawToken;
        let excluded = false;
        if (token.startsWith('-') || token.startsWith('!')) {
            excluded = true;
            token = token.slice(1);
        }

        const comparison = token.match(/^([a-z][a-z0-9_-]*)(<=|>=|<|>)(.+)$/i);
        if (comparison) {
            return {
                excluded,
                field: comparison[1].toLowerCase(),
                operator: comparison[2],
                values: [comparison[3]]
            };
        }

        const separator = token.indexOf(':');
        const hasField = separator > 0
            && /^[a-z][a-z0-9_-]*$/i.test(token.slice(0, separator));
        const field = hasField ? token.slice(0, separator).toLowerCase() : 'any';
        const rawValue = hasField ? token.slice(separator + 1) : token;

        return {
            excluded,
            field,
            operator: ':',
            values: rawValue.split('|').map(normalize).filter(Boolean)
        };
    }

    function parse(query) {
        return tokenize(query)
            .map(parseToken)
            .filter((clause) => clause.values.length > 0);
    }

    function valuesFor(record, field, schema) {
        const source = schema[field] || (field === 'any' ? schema.any : null);
        if (!source) return [];
        const value = typeof source === 'function' ? source(record) : record[source];
        return (Array.isArray(value) ? value : [value])
            .filter((item) => item !== null && item !== undefined)
            .map(normalize);
    }

    function compare(candidate, expected, operator) {
        if (operator === ':') {
            return candidate.includes(expected);
        }

        const dateComparison = /^\d{4}-\d{2}-\d{2}/.test(candidate)
            && /^\d{4}-\d{2}-\d{2}/.test(expected);
        const left = dateComparison ? Date.parse(candidate) : Number(candidate);
        const right = dateComparison ? Date.parse(expected) : Number(expected);
        if (Number.isNaN(left) || Number.isNaN(right)) return false;

        if (operator === '<') return left < right;
        if (operator === '<=') return left <= right;
        if (operator === '>') return left > right;
        return left >= right;
    }

    function clauseMatches(record, clause, schema) {
        const candidates = valuesFor(record, clause.field, schema);
        if (candidates.length === 0) return false;

        return clause.values.some((expected) =>
            candidates.some((candidate) => compare(candidate, expected, clause.operator))
        );
    }

    function matches(record, query, schema) {
        return parse(query).every((clause) => {
            const result = clauseMatches(record, clause, schema);
            return clause.excluded ? !result : result;
        });
    }

    function inDateRange(value, from, to) {
        if (!from && !to) return true;
        const date = Date.parse(value || '');
        if (Number.isNaN(date)) return false;
        if (from && date < Date.parse(`${from}T00:00:00`)) return false;
        if (to && date > Date.parse(`${to}T23:59:59.999`)) return false;
        return true;
    }

    const textOperators = [
        ['contains', 'contains'],
        ['not_contains', 'does not contain'],
        ['equals', 'is exactly'],
        ['not_equals', 'is not']
    ];

    const orderedOperators = [
        ['equals', 'is'],
        ['before', 'is before'],
        ['on_or_before', 'is on or before'],
        ['after', 'is after'],
        ['on_or_after', 'is on or after']
    ];

    function compareRuleValue(candidate, expected, operator, type) {
        const leftNormalized = normalize(candidate);
        const rightNormalized = normalize(expected);

        if (operator === 'contains') return leftNormalized.includes(rightNormalized);
        if (operator === 'not_contains') return !leftNormalized.includes(rightNormalized);
        if (operator === 'equals' && type === 'text') return leftNormalized === rightNormalized;
        if (operator === 'not_equals') return leftNormalized !== rightNormalized;

        const orderedType = type === 'date' || type === 'number';
        if (!orderedType) return leftNormalized === rightNormalized;
        const left = type === 'date' ? Date.parse(candidate) : Number(candidate);
        const right = type === 'date' ? Date.parse(expected) : Number(expected);
        if (Number.isNaN(left) || Number.isNaN(right)) return false;
        if (operator === 'before') return left < right;
        if (operator === 'on_or_before') return left <= right;
        if (operator === 'after') return left > right;
        if (operator === 'on_or_after') return left >= right;
        return left === right;
    }

    function ruleMatches(record, rule, schema) {
        const candidates = valuesFor(record, rule.field, schema);
        if (candidates.length === 0) return false;

        if (rule.operator === 'not_contains' || rule.operator === 'not_equals') {
            return candidates.every((candidate) =>
                compareRuleValue(candidate, rule.value, rule.operator, rule.type)
            );
        }

        return candidates.some((candidate) =>
            compareRuleValue(candidate, rule.value, rule.operator, rule.type)
        );
    }

    function matchesRules(record, rules, schema, mode = 'all') {
        const validRules = (Array.isArray(rules) ? rules : []).filter((rule) =>
            rule && rule.field && String(rule.value || '').trim()
        );
        if (validRules.length === 0) return true;
        const results = validRules.map((rule) => ruleMatches(record, rule, schema));
        return mode === 'any' ? results.some(Boolean) : results.every(Boolean);
    }

    function appendOption(select, value, label) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.append(option);
    }

    function createFilterController(options) {
        const root = typeof options.root === 'string'
            ? document.querySelector(options.root)
            : options.root;
        if (!root) {
            return {
                clearRules() {},
                matchesRules() { return true; },
                refresh() {}
            };
        }

        const schema = options.schema || {};
        const fields = Array.isArray(options.fields) ? options.fields : [];
        const onChange = typeof options.onChange === 'function'
            ? options.onChange
            : () => {};
        const toggle = root.querySelector('[data-advanced-search-toggle]');
        const panel = root.querySelector('[data-advanced-search-panel]');
        const count = root.querySelector('[data-advanced-filter-count]');
        const summary = root.querySelector('[data-advanced-filter-summary]');
        const list = root.querySelector('[data-search-rule-list]');
        const add = root.querySelector('[data-add-search-rule]');
        const mode = root.querySelector('[data-search-match-mode]');
        const modeWrap = root.querySelector('[data-search-match-mode-wrap]');

        function setExpanded(expanded) {
            if (!toggle || !panel) return;
            toggle.setAttribute('aria-expanded', String(expanded));
            panel.hidden = !expanded;
            root.classList.toggle('is-expanded', expanded);
        }

        function operatorsFor(type) {
            return type === 'date' || type === 'number'
                ? orderedOperators
                : textOperators;
        }

        function syncRule(row) {
            const fieldSelect = row.querySelector('[data-rule-field]');
            const operatorSelect = row.querySelector('[data-rule-operator]');
            const valueInput = row.querySelector('[data-rule-value]');
            const field = fields.find((item) => item.key === fieldSelect.value) || fields[0];
            const previousOperator = operatorSelect.value;
            operatorSelect.replaceChildren();
            operatorsFor(field?.type || 'text').forEach(([value, label]) =>
                appendOption(operatorSelect, value, label)
            );
            if ([...operatorSelect.options].some((item) => item.value === previousOperator)) {
                operatorSelect.value = previousOperator;
            }
            valueInput.type = field?.type === 'date' ? 'date'
                : field?.type === 'number' ? 'number'
                    : 'text';
            valueInput.placeholder = field?.placeholder || 'Enter a value';
            valueInput.setAttribute('aria-label', `${field?.label || 'Condition'} value`);
        }

        function addRule(initial = {}) {
            if (!list || fields.length === 0) return;
            const row = document.createElement('div');
            row.className = 'search-rule-row';

            const fieldSelect = document.createElement('select');
            fieldSelect.className = 'form-select form-select-sm';
            fieldSelect.dataset.ruleField = '';
            fieldSelect.setAttribute('aria-label', 'Search field');
            fields.forEach((field) => appendOption(fieldSelect, field.key, field.label));
            if (initial.field) fieldSelect.value = initial.field;

            const operatorSelect = document.createElement('select');
            operatorSelect.className = 'form-select form-select-sm';
            operatorSelect.dataset.ruleOperator = '';
            operatorSelect.setAttribute('aria-label', 'Condition');

            const valueInput = document.createElement('input');
            valueInput.className = 'form-control form-control-sm';
            valueInput.dataset.ruleValue = '';
            valueInput.value = initial.value || '';

            const remove = document.createElement('button');
            remove.className = 'btn btn-sm search-rule-remove';
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Remove condition');
            remove.textContent = 'Remove';

            row.append(fieldSelect, operatorSelect, valueInput, remove);
            list.append(row);
            syncRule(row);
            if (initial.operator) operatorSelect.value = initial.operator;
            fieldSelect.addEventListener('change', () => {
                syncRule(row);
                refresh();
                onChange();
            });
            operatorSelect.addEventListener('change', () => {
                refresh();
                onChange();
            });
            valueInput.addEventListener('input', () => {
                refresh();
                onChange();
            });
            remove.addEventListener('click', () => {
                row.remove();
                refresh();
                onChange();
                add?.focus();
            });
            refresh();
            valueInput.focus();
        }

        function readRules() {
            if (!list) return [];
            return [...list.querySelectorAll('.search-rule-row')].map((row) => {
                const fieldKey = row.querySelector('[data-rule-field]').value;
                const field = fields.find((item) => item.key === fieldKey);
                return {
                    field: fieldKey,
                    type: field?.type || 'text',
                    operator: row.querySelector('[data-rule-operator]').value,
                    value: row.querySelector('[data-rule-value]').value.trim()
                };
            }).filter((rule) => rule.value !== '');
        }

        function activeAdvancedInputs() {
            return [...root.querySelectorAll('[data-advanced-filter]')]
                .filter((input) => String(input.value || '').trim() !== '');
        }

        function refresh() {
            const activeInputs = activeAdvancedInputs();
            const rules = readRules();
            const activeCount = activeInputs.length + rules.length;
            if (count) {
                count.textContent = String(activeCount);
                count.classList.toggle('d-none', activeCount === 0);
            }
            if (summary) {
                const labels = activeInputs.map((input) => input.dataset.filterLabel || 'filter');
                if (rules.length > 0) labels.push(`${rules.length} precise ${rules.length === 1 ? 'condition' : 'conditions'}`);
                summary.textContent = activeCount > 0
                    ? `${activeCount} additional ${activeCount === 1 ? 'filter is' : 'filters are'} active: ${labels.join(', ')}`
                    : '';
                summary.classList.toggle('d-none', activeCount === 0 || !panel?.hidden);
            }
            const ruleCount = list?.querySelectorAll('.search-rule-row').length || 0;
            modeWrap?.classList.toggle('d-none', ruleCount < 2);
        }

        function clearRules() {
            list?.replaceChildren();
            if (mode) mode.selectedIndex = 0;
            refresh();
        }

        toggle?.addEventListener('click', () => {
            setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
            refresh();
        });
        add?.addEventListener('click', () => addRule());
        mode?.addEventListener('change', () => {
            refresh();
            onChange();
        });
        root.querySelectorAll('[data-advanced-filter]').forEach((input) => {
            input.addEventListener('change', refresh);
        });
        refresh();

        return {
            clearRules,
            matchesRules(record) {
                return matchesRules(record, readRules(), schema, mode?.value || 'all');
            },
            refresh,
            setExpanded
        };
    }

    global.UobAdvancedSearch = Object.freeze({
        normalize,
        parse,
        matches,
        inDateRange,
        matchesRules,
        createFilterController
    });
})(window);
