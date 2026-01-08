// public/js/pages/line-items.js
// Handles AJAX customer search for the Line-Items tab using event delegation

(function () {
    const debounceTimers = new WeakMap();
    const articleDebounceTimers = new WeakMap();

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
            fetch(`/lex-bridge/api/customers/search?q=${encodeURIComponent(query)}`)
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

    function syncArticleSelection(input, datalistOverride) {
        if (!input) {
            return;
        }

        const wrapper = input.closest('.article-selector');
        if (!wrapper) {
            return;
        }

        const hiddenField = wrapper.querySelector('.article-id-field');
        if (!hiddenField) {
            return;
        }

        hiddenField.value = '';

        const listId = input.getAttribute('list');
        const datalist = datalistOverride || (listId ? document.getElementById(listId) : null);
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
            const articleId = option.dataset.articleId || option.getAttribute('data-article-id');
            if (articleId) {
                hiddenField.value = articleId;
            }
            break;
        }
    }

    function handleArticleSearch(input) {
        const listId = input.getAttribute('list');
        const datalist = listId ? document.getElementById(listId) : null;
        if (!datalist) {
            console.warn('Article datalist not found for input', input);
            return;
        }

        const query = input.value.trim();
        if (!query) {
            datalist.innerHTML = '<option value="">Artikel wählen</option>';
            syncArticleSelection(input, datalist);
            return;
        }

        const timer = articleDebounceTimers.get(input);
        if (timer) {
            clearTimeout(timer);
        }

        syncArticleSelection(input, datalist);

        const newTimer = setTimeout(() => {
            fetch(`/lex-bridge/api/articles/search?q=${encodeURIComponent(query)}`)
                .then(async res => {
                    if (!res.ok) {
                        const errorText = await res.text();
                        console.error('Article search HTTP error:', res.status, errorText);
                        return null;
                    }
                    const text = await res.text();
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        console.error('Article search response parse error:', error, text);
                        return null;
                    }
                })
                .then(data => {
                    datalist.innerHTML = '<option value="">Artikel wählen</option>';
                    if (Array.isArray(data)) {
                        data.forEach(article => {
                            const opt = document.createElement('option');
                            const number = article.article_number || article.number || '';
                            const name = article.name || article.title || '';
                            const labelParts = [number, name].filter(Boolean);
                            opt.value = labelParts.join(' - ');
                            opt.dataset.articleId = String(article.id ?? '');
                            opt.setAttribute('data-article-id', String(article.id ?? ''));
                            datalist.appendChild(opt);
                        });
                    }
                    syncArticleSelection(input, datalist);
                })
                .catch(error => {
                    console.error('Article search error:', error);
                });
        }, 300);

        articleDebounceTimers.set(input, newTimer);
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

    document.addEventListener('input', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('article-search-combobox')) {
            return;
        }
        handleArticleSearch(target);
        syncArticleSelection(target);
    }, true);

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('article-search-combobox')) {
            return;
        }
        syncArticleSelection(target);
    }, true);
})();

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
                const response = await fetch('/lex-bridge/api/invoices', {
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
    static handlerSetup = false;

    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        if (!LineItemsPage.handlerSetup) {
            this.setupFilterDelegation();
            LineItemsPage.handlerSetup = true;
        }
        this.setupFilterFormDirect();
        // Send-invoice button is rendered dynamically with the line-items list
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

    async handleFilterSubmit(form) {
        this.ensureCustomerSelection(form);

        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
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

            const response = await fetch(`/lex-bridge/api/line-items?${params.toString()}`);
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

    updateLineItemsList(data) {
        const container = document.querySelector('.line-items-list');
        if (!container) {
            console.warn('Line items list container not found');
            return;
        }

        if (this.lineItemsChangeHandler) {
            container.removeEventListener('change', this.lineItemsChangeHandler);
            this.lineItemsChangeHandler = null;
        }

        container.innerHTML = '';

        const sendBtn = document.createElement('button');
        sendBtn.type = 'button';
        sendBtn.id = 'send-invoice-btn';
        sendBtn.className = 'btn btn-primary';
        sendBtn.style.margin = '10px 0';
        sendBtn.style.height = '32px';
        sendBtn.style.fontSize = '1em';
        sendBtn.innerHTML = 'Erstellen <span class="btn-icon" style="font-size:1.1em;">➤</span>';
        sendBtn.disabled = true;
        sendBtn.addEventListener('click', () => {
            this.handleCreateInvoiceFromSelection();
        });
        container.appendChild(sendBtn);

        const items = Array.isArray(data?.lineItems) ? data.lineItems : [];
        if (items.length === 0) {
            const emptyState = document.createElement('p');
            emptyState.className = 'line-items-empty';
            emptyState.textContent = 'Keine Positionen gefunden.';
            container.appendChild(emptyState);
            return;
        }

        const tableRows = items.map((item, index) => {
            const position = item.line_order != null ? item.line_order : '';
            const quantity = item.quantity != null ? this.formatNumber(item.quantity, 3) : '';
            const netAmount = item.net_amount != null ? this.formatNumber(item.net_amount, 2) : '';
            const grossAmount = item.line_total_gross != null ? this.formatNumber(item.line_total_gross, 2) : '';
            const taxRate = item.tax_rate_percentage != null ? this.formatNumber(item.tax_rate_percentage, 2) : '';
            const { date: createdDate, time: createdTime } = this.splitDateTime(item.created_at);

            const checkbox = `<input type="checkbox" class="line-item-select-checkbox" data-line-item-id="${this.escapeHtml(item.id)}">`;
            const articleText = item.article_label || item.article_name || item.article_title || '';
            const articleId = item.article_id ?? item.articleId ?? '';
            const articleListId = `article-options-${index}-${item.id ?? 'row'}`;
            const safeArticleText = this.escapeHtml(articleText);
            const safeArticleId = this.escapeHtml(articleId);
            const presetOption = safeArticleText && safeArticleId
                ? `<option value="${safeArticleText}" data-article-id="${safeArticleId}"></option>`
                : '';
            const articleCell = `
                <div class="article-selector">
                    <input type="text" class="article-search-combobox" list="${articleListId}" value="${safeArticleText}" placeholder="Artikel wählen">
                    <input type="hidden" class="article-id-field" value="${safeArticleId}">
                    <datalist id="${articleListId}">
                        <option value="">Artikel wählen</option>
                        ${presetOption}
                    </datalist>
                </div>
            `;

            return `
                <tr>
                    <td>${checkbox}</td>
                    <td>${this.escapeHtml(position)}</td>
                    <td>${this.escapeHtml(item.name || '')}</td>
                    <td>${articleCell}</td>
                    <td>${this.escapeHtml(quantity)}</td>
                    <td>${this.escapeHtml(netAmount)}</td>
                    <td>${this.escapeHtml(grossAmount)}</td>
                    <td>${this.escapeHtml(taxRate)}</td>
                    <td>${this.escapeHtml(createdDate)}</td>
                    <td>${this.escapeHtml(createdTime)}</td>
                </tr>
            `;
        }).join('');

        const table = document.createElement('table');
        table.className = 'line-items-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th><input type="checkbox" class="line-items-select-all"></th>
                    <th>Pos.</th>
                    <th>Bezeichnung</th>
                    <th>Artikel</th>
                    <th>Menge</th>
                    <th>Netto</th>
                    <th>Brutto</th>
                    <th>Steuer %</th>
                    <th>Erstellt am</th>
                    <th>Uhrzeit</th>
                </tr>
            </thead>
            <tbody>
                ${tableRows}
            </tbody>
        `;
        container.appendChild(table);

        const updateButtonState = () => {
            const anyChecked = container.querySelector('.line-item-select-checkbox:checked');
            sendBtn.disabled = !anyChecked;
        };

        updateButtonState();

        this.lineItemsChangeHandler = (event) => {
            const target = event.target;
            if (!target) {
                return;
            }

            if (target.classList.contains('line-items-select-all')) {
                const checkboxes = container.querySelectorAll('.line-item-select-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = target.checked;
                });
                updateButtonState();
                return;
            }

            if (target.classList.contains('line-item-select-checkbox')) {
                updateButtonState();
                return;
            }

            if (target.classList.contains('article-search-combobox')) {
                syncArticleSelection(target);
            }
        };

        container.addEventListener('change', this.lineItemsChangeHandler);
    }

    escapeHtml(value) {
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
