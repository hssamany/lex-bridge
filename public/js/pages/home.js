'use strict';

class ContactsPage {
    static handlerSetup = false; // Track if handler is already set up
    
    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.currentPage = 0;
        this.init();
    }
    
    init() {
        console.log('ContactsPage initialized');
        // Event delegation will be set up globally
        if (!ContactsPage.handlerSetup) {
            this.setupRefreshButton();
            ContactsPage.handlerSetup = true;
        }
        // Removed autoLoadIfEmpty to prevent toast on first tab load
    }
    
    /**
     * Auto-load contacts if the list is empty
     */
    async autoLoadIfEmpty() {
        console.log('=== autoLoadIfEmpty called');
        // Wait a tick for the tab to be fully rendered
        setTimeout(async () => {
            const tbody = document.querySelector('.contacts-container tbody');
            console.log('Contacts tbody found:', tbody);
            console.log('Contacts tbody children count:', tbody?.children.length);
            if (tbody && tbody.children.length === 0) {
                console.log('Auto-loading contacts...');
                await this.loadContacts(0);
            } else {
                console.log('Contacts already loaded or tbody not found');
            }
        }, 100);
    }
    
    /**
     * Setup refresh button using event delegation (only once)
     */
    setupRefreshButton() {
        // Use event delegation on document to catch form submit even if form is added later
        document.addEventListener('submit', async (e) => {
            if (e.target.matches('form[name="get-contacts"]')) {
                console.log('Contacts form submit intercepted - loading via AJAX');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                await this.loadContacts(0);
                return false;
            }
        }, true); // Use capture phase to intercept before other handlers
    }
    
    /**
     * Setup refresh button directly on the form element (called after tab is visible)
     */
    setupRefreshButtonDirect() {
        const refreshForm = document.querySelector('form[name="get-contacts"]');
        console.log('setupRefreshButtonDirect - form found:', refreshForm);
        
        if (refreshForm) {
            // Remove the action attribute to prevent navigation
            refreshForm.removeAttribute('action');
            refreshForm.setAttribute('data-original-action', '?action=get-contacts');
            
            const button = refreshForm.querySelector('button[type="submit"]');
            console.log('Button found:', button);
            
            if (button && !button.dataset.ajaxHandlerAttached) {
                console.log('Attaching click handler to contacts button');
                button.dataset.ajaxHandlerAttached = 'true';
                
                // Remove submit type to prevent form submission
                button.type = 'button';
                
                button.addEventListener('click', async (e) => {
                    console.log('Contacts button clicked - loading via AJAX');
                    e.preventDefault();
                    e.stopPropagation();
                    await this.loadContacts(0);
                });
            }
        }
    }
    
    /**
     * Load contacts via AJAX
     */
    async loadContacts(page = 0) {
        const button = document.querySelector('form[name="get-contacts"] button');
        
        if (!button) {
            console.error('Refresh button html tag not found');
            return;
        }
        
        const originalText = button.innerHTML;
        console.log('Original button text:', originalText);
        
        try 
        {
            button.disabled = true;
            button.innerHTML = '<span class="btn-icon spinning">↻</span> Loading...';
            
            console.log('Fetching contacts from API...');
            const response = await fetch(`/lex-bridge/api/contacts?page=${page}`);
            console.log('Response received:', response.status);
            
            const data = await response.json();
            console.log('Data received:', data);
            
            if (data.isSuccess) {
                console.log('Success! Updating contact list with', data.contacts.length, 'contacts');
                this.updateContactList(data);
                this.lexBridge.toastNotifier.show
                (
                    `Loaded ${data.contacts.length} contacts`,
                    'success'
                );

            } else {
                throw new Error(data.error || 'Failed to load contacts');
            }
            
        } catch (error) {
            console.error('Error loading contacts:', error);
            this.lexBridge.toastNotifier.show(
                'Error loading contacts: ' + error.message,
                'error'
            );
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
            console.log('=== loadContacts finished');
        }
    }
    
    /**
     * Update contact list in DOM
     */
    updateContactList(data) {

        const tbody = document.querySelector('.contacts-container tbody');
        
        if (!tbody) return;
        
        if (data.contacts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;">No contacts found</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.contacts.map(contact => this.createContactRow(contact)).join('');
        
        // Update total
        const totalElement = document.querySelector('.contacts-container p strong');
        if (totalElement && totalElement.parentElement) {
            totalElement.parentElement.innerHTML = `<strong>Total:</strong> ${data.contacts.length} contacts`;
        }
    }
    
    /**
     * Create contact row HTML
     */
    createContactRow(contact) {
        return `
            <tr>
                <td>${this.escapeHtml(contact.id || '')}</td>
                <td>${this.escapeHtml(contact.companyName || '')}</td>
                <td>${this.escapeHtml(contact.customerNumber || '')}</td>
            </tr>
        `;
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

window.ContactsPage = ContactsPage;
