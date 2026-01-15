'use strict';

(function () {
    const SELECTORS = {
        filterForm: 'form[name="get-orders"]',
        customerSearch: 'form[name="get-orders"] .customer-search-combobox',
        ordersContainer: '.orders-list',
        submitButton: 'button[type="submit"]'
    };

    const CLASS_NAMES = {
        orderSelectCheckbox: 'order-select-checkbox',
        ordersSelectAll: 'orders-select-all',
        ordersToolbar: 'orders-toolbar line-items-toolbar',
        toolbarLeft: 'line-items-toolbar-left',
        toolbarButton: 'btn btn-primary line-items-toolbar-btn',
        loading: 'orders-loading',
        error: 'orders-error',
        empty: 'orders-empty',
        ordersTable: 'orders-table'
    };

    class OrdersPage {
        constructor(lexBridge) 
        {
            this.lexBridge = lexBridge;
            this.customerSearchTimers = new WeakMap();
            this.lastQueryString = '';
            this.ordersContainer = null;

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

        setupFilterFormDirect() 
        {
            const form = document.querySelector(SELECTORS.filterForm);
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

            if (!target.matches(SELECTORS.customerSearch)) {
                return;
            }

            this.handleCustomerSearch(target);
        }

        handleChangeEvent(event) 
        {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (!target.matches(SELECTORS.customerSearch)) {
                return;
            }

            this.syncCustomerSelection(target);
        }

        handleSubmitEvent(event) 
        {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (!form.matches(SELECTORS.filterForm)) {
                return;
            }

            if (form.dataset.ajaxHandlerAttached === 'true') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.processFilterForm(form);
        }

        getSelectedOrderIds() 
        {
            const selector = `.${CLASS_NAMES.orderSelectCheckbox}:checked`;
            return Array.from(document.querySelectorAll(selector))
                .map((element) => {
                    if (!(element instanceof HTMLInputElement)) {
                        return Number.NaN;
                    }

                    const raw = element.dataset.orderId || element.value || '';
                    return parseInt(raw, 10);
                })
                .filter((id) => Number.isInteger(id) && id > 0);
        }

        updateGenerateButtonState() 
        {
            if (!this.ordersGenerateButton) {
                return;
            }

            const selectedCount = this.getSelectedOrderIds().length;
            this.ordersGenerateButton.disabled = selectedCount === 0;
        }

        getOrdersContainer() {
            if (this.ordersContainer && document.body.contains(this.ordersContainer)) {
                return this.ordersContainer;
            }

            this.ordersContainer = document.querySelector(SELECTORS.ordersContainer);
            return this.ordersContainer;
        }

        async withButtonLoading(button, loadingMarkup, callback) 
        {
            if (!(button instanceof HTMLButtonElement)) {
                return await callback();
            }

            const originalMarkup = button.innerHTML;
            button.disabled = true;
            if (loadingMarkup) {
                button.innerHTML = loadingMarkup;
            }

            try {
                return await callback();
            } finally {
                button.disabled = false;
                button.innerHTML = originalMarkup;
            }
        }

        async requestJson(url, options = {}, contextLabel = 'Anfrage fehlgeschlagen') {
            const response = await fetch(url, options);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`${contextLabel} (${response.status}): ${errorText}`);
            }

            const raw = await response.text();
            const payload = raw.trim();
            if (payload === '') {
                return {};
            }

            try {
                return JSON.parse(payload);
            } catch (error) {
                throw new Error(`${contextLabel}: Ungültige JSON-Antwort.`);
            }
        }

        async bulkGenerateSelectedOrders(button) {
            const orderIds = this.getSelectedOrderIds();
            if (!orderIds.length) {
                this.notify('Bitte wählen Sie mindestens eine Bestellung aus.', 'warning');
                return;
            }

            await this.withButtonLoading(button, '<span class="btn-icon spinning">↻</span> Erstelle...', async () => {
                try {
                    const data = await this.requestJson('/lex-bridge/api/orders/generate-line-items', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ order_ids: orderIds })
                    }, 'Generieren fehlgeschlagen');

                    if (!data?.isSuccess) {
                        throw new Error(data?.error || 'Erstellung der Positionen fehlgeschlagen.');
                    }

                    const generated = data.generatedCount ?? data.generated ?? 0;
                    this.notify(`Es wurden ${generated} Positionen erstellt.`, 'success');
                    await this.reloadOrders();
                } catch (error) {
                    console.error('Bulk generate line items error:', error);
                    this.notify('Fehler beim Erstellen der Positionen.', 'error');
                }
            });
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
            const submitButton = form.querySelector(SELECTORS.submitButton);
            await this.fetchOrders(params, submitButton instanceof HTMLButtonElement ? submitButton : null);
        }

        async fetchOrders(params, submitButton) {
            const container = this.getOrdersContainer();
            if (!container) {
                return;
            }

            container.setAttribute('aria-busy', 'true');
            container.innerHTML = `<p class="${CLASS_NAMES.loading}">Lade Bestellungen...</p>`;

            let originalButtonLabel = null;
            if (submitButton) {
                originalButtonLabel = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="btn-icon spinning">↻</span> Filtern...';
            }

            try {
                const data = await this.requestJson(`/lex-bridge/api/orders?${params.toString()}`, {}, 'Orders request failed');
                if (!data?.isSuccess) {
                    throw new Error(data?.error || 'Unbekannter Fehler beim Laden der Bestellungen.');
                }

                this.renderOrdersList(Array.isArray(data.orders) ? data.orders : []);
                this.notify('Bestellungen aktualisiert.', 'success');
            } catch (error) {
                console.error('Orders filter error:', error);
                container.innerHTML = `<p class="${CLASS_NAMES.error}">Fehler beim Laden der Bestellungen.</p>`;
                this.notify('Fehler beim Laden der Bestellungen.', 'error');
            } finally {
                container.setAttribute('aria-busy', 'false');
                if (submitButton && originalButtonLabel !== null) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonLabel;
                }
            }
        }

        renderOrdersList(orders) {
            const container = this.getOrdersContainer();
            if (!container) {
                return;
            }

            if (this.ordersChangeHandler) {
                container.removeEventListener('change', this.ordersChangeHandler);
                this.ordersChangeHandler = null;
            }

            this.ordersGenerateButton = null;
            container.innerHTML = '';

            const toolbar = document.createElement('div');
            toolbar.className = CLASS_NAMES.ordersToolbar;

            const leftGroup = document.createElement('div');
            leftGroup.className = CLASS_NAMES.toolbarLeft;

            const generateBtn = document.createElement('button');
            generateBtn.type = 'button';
            generateBtn.className = CLASS_NAMES.toolbarButton;
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
                emptyState.className = CLASS_NAMES.empty;
                emptyState.textContent = 'Keine Bestellungen gefunden.';
                container.appendChild(emptyState);
                return;
            }

            const table = document.createElement('table');
            table.className = CLASS_NAMES.ordersTable;
            table.innerHTML = `
                <thead>
                    <tr>
                        <th><input type="checkbox" class="${CLASS_NAMES.ordersSelectAll}"></th>
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
            const selectAllCheckbox = table.querySelector(`.${CLASS_NAMES.ordersSelectAll}`);

            orders.forEach((order) => {
                const row = document.createElement('tr');
                row.dataset.orderId = String(order.order_id ?? '');

                const selectCell = document.createElement('td');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = CLASS_NAMES.orderSelectCheckbox;
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

                if (target.classList.contains(CLASS_NAMES.ordersSelectAll)) {
                    const checkboxes = table.querySelectorAll(`.${CLASS_NAMES.orderSelectCheckbox}`);
                    checkboxes.forEach((checkboxElement) => {
                        checkboxElement.checked = target.checked;
                    });
                    this.updateGenerateButtonState();
                    return;
                }

                if (target.classList.contains(CLASS_NAMES.orderSelectCheckbox)) {
                    if (selectAllCheckbox instanceof HTMLInputElement) {
                        if (!target.checked) {
                            selectAllCheckbox.checked = false;
                        } else {
                            const allCheckboxes = table.querySelectorAll(`.${CLASS_NAMES.orderSelectCheckbox}`);
                            const checkedCheckboxes = table.querySelectorAll(`.${CLASS_NAMES.orderSelectCheckbox}:checked`);
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
                    const data = await this.requestJson(
                        `/lex-bridge/api/customers/search?q=${encodeURIComponent(query)}`,
                        {},
                        'Customer search failed'
                    );

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
            const form = input.closest(SELECTORS.filterForm);
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

    }

    window.OrdersPage = OrdersPage;
})();
