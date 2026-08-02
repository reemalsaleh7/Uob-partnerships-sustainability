(function () {
    'use strict';

    const config = window.WorkspaceI18nConfig || {};
    const language = String(config.language || 'en');
    const translations = config.translations || {};
    const patternDefinitions = Array.isArray(config.patterns)
        ? config.patterns
        : [];
    const patterns = patternDefinitions
        .map((definition) => {
            try {
                return {
                    regex: new RegExp(
                        String(definition.source || ''),
                        'u'
                    ),
                    replacement: String(
                        definition.replacement || ''
                    )
                };
            } catch (error) {
                return null;
            }
        })
        .filter(Boolean);

    const dateTokens = new Map([
        ['January', 'يناير'],
        ['February', 'فبراير'],
        ['March', 'مارس'],
        ['April', 'أبريل'],
        ['May', 'مايو'],
        ['June', 'يونيو'],
        ['July', 'يوليو'],
        ['August', 'أغسطس'],
        ['September', 'سبتمبر'],
        ['October', 'أكتوبر'],
        ['November', 'نوفمبر'],
        ['December', 'ديسمبر'],
        ['Jan', 'يناير'],
        ['Feb', 'فبراير'],
        ['Mar', 'مارس'],
        ['Apr', 'أبريل'],
        ['Jun', 'يونيو'],
        ['Jul', 'يوليو'],
        ['Aug', 'أغسطس'],
        ['Sep', 'سبتمبر'],
        ['Sept', 'سبتمبر'],
        ['Oct', 'أكتوبر'],
        ['Nov', 'نوفمبر'],
        ['Dec', 'ديسمبر'],
        ['Sunday', 'الأحد'],
        ['Monday', 'الاثنين'],
        ['Tuesday', 'الثلاثاء'],
        ['Wednesday', 'الأربعاء'],
        ['Thursday', 'الخميس'],
        ['Friday', 'الجمعة'],
        ['Saturday', 'السبت'],
        ['AM', 'ص'],
        ['PM', 'م']
    ]);

    function normalizedText(value) {
        return String(value ?? '')
            .replace(/\s+/gu, ' ')
            .trim();
    }

    function escapeRegularExpression(value) {
        return String(value).replace(
            /[.*+?^${}()|[\]\\]/gu,
            '\\$&'
        );
    }

    function translateDateTokens(value) {
        let result = String(value ?? '');

        dateTokens.forEach((replacement, source) => {
            const expression = new RegExp(
                `(^|[^\\p{L}])${escapeRegularExpression(source)}`
                    + `(?=$|[^\\p{L}])`,
                'giu'
            );

            result = result.replace(
                expression,
                (match, prefix) =>
                    `${prefix}${replacement}`
            );
        });

        return result;
    }

    function translateInternal(value, depth = 0) {
        const original = normalizedText(value);

        if (language !== 'ar' || original === '') {
            return String(value ?? '');
        }

        if (
            Object.prototype.hasOwnProperty.call(
                translations,
                original
            )
        ) {
            return String(translations[original]);
        }

        for (const pattern of patterns) {
            if (pattern.regex.test(original)) {
                return translateDateTokens(
                    original.replace(
                        pattern.regex,
                        pattern.replacement
                    )
                );
            }
        }

        if (/^[A-Z][A-Z0-9_ ]{1,100}$/u.test(original)) {
            const humanized = original.replaceAll('_', ' ');
            const titleCased = humanized
                .toLowerCase()
                .replace(
                    /(^|\s)\p{L}/gu,
                    (letter) => letter.toUpperCase()
                );

            for (const candidate of [
                original,
                humanized,
                titleCased
            ]) {
                if (
                    Object.prototype.hasOwnProperty.call(
                        translations,
                        candidate
                    )
                ) {
                    return String(translations[candidate]);
                }
            }
        }

        if (depth < 3) {
            const arrowMatch = original.match(
                /^(←|→)?\s*(.*?)\s*(←|→)?$/u
            );

            if (
                arrowMatch
                && (arrowMatch[1] || arrowMatch[3])
                && arrowMatch[2]
            ) {
                const translatedCore = translateInternal(
                    arrowMatch[2],
                    depth + 1
                );

                if (translatedCore !== arrowMatch[2]) {
                    return language === 'ar'
                        ? `${translatedCore} ←`
                        : original;
                }
            }

            for (const delimiter of [' · ', ' → ', ' — ']) {
                if (!original.includes(delimiter)) {
                    continue;
                }

                const parts = original.split(delimiter);
                const translatedParts = parts.map(
                    (part) => translateInternal(
                        part,
                        depth + 1
                    )
                );

                if (
                    translatedParts.some(
                        (part, index) => part !== parts[index]
                    )
                ) {
                    return translatedParts.join(delimiter);
                }
            }

            const colon = original.match(
                /^([^:]{1,90}):\s+(.+)$/u
            );

            if (colon) {
                const prefix = translateInternal(
                    colon[1],
                    depth + 1
                );

                if (prefix !== colon[1]) {
                    return `${prefix}: ${
                        translateDateTokens(colon[2])
                    }`;
                }
            }
        }

        return translateDateTokens(original);
    }

    function translate(value, variables = {}) {
        let translated = translateInternal(value);

        Object.entries(variables).forEach(
            ([key, replacement]) => {
                translated = translated.replaceAll(
                    `{${key}}`,
                    String(replacement)
                );
            }
        );

        return translated;
    }

    window.workspaceT = translate;

    if (language !== 'ar') {
        window.workspaceTranslateNode = function () {};
        return;
    }

    document.documentElement.lang = 'ar';
    document.documentElement.dir = 'rtl';

    const blockedTags = new Set([
        'SCRIPT',
        'STYLE',
        'CODE',
        'PRE',
        'TEXTAREA'
    ]);
    const translatedAttributes = [
        'placeholder',
        'title',
        'aria-label',
        'aria-description',
        'alt',
        'data-bs-title',
        'data-bs-original-title'
    ];

    function isBlocked(node) {
        const element = node.nodeType === Node.ELEMENT_NODE
            ? node
            : node.parentElement;

        return !element
            || blockedTags.has(element.tagName)
            || Boolean(
                element.closest('[data-i18n-skip]')
            );
    }

    function translateTextNode(node) {
        if (
            node.nodeType !== Node.TEXT_NODE
            || isBlocked(node)
        ) {
            return;
        }

        const raw = String(node.nodeValue || '');
        const core = normalizedText(raw);

        if (core === '') {
            return;
        }

        const translated = translate(core);

        if (translated === core) {
            return;
        }

        const leading = raw.match(/^\s*/u)?.[0] || '';
        const trailing = raw.match(/\s*$/u)?.[0] || '';

        node.nodeValue = `${
            leading
        }${translated}${trailing}`;
    }

    function translateButtonValue(element) {
        if (!(element instanceof HTMLInputElement)) {
            return;
        }

        if (
            !['button', 'submit', 'reset'].includes(
                element.type
            )
        ) {
            return;
        }

        const translated = translate(element.value);

        if (translated !== normalizedText(element.value)) {
            element.value = translated;
        }
    }

    function translateAttributes(element) {
        if (
            !(element instanceof Element)
            || isBlocked(element)
        ) {
            return;
        }

        translatedAttributes.forEach((attribute) => {
            if (!element.hasAttribute(attribute)) {
                return;
            }

            const current =
                element.getAttribute(attribute) || '';
            const translated = translate(current);

            if (
                translated !== normalizedText(current)
            ) {
                element.setAttribute(
                    attribute,
                    translated
                );
            }
        });

        translateButtonValue(element);
    }

    function translateNode(root) {
        if (!root) {
            return;
        }

        if (root.nodeType === Node.TEXT_NODE) {
            translateTextNode(root);
            return;
        }

        if (
            !(root instanceof Element)
            && root !== document
        ) {
            return;
        }

        if (root instanceof Element) {
            translateAttributes(root);
        }

        const walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_ELEMENT
                | NodeFilter.SHOW_TEXT
        );
        let current = walker.nextNode();

        while (current) {
            if (current.nodeType === Node.TEXT_NODE) {
                translateTextNode(current);
            } else {
                translateAttributes(current);
            }

            current = walker.nextNode();
        }
    }

    window.workspaceTranslateNode = translateNode;

    const nativeAppend = Element.prototype.append;
    const nativeAppendChild = Node.prototype.appendChild;
    const nativeReplaceChildren = Element.prototype.replaceChildren;

    Element.prototype.append = function (...nodes) {
        const result = nativeAppend.apply(this, nodes);
        window.queueMicrotask(() => translateNode(this));
        return result;
    };

    Node.prototype.appendChild = function (node) {
        const result = nativeAppendChild.call(this, node);
        window.queueMicrotask(() => translateNode(node));
        return result;
    };

    Element.prototype.replaceChildren = function (...nodes) {
        const result = nativeReplaceChildren.apply(this, nodes);
        window.queueMicrotask(() => translateNode(this));
        return result;
    };

    translateNode(document.documentElement);
    document.title = translate(document.title);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'characterData') {
                translateTextNode(mutation.target);
                return;
            }

            if (mutation.type === 'attributes') {
                translateAttributes(mutation.target);
                return;
            }

            mutation.addedNodes.forEach((node) => {
                translateNode(node);
            });
        });
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: translatedAttributes
    });

    const originalConfirm = window.confirm.bind(window);
    const originalAlert = window.alert.bind(window);
    const originalPrompt = window.prompt.bind(window);

    window.confirm = (message) => originalConfirm(
        translate(message)
    );
    window.alert = (message) => originalAlert(
        translate(message)
    );
    window.prompt = (
        message,
        defaultValue
    ) => originalPrompt(
        translate(message),
        defaultValue
    );

    [
        window.HTMLInputElement,
        window.HTMLSelectElement,
        window.HTMLTextAreaElement
    ].filter(Boolean).forEach((Constructor) => {
        const nativeMethod =
            Constructor.prototype.setCustomValidity;

        if (
            typeof nativeMethod !== 'function'
            || nativeMethod.__workspaceI18nWrapped
        ) {
            return;
        }

        function translatedSetCustomValidity(message) {
            return nativeMethod.call(
                this,
                message ? translate(message) : ''
            );
        }

        translatedSetCustomValidity
            .__workspaceI18nWrapped = true;
        Constructor.prototype.setCustomValidity =
            translatedSetCustomValidity;
    });
}());
