// public/js/modules/customer-search-controller.js
// Wires the shared customer search utility into the global LexBridge namespace

(function () {
    if (!window.lexBridge) {
        window.lexBridge = {};
    }

    if (window.lexBridge.customerSearchController) {
        return;
    }

    if (window.lexBridgeUtils && typeof window.lexBridgeUtils.createCustomerSearchController === 'function') {
        const controller = window.lexBridgeUtils.createCustomerSearchController({
            inputSelector: '.customer-search-combobox',
            hiddenFieldName: 'customer_id'
        });
        controller.attach();
        window.lexBridge.customerSearchController = controller;
        return;
    }

    console.warn('customer-search-controller.js: shared utilities not available; customer search combo will not initialise.');
})();
