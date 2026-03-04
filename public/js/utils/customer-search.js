'use strict';

(function () {

    /**
     * CustomerSearchController
     * 
     * Manages customer search combobox inputs with datalist autocomplete.
     * Handles debounced API searches, populates datalist options, and syncs
     * selected customer IDs to hidden form fields.
     * 
     * Uses event delegation so it works with dynamically added inputs.
     */
    class CustomerSearchController 
    {
        static DEFAULT_SELECTOR = '.customer-search-combobox';
        static DEFAULT_HIDDEN_NAME = 'customer_id';
        static DEFAULT_DATALIST_OPTION = '<option value="">Alle Kunden</option>';

        /**
         * Extracts customer number from a display value like "12345 - Company Name"
         * @param {string} value - The input value to parse
         * @returns {string} The numeric customer number prefix, or empty string if not found
         */
        static extractCustomerNumber(value) {
            if (typeof value !== 'string') {
                return '';
            }

            const prefix = value.split('-')[0]?.trim() || '';
            return /^\d+$/.test(prefix) ? prefix : '';
        }

        /**
         * Normalises a value to a trimmed string
         * @param {*} value - Any value to normalise
         * @returns {string} Trimmed string representation
         */
        static normaliseString(value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value).trim();
        }

        /**
         * Creates a new CustomerSearchController instance
         * @param {Object} options - Configuration options
         * @param {string} [options.inputSelector='.customer-search-combobox'] - CSS selector for search inputs
         * @param {string|null} [options.formSelector=null] - Optional CSS selector to find parent form
         * @param {string} [options.hiddenFieldName='customer_id'] - Name attribute of hidden field to populate
         * @param {number} [options.debounceMs=250] - Debounce delay for API calls in milliseconds
         * @param {Object} [options.requestHeaders={}] - Additional headers for fetch requests
         */
        constructor(options = {}) {
            this.inputSelector = options.inputSelector || CustomerSearchController.DEFAULT_SELECTOR;
            this.formSelector = options.formSelector || null;
            this.hiddenFieldName = options.hiddenFieldName || CustomerSearchController.DEFAULT_HIDDEN_NAME;
            this.debounceMs = typeof options.debounceMs === 'number' ? options.debounceMs : 250;
            this.requestHeaders = options.requestHeaders || {};

            this.timerMap = new WeakMap();
            this.attached = false;

            this.boundHandleInput = this.handleInput.bind(this);
            this.boundHandleChange = this.handleChange.bind(this);
        }

        /**
         * Attaches event listeners to the document for event delegation.
         * Safe to call multiple times - will only attach once.
         */
        attach() {
            if (this.attached) {
                return;
            }

            document.addEventListener('input', this.boundHandleInput, true);
            document.addEventListener('change', this.boundHandleChange, true);
            this.attached = true;
        }

        /**
         * Removes event listeners from the document.
         * Safe to call multiple times.
         */
        detach() {
            if (!this.attached) {
                return;
            }

            document.removeEventListener('input', this.boundHandleInput, true);
            document.removeEventListener('change', this.boundHandleChange, true);
            this.attached = false;
        }

        /**
         * Ensures the hidden field is synced with the current input selection.
         * Call this before form submission to guarantee the customer_id is set.
         * @param {HTMLFormElement|HTMLInputElement} formOrInput - The form or input element
         */
        ensureSelection(formOrInput) {
            if (!formOrInput) {
                return;
            }

            let inputElement = null;
            if (formOrInput instanceof HTMLInputElement) {
                inputElement = formOrInput;
            } else if (formOrInput instanceof HTMLFormElement) {
                inputElement = formOrInput.querySelector(this.inputSelector);
            }

            if (inputElement instanceof HTMLInputElement) {
                const datalist = this.getDatalist(inputElement);
                this.syncSelection(inputElement, datalist);
            }
        }

        /**
         * Handles input events on customer search fields.
         * Triggers debounced API search and syncs selection.
         * @param {Event} event - The input event
         */
        handleInput(event) {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (!target.matches(this.inputSelector)) {
                return;
            }

            const datalist = this.getDatalist(target);
            if (!datalist) {
                return;
            }

            const query = target.value.trim();
            if (query === '') {
                datalist.innerHTML = CustomerSearchController.DEFAULT_DATALIST_OPTION;
                this.syncSelection(target, datalist);
                this.clearTimer(target);
                return;
            }

            this.syncSelection(target, datalist);
            this.scheduleFetch(target, datalist, query);
        }

        /**
         * Handles change events on customer search fields.
         * Syncs the selection when user selects from datalist.
         * @param {Event} event - The change event
         */
        handleChange(event) {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (!target.matches(this.inputSelector)) {
                return;
            }

            const datalist = this.getDatalist(target);
            if (!datalist) {
                return;
            }

            this.syncSelection(target, datalist);
        }

        /**
         * Schedules a debounced API fetch for customer search.
         * Cancels any pending fetch for the same input.
         * @param {HTMLInputElement} input - The search input element
         * @param {HTMLDataListElement} datalist - The associated datalist
         * @param {string} query - The search query
         */
        scheduleFetch(input, datalist, query) {
            const existingTimer = this.timerMap.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
            }

            const timer = setTimeout(async () => {
                try {
                    const customers = await this.fetchCustomers(query);
                    this.populateOptions(datalist, customers);
                } catch (error) {
                    console.error('Customer search fetch failed:', error);
                } finally {
                    this.timerMap.delete(input);
                    this.syncSelection(input, datalist);
                }
            }, this.debounceMs);

            this.timerMap.set(input, timer);
        }

        /**
         * Clears any pending debounce timer for an input
         * @param {HTMLInputElement} input - The input element
         */
        clearTimer(input) {
            const existingTimer = this.timerMap.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
                this.timerMap.delete(input);
            }
        }

        /**
         * Fetches customers from the API matching the query
         * @param {string} query - The search query
         * @returns {Promise<Array>} Array of customer objects
         * @throws {Error} If the HTTP request fails
         */
        async fetchCustomers(query) {
            const url = window.LexBridge.resolveApiUrl(`customers/search?q=${encodeURIComponent(query)}`);
            const response = await fetch(url, {
                headers: this.requestHeaders
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            const text = await response.text();
            const trimmed = text.trim();
            if (trimmed === '') {
                return [];
            }

            try {
                const data = JSON.parse(trimmed);
                return Array.isArray(data) ? data : [];
            } catch (error) {
                console.error('Customer search parse error:', error, trimmed);
                return [];
            }
        }

        /**
         * Populates a datalist with customer options.
         * Deduplicates by customer number and label.
         * @param {HTMLDataListElement} datalist - The datalist to populate
         * @param {Array} customers - Array of customer objects from API
         */
        populateOptions(datalist, customers) {
            datalist.innerHTML = CustomerSearchController.DEFAULT_DATALIST_OPTION;
            if (!Array.isArray(customers) || customers.length === 0) {
                return;
            }

            const seenLabels = new Set();
            customers.forEach((customer) => {
                const number = CustomerSearchController.normaliseString(customer.customer_number ?? customer.customerNumber);
                const name = CustomerSearchController.normaliseString(customer.company_name ?? customer.companyName);
                const id = CustomerSearchController.normaliseString(customer.id);

                const labelParts = [];
                if (number) {
                    labelParts.push(number);
                }
                if (name) {
                    labelParts.push(name);
                }
                const label = labelParts.join(' - ') || number || name;
                const resolvedLabel = label || '';

                if (seenLabels.has(`${number}|${resolvedLabel}`)) {
                    return;
                }
                seenLabels.add(`${number}|${resolvedLabel}`);

                const option = document.createElement('option');
                option.value = resolvedLabel;

                if (id) {
                    option.dataset.customerId = id;
                    option.setAttribute('data-customer-id', id);
                }

                if (number) {
                    option.dataset.customerNumber = number;
                    option.setAttribute('data-customer-number', number);
                }

                datalist.appendChild(option);
            });
        }

        /**
         * Synchronises the hidden field value with the current input selection.
         * 
         * Matching strategy:
         * 1. Use cached selection if input value unchanged
         * 2. Exact match against datalist option values (case-insensitive)
         * 3. Fallback: match by customer number prefix extracted from input
         * 
         * Caches successful matches on the input element for form submission.
         * 
         * @param {HTMLInputElement} input - The search input element
         * @param {HTMLDataListElement|null} datalist - The associated datalist (may be null)
         */
        syncSelection(input, datalist) {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const form = this.resolveForm(input);
            if (!form) {
                return;
            }

            const hidden = form.querySelector(`input[type="hidden"][name="${this.hiddenFieldName}"]`);
            if (!(hidden instanceof HTMLInputElement)) {
                return;
            }

            const value = input.value.trim();
            if (value === '') {
                hidden.value = '';
                input.dataset.selectedCustomerId = '';
                return;
            }

            // First check if we have a cached selection on the input
            if (input.dataset.selectedCustomerId && input.dataset.selectedValue === value) {
                hidden.value = input.dataset.selectedCustomerId;
                return;
            }

            hidden.value = '';
            if (!datalist) {
                return;
            }

            const options = datalist instanceof HTMLDataListElement && datalist.options
                ? Array.from(datalist.options)
                : Array.from(datalist.children);
            const lowerValue = value.toLowerCase();
            let matchedId = '';
            let matchedByNumber = '';

            // Extract customer number from input (e.g., "12345 - Company" => "12345")
            const inputCustomerNumber = CustomerSearchController.extractCustomerNumber(value);

            for (const optionElement of options) {
                if (!(optionElement instanceof HTMLOptionElement)) {
                    continue;
                }

                const optionValue = (optionElement.value || optionElement.textContent || '').trim();
                if (optionValue === '') {
                    continue;
                }

                const optionLower = optionValue.toLowerCase();
                const optionId = optionElement.dataset.customerId || optionElement.getAttribute('data-customer-id') || '';
                const optionNumber = optionElement.dataset.customerNumber || optionElement.getAttribute('data-customer-number') || '';

                // Exact match by label (displayed value)
                if (optionValue === value || optionLower === lowerValue) {
                    matchedId = optionId;
                    break;
                }

                // Fallback: match by customer number prefix
                if (inputCustomerNumber && optionNumber === inputCustomerNumber && !matchedByNumber) {
                    matchedByNumber = optionId;
                }
            }

            const finalId = matchedId || matchedByNumber;
            if (finalId) {
                hidden.value = finalId;
                // Cache the selection on the input element
                input.dataset.selectedCustomerId = finalId;
                input.dataset.selectedValue = value;
            }
        }

        /**
         * Resolves the parent form for an input element.
         * Uses formSelector if configured, otherwise falls back to input.form.
         * @param {HTMLInputElement} input - The input element
         * @returns {HTMLFormElement|null} The parent form, or null if not found
         */
        resolveForm(input) {
            if (!(input instanceof HTMLInputElement)) {
                return null;
            }

            if (this.formSelector) {
                const form = input.closest(this.formSelector);
                if (form instanceof HTMLFormElement) {
                    return form;
                }
            }

            const form = input.form;
            return form instanceof HTMLFormElement ? form : null;
        }

        /**
         * Gets the datalist element associated with an input via its 'list' attribute
         * @param {HTMLInputElement} input - The input element
         * @returns {HTMLDataListElement|null} The datalist element, or null if not found
         */
        getDatalist(input) {
            if (!(input instanceof HTMLInputElement)) {
                return null;
            }

            const listId = input.getAttribute('list');
            if (!listId) {
                return null;
            }

            return document.getElementById(listId);
        }
    }

    if (!window.lexBridgeUtils) {
        window.lexBridgeUtils = {};
    }

    /**
     * Factory function to create or retrieve the global CustomerSearchController instance.
     * Implements singleton pattern - returns existing controller if already created.
     * Automatically attaches event listeners on first creation.
     * 
     * @param {Object} options - Configuration options (see CustomerSearchController constructor)
     * @returns {CustomerSearchController} The singleton controller instance
     * 
     * @example
     * const controller = window.lexBridgeUtils.createCustomerSearchController({
     *     hiddenFieldName: 'customer_id',
     *     debounceMs: 300
     * });
     */
    window.lexBridgeUtils.createCustomerSearchController = function createCustomerSearchController(options) {
        // Always return or create a single global instance
        if (!window.lexBridge) {
            window.lexBridge = {};
        }

        // If global controller exists, return it (singleton pattern)
        if (window.lexBridge.customerSearchController) {
            return window.lexBridge.customerSearchController;
        }

        // Create, attach, and store new controller
        const controller = new CustomerSearchController(options);
        controller.attach();
        window.lexBridge.customerSearchController = controller;
        
        return controller;
    };

    // Expose class for direct instantiation if needed
    window.lexBridgeUtils.CustomerSearchController = CustomerSearchController;

})();
