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

    global.UobAdvancedSearch = Object.freeze({
        normalize,
        parse,
        matches,
        inDateRange
    });
})(window);
