'use strict';

(function () {
    const globalObject = typeof window !== 'undefined' ? window : globalThis;
    const existing = globalObject.lexBridgeUtils || {};

    function escapeHtml(value) {
        const div = globalObject.document ? globalObject.document.createElement('div') : null;
        if (!div) {
            const text = value == null ? '' : String(value);
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    globalObject.lexBridgeUtils = Object.assign({}, existing, {
        escapeHtml
    });
})();
