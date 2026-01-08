// public/js/pages/line-items.js
// Handles AJAX customer search for the Line-Items tab using event delegation

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
                static moveSendButtonNextToForm() {
                    // Removed moveSendButtonNextToForm. The button must always stay inside #line-items-filter-container in the HTML.
                }
            setupSendInvoiceButton() {
                const sendBtn = document.getElementById('send-invoice-btn');
                if (!sendBtn) return;
                // Enable/disable button based on selection
                document.addEventListener('change', () => {
                    const anyChecked = document.querySelector('.line-item-select-checkbox:checked');
                    sendBtn.disabled = !anyChecked;
                });
                // On click, trigger invoice creation
                sendBtn.addEventListener('click', () => {
                    this.handleCreateInvoiceFromSelection();
                });
            }

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
        // Setup select-all checkbox event
        document.addEventListener('change', function (event) {
            const target = event.target;
            if (target && target.classList.contains('line-items-select-all')) {
                const checkboxes = document.querySelectorAll('.line-item-select-checkbox');
                checkboxes.forEach(cb => { cb.checked = target.checked; });
            }
        });
        // Setup send-invoice button logic
        this.setupSendInvoiceButton();

        // No need to move the send-invoice button; it stays in #line-items-filter-container
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

        // Remove any existing send-invoice button to avoid duplicates
        const oldBtn = container.querySelector('#send-invoice-btn');
        if (oldBtn) oldBtn.remove();

        // Create and add send-invoice button
        const sendBtn = document.createElement('button');
        sendBtn.type = 'button';
        sendBtn.id = 'send-invoice-btn';
        sendBtn.className = 'btn btn-primary';
        sendBtn.style.margin = '10px 0';
        sendBtn.style.height = '32px';
        sendBtn.style.width = '';
        sendBtn.style.fontSize = '1em';
        sendBtn.innerHTML = 'Erstellen <span class="btn-icon" style="font-size:1.1em;">➤</span>';
        sendBtn.disabled = true;
        container.prepend(sendBtn);

        // Check selection state immediately after rendering
        const anyChecked = container.querySelector('.line-item-select-checkbox:checked');
        sendBtn.disabled = !anyChecked;

        // Enable/disable button based on selection using global event delegation
        document.addEventListener('change', function (event) {
            if (event.target && event.target.classList.contains('line-item-select-checkbox')) {
                const container = document.querySelector('.line-items-list');
                const sendBtn = container ? container.querySelector('#send-invoice-btn') : null;
                if (sendBtn) {
                    const anyChecked = container.querySelector('.line-item-select-checkbox:checked');
                    sendBtn.disabled = !anyChecked;
                }
            }
        }, true);

        const items = Array.isArray(data?.lineItems) ? data.lineItems : [];

        if (items.length === 0) {
            container.innerHTML += '<p class="line-items-empty">Keine Positionen gefunden.</p>';
            return;
        }


        const tableRows = items.map(item => {
            const position = item.line_order != null ? item.line_order : '';
            const quantity = item.quantity != null ? this.formatNumber(item.quantity, 3) : '';
            const netAmount = item.net_amount != null ? this.formatNumber(item.net_amount, 2) : '';
            const grossAmount = item.line_total_gross != null ? this.formatNumber(item.line_total_gross, 2) : '';
            const taxRate = item.tax_rate_percentage != null ? this.formatNumber(item.tax_rate_percentage, 2) : '';
            const { date: createdDate, time: createdTime } = this.splitDateTime(item.created_at);

            // Add a checkbox with a data-line-item-id attribute
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

        container.innerHTML += `
            <table class="line-items-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="line-items-select-all"></th>
                        <th>Pos.</th>
                        <th>Bezeichnung</th>
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
            </table>
        `;
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
