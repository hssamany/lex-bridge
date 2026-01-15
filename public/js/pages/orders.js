'use strict';

(function () {
    class OrdersPage {
        constructor(lexBridge) {
            this.lexBridge = lexBridge;
            this.customerSearchTimers = new WeakMap();
            this.lastQueryString = '';

            this.onInputDelegate = this.handleInputEvent.bind(this);
            this.onChangeDelegate = this.handleChangeEvent.bind(this);
            this.onSubmitDelegate = this.handleSubmitEvent.bind(this);
            this.ordersChangeHandler = null;
            this.ordersGenerateButton = null;

            this.init();
        }

        init() {
            document.addEventListener('input', this.onInputDelegate, true);
            document.addEventListener('change', this.onChangeDelegate, true);
            document.addEventListener('submit', this.onSubmitDelegate, true);
        }

        setupFilterFormDirect() {
            const form = document.querySelector('form[name="get-orders"]');
            if (!form || form.dataset.ajaxHandlerAttached === 'true') {
                return;
            }

            form.dataset.ajaxHandlerAttached = 'true';
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                await this.processFilterForm(form);
            });
        }

        handleInputEvent(event) {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (!target.matches('form[name="get-orders"] .customer-search-combobox')) {
                return;
            }

            this.handleCustomerSearch(target);
        }

        handleChangeEvent(event) {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (!target.matches('form[name="get-orders"] .customer-search-combobox')) {
                return;
            }

            this.syncCustomerSelection(target);
        }

        handleSubmitEvent(event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (!form.matches('form[name="get-orders"]')) {
                return;
            }

            if (form.dataset.ajaxHandlerAttached === 'true') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.processFilterForm(form);
        }

        getSelectedOrderIds() {
            return Array.from(document.querySelectorAll('.order-select-checkbox:checked'))
                .map((checkbox) => parseInt(checkbox.getAttribute('data-order-id') || checkbox.value || '', 10))
                .filter((id) => Number.isInteger(id) && id > 0);
        }

        updateGenerateButtonState() {
            if (!this.ordersGenerateButton) {
                return;
            }

            const selectedCount = this.getSelectedOrderIds().length;
            this.ordersGenerateButton.disabled = selectedCount === 0;
        }

        async bulkGenerateSelectedOrders(button) {
            const orderIds = this.getSelectedOrderIds();
            if (!orderIds.length) {
                this.notify('Bitte wählen Sie mindestens eine Bestellung aus.', 'warning');
                return;
            }

            const originalLabel = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="btn-icon spinning">↻</span> Erstelle...';

            try {
                const response = await fetch('/lex-bridge/api/orders/generate-line-items', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ order_ids: orderIds })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Generieren fehlgeschlagen (${response.status}): ${errorText}`);
                }

                const data = await response.json();
                if (!data?.isSuccess) {
                    throw new Error(data?.error || 'Erstellung der Positionen fehlgeschlagen.');
                }

                const generated = data.generatedCount ?? data.generated ?? 0;
                this.notify(`Es wurden ${generated} Positionen erstellt.`, 'success');
                await this.reloadOrders();
            } catch (error) {
                console.error('Bulk generate line items error:', error);
                this.notify('Fehler beim Erstellen der Positionen.', 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = originalLabel;
            }
        }

        async processFilterForm(form) {
            this.ensureCustomerSelection(form);

            const fromInput = form.querySelector('input[name="geaendertAm_from"]');
            if (fromInput && fromInput.value.trim() === '') {
                this.notify('Bitte geben Sie ein "Von"-Datum an.', 'warning');
                fromInput.focus();
                return;
            }

            const params = new URLSearchParams();
            const formData = new FormData(form);

            for (const [key, value] of formData.entries()) {
                if (key === 'customer_search') {
                    continue;
                }

                if (typeof value === 'string' && value.trim() !== '') {
                    params.append(key, value.trim());
                }
            }

            this.lastQueryString = params.toString();
            await this.fetchOrders(params, form.querySelector('button[type="submit"]'));
        }

        async fetchOrders(params, submitButton) {
            const container = document.querySelector('.orders-list');
            if (!container) {
                return;
            }

            container.setAttribute('aria-busy', 'true');
            container.innerHTML = '<p class="orders-loading">Lade Bestellungen...</p>';

            let originalButtonLabel = null;
            if (submitButton) {
                originalButtonLabel = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="btn-icon spinning">↻</span> Filtern...';
            }

            try {
                const response = await fetch(`/lex-bridge/api/orders?${params.toString()}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Orders request failed (${response.status}): ${errorText}`);
                }

                const data = await response.json();
                if (!data?.isSuccess) {
                    throw new Error(data?.error || 'Unbekannter Fehler beim Laden der Bestellungen.');
                }

                this.renderOrdersList(container, Array.isArray(data.orders) ? data.orders : []);
                this.notify('Bestellungen aktualisiert.', 'success');
            } catch (error) {
                console.error('Orders filter error:', error);
                container.innerHTML = '<p class="orders-error">Fehler beim Laden der Bestellungen.</p>';
                this.notify('Fehler beim Laden der Bestellungen.', 'error');
            } finally {
                container.setAttribute('aria-busy', 'false');
                if (submitButton && originalButtonLabel !== null) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonLabel;
                }
            }
        }

        renderOrdersList(container, orders) {
            if (this.ordersChangeHandler) {
                container.removeEventListener('change', this.ordersChangeHandler);
                this.ordersChangeHandler = null;
            }

            this.ordersGenerateButton = null;
            container.innerHTML = '';

            const toolbar = document.createElement('div');
            toolbar.className = 'orders-toolbar line-items-toolbar';

            const leftGroup = document.createElement('div');
            leftGroup.className = 'line-items-toolbar-left';

            const generateBtn = document.createElement('button');
            generateBtn.type = 'button';
            generateBtn.className = 'btn btn-primary line-items-toolbar-btn';
            generateBtn.innerHTML = 'Erstellen <span class="btn-icon" style="font-size:1.1em;">➤</span>';
            generateBtn.disabled = true;
            generateBtn.addEventListener('click', () => {
                this.bulkGenerateSelectedOrders(generateBtn);
            });

            leftGroup.appendChild(generateBtn);
            toolbar.appendChild(leftGroup);
            container.appendChild(toolbar);

            this.ordersGenerateButton = generateBtn;
            this.updateGenerateButtonState();

            if (!orders.length) {
                const emptyState = document.createElement('p');
                emptyState.className = 'orders-empty';
                emptyState.textContent = 'Keine Bestellungen gefunden.';
                container.appendChild(emptyState);
                return;
            }

            const table = document.createElement('table');
            table.className = 'orders-table';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th><input type="checkbox" class="orders-select-all"></th>
                        <th>Kunde</th>
                        <th>Jahr</th>
                        <th>KW</th>
                        <th>Mo</th>
                        <th>Di</th>
                        <th>Mi</th>
                        <th>Do</th>
                        <th>Fr</th>
                        <th>Geändert am</th>
                        <th>Artikel-Nr.</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;

            const tbody = table.querySelector('tbody');
            const selectAllCheckbox = table.querySelector('.orders-select-all');

            orders.forEach((order) => {
                const row = document.createElement('tr');
                row.dataset.orderId = this.escapeHtml(String(order.order_id ?? ''));

                const selectCell = document.createElement('td');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'order-select-checkbox';
                const orderIdValue = this.safeText(order.order_id);
                checkbox.value = orderIdValue;
                checkbox.setAttribute('data-order-id', orderIdValue);
                selectCell.appendChild(checkbox);
                row.appendChild(selectCell);

                const appendCell = (value) => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.appendChild(cell);
                };

                appendCell(this.safeText(order.customer_id));
                appendCell(this.safeText(order.order_year));
                appendCell(this.safeText(order.order_week));

                const quantities = order.quantities || {};
                ['Mo', 'Di', 'Mi', 'Do', 'Fr'].forEach((day) => {
                    appendCell(this.formatNumber(quantities[day] ?? null));
                });

                appendCell(this.formatDateTime(order.geaendert_am));
                appendCell(this.safeText(order.article_number));

                tbody.appendChild(row);
            });

            container.appendChild(table);

            this.ordersChangeHandler = (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                if (target.classList.contains('orders-select-all')) {
                    const checkboxes = table.querySelectorAll('.order-select-checkbox');
                    checkboxes.forEach((checkboxElement) => {
                        checkboxElement.checked = target.checked;
                    });
                    this.updateGenerateButtonState();
                    return;
                }

                if (target.classList.contains('order-select-checkbox')) {
                    if (selectAllCheckbox instanceof HTMLInputElement) {
                        if (!target.checked) {
                            selectAllCheckbox.checked = false;
                        } else {
                            const allCheckboxes = table.querySelectorAll('.order-select-checkbox');
                            const checkedCheckboxes = table.querySelectorAll('.order-select-checkbox:checked');
                            selectAllCheckbox.checked = allCheckboxes.length > 0 && checkedCheckboxes.length === allCheckboxes.length;
                        }
                    }
                    this.updateGenerateButtonState();
                }
            };

            container.addEventListener('change', this.ordersChangeHandler);
            this.updateGenerateButtonState();
        }

        async reloadOrders() {
            if (!this.lastQueryString) {
                return;
            }

            const params = new URLSearchParams(this.lastQueryString);
            await this.fetchOrders(params, null);
        }

        handleCustomerSearch(input) {
            const listId = input.getAttribute('list');
            const datalist = listId ? document.getElementById(listId) : null;
            if (!datalist) {
                return;
            }

            const query = input.value.trim();

            if (query === '') {
                datalist.innerHTML = '<option value="">Alle Kunden</option>';
                this.syncCustomerSelection(input, datalist);
                return;
            }

            const timer = this.customerSearchTimers.get(input);
            if (timer) {
                clearTimeout(timer);
            }

            const newTimer = setTimeout(async () => {
                try {
                    const response = await fetch(`/lex-bridge/api/customers/search?q=${encodeURIComponent(query)}`);
                    if (!response.ok) {
                        throw new Error(`Customer search failed (${response.status}).`);
                    }

                    const text = await response.text();
                    const data = JSON.parse(text);

                    datalist.innerHTML = '<option value="">Alle Kunden</option>';
                    if (Array.isArray(data)) {
                        data.forEach((customer) => {
                            const option = document.createElement('option');
                            const number = customer.customer_number || '';
                            const name = customer.company_name || '';
                            const label = [number, name].filter(Boolean).join(' - ');
                            option.value = label;
                            option.dataset.customerId = String(customer.id ?? '');
                            option.setAttribute('data-customer-id', String(customer.id ?? ''));
                            datalist.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('Customer search error:', error);
                } finally {
                    this.syncCustomerSelection(input, datalist);
                }
            }, 250);

            this.customerSearchTimers.set(input, newTimer);
        }

        syncCustomerSelection(input, datalist) {
            const form = input.closest('form[name="get-orders"]');
            if (!form) {
                return;
            }

            const hidden = form.querySelector('input[type="hidden"][name="customer_id"]');
            if (!hidden) {
                return;
            }

            hidden.value = '';
            const list = datalist || (input.getAttribute('list') ? document.getElementById(input.getAttribute('list')) : null);
            if (!list) {
                return;
            }

            const value = input.value.trim();
            if (value === '') {
                return;
            }

            const options = list.options || list.children;
            for (let index = 0; index < options.length; index += 1) {
                const option = options[index];
                if (option.value !== value) {
                    continue;
                }

                const customerId = option.dataset.customerId || option.getAttribute('data-customer-id');
                if (customerId) {
                    hidden.value = customerId;
                }
                break;
            }
        }

        ensureCustomerSelection(form) {
            const input = form.querySelector('.customer-search-combobox');
            if (!input) {
                return;
            }

            this.syncCustomerSelection(input);
        }

        notify(message, type) {
            if (this.lexBridge?.toastNotifier) {
                this.lexBridge.toastNotifier.show(message, type);
            }
        }

        safeText(value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value);
        }

        formatNumber(value) {
            if (value === null || value === undefined || value === '') {
                return '';
            }

            const numeric = Number(value);
            if (Number.isNaN(numeric)) {
                return '';
            }

            return numeric.toLocaleString('de-DE', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3
            });
        }

        formatDateTime(value) {
            if (!value) {
                return '';
            }

            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (trimmed === '') {
                    return '';
                }

                const parts = trimmed.split(/[T\s]/);
                if (parts.length >= 2) {
                    return `${parts[0]} ${parts[1].substring(0, 8)}`;
                }

                return trimmed;
            }

            return String(value);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    window.OrdersPage = OrdersPage;
})();
