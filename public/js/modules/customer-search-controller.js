// public/js/modules/customer-search-controller.js
// Manages customer combobox search behaviour using event delegation

(function () {
    class CustomerSearchController {
        constructor() {
            this.debounceTimers = new WeakMap();
            this.debounceDelay = 300;
            this.listenersAttached = false;
            this.onInputCapture = this.onInputCapture.bind(this);
            this.onChangeCapture = this.onChangeCapture.bind(this);
        }

        attachListeners() {
            if (this.listenersAttached) {
                return;
            }

            document.addEventListener('input', this.onInputCapture, true);
            document.addEventListener('change', this.onChangeCapture, true);
            this.listenersAttached = true;
        }

        onInputCapture(event) {
            const target = event.target;
            if (!target || !target.classList.contains('customer-search-combobox')) {
                return;
            }

            this.handleSearchInput(target);
            this.syncSelection(target);
        }

        onChangeCapture(event) {
            const target = event.target;
            if (!target || !target.classList.contains('customer-search-combobox')) {
                return;
            }

            this.syncSelection(target);
        }

        getDatalistForInput(input) {
            const listId = input.getAttribute('list');
            if (!listId) {
                return null;
            }

            const datalist = document.getElementById(listId);
            if (!datalist) {
                console.warn('Customer datalist not found for input', input);
            }
            return datalist;
        }

        syncSelection(input, datalistOverride) {
            if (!input) {
                return;
            }

            const form = input.closest('form');
            if (!form) {
                return;
            }

            const hiddenField = form.querySelector('input[type="hidden"][name="customer_id"]');
            if (!hiddenField) {
                return;
            }

            const datalist = datalistOverride || this.getDatalistForInput(input);
            hiddenField.value = '';

            if (!datalist) {
                return;
            }

            const value = input.value.trim();
            if (!value) {
                return;
            }

            const options = datalist.options || datalist.children;
            for (let index = 0; index < options.length; index += 1) {
                const option = options[index];
                if (option.value !== value) {
                    continue;
                }

                const customerId = option.dataset.customerId || option.getAttribute('data-customer-id');
                if (customerId) {
                    hiddenField.value = customerId;
                }
                break;
            }
        }

        handleSearchInput(input) {
            const datalist = this.getDatalistForInput(input);
            if (!datalist) {
                return;
            }

            const query = input.value.trim();
            if (!query) {
                datalist.innerHTML = '<option value="">Alle Kunden</option>';
                this.syncSelection(input, datalist);
                return;
            }

            const existingTimer = this.debounceTimers.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
            }

            this.syncSelection(input, datalist);

            const newTimer = setTimeout(() => {
                this.fetchCustomers(input, datalist, query);
            }, this.debounceDelay);

            this.debounceTimers.set(input, newTimer);
        }

        async fetchCustomers(input, datalist, query) {
            this.debounceTimers.delete(input);

            let response;
            try {
                response = await fetch(LexBridge.resolveApiUrl(`customers/search?q=${encodeURIComponent(query)}`));
            } catch (error) {
                console.error('Customer search network error:', error);
                return;
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Customer search HTTP error:', response.status, errorText);
                return;
            }

            let payload = null;
            try {
                const text = await response.text();
                payload = text ? JSON.parse(text) : [];
            } catch (error) {
                console.error('Customer search response parse error:', error);
                return;
            }

            if (input.value.trim() !== query) {
                return;
            }

            this.populateCustomerOptions(datalist, Array.isArray(payload) ? payload : []);
            this.syncSelection(input, datalist);
        }

        populateCustomerOptions(datalist, customers) {
            datalist.innerHTML = '<option value="">Alle Kunden</option>';

            customers.forEach(customer => {
                const option = document.createElement('option');
                const number = customer.customer_number || '';
                const name = customer.company_name || '';
                option.value = `${number}${number && name ? ' - ' : ''}${name}`;
                option.dataset.customerId = String(customer.id ?? '');
                option.setAttribute('data-customer-id', String(customer.id ?? ''));
                datalist.appendChild(option);
            });
        }
    }

    if (!window.lexBridge) {
        window.lexBridge = {};
    }

    if (!window.lexBridge.customerSearchController) {
        window.lexBridge.customerSearchController = new CustomerSearchController();
        window.lexBridge.customerSearchController.attachListeners();
    }
})();
