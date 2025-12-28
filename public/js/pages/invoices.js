'use strict';

/**
 * Invoice page functionality
 * Handles invoice transfer button clicks
 */
class InvoicePage {
    
    constructor() {
        this.transferForms = null;
    }
    
    /**
     * Initialize invoice page functionality
     */
    init() {
        this.transferForms = document.querySelectorAll('.inline-form');
        
        if (this.transferForms) {
            this.transferForms.forEach(form => {
                form.addEventListener('submit', (e) => this.handleTransferSubmit(e, form));
            });
        }
    }
    
    /**
     * Handle transfer form submission
     */
    handleTransferSubmit(e, form) {
        const button = form.querySelector('.transfer-invoice-btn');
        
        if (button) {
            button.disabled = true;
            button.innerHTML = '↻';
            button.classList.add('spinning');
        }
        
        // Form will submit normally
        return true;
    }
}

// Export to global scope
window.InvoicePage = InvoicePage;
