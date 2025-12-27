'use strict';

/**
 * LEX Bridge - Main Application Class
 * Handles initialization and global application state
 */
class LexBridge {
    
    static version = '1.0.0';
    
    constructor() {
        this.tabManager = null;
        this.toastNotifier = null;
        this.config = {
            apiEndpoint: window.location.origin,
            debug: true
        };
    }
    
    /**
     * Initialize the application
     */
    async init() {

        this.initializeToastNotifier();
        await this.initializeTabManager();
        this.setupEventListeners();
        this.attachFormHandlers();
        this.checkForNotifications();
        
        if (this.config.debug) {
            console.log('LexBridge application initialized');
        }
    }
    
      
    /**
     * Update application configuration
     * @param {object} newConfig - Configuration options to update
     */
    configure = (newConfig) => this.config = { ...this.config, ...newConfig };
    
    /**
     * Get current configuration
     * @returns {object} Current configuration
     */
    getConfig  = () => ({ ... this.config });
    
    /**
     * Destroy application instance (cleanup)
     */
    destroy  = () => this.tabManager != null ? this.tabManager.destroy() : null;
    

    /**
     * Check for notifications from PHP (sync results, etc.)
     */
    checkForNotifications() {
        
        if (this.config.debug) {
            console.log('Checking for notifications...');
        }
        
        // Only show notification if status is in URL (meaning operation just completed)
        const urlParams = new URLSearchParams(window.location.search);
        const hasStatusParam = urlParams.has('status');
        
        if (!hasStatusParam) {
            // No status in URL = just a regular page load, don't show notification
            return;
        }
        
        // Check for operation status from tab-manager container
        const container = document.getElementById('tab-manager-container');
        const statusData = container?.dataset.operationStatus;
        
        let opStatus = null;
        if (statusData) {
            try {
                opStatus = JSON.parse(statusData);
            } catch (error) {
                console.error('Error parsing operation status:', error);
            }
        }

        if (opStatus) {
            
            // Try to get contacts data from data attribute
            const contactsContainer = document.querySelector('.contacts-container[data-contacts]');
            let contactsData = null;
            
            if (contactsContainer?.dataset.contacts) {
                try {
                    contactsData = JSON.parse(contactsContainer.dataset.contacts);
                } catch (error) {
                    console.error('Error parsing contacts data:', error);
                }
            }
            
            const count = contactsData?.contacts ? contactsData.contacts.length : 0;

            if (contactsData && contactsData.statusCode) {

                if (contactsData.isSuccess) {
                    this.notify(
                        `Successfully synchronized ${count} contact${count !== 1 ? 's' : ''}`,
                        'success',
                        'Sync Complete'
                    );
                } else if (contactsData.error) {
                    this.notify ( contactsData.error, 'error','Sync Failed');
                }

            } else if (opStatus.status === 'success') {
                this.notify(opStatus.message, 'success');
            } else if (opStatus.status === 'error') {
                this.notify(opStatus.message, 'error');
            }
            
            // Clean up data attribute
            if (container) {
                delete container.dataset.operationStatus;
            }
            
            // Clean up data attribute
            if (container) {
                delete container.dataset.operationStatus;
            }
            
            // Clean up URL by removing status parameter (prevent notification on reload)
            this.cleanupUrl();
        }
    }
    
    /**
     * Remove status parameter from URL without page reload
     */
    cleanupUrl() {
        const url = new URL(window.location.href);
        url.searchParams.delete('status');
        window.history.replaceState({}, '', url.toString());
    }
    
    /**
     * Initialize ToastNotifier component
     */
    initializeToastNotifier() {
        this.toastNotifier = new ToastNotifier({
            debug: this.config.debug
        });
    }
    
    /**
     * Initialize TabManager component
     */
    async initializeTabManager() {
        // Get tab content from templates
        const contactsContent = document.getElementById('contacts-tab-content')?.innerHTML || '<p>Loading contacts...</p>';
        const invoicesContent = document.getElementById('invoices-tab-content')?.innerHTML || '<p>Invoice management coming soon...</p>';
        
        // Define tabs configuration
        const tabsConfig = [
            {
                id: 'contacts',
                label: 'Contacts',
                content: contactsContent,
                action: {
                    url: '?action=get-contacts',
                    method: 'get',
                    icon: '↻',
                    label: 'Sync Contacts',
                    hiddenFields: {
                        action: 'get-contacts',
                        page: '0'
                    }
                }
            },
            {
                id: 'invoices',
                label: 'Invoices',
                content: invoicesContent,
                action: {
                    url: 'post-invoices.php',
                    method: 'post',
                    icon: '✓',
                    label: 'Post Invoices'
                }
            }
        ];
        
        this.tabManager = new TabManager({
            containerId: 'tab-manager-container',
            tabs: tabsConfig,
            defaultTab: 'contacts',
            debug: this.config.debug
        });
        
        // Wait for TabManager to be ready
        await this.tabManager.initPromise;
        
        // Set callback for tab changes
        this.tabManager.onTabChange((tabName) => {
            this.onTabChange(tabName);
        });
    }
    
    /**
     * Set up application-wide event listeners
     */
    setupEventListeners() {
        
        document.addEventListener('contactUpdated', (e) => {
            this.notify('Contact updated successfully!', 'success');
        });
        
        document.addEventListener('invoiceCreated', (e) => {
            this.notify('Invoice created successfully!', 'success');
        });
    }
    
    /**
     * Attach handlers to forms (sync, post, etc.)
     */
    attachFormHandlers() {
        // Handle sync contacts form
        const syncForm = document.querySelector('form[action*="get-contacts"]');
        syncForm?.addEventListener('submit', (e) => {
            this.handleSyncStart(e);
        });
        
        // Handle post invoices form
        const postForm = document.querySelector('form[action*="post-invoices"]');
        postForm?.addEventListener('submit', (e) => {
            this.handlePostStart(e);
        });
    }
    
    /**
     * Handle sync contacts start - show spinning animation
     * @param {Event} e - Submit event
     */
    handleSyncStart(e) {

        const form = e.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        const icon = button.querySelector('.btn-icon');
        
        if (button && icon) {
            button.disabled = true;            
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<span class="btn-icon spinning">↻</span> Synchronizing...';
        }
        
        if (this.config.debug) {
            console.log('Starting contact synchronization...');
        }
    }
    
    /**
     * Handle post invoices start - show spinning animation
     * @param {Event} e - Submit event
     */
    handlePostStart(e) {

        const form = e.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        const icon = button.querySelector('.btn-icon');
        
        if (button && icon) {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<span class="btn-icon spinning">↻</span> Posting...';
        }
        
        if (this.config.debug) {
            console.log('Starting invoice posting...');
        }
    }
    
    /**
     * Handle tab change events
     * @param {string} tabName - Name of activated tab
     */
    onTabChange(tabName) {

        if (this.config.debug) {
            console.log('Tab changed to:', tabName);
        }
        
        this.updateActionButtons(tabName);
        
        switch(tabName) {
            case 'contacts':
                this.loadContactsIfNeeded();
                break;
            case 'invoices':
                this.loadInvoicesIfNeeded();
                break;
        }
    }
    
    /**
     * Show/hide action buttons based on active tab
     * @param {string} tabName - Active tab name
     */
    updateActionButtons(tabName) {
        const actionButtons = document.querySelectorAll('.tab-action');
        actionButtons.forEach(action => {
            const forTab = action.getAttribute('data-for');
            action.style.display = forTab === tabName ? 'block' : 'none';
        });
    }
    
    /**
     * Load contacts if not already loaded
     */
    loadContactsIfNeeded() {
        // Future: Check if contacts need refresh
        // if (this.shouldRefreshContacts()) {
        //     this.api('?action=get-contacts').then(data => ...);
        // }
    }
    
    /**
     * Load invoices if not already loaded
     */
    loadInvoicesIfNeeded() {
        // Future: Lazy load invoice data
    }
    
    /**
     * Get the tab manager instance
     * @returns {TabManager|null}
     */
    getTabManager() {
        return this.tabManager;
    }
    
    /**
     * Display notification
     * @param {string} message - Message to display
     * @param {string} type - Type of notification (success, error, info, warning)
     * @param {string} title - Optional title for the notification
     * @param {number} duration - Duration in milliseconds (default: 5000)
     */
    notify(message, type = 'info', title = '', duration = 5000) {
        if (this.toastNotifier) {
            this.toastNotifier.show(message, type, title, duration);
        }
        
        // Also log to console if debug enabled
        if (this.config.debug) {
            const emoji = {
                success: '✅',
                error: '❌',
                info: 'ℹ️',
                warning: '⚠️'
            };
            console.log(`${emoji[type] || 'ℹ️'} [${type.toUpperCase()}]`, message);
        }
    }
    
    /**
     * Make API calls
     * @param {string} endpoint - API endpoint
     * @param {object} options - Fetch options
     * @returns {Promise<any>} API response
     */
    async api(endpoint, options = {}) {
        const url = endpoint.startsWith('http') 
            ? endpoint 
            : `${this.config.apiEndpoint}/${endpoint.replace(/^\//, '')}`;
        
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (this.config.debug) {
                console.log('API Response:', data);
            }
            
            return data;
            
        } catch (error) {
            console.error('API Error:', error);
            this.notify(error.message, 'error');
            throw error;
        }
    }  
}

// Export to global scope
window.LexBridgeClass = LexBridge;
