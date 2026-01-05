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
    static handlerSetup = false;

    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        if (!LineItemsPage.handlerSetup) {
            this.setupFilterDelegation();
            LineItemsPage.handlerSetup = true;
        }
        this.setupFilterFormDirect();
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

        } catch (error) {
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

        const items = Array.isArray(data?.lineItems) ? data.lineItems : [];

        if (items.length === 0) {
            container.innerHTML = '<p class="line-items-empty">Keine Positionen gefunden.</p>';
            return;
        }

        const tableRows = items.map(item => {
            const position = item.line_order != null ? item.line_order : '';
            const quantity = item.quantity != null ? this.formatNumber(item.quantity, 3) : '';
            const netAmount = item.net_amount != null ? this.formatNumber(item.net_amount, 2) : '';
            const grossAmount = item.line_total_gross != null ? this.formatNumber(item.line_total_gross, 2) : '';
            const taxRate = item.tax_rate_percentage != null ? this.formatNumber(item.tax_rate_percentage, 2) : '';
            const { date: createdDate, time: createdTime } = this.splitDateTime(item.created_at);

            return `
                <tr>
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

        container.innerHTML = `
            <table class="line-items-table">
                <thead>
                    <tr>
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
