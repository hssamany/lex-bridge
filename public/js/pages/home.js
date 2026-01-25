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
        this.autoLoadIfEmpty();
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
                console.log('Contacts form submit intercepted - syncing before reload');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                await this.syncAndReload(0);
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
                    console.log('Contacts button clicked - syncing contacts');
                    e.preventDefault();
                    e.stopPropagation();
                    await this.syncAndReload(0);
                });
            }
        }
    }
    
    /**
     * Load contacts via AJAX
     */
    async loadContacts(page = 0) {
        try {
            console.log('Fetching contacts from API...');
            const response = await fetch(LexBridge.resolveApiUrl(`contacts?page=${page}`));
            console.log('Response received:', response.status);

            const data = await response.json();
            console.log('Data received:', data);

            const contacts = Array.isArray(data.contacts) ? data.contacts : [];
            this.updateContactList({ contacts });

            return contacts;
        } catch (error) {
            console.error('Error loading contacts:', error);
            this.lexBridge.toastNotifier.show(
                'Error loading contacts: ' + error.message,
                'error'
            );
            return [];
        }
    }

    async syncContacts(page = 0) {
        console.log('Starting contact sync...');

        const response = await fetch(LexBridge.resolveApiUrl(`contacts/sync?page=${page}`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ page })
        });

        const data = await response.json();
        console.log('Sync response:', data);

        const contacts = Array.isArray(data.contacts) ? data.contacts : [];
        const hasContacts = contacts.length > 0;

        if (!response.ok || (!data.isSuccess && !hasContacts)) {
            throw new Error(data.error || 'Failed to sync contacts');
        }

        return data;
    }

    async syncAndReload(page = 0) {
        const button = document.querySelector('form[name="get-contacts"] button');
        const originalText = button ? button.innerHTML : '';

        try {
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="btn-icon spinning">↻</span> Synchronizing...';
            }

            const syncData = await this.syncContacts(page);
            const contacts = Array.isArray(syncData.contacts) ? syncData.contacts : [];

            this.updateContactList({ contacts });

            if (syncData.isSuccess) {
                this.lexBridge.toastNotifier.show(
                    `Synchronized ${contacts.length} contacts`,
                    'success'
                );
            } else {
                const warningMessage = syncData.error
                    ? `Synchronized ${contacts.length} contacts (with warnings: ${syncData.error})`
                    : `Synchronized ${contacts.length} contacts (with warnings)`;
                this.lexBridge.toastNotifier.show(warningMessage, 'warning');
            }
        } catch (error) {
            console.error('Contact sync failed:', error);
            this.lexBridge.toastNotifier.show(
                'Contact synchronization failed: ' + error.message,
                'error'
            );
            await this.loadContacts(page);
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalText;
            }
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
                <td>${this.escapeHtml(contact.companyName || '')}</td>
                <td>${this.escapeHtml(contact.customerNumber || '')}</td>
                <td>${this.escapeHtml(contact.lexCustomerNumber || '')}</td>
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
