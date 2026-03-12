// Use injected translation map from PHP
const invoiceStatusTranslations = window.invoiceStatusTranslations || {};

(function () {
'use strict';

class InvoicesPage {

    static handlerSetup = false; // Track if handler is already set up
    
    constructor(lexBridge) 
    {
        this.lexBridge = lexBridge;
        this.currentPage = 1;
        this.pageSize = 10;
        this.totalCount = 0;
        this.paginator = null;
        this.lastQueryParams = null;
        this.init();
    }
    
    init() {
        // Event delegation will be set up globally
        if (!InvoicesPage.handlerSetup) {
            this.setupRefreshButton();
            this.setupStatusDropdown();
            InvoicesPage.handlerSetup = true;
        }

        this.setupTransferButtons();
        // Auto-load invoices when tab is activated
        this.loadInvoicesOnTabActivation();
        this.setupPaginator();
    }

    setupPaginator() {

        const container = document.querySelector('.invoices-paginator');
        if (!container || !window.lexBridgeUtils || typeof window.lexBridgeUtils.Paginator !== 'function') {
            return;
        }

        this.paginator = new window.lexBridgeUtils.Paginator(container, {
            pageSize: this.pageSize,
            onChange: ({ page, pageSize }) => {
                this.currentPage = page;
                this.pageSize = pageSize;
                this.loadInvoicesWithParams(this.lastQueryParams || new URLSearchParams());
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
    
    /**
     * Load invoices when tab is activated
     */
    async loadInvoicesOnTabActivation() {

        // Wait for the button to be available in the DOM
        const waitForButton = () => {
            return new Promise((resolve) => {
                const checkButton = () => {
                    const button = document.querySelector('form[name="get-invoices"] button[type="submit"]');
                    if (button) {
                        resolve();
                    } else {
                        setTimeout(checkButton, 50);
                    }
                };
                checkButton();
            });
        };
        
        await waitForButton();
        await this.loadInvoices(1, false);
    }
    
    /**
     * Setup refresh button using event delegation (only once)
     */
    setupRefreshButton() {
        // Use event delegation on document to catch form submit even if form is added later
        document.addEventListener('submit', async (e) => {

            if (e.target.matches('form[name="get-invoices"]')) {
                e.preventDefault(); 
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Get the submit button from the form
                const button = e.target.querySelector('button[type="submit"]');
                await this.processFilterForm(e.target, button);
                return false;
            }

        }, true); // Use capture phase to intercept before other handlers
    }
    
    /**
     * Setup status dropdown to auto-submit form on change
     */
    setupStatusDropdown() {
        // Use event delegation to catch status changes
        document.addEventListener('change', async (e) => {
            if (e.target.matches('select[name="status"]')) {
                const form = e.target.closest('form[name="get-invoices"]');
                if (form) {
                    const button = form.querySelector('button[type="submit"]');
                    await this.processFilterForm(form, button);
                }
            }
        }, true);
    }
    
    /**
     * Load invoices via AJAX
     */
    async loadInvoices(page = 1, isUserAction = false, button = null) {

        // Use provided button or try to find it
        if (!button) {
            button = document.querySelector('form[name="get-invoices"] button[type="submit"]');
        }
        
        const originalText = button?.innerHTML;
        
        try {
            
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="btn-icon spinning">↻</span> Loading...';
            }

            const params = new URLSearchParams();
            params.set('page', String(page));
            params.set('page_size', String(this.pageSize));
            this.lastQueryParams = params;
            const response = await fetch(LexBridge.resolveApiUrl(`invoices?${params.toString()}`));
            const data = await response.json();
            
            if (data && data.invoices) {
                this.totalCount = Number.isFinite(Number(data.total_count)) ? Number(data.total_count) : data.invoices.length;
                if (Number(data.page) > 0) {
                    this.currentPage = Number(data.page);
                }
                if (Number(data.page_size) > 0) {
                    this.pageSize = Number(data.page_size);
                }
                this.updateInvoiceList(data.invoices);
                this.renderPaginator();
                    // Only show toast for errors or sync/save actions, not for successful loads
            } else {
                throw new Error('Failed to load invoices');
            }

        } catch (error) {

            console.error('Error loading invoices:', error);

            if (isUserAction) {
                this.lexBridge.toastNotifier.show
                (
                    'Error loading invoices: ' + error.message,
                    'error'
                );
            }
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
    }
    
    /**
     * Update invoice list in DOM
     */
    updateInvoiceList(invoices) {

        const tbody = document.querySelector('.invoices-container tbody');

        if (!tbody) {
            console.error('Invoice tbody not found in updateInvoiceList');
            return;
        }
        
        if (invoices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No invoices found</td></tr>';
            const totalElement = document.querySelector('.invoices-total');
            if (totalElement) {
                totalElement.textContent = 'Gesammt: 0';
            }
            return;
        }
        
        tbody.innerHTML = invoices.map(invoice => this.createInvoiceRow(invoice)).join('');
        
        const totalElement = document.querySelector('.invoices-total');
        
        if (totalElement) {
            const totalValue = Number.isFinite(Number(this.totalCount)) ? Number(this.totalCount) : invoices.length;
            totalElement.textContent = `Gesammt: ${totalValue}`;
        }
        
        this.setupTransferButtons();
    }
    
    /**
     * Create invoice row HTML
     */
    createInvoiceRow(invoice) {

        const companyName = invoice.company_name || 'N/A';
        const displayName = companyName.length > 20 
            ? companyName.substring(0, 20) + '...' 
            : companyName;
        
        const translatedStatus = invoiceStatusTranslations[invoice.status] || invoice.status;
            
            return `
                <tr>
                    <td>
                        <button 
                            type="button" 
                            class="btn btn-action transfer-btn" 
                            data-invoice-id="${invoice.id}"
                            title="Transfer to Lexware"
                            style="padding: 2px 8px; line-height: 1;">
                            ▶
                        </button>
                    </td>
                    <td title="${this.escapeHtml(companyName)}">${this.escapeHtml(displayName)}</td>
                    <td>${invoice.voucher_date}</td>
                    <td>${invoice.item_count || 0}</td>
                    <td>${translatedStatus}</td>
                    <td>${invoice.transmission_attempts || 0}</td>
                    <td>${invoice.formatted_total_net} </td>
                    <td>${invoice.formatted_total_gross}</td>
                </tr>
            `;
    }
    
    /**
     * Setup transfer buttons
     */
    setupTransferButtons() {

        const transferBtns = document.querySelectorAll('.transfer-btn');
        
        transferBtns.forEach(btn => {
            btn.addEventListener('click', async () => {
                const invoiceId = btn.dataset.invoiceId;
                await this.transferInvoice(invoiceId, btn);
            });
        });
    }
    
    /**
     * Transfer invoice via AJAX
     */
    async transferInvoice(invoiceId, button) {

        const originalText = button.innerHTML;
        
        try {
            button.disabled = true;
            button.innerHTML = '⏳';
            
            const response = await fetch(LexBridge.resolveApiUrl('invoices/transfer'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ invoice_id: invoiceId })
            });
            
            const result = await response.json();
            
            if (result.isSuccess) {
                this.lexBridge.toastNotifier.show(
                    'Invoice transferred successfully to Lexware',
                    'success'
                );

                await this.loadInvoicesWithParams(this.lastQueryParams || new URLSearchParams());

            } else {
                throw new Error(result.error || 'Failed to transfer invoice');
            }
            
        } catch (error) {

            console.error('Error transferring invoice:', error);

            this.lexBridge.toastNotifier.show(
                'Error transferring invoice: ' + error.message,
                'error'
            );
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }
    
    /**
     * Process filter form submission
     */
    async processFilterForm(form, button = null) {
        // Build query parameters from form data
        const params = new URLSearchParams();
        const formData = new FormData(form);

        for (const [key, value] of formData.entries()) {
            // Skip the customer search field (it's just for autocomplete UI)
            if (key === 'customer_search') {
                continue;
            }

            // Only add non-empty values
            if (typeof value === 'string' && value.trim() !== '') {
                params.append(key, value.trim());
            }
        }

        // Load invoices with filter parameters
        this.currentPage = 1;
        await this.loadInvoicesWithParams(params, button);
    }
    
    /**
     * Load invoices with filter parameters
     */
    async loadInvoicesWithParams(params, button = null) {
        // Use provided button or try to find it
        if (!button) {
            button = document.querySelector('form[name="get-invoices"] button[type="submit"]');
        }
        
        const originalText = button?.innerHTML;
        
        try {
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="btn-icon spinning">↻</span> Loading...';
            }
            
            params.set('page', String(this.currentPage));
            params.set('page_size', String(this.pageSize));
            const queryString = params.toString();
            const url = LexBridge.resolveApiUrl(`invoices${queryString ? '?' + queryString : ''}`);
            const response = await fetch(url);
            const data = await response.json();
            
            if (data && data.invoices) {
                this.totalCount = Number.isFinite(Number(data.total_count)) ? Number(data.total_count) : data.invoices.length;
                if (Number(data.page) > 0) {
                    this.currentPage = Number(data.page);
                }
                if (Number(data.page_size) > 0) {
                    this.pageSize = Number(data.page_size);
                }
                this.lastQueryParams = params;
                this.updateInvoiceList(data.invoices);
                this.renderPaginator();
                    // Only show toast for errors or sync/save actions, not for successful loads
            } else {
                throw new Error('Failed to load invoices');
            }

        } catch (error) {
            console.error('Error loading invoices:', error);
            this.lexBridge.toastNotifier.show(
                'Error loading invoices: ' + error.message,
                'error'
            );
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
    }
    
    escapeHtml(text) {

        const div = document.createElement('div');
        if (window.lexBridgeUtils && typeof window.lexBridgeUtils.escapeHtml === 'function') {
            return window.lexBridgeUtils.escapeHtml(text);
        }

        div.textContent = text ? String(text) : '';
        return div.innerHTML;
    }
}

    if (!window.lexBridge) {
        window.lexBridge = {};
    }

    window.lexBridge.InvoicesPage = InvoicesPage;
})();
