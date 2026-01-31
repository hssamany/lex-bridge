// public/js/pages/line-items.js
// Handles AJAX customer search for the Line-Items tab using event delegation

const resolveApiUrl = (path = '') => {
    if (typeof LexBridge !== 'undefined' && typeof LexBridge.resolveApiUrl === 'function') {
        return LexBridge.resolveApiUrl(path);
    }

    const cleanedPath = (path || '').replace(/^\/+/u, '');
    if (cleanedPath.startsWith('api/')) {
        return `/lex-bridge/${cleanedPath}`;
    }

    return `/lex-bridge/api/${cleanedPath}`;
};

(function () {
    const debounceTimers = new WeakMap();

    function syncCustomerSelection(input, datalistOverride) {
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

        const listId = input.getAttribute('list');
        const datalist = datalistOverride || (listId ? document.getElementById(listId) : null);

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

    function handleCustomerSearch(input) {
        const listId = input.getAttribute('list');
        const datalist = listId ? document.getElementById(listId) : null;
        if (!datalist) {
            console.warn('Customer datalist not found for input', input);
            return;
        }

        const query = input.value.trim();
        if (!query) {
            datalist.innerHTML = '<option value="">Alle Kunden</option>';
            syncCustomerSelection(input, datalist);
            return;
        }

        const timer = debounceTimers.get(input);
        if (timer) {
            clearTimeout(timer);
        }

        syncCustomerSelection(input, datalist);

        const newTimer = setTimeout(() => {
            const url = resolveApiUrl(`customers/search?q=${encodeURIComponent(query)}`);
            fetch(url)
                .then(async res => {
                    if (!res.ok) {
                        const errorText = await res.text();
                        console.error('Customer search HTTP error:', res.status, errorText);
                        return null;
                    }
                    const text = await res.text();
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        console.error('Customer search response parse error:', error, text);
                        return null;
                    }
                })
                .then(data => {
                    datalist.innerHTML = '<option value="">Alle Kunden</option>';
                    if (Array.isArray(data)) {
                        data.forEach(cust => {
                            const opt = document.createElement('option');
                            const number = cust.customer_number || '';
                            const name = cust.company_name || '';
                            opt.value = `${number}${number && name ? ' - ' : ''}${name}`;
                            opt.dataset.customerId = String(cust.id ?? '');
                            opt.setAttribute('data-customer-id', String(cust.id ?? ''));
                            datalist.appendChild(opt);
                        });
                    }

                    syncCustomerSelection(input, datalist);
                })
                .catch(error => {
                    console.error('Customer search error:', error);
                });
        }, 300);

        debounceTimers.set(input, newTimer);
    }

    document.addEventListener('input', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('customer-search-combobox')) {
            return;
        }
        handleCustomerSearch(target);
        syncCustomerSelection(target);
    }, true); // capture to ensure we catch events even if re-rendered

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('customer-search-combobox')) {
            return;
        }
        syncCustomerSelection(target);
    }, true); // capture to ensure we catch events even if re-rendered
})();

class LineItemsPage {
    static handlerSetup = false;
    static activeInstance = null;

    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.filterForm = null;
        this.sendInvoiceButton = null;
        this.syncArticlesButton = null;

        if (!LineItemsPage.handlerSetup) {
            this.registerGlobalSubmitInterceptor();
            this.registerSelectAllHandler();
            LineItemsPage.handlerSetup = true;
        }

        LineItemsPage.activeInstance = this;
        this.initialize();
    }

    initialize() {
        this.filterForm = document.querySelector('form[name="get-line-items"]');
        this.sendInvoiceButton = document.getElementById('send-invoice-btn');
        this.syncArticlesButton = document.getElementById('sync-articles-btn');

        this.setupFilterFormDirect();
        this.setupSendInvoiceButton();
        this.setupSyncArticlesButton();
    }

    registerGlobalSubmitInterceptor() {
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (!form.matches('form[name="get-line-items"]')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const instance = LineItemsPage.activeInstance;
            if (instance) {
                await instance.handleFilterSubmit(form);
            }
        }, true);
    }

    registerSelectAllHandler() {
        document.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            const instance = LineItemsPage.activeInstance;
            if (!instance) {
                return;
            }

            if (target.classList.contains('line-items-select-all')) {
                const checkboxes = document.querySelectorAll('.line-item-select-checkbox');
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = target.checked;
                });
                instance.updateSendInvoiceButtonState();
                return;
            }

            if (target.classList.contains('line-item-select-checkbox')) {
                instance.updateSendInvoiceButtonState();
            }
        }, true);
    }

    setupFilterFormDirect() {
        if (!this.filterForm || this.filterForm.dataset.ajaxHandlerAttached === 'true') {
            return;
        }

        this.filterForm.dataset.ajaxHandlerAttached = 'true';
        this.filterForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            await this.handleFilterSubmit(this.filterForm);
        });
    }

    setupSendInvoiceButton() {
        if (!this.sendInvoiceButton) {
            return;
        }

        if (this.sendInvoiceButton.dataset.ajaxHandlerAttached !== 'true') {
            this.sendInvoiceButton.dataset.ajaxHandlerAttached = 'true';
            this.sendInvoiceButton.addEventListener('click', async () => {
                await this.handleCreateInvoiceFromSelection();
            });
        }

        this.updateSendInvoiceButtonState();
    }

    setupSyncArticlesButton() {
        if (!this.syncArticlesButton || this.syncArticlesButton.dataset.ajaxHandlerAttached === 'true') {
            return;
        }

        this.syncArticlesButton.dataset.ajaxHandlerAttached = 'true';
        this.syncArticlesButton.addEventListener('click', async () => {
            await this.handleArticlesSync();
        });
    }

    async handleFilterSubmit(form) {
        this.ensureCustomerSelection(form);

        const params = this.buildFilterParams(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalLabel = submitButton ? submitButton.innerHTML : null;

        try {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="btn-icon spinning">↻</span> Filtern...';
            }

            const data = await this.fetchLineItems(params);
            this.updateLineItemsList(data);
            this.updateSendInvoiceButtonState();
            this.showToast('Line items aktualisiert', 'success');
        } catch (error) {
            console.error('Line items filter error:', error);
            this.showToast(error.message || 'Fehler beim Laden der Positionen', 'error');
        } finally {
            if (submitButton && originalLabel !== null) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalLabel;
            }
        }
    }

    buildFilterParams(form) {
        const params = new URLSearchParams();
        const formData = new FormData(form);

        for (const [key, value] of formData.entries()) {
            if (key === 'customer_search') {
                continue;
            }

            if (value !== null && value !== '') {
                params.append(key, value);
            }
        }

        return params;
    }

    async fetchLineItems(params) {
        const query = params.toString();
        const url = query ? resolveApiUrl(`line-items?${query}`) : resolveApiUrl('line-items');

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        });

        const text = await response.text();
        let data = {};
        if (text.trim() !== '') {
            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error('Antwort konnte nicht interpretiert werden.');
            }
        }

        if (!response.ok) {
            const message = data.error || `Line items Anfrage fehlgeschlagen (Status ${response.status}).`;
            throw new Error(message);
        }

        return data;
    }

    updateLineItemsList(data) {
        const container = document.querySelector('.line-items-list');
        if (!container) {
            console.warn('Line items list container not found');
            return;
        }

        const tableBody = container.querySelector('.line-items-table-body');
        const selectAll = container.querySelector('.line-items-select-all');
        const totalLabel = container.querySelector('.line-items-total');

        if (!(tableBody instanceof HTMLElement)) {
            console.warn('Line items table body not found');
            return;
        }

        const items = Array.isArray(data?.lineItems) ? data.lineItems : [];
        const columnCount = container.querySelectorAll('thead th').length || 9;

        if (items.length === 0) {
            tableBody.innerHTML = `
                <tr class="line-items-empty-row">
                    <td colspan="${columnCount}" style="text-align:center;">Keine Positionen gefunden.</td>
                </tr>
            `;
        } else {
            const tableRows = items.map((item) => {
                const position = item.line_order != null ? item.line_order : '';
                const quantity = item.quantity != null ? this.formatNumber(item.quantity, 3) : '';
                const netAmount = item.net_amount != null ? this.formatNumber(item.net_amount, 2) : '';
                const grossAmount = item.line_total_gross != null ? this.formatNumber(item.line_total_gross, 2) : '';
                const taxRate = item.tax_rate_percentage != null ? this.formatNumber(item.tax_rate_percentage, 2) : '';
                const { date: createdDate, time: createdTime } = this.splitDateTime(item.created_at);

                const checkbox = `<input type="checkbox" class="line-item-select-checkbox" data-line-item-id="${this.escapeHtml(item.id)}">`;

                return `
                    <tr>
                        <td>${checkbox}</td>
                        <td>${this.escapeHtml(position)}</td>
                        <td>${this.escapeHtml(item.name || '')}</td>
                        <td>${this.escapeHtml(quantity)}</td>
                        <td>${this.escapeHtml(netAmount)}</td>
                        <td>${this.escapeHtml(grossAmount)}</td>
                        <td>${this.escapeHtml(taxRate)}</td>
                        <td>${this.escapeHtml(createdDate)}</td>
                        <td>${this.escapeHtml(createdTime)}</td>
                    </tr>
                `;
            }).join('');

            tableBody.innerHTML = tableRows;
        }

        if (selectAll instanceof HTMLInputElement) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }

        if (totalLabel) {
            totalLabel.textContent = `Gesammt: ${items.length}`;
        }
    }

    async handleArticlesSync() {
        const button = this.syncArticlesButton;
        if (!button) {
            return;
        }

        const originalDisabled = button.disabled;
        const originalHtml = button.innerHTML;

        try {
            button.disabled = true;
            button.innerHTML = '<span class="btn-icon spinning">↻</span><span>Synchronisiere...</span>';

            const response = await fetch(resolveApiUrl('articles/sync'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})
            });

            const text = await response.text();
            const data = this.parseJsonSafely(text);

            if (!response.ok || data.isSuccess === false) {
                const errors = Array.isArray(data.errors) && data.errors.length ? data.errors.join(', ') : null;
                const message = data.error || errors || `Synchronisierung fehlgeschlagen (Status ${response.status}).`;
                throw new Error(message);
            }

            const created = typeof data.created === 'number' ? data.created : 0;
            const updated = typeof data.updated === 'number' ? data.updated : 0;
            const priceUpdates = typeof data.price_updates === 'number' ? data.price_updates : 0;

            const summaryParts = [];
            if (created > 0) {
                summaryParts.push(`${created} neu`);
            }
            if (updated > 0) {
                summaryParts.push(`${updated} aktualisiert`);
            }
            if (priceUpdates > 0) {
                summaryParts.push(`${priceUpdates} Preisänderungen`);
            }

            const suffix = summaryParts.length ? ` (${summaryParts.join(', ')})` : '';
            this.showToast(`Artikel synchronisiert${suffix}`, 'success');

            await this.reloadCurrentLineItems();
        } catch (error) {
            console.error('Article sync failed:', error);
            this.showToast(error.message || 'Artikel konnten nicht synchronisiert werden.', 'error');
        } finally {
            button.disabled = originalDisabled;
            button.innerHTML = originalHtml;
        }
    }

    async handleCreateInvoiceFromSelection() {
        const selectedIds = this.getSelectedLineItemIds();
        if (selectedIds.length === 0) {
            this.showToast('Bitte wählen Sie mindestens eine Position aus.', 'warning');
            return;
        }

        const customerId = this.getSelectedCustomerId();
        if (!customerId) {
            this.showToast('Bitte wählen Sie einen Kunden aus.', 'warning');
            return;
        }

        try {
            const payload = {
                customer_id: customerId,
                currency: 'EUR',
                line_items: selectedIds.map((id) => ({ id }))
            };

            const response = await fetch(resolveApiUrl('invoices'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const text = await response.text();
            const data = this.parseJsonSafely(text);

            if (!response.ok || !data.invoice_id) {
                const message = data.error || `Rechnung konnte nicht erstellt werden (Status ${response.status}).`;
                throw new Error(message);
            }

            this.showToast('Rechnung erfolgreich erstellt.', 'success');
            await this.reloadCurrentLineItems();
        } catch (error) {
            console.error('Invoice creation failed:', error);
            this.showToast(error.message || 'Rechnung konnte nicht erstellt werden.', 'error');
        }
    }

    async reloadCurrentLineItems() {
        if (!this.filterForm) {
            return;
        }

        try {
            this.ensureCustomerSelection(this.filterForm);
            const params = this.buildFilterParams(this.filterForm);
            const data = await this.fetchLineItems(params);
            this.updateLineItemsList(data);
            this.updateSendInvoiceButtonState();
        } catch (error) {
            console.error('Reloading line items failed:', error);
        }
    }

    getSelectedLineItemIds() {
        return Array.from(document.querySelectorAll('.line-item-select-checkbox:checked'))
            .map((checkbox) => checkbox.getAttribute('data-line-item-id'))
            .filter(Boolean);
    }

    getSelectedCustomerId() {
        if (!this.filterForm) {
            return '';
        }

        const hidden = this.filterForm.querySelector('input[type="hidden"][name="customer_id"]');
        return hidden ? hidden.value.trim() : '';
    }

    updateSendInvoiceButtonState() {
        if (!this.sendInvoiceButton) {
            return;
        }

        const anyChecked = document.querySelector('.line-item-select-checkbox:checked');
        this.sendInvoiceButton.disabled = !anyChecked;
    }

    ensureCustomerSelection(form) {
        if (!form) {
            return;
        }

        const customerInput = form.querySelector('.customer-search-combobox');
        if (!customerInput) {
            return;
        }

        const event = new Event('change');
        customerInput.dispatchEvent(event);
    }

    parseJsonSafely(text) {
        if (typeof text !== 'string' || text.trim() === '') {
            return {};
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('Antwort konnte nicht interpretiert werden.');
        }
    }

    showToast(message, level = 'info') {
        if (this.lexBridge && this.lexBridge.toastNotifier) {
            this.lexBridge.toastNotifier.show(message, level);
            return;
        }

        if (level === 'error' || level === 'warning') {
            window.alert(message);
        }
    }

    escapeHtml(value) {
        if (window.lexBridgeUtils && typeof window.lexBridgeUtils.escapeHtml === 'function') {
            return window.lexBridgeUtils.escapeHtml(value);
        }
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    formatNumber(value, fractionDigits) {
        const number = Number(value);
        if (Number.isNaN(number)) {
            return '';
        }

        const digits = typeof fractionDigits === 'number' ? fractionDigits : 2;
        return number.toLocaleString('de-DE', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });
    }

    splitDateTime(value) {
        if (!value) {
            return { date: '', time: '' };
        }

        const parsedDate = new Date(value);
        if (!Number.isNaN(parsedDate.getTime())) {
            return {
                date: parsedDate.toLocaleDateString('de-DE'),
                time: parsedDate.toLocaleTimeString('de-DE', {
                    hour: '2-digit',
                    minute: '2-digit'
                })
            };
        }

        const [datePartRaw = '', timePartRaw = ''] = value.split(/[T ]/);
        const date = this.formatIsoDate(datePartRaw);
        const time = this.formatTimeString(timePartRaw);

        return { date, time };
    }

    formatIsoDate(value) {
        if (!value) {
            return '';
        }

        const parts = value.split('-');
        if (parts.length !== 3) {
            return value;
        }

        const [year, month, dayWithRest] = parts;
        const day = dayWithRest && typeof dayWithRest === 'string' ? dayWithRest.substring(0, 2) : dayWithRest;

        if (!year || !month || !day) {
            return value;
        }

        return `${day}.${month}.${year}`;
    }

    formatTimeString(value) {
        if (!value) {
            return '';
        }

        const [timePart] = value.split(/[Z+-]/);
        const normalized = timePart && typeof timePart === 'string' ? timePart.trim() : '';
        if (!normalized) {
            return '';
        }

        const [hour = '', minute = ''] = normalized.split(':');
        if (!hour) {
            return normalized.substring(0, 5);
        }

        return `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
    }
}
if (!window.lexBridge) {
    window.lexBridge = {};
}

window.lexBridge.LineItemsPage = LineItemsPage;
window.LineItemsPage = LineItemsPage;

// Utility to show/hide both form and sendBtn together
window.showLineItemsFilter = function(show) {
    const container = document.getElementById('line-items-filter-container');
    if (container) {
        container.style.display = show ? '' : 'none';
    }
    const actionBar = document.querySelector('.line-items-actions-bar');
    if (actionBar) {
        actionBar.style.display = show ? 'flex' : 'none';
    }
};


// Show/hide the filter container when switching tabs
function handleTabSwitch(tabId) {
    if (tabId === 'line-items') {
        showLineItemsFilter(true);
    } else {
        showLineItemsFilter(false);
    }
}

// Example: listen for tab switch events (customize as needed)
document.addEventListener('tabchange', function(e) {
    if (e.detail && e.detail.tabId) {
        handleTabSwitch(e.detail.tabId);
    }
});
