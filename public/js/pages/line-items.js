// public/js/pages/line-items.js
// Relies on customer-search-controller.js and article-search-controller.js with line-item persistence helpers

class LineItemsPage {
        getSelectedLineItemIds() {
            return Array.from(document.querySelectorAll('.line-item-select-checkbox:checked'))
                .map(cb => cb.getAttribute('data-line-item-id'))
                .filter(Boolean);
        }

        async handleCreateInvoiceFromSelection() {
            // Get selected line item IDs
            const selectedIds = this.getSelectedLineItemIds();
            if (!selectedIds.length) {
                alert('Bitte wählen Sie mindestens eine Position aus.');
                return;
            }

            // Get customer ID from filter form
            const form = document.querySelector('form[name="get-line-items"]');
            let customerId = '';
            if (form) {
                const hidden = form.querySelector('input[type="hidden"][name="customer_id"]');
                if (hidden) customerId = hidden.value;
            }
            if (!customerId) {
                alert('Bitte wählen Sie einen Kunden aus.');
                return;
            }

            // Prepare lineItems array (just IDs, or you can fetch more info if needed)
            const lineItems = selectedIds.map(id => ({ id }));

            // Optionally, ask for currency or use a default
            const currency = 'EUR';

            // Send to API
            try {
                const response = await fetch(LexBridge.resolveApiUrl('invoices'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: customerId, currency, line_items: lineItems })
                });
                const result = await response.json();
                if (response.ok && result.invoice_id) {
                    alert('Rechnung erfolgreich erstellt!');
                } else {
                    alert('Fehler beim Erstellen der Rechnung: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (e) {
                alert('Fehler beim Senden der Anfrage: ' + e.message);
            }
        }

        async syncArticlesFromLexware(button) {
            const targetButton = button instanceof HTMLElement ? button : null;
            const originalLabel = targetButton ? targetButton.innerHTML : null;

            try {
                if (targetButton) {
                    targetButton.disabled = true;
                    targetButton.innerHTML = '<span class="btn-icon spinning">↻</span> Synchronisieren...';
                }

                const response = await fetch(LexBridge.resolveApiUrl('articles/sync'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: '{}'
                });

                const text = await response.text();
                let result;
                try {
                    result = text ? JSON.parse(text) : {};
                } catch (parseError) {
                    throw new Error('Antwort der Artikelsynchronisation konnte nicht gelesen werden.');
                }

                if (!response.ok || !result?.isSuccess) {
                    const errorMessage = Array.isArray(result?.errors) && result.errors.length
                        ? result.errors.join(', ')
                        : result?.error || `Synchronisation fehlgeschlagen (${response.status})`;
                    throw new Error(errorMessage);
                }

                if (window.lexBridge?.clearArticleCache) {
                    window.lexBridge.clearArticleCache();
                }

                if (this.lexBridge?.toastNotifier) {
                    const created = result.created ?? 0;
                    const updated = result.updated ?? 0;
                    const prices = result.price_updates ?? 0;
                    this.lexBridge.toastNotifier.show(`Artikel synchronisiert (neu: ${created}, aktualisiert: ${updated}, Preise: ${prices})`, 'success');
                } else {
                    alert('Artikel erfolgreich synchronisiert.');
                }
            } catch (error) {
                console.error('Article sync error:', error);
                const message = error instanceof Error ? error.message : String(error);
                if (this.lexBridge?.toastNotifier) {
                    this.lexBridge.toastNotifier.show(message, 'error');
                } else {
                    alert(message);
                }
            } finally {
                if (targetButton && originalLabel !== null) {
                    targetButton.disabled = false;
                    targetButton.innerHTML = originalLabel;
                }
            }
        }
    static handlerSetup = false;

    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.editorDialog = new LineItemEditorDialog(this);
        this.listContainer = null;
        this.tableElement = null;
        this.tbodyElement = null;
        this.emptyStateElement = null;
        this.sendInvoiceButton = null;
        this.syncArticlesButton = null;
        this.rowTemplate = document.getElementById('line-item-row-template');
        this.listHandlersAttached = false;
        this.boundListChangeHandler = this.handleListChange.bind(this);
        this.boundListClickHandler = this.handleListClick.bind(this);

        if (!LineItemsPage.handlerSetup) {
            this.setupFilterDelegation();
            LineItemsPage.handlerSetup = true;
        }

        this.setupFilterFormDirect();
        this.cacheDomReferences();
        this.setupToolbarHandlers();
        this.setupListDelegation();
    }

    setupFilterDelegation() {
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!form || !form.matches('form[name="get-line-items"]')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            await this.handleFilterSubmit(form);
        }, true);
    }

    setupFilterFormDirect() {
        const form = document.querySelector('form[name="get-line-items"]');
        if (!form || form.dataset.ajaxHandlerAttached === 'true') {
            return;
        }
        form.dataset.ajaxHandlerAttached = 'true';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            await this.handleFilterSubmit(form);
        });
    }

    cacheDomReferences() {
        const latestContainer = document.querySelector('.line-items-list');

        if (latestContainer && this.listContainer && this.listHandlersAttached && this.listContainer !== latestContainer) {
            this.listContainer.removeEventListener('change', this.boundListChangeHandler);
            this.listContainer.removeEventListener('click', this.boundListClickHandler);
            this.listHandlersAttached = false;
        }

        this.listContainer = latestContainer || null;

        if (!this.listContainer) {
            this.tableElement = null;
            this.tbodyElement = null;
            this.emptyStateElement = null;
            this.sendInvoiceButton = null;
            this.syncArticlesButton = null;
            this.listHandlersAttached = false;
            return;
        }

        this.tableElement = this.listContainer.querySelector('.line-items-table') || null;
        this.tbodyElement = this.tableElement?.querySelector('[data-role="line-items-tbody"]') || null;
        this.emptyStateElement = this.listContainer.querySelector('[data-role="line-items-empty"]') || null;
        this.sendInvoiceButton = this.listContainer.querySelector('#send-invoice-btn') || null;
        this.syncArticlesButton = this.listContainer.querySelector('#sync-articles-btn') || null;

        if (!this.rowTemplate) {
            this.rowTemplate = document.getElementById('line-item-row-template');
        }
    }

    setupToolbarHandlers() {
        this.cacheDomReferences();

        if (this.sendInvoiceButton && !this.sendInvoiceButton.dataset.handlerAttached) {
            this.sendInvoiceButton.dataset.handlerAttached = 'true';
            this.sendInvoiceButton.addEventListener('click', () => {
                this.handleCreateInvoiceFromSelection();
            });
        }

        if (this.syncArticlesButton && !this.syncArticlesButton.dataset.handlerAttached) {
            this.syncArticlesButton.dataset.handlerAttached = 'true';
            this.syncArticlesButton.addEventListener('click', () => {
                this.syncArticlesFromLexware(this.syncArticlesButton);
            });
        }
    }

    setupListDelegation() {
        this.cacheDomReferences();

        if (!this.listContainer || this.listHandlersAttached) {
            return;
        }

        this.listContainer.addEventListener('change', this.boundListChangeHandler);
        this.listContainer.addEventListener('click', this.boundListClickHandler);
        this.listHandlersAttached = true;
    }

    async handleFilterSubmit(form) {
        this.ensureCustomerSelection(form);

        const formData = new FormData(form);
        const params = new URLSearchParams();

        const formEntries = formData.entries();
        for (const [key, value] of formEntries) {
            if (key === 'customer_search') {
                continue;
            }
            if (value !== null && value !== '') {
                params.append(key, value);
            }
        }

        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button ? button.innerHTML : null;

        try {
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="btn-icon spinning">↻</span> Filtern...';
            }

            const response = await fetch(`${LexBridge.resolveApiUrl('line-items')}?${params.toString()}`);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Line items request failed (${response.status}): ${errorText}`);
            }

            const data = await response.json();
            this.updateLineItemsList(data);

            if (this.lexBridge?.toastNotifier) {
                this.lexBridge.toastNotifier.show('Line items aktualisiert', 'success');
            }

        } 
        catch (error) {
            console.error('Line items filter error:', error);
            if (this.lexBridge?.toastNotifier) {
                this.lexBridge.toastNotifier.show('Fehler beim Laden der Positionen', 'error');
            }
        } finally {
            if (button && originalLabel !== null) {
                button.disabled = false;
                button.innerHTML = originalLabel;
            }
        }
    }

    handleListChange(event) {
        const target = event.target;
        if (!target || !this.listContainer) {
            return;
        }

        if (target.classList.contains('line-items-select-all')) {
            const selectAllChecked = target.checked;
            const checkboxes = this.listContainer.querySelectorAll('.line-item-select-checkbox');
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAllChecked;
            });
        } else if (target.classList.contains('line-item-select-checkbox')) {
            if (!target.checked) {
                const selectAll = this.listContainer.querySelector('.line-items-select-all');
                if (selectAll) {
                    selectAll.checked = false;
                }
            } else {
                const checkboxes = Array.from(this.listContainer.querySelectorAll('.line-item-select-checkbox'));
                const allChecked = checkboxes.length > 0 && checkboxes.every((checkbox) => checkbox.checked);
                const selectAll = this.listContainer.querySelector('.line-items-select-all');
                if (selectAll) {
                    selectAll.checked = allChecked;
                }
            }
        }

        this.updateSendButtonState();
    }

    handleListClick(event) {
        const target = event.target instanceof Element ? event.target.closest('.line-item-edit-btn') : null;
        if (!target) {
            return;
        }

        event.preventDefault();

        const row = target.closest('tr[data-line-item-id]');
        if (row && this.editorDialog) {
            this.editorDialog.open(row);
        }
    }

    updateSendButtonState() {
        if (!this.sendInvoiceButton || !this.listContainer) {
            return;
        }

        const anyChecked = this.listContainer.querySelector('.line-item-select-checkbox:checked');
        this.sendInvoiceButton.disabled = !anyChecked;
    }

    toggleEmptyState(shouldShow) {
        if (!this.emptyStateElement) {
            return;
        }

        if (shouldShow) {
            this.emptyStateElement.removeAttribute('hidden');
        } else {
            this.emptyStateElement.setAttribute('hidden', '');
        }
    }

    applyArticleState(row, articleData, label, options = {}) {
        const helper = window.lexBridge?.writeRowArticleState;
        if (typeof helper === 'function') {
            helper(row, articleData, label, options);
        } else {
            console.warn('writeRowArticleState helper is unavailable.');
        }
    }

    renderLineItemRow(item, index) {
        if (!this.rowTemplate || !this.rowTemplate.content) {
            return null;
        }

        const clone = this.rowTemplate.content.cloneNode(true);
        const row = clone.querySelector('tr');
        if (!row) {
            return null;
        }

        const rowId = item?.id != null ? String(item.id) : '';
        row.dataset.lineItemId = rowId;

        const positionValue = item?.line_order != null ? String(item.line_order) : '';
        row.dataset.lineOrder = positionValue;

        const quantityValue = item?.quantity != null ? String(item.quantity) : '';
        row.dataset.quantity = quantityValue;

        const netValue = item?.net_amount != null ? String(item.net_amount) : '';
        const grossValueRaw = item?.gross_amount ?? item?.line_total_gross;
        const grossValue = grossValueRaw != null ? String(grossValueRaw) : '';
        const taxValue = item?.tax_rate_percentage != null ? String(item.tax_rate_percentage) : '';
        const currencyValue = item?.currency != null ? String(item.currency) : '';
        const articleIdValue = item?.article_id != null ? String(item.article_id) : '';
        const articleNumberValue = item?.article_number != null ? String(item.article_number) : '';
        const articleNameValue = item?.name != null ? String(item.name) : '';
        const articleLabelValue = item?.article_label
            || (articleNumberValue && articleNameValue ? `${articleNumberValue} - ${articleNameValue}` : articleNameValue)
            || '';
        const validFromValue = item?.article_valid_from != null ? String(item.article_valid_from) : '';
        const validUntilValue = item?.article_valid_until != null ? String(item.article_valid_until) : '';

        row.dataset.articleId = articleIdValue;
        row.dataset.articleNumber = articleNumberValue;
        row.dataset.articleName = articleNameValue;
        row.dataset.articleCurrency = currencyValue;
        row.dataset.articleNet = netValue;
        row.dataset.articleGross = grossValue;
        row.dataset.articleTax = taxValue;
        row.dataset.articleValidFrom = validFromValue;
        row.dataset.articleValidUntil = validUntilValue;
        row.dataset.articleLabel = articleLabelValue;

        const checkbox = row.querySelector('.line-item-select-checkbox');
        if (checkbox) {
            checkbox.dataset.lineItemId = rowId;
            checkbox.checked = false;
        }

        const articleInput = row.querySelector('[data-role="article-input"]');
        const datalist = row.querySelector('[data-role="article-datalist"]');
        if (articleInput && datalist) {
            const uniqueId = `article-options-${Date.now()}-${index}-${Math.random().toString(16).slice(2)}`;
            datalist.id = uniqueId;
            articleInput.setAttribute('list', uniqueId);

            if (articleLabelValue && articleIdValue) {
                const option = document.createElement('option');
                option.value = articleLabelValue;
                option.dataset.articleId = articleIdValue;
                if (articleNumberValue) {
                    option.dataset.articleNumber = articleNumberValue;
                }
                if (articleNameValue) {
                    option.dataset.articleName = articleNameValue;
                }
                if (netValue) {
                    option.dataset.netAmount = netValue;
                }
                if (grossValue) {
                    option.dataset.grossAmount = grossValue;
                }
                if (taxValue) {
                    option.dataset.taxRatePercentage = taxValue;
                }
                if (currencyValue) {
                    option.dataset.currency = currencyValue;
                }
                if (validFromValue) {
                    option.dataset.validFrom = validFromValue;
                }
                if (validUntilValue) {
                    option.dataset.validUntil = validUntilValue;
                }
                datalist.appendChild(option);
            }
        }

        const setCellText = (selector, value) => {
            const cell = row.querySelector(selector);
            if (cell) {
                cell.textContent = value ?? '';
            }
        };

        setCellText('[data-field="position"]', positionValue);
        setCellText('.line-item-name-cell', articleNameValue);

        const quantityDisplay = quantityValue !== '' ? this.formatNumber(quantityValue, 3) : '';
        const netAmountDisplay = netValue !== '' ? this.formatNumber(netValue, 2) : '';
        const grossAmountDisplay = grossValue !== '' ? this.formatNumber(grossValue, 2) : '';
        const taxRateDisplay = taxValue !== '' ? this.formatNumber(taxValue, 2) : '';

        setCellText('[data-field="quantity"]', quantityDisplay);
        setCellText('.line-item-net-cell', netAmountDisplay);
        setCellText('.line-item-gross-cell', grossAmountDisplay);
        setCellText('.line-item-tax-cell', taxRateDisplay);

        const { date: createdDate, time: createdTime } = this.splitDateTime(item?.created_at);
        setCellText('[data-field="created-date"]', createdDate);
        setCellText('[data-field="created-time"]', createdTime);

        const articleData = {
            id: articleIdValue,
            number: articleNumberValue,
            name: articleNameValue,
            netAmount: netValue,
            grossAmount: grossValue,
            taxRate: taxValue,
            currency: currencyValue,
            validFrom: validFromValue,
            validUntil: validUntilValue
        };

        this.applyArticleState(row, articleData, articleLabelValue, {
            skipSchedule: true,
            markPersistedSignature: true
        });

        return row;
    }

    updateLineItemsList(data) {
        this.cacheDomReferences();

        this.setupToolbarHandlers();
        this.setupListDelegation();

        if (!this.listContainer || !this.tableElement || !this.tbodyElement) {
            console.warn('Line items table structure not found');
            return;
        }

        const items = Array.isArray(data?.lineItems) ? data.lineItems : [];

        try {
            this.listContainer.dataset.lineItems = JSON.stringify({ lineItems: items });
            this.listContainer.dataset.lineItemsLoaded = 'true';
        } catch (error) {
            console.warn('Could not store line items dataset:', error);
        }

        const selectAll = this.listContainer.querySelector('.line-items-select-all');
        if (selectAll) {
            selectAll.checked = false;
        }

        this.tbodyElement.innerHTML = '';

        if (items.length === 0) {
            this.toggleEmptyState(true);
            this.updateSendButtonState();
            return;
        }

        const fragment = document.createDocumentFragment();
        items.forEach((item, index) => {
            const row = this.renderLineItemRow(item, index);
            if (row) {
                fragment.appendChild(row);
            }
        });

        this.tbodyElement.appendChild(fragment);
        this.toggleEmptyState(false);

        if (window.lexBridge?.initializeLineItemPersistenceState) {
            window.lexBridge.initializeLineItemPersistenceState(this.tableElement);
        }

        this.updateSendButtonState();
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
        const day = dayWithRest?.substring(0, 2) || dayWithRest;

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
        const normalized = timePart?.trim() || '';
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


window.LineItemsPage = LineItemsPage;

// Utility to show/hide both form and sendBtn together
window.showLineItemsFilter = function(show) {
    const container = document.getElementById('line-items-filter-container');
    if (container) {
        container.style.display = show ? '' : 'none';
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
