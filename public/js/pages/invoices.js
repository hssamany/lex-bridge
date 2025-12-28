'use strict';

class InvoicesPage {
    static handlerSetup = false; // Track if handler is already set up
    
    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.init();
    }
    
    init() {
        console.log('InvoicesPage initialized');
        // Event delegation will be set up globally
        if (!InvoicesPage.handlerSetup) {
            this.setupRefreshButton();
            InvoicesPage.handlerSetup = true;
        }
        this.setupTransferButtons();
        // Auto-load invoices on page load if empty
        this.autoLoadIfEmpty();
    }
    
    /**
     * Auto-load invoices if the list is empty
     */
    async autoLoadIfEmpty() {
        console.log('InvoicesPage: checking if should auto-load');
        // Wait a tick for the tab to be fully rendered
        setTimeout(async () => {
            const tbody = document.querySelector('.invoices-container tbody');
            console.log('Invoice tbody found:', tbody);
            console.log('Invoice tbody children count:', tbody?.children.length);
            if (tbody && tbody.children.length === 0) {
                console.log('Auto-loading invoices...');
                await this.loadInvoices();
            }
        }, 100);
    }
    
    /**
     * Setup refresh button using event delegation (only once)
     */
    setupRefreshButton() {
        // Use event delegation on document to catch form submit even if form is added later
        document.addEventListener('submit', async (e) => {
            if (e.target.matches('form[name="get-invoices"]')) {
                console.log('Invoices form submit intercepted - loading via AJAX');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                await this.loadInvoices();
                return false;
            }
        }, true); // Use capture phase to intercept before other handlers
    }
    
    /**
     * Setup refresh button directly on the form element (called after tab is visible)
     */
    setupRefreshButtonDirect() {

        const refreshForm = document.querySelector('form[name="get-invoices"]');
        console.log('setupRefreshButtonDirect - form found:', refreshForm);
        
        if (refreshForm) {
            // Remove the action attribute to prevent navigation
            refreshForm.removeAttribute('action');
            refreshForm.setAttribute('data-original-action', '?action=get-invoices');
            
            const button = refreshForm.querySelector('button[type="submit"]');
            console.log('Button found:', button);
            
            if (button && !button.dataset.ajaxHandlerAttached) {
                
                button.dataset.ajaxHandlerAttached = 'true';
                
                // Remove submit type to prevent form submission
                button.type = 'button';
                
                button.addEventListener('click', async (e) => {

                    e.preventDefault();
                    e.stopPropagation();

                    await this.loadInvoices();
                });
            }
        }
    }
    
    /**
     * Load invoices via AJAX
     */
    async loadInvoices() {
        const button = document.querySelector('form[name="get-invoices"] button');
        
        if (!button) {
            console.error('Refresh button not found');
            return;
        }
        
        const originalText = button.innerHTML;
        
        console.log('Loading invoices from API...');
        
        try {
            button.disabled = true;
            button.innerHTML = '<span class="btn-icon spinning">↻</span> Loading...';
            
            const response = await fetch('/lex-bridge/public/index.php?api=invoices');
            const data = await response.json();
            
            if (data && data.invoices) {
                this.updateInvoiceList(data.invoices);
                this.lexBridge.toastNotifier.show(
                    `Loaded ${data.invoices.length} invoices`,
                    'success'
                );
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
            button.disabled = false;
            button.innerHTML = originalText;
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
            return;
        }
        
        tbody.innerHTML = invoices.map(invoice => this.createInvoiceRow(invoice)).join('');
        
        const totalElement = document.querySelector('.invoices-container p strong');
        if (totalElement && totalElement.parentElement) {
            totalElement.parentElement.innerHTML = `<strong>Total:</strong> ${invoices.length} invoices`;
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
        
        return `
            <tr>
                <td>
                    <button 
                        type="button" 
                        class="btn btn-action transfer-btn" 
                        data-invoice-id="${invoice.id}"
                        title="Transfer to Lexware">
                        ▶
                    </button>
                </td>
                <td title="${this.escapeHtml(companyName)}">${this.escapeHtml(displayName)}</td>
                <td>${invoice.voucher_date}</td>
                <td>${invoice.item_count || 0}</td>
                <td>${invoice.status}</td>
                <td>${invoice.transmission_attempts || 0}</td>
                <td>${invoice.total_gross_amount} ${invoice.currency}</td>
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
            
            const response = await fetch('/lex-bridge/public/index.php?api=invoices/transfer', {
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
                await this.loadInvoices();
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
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

window.InvoicesPage = InvoicesPage;
