"use strict";

(function () {

    class OrdersPage {
        static SELECTORS = {
            filterForm: 'form[name="get-bestellg"]',
            customerSearchInput: '.customer-search-combobox',
            ordersContainer: '.orders-list',
            submitButton: 'button[type="submit"]'
        };

        static CLASS_NAMES = {
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

        constructor(lexBridge) 
        {
            this.lexBridge = lexBridge;
            this.lastQueryString = '';
            this.ordersContainer = null;
            this.allOrders = []; // Store all fetched orders
            this.showProcessed = false; // Default: hide processed orders
            this.hasLoadedOnce = false; // Track if orders have been loaded
            this.currentPage = 1;
            this.pageSize = 10;
            this.totalCount = 0;
            this.paginator = null;

            this.ordersChangeHandler = null;
            this.ordersGenerateButton = null;
            this.customerSearchController = null;

            window.lexBridge.OrdersPageInstance = this;
            this.init();
        }

        init() {
            this.setupCustomerSearchController();
            this.attachFormHandler();
            this.setupGenerateButton();
            this.setupGenerateInvoicesButton();
            this.setupProcessedFilterCheckbox();
            this.registerSelectAllHandler();
            this.setupPaginator();
            this.autoLoadOrdersOnFirstOpen();
        }

        setupPaginator() {
            const container = document.querySelector('.orders-paginator');
            if (!container || !window.lexBridgeUtils || typeof window.lexBridgeUtils.Paginator !== 'function') {
                return;
            }

            this.paginator = new window.lexBridgeUtils.Paginator(container, {
                pageSize: this.pageSize,
                onChange: ({ page, pageSize }) => {
                    this.currentPage = page;
                    this.pageSize = pageSize;
                    this.reloadOrders();
                }
            });

            this.renderPaginator();
        }

        renderPaginator() {
            if (!this.paginator) {
                return;
            }

            this.paginator.render({
                page: this.currentPage,
                pageSize: this.pageSize,
                totalCount: this.totalCount
            });
        }

        attachFormHandler() {
            const form = document.querySelector(OrdersPage.SELECTORS.filterForm);
            if (!form) {
                console.warn('Orders filter form not found');
                return;
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                await this.processFilterForm(form);
            });
        }

        setupGenerateButton() {
            const button = document.querySelector('.orders-generate-button');
            if (!button) {
                console.warn('Orders generate button not found');
                return;
            }

            this.ordersGenerateButton = button;
            
            button.addEventListener('click', () => {
                this.bulkGenerateSelectedOrders(button);
            });
        }

        setupGenerateInvoicesButton() {
            const button = document.querySelector('.orders-generate-invoices-button');
            if (!button) {
                console.warn('Orders generate invoices button not found');
                return;
            }

            this.ordersGenerateInvoicesButton = button;
            
            button.addEventListener('click', () => {
                this.bulkGenerateInvoicesFromOrders(button);
            });
        }

        setupProcessedFilterCheckbox() {
            const checkbox = document.querySelector('.orders-filter-processed');
            if (!checkbox) {
                console.warn('Orders filter processed checkbox not found');
                return;
            }

            // Set default state (unchecked = hide processed)
            checkbox.checked = this.showProcessed;

            checkbox.addEventListener('change', (event) => {
                this.showProcessed = event.target.checked;
                this.applyProcessedFilter();
            });
        }

        applyProcessedFilter() {
            // Filter orders based on showProcessed flag
            const filteredOrders = this.showProcessed 
                ? this.allOrders 
                : this.allOrders.filter(order => !order.verarbeitet);
            
            this.updateOrdersList(filteredOrders, this.totalCount);
        }

        async autoLoadOrdersOnFirstOpen() {
            // Auto-load orders if this is the first time and list is empty
            if (!this.hasLoadedOnce && this.allOrders.length === 0) {
                console.log('OrdersPage: Auto-loading orders on first open');
                setTimeout(async () => {
                    const form = document.querySelector(OrdersPage.SELECTORS.filterForm);
                    if (form) {
                        await this.processFilterForm(form);
                        this.hasLoadedOnce = true;
                    }
                }, 100);
            }
        }

        registerSelectAllHandler() {
            // Use event delegation on document for select-all checkbox
            document.addEventListener('change', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                // Handle select-all checkbox
                if (target.classList.contains(OrdersPage.CLASS_NAMES.ordersSelectAll)) {
                    const checkboxes = document.querySelectorAll(`.${OrdersPage.CLASS_NAMES.orderSelectCheckbox}`);
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = target.checked;
                    });
                    this.updateGenerateButtonState();
                    return;
                }

                // Handle individual order checkboxes
                if (target.classList.contains(OrdersPage.CLASS_NAMES.orderSelectCheckbox)) {
                    const selectAllCheckbox = document.querySelector(`.${OrdersPage.CLASS_NAMES.ordersSelectAll}`);
                    if (selectAllCheckbox instanceof HTMLInputElement) {
                        if (!target.checked) {
                            selectAllCheckbox.checked = false;
                        } else {
                            const allCheckboxes = document.querySelectorAll(`.${OrdersPage.CLASS_NAMES.orderSelectCheckbox}`);
                            const checkedCheckboxes = document.querySelectorAll(`.${OrdersPage.CLASS_NAMES.orderSelectCheckbox}:checked`);
                            selectAllCheckbox.checked = allCheckboxes.length > 0 && checkedCheckboxes.length === allCheckboxes.length;
                        }
                    }
                    this.updateGenerateButtonState();
                }
            });
        }

        setupCustomerSearchController() 
        {
            if (!window.lexBridgeUtils || typeof window.lexBridgeUtils.createCustomerSearchController !== 'function') {
                return;
            }
            
            this.customerSearchController = window
            .lexBridgeUtils.createCustomerSearchController({
                hiddenFieldName: 'customer_id'
            });
        }



        getSelectedOrderIds() 
        {
            const selector = `.${OrdersPage.CLASS_NAMES.orderSelectCheckbox}:checked`;
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
            const selectedCount = this.getSelectedOrderIds().length;
            
            if (this.ordersGenerateButton) {
                this.ordersGenerateButton.disabled = selectedCount === 0;
                // No error toast here; error is not defined in this scope
            }
            
            if (this.ordersGenerateInvoicesButton) {
                this.ordersGenerateInvoicesButton.disabled = selectedCount === 0;
            }
        }

        getOrdersContainer() 
        {
            if (this.ordersContainer && document.body.contains(this.ordersContainer)) {
                return this.ordersContainer;
            }

            this.ordersContainer = document.querySelector(OrdersPage.SELECTORS.ordersContainer);
            
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

        async requestJson(url, options = {}, contextLabel = 'Anfrage fehlgeschlagen') 
        {
            const response = await fetch(url, options);

            if (!response.ok) 
            {
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

        async bulkGenerateSelectedOrders(button) 
        {
            const orderIds = this.getSelectedOrderIds();
            await this.withButtonLoading(button, '<span class="btn-icon spinning">↻</span> Erstelle...', async () => {
                try {
                    const data = await this.requestJson(LexBridge.resolveApiUrl('orders/generate-line-items'), {
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
                    const message = error instanceof Error && error.message ? error.message : 'Fehler beim Erstellen der Positionen.';
                    this.notify(message, 'error');
                }
            });
        }

        async bulkGenerateInvoicesFromOrders(button) 
        {
            const orderIds = this.getSelectedOrderIds();

            if (!orderIds.length) {
                this.notify('Bitte wählen Sie mindestens eine Bestellung aus.', 'warning');
                return;
            }

            await this.withButtonLoading(button, '<span class="btn-icon spinning">↻</span> Erstelle Rechnungen...', async () => {
                try {
                    const data = await this.requestJson(LexBridge.resolveApiUrl('orders/generate-invoices'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ order_ids: orderIds })
                    }, 'Rechnungserstellung fehlgeschlagen');

                    if (!data?.isSuccess) {
                        throw new Error(data?.error || 'Erstellung der Rechnungen fehlgeschlagen.');
                    }

                    const lineItemsGenerated = data.lineItemsGenerated ?? 0;
                    const invoicesCreated = data.invoicesCreated ?? 0;
                    
                    // Build success message with details
                    let message = `${lineItemsGenerated} Positionen und ${invoicesCreated} Rechnung(en) erstellt.`;
                    
                    // Display warnings if any
                    if (data.warnings && Array.isArray(data.warnings) && data.warnings.length > 0) {
                        console.warn('Invoice generation warnings:', data.warnings);
                        const warningCount = data.warnings.length;
                        message += ` (${warningCount} Warnung${warningCount > 1 ? 'en' : ''} - siehe Konsole)`;
                    }
                    
                    this.notify(message, 'success');
                    await this.reloadOrders();

                } catch (error) {
                    console.error('Bulk generate invoices error:', error);
                    const message = error instanceof Error && error.message ? error.message : 'Fehler beim Erstellen der Rechnungen.';
                    this.notify(message, 'error');
                }
            });
        }

        async processFilterForm(form) 
        {
            this.ensureCustomerSelection(form);

            const fromInput = form.querySelector('input[name="geaendertAm_from"]');
            
            if (fromInput && fromInput.value.trim() === '') 
            {
                this.notify('Bitte geben Sie ein "Von"-Datum an.', 'warning');
                fromInput.focus();
                return;
            }

            const params = new URLSearchParams();
            const formData = new FormData(form);

            for (const [key, value] of formData.entries()) {
                // Skip customer_search display field
                if (key === 'customer_search') {
                    continue;
                }

                // Skip empty customer_id (ensures "All Customers" works)
                if (key === 'customer_id' && (!value || value.trim() === '' || value === '0')) {
                    continue;
                }

                // if (key === 'customer_id') {
                //     const input = form.querySelector('input[name="customer_id"]');
                //     const dataValue = input ? input.dataset.customerId: null;
                //     params.append("customer_id", dataValue || '');
                // }

                if (typeof value === 'string' && value.trim() !== '') {
                    params.append(key, value.trim());
                }
            }

            this.currentPage = 1;
            this.lastQueryString = params.toString();
            const submitButton = form.querySelector(OrdersPage.SELECTORS.submitButton);
            await this.fetchOrders(params, submitButton instanceof HTMLButtonElement ? submitButton : null);
        }

        async fetchOrders(params, submitButton) {
            const container = this.getOrdersContainer();
            if (!container) {
                return;
            }

            container.setAttribute('aria-busy', 'true');
            const tableBody = container.querySelector('.orders-table-body');
            const columnCount = 12;

            if (tableBody instanceof HTMLElement) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="${columnCount}" style="text-align:center;">
                            <span class="${OrdersPage.CLASS_NAMES.loading}">Lade Bestellungen...</span>
                        </td>
                    </tr>
                `;
            }

            let originalButtonLabel = null;

            if (submitButton) {
                originalButtonLabel = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="btn-icon spinning">↻</span> Filtern...';
            }

            try 
            {
                params.set('page', String(this.currentPage));
                params.set('page_size', String(this.pageSize));
                const data = await this.requestJson(`${LexBridge.resolveApiUrl('orders')}?${params.toString()}`, {}, 'Orders request failed');
                
                if (!data?.isSuccess) {
                    throw new Error(data?.error || 'Unbekannter Fehler beim Laden der Bestellungen.');
                }

                // Store all orders and apply filter
                this.allOrders = Array.isArray(data.orders) ? data.orders : [];
                this.totalCount = Number.isFinite(Number(data.total_count)) ? Number(data.total_count) : this.allOrders.length;
                this.currentPage = Number(data.page) > 0 ? Number(data.page) : this.currentPage;
                this.pageSize = Number(data.page_size) > 0 ? Number(data.page_size) : this.pageSize;
                this.applyProcessedFilter();
                this.renderPaginator();
                // Only show toast for errors or sync/save actions, not for successful loads

            } catch (error) {
                console.error('Orders filter error:', error);

                if (tableBody instanceof HTMLElement) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="${columnCount}" style="text-align:center;">
                                <span class="${OrdersPage.CLASS_NAMES.error}">Fehler beim Laden der Bestellungen.</span>
                            </td>
                        </tr>
                    `;
                }

                const totalLabel = container.querySelector('.orders-total');
                if (totalLabel instanceof HTMLElement) {
                    totalLabel.textContent = 'Gesammt: 0';
                }

                this.totalCount = 0;
                this.renderPaginator();

                this.notify('Fehler beim Laden der Bestellungen.', 'error');

            } finally {

                container.setAttribute('aria-busy', 'false');

                if (submitButton && originalButtonLabel !== null) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonLabel;
                }
            }
        }

        updateOrdersList(orders, totalCount = null) 
        {
            const container = this.getOrdersContainer();

            if (!container) {
                return;
            }

            const tableBody = container.querySelector('.orders-table-body');
            const selectAllCheckbox = container.querySelector(`.${OrdersPage.CLASS_NAMES.ordersSelectAll}`);
            const totalLabel = container.querySelector('.orders-total');
            const columnCount = 12;

            if (!(tableBody instanceof HTMLElement)) {
                console.warn('Orders table body not found.');
                return;
            }

            if (!orders.length) {
                tableBody.innerHTML = `
                    <tr class="${OrdersPage.CLASS_NAMES.empty}">
                        <td colspan="${columnCount}" style="text-align:center;">Keine Bestellungen gefunden.</td>
                    </tr>
                `;
                if (selectAllCheckbox instanceof HTMLInputElement) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.disabled = true;
                    selectAllCheckbox.indeterminate = false;
                }
                if (totalLabel instanceof HTMLElement) {
                    totalLabel.textContent = 'Gesammt: 0';
                }
                this.updateGenerateButtonState();
                return;
            }

            const rowsHtml = orders.map((order) => {
                const positionCells = [];
                const orderIdValue = this.safeText(order.order_id);
                const orderIdEscaped = this.escapeHtml(orderIdValue);
                positionCells.push(`
                    <td><input type="checkbox" class="${OrdersPage.CLASS_NAMES.orderSelectCheckbox}" data-order-id="${orderIdEscaped}" value="${orderIdEscaped}"></td>
                `);

                positionCells.push(`<td>${this.escapeHtml(this.safeText(order.customer_number))}</td>`);
                positionCells.push(`<td>${this.escapeHtml(this.safeText(order.lex_customer_number))}</td>`);
                positionCells.push(`<td>${this.escapeHtml(this.safeText(order.order_week))}</td>`);

                const quantities = order.quantities || {};
                ['Mo', 'Di', 'Mi', 'Do', 'Fr'].forEach((day) => {
                    positionCells.push(`<td>${this.escapeHtml(this.formatNumber(quantities[day] ?? null))}</td>`);
                });

                positionCells.push(`<td>${this.escapeHtml(this.formatDateTime(order.geaendert_am))}</td>`);
                positionCells.push(`<td>${this.escapeHtml(this.safeText(order.article_number))}</td>`);
                positionCells.push(`<td style="text-align:center;"><input type="checkbox" ${order.verarbeitet ? 'checked' : ''} disabled></td>`);

                return `<tr data-order-id="${orderIdEscaped}">${positionCells.join('')}</tr>`;
            }).join('');

            tableBody.innerHTML = rowsHtml;

            if (selectAllCheckbox instanceof HTMLInputElement) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.disabled = false;
                selectAllCheckbox.indeterminate = false;
            }

            if (totalLabel instanceof HTMLElement) {
                const totalValue = Number.isFinite(Number(totalCount)) ? Number(totalCount) : orders.length;
                if (this.showProcessed) {
                    totalLabel.textContent = `Gesammt: ${totalValue}`;
                } else {
                    totalLabel.textContent = `Gesammt: ${orders.length} von ${totalValue}`;
                }
            }

            this.updateGenerateButtonState();
        }

        async reloadOrders() {
            if (!this.lastQueryString) {
                return;
            }

            const params = new URLSearchParams(this.lastQueryString);
            await this.fetchOrders(params, null);
        }

        ensureCustomerSelection(form) {
            if (!form) {
                return;
            }

            if (this.customerSearchController) {
                this.customerSearchController.ensureSelection(form);
                return;
            }

            const input = form.querySelector(OrdersPage.SELECTORS.customerSearchInput);
            if (input instanceof HTMLInputElement) {
                input.dispatchEvent(new Event('change'));
            }
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
                if (parts.length >= 1) {
                    return parts[0];
                }

                return trimmed;
            }

            return String(value);
        }

        escapeHtml(value) {
            if (window.lexBridgeUtils && typeof window.lexBridgeUtils.escapeHtml === 'function') {
                return window.lexBridgeUtils.escapeHtml(value);
            }
            const div = document.createElement('div');
            div.textContent = value == null ? '' : String(value);
            return div.innerHTML;
        }
    }

    if (!window.lexBridge) {
        window.lexBridge = {};
    }

    window.lexBridge.OrdersPage = OrdersPage;
    window.lexBridge.OrdersPageInstance = null;

})();
