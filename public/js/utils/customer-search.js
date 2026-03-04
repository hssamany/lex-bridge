'use strict';

(function () {

    class CustomerSearchController 
    {
        static DEFAULT_SELECTOR = '.customer-search-combobox';
        static DEFAULT_HIDDEN_NAME = 'customer_id';
        static DEFAULT_DATALIST_OPTION = '<option value="">Alle Kunden</option>';

        static extractCustomerNumber(value) {
            if (typeof value !== 'string') {
                return '';
            }

            const prefix = value.split('-')[0]?.trim() || '';
            return /^\d+$/.test(prefix) ? prefix : '';
        }

        static normaliseString(value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value).trim();
        }

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

        attach() {
            if (this.attached) {
                return;
            }

            document.addEventListener('input', this.boundHandleInput, true);
            document.addEventListener('change', this.boundHandleChange, true);
            this.attached = true;
        }

        detach() {
            if (!this.attached) {
                return;
            }

            document.removeEventListener('input', this.boundHandleInput, true);
            document.removeEventListener('change', this.boundHandleChange, true);
            this.attached = false;
        }

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

        clearTimer(input) {
            const existingTimer = this.timerMap.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
                this.timerMap.delete(input);
            }
        }

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

    window.lexBridgeUtils.CustomerSearchController = CustomerSearchController;

})();
