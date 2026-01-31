// public/js/utils/form-interceptor.js
// Centralized AJAX form submit interception utility

(function () {
    'use strict';

    const globalObject = typeof window !== 'undefined' ? window : globalThis;
    const documentObject = globalObject.document;
    if (!documentObject) {
        return;
    }

    // Registry for form selectors and their handlers
    const formHandlerRegistry = [];

    function registerAjaxFormHandler(selector, handler) {
        if (typeof selector !== 'string' || typeof handler !== 'function') {
            throw new Error('registerAjaxFormHandler requires a selector string and a handler function');
        }
        formHandlerRegistry.push({ selector, handler });
    }

    // Global submit event listener (single instance)
    documentObject.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        for (const { selector, handler } of formHandlerRegistry) {
            if (form.matches(selector)) {
                event.preventDefault();
                event.stopPropagation();
                try {
                    await handler(form, event);
                } catch (error) {
                    // Optionally, add global error handling here
                    if (globalObject.lexBridge?.toastNotifier) {
                        globalObject.lexBridge.toastNotifier.show(error.message || 'Formularfehler', 'error');
                    } else {
                        window.alert(error.message || 'Formularfehler');
                    }
                }
                break;
            }
        }
    }, true);

    // Expose registration function globally
    if (!globalObject.lexBridgeUtils) {
        globalObject.lexBridgeUtils = {};
    }
    globalObject.lexBridgeUtils.registerAjaxFormHandler = registerAjaxFormHandler;
})();
