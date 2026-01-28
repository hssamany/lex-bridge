'use strict';

/**
 * LEX Bridge - Main Application Class
 * Handles initialization and global application state
 */
class LexBridge {
    
    static version = '1.0.0';
    static baseConfig = null;

    static getBaseConfig() {
        if (!LexBridge.baseConfig) {
            const globalConfig = (typeof window !== 'undefined' && window.lexBridgeConfig && typeof window.lexBridgeConfig === 'object')
                ? window.lexBridgeConfig
                : {};
            const baseElementHref = document.querySelector('base')?.getAttribute('href') || '/';
            const baseHref = typeof globalConfig.baseHref === 'string' && globalConfig.baseHref !== ''
                ? globalConfig.baseHref
                : baseElementHref;
            let normalizedBaseHref = baseHref.endsWith('/') ? baseHref : `${baseHref}/`;

            let basePath = typeof globalConfig.basePath === 'string' && globalConfig.basePath !== ''
                ? globalConfig.basePath
                : new URL(normalizedBaseHref, window.location.origin).pathname;

            if (!basePath.startsWith('/')) {
                basePath = `/${basePath}`;
            }

            if (basePath !== '/' && basePath.endsWith('/')) {
                basePath = basePath.slice(0, -1);
            }

            LexBridge.baseConfig = {
                baseHref: normalizedBaseHref,
                basePath: basePath || '/'
            };
        }

        return LexBridge.baseConfig;
    }

    static resolveInAppUrl(path = '') {
        const { baseHref } = LexBridge.getBaseConfig();
        const cleanedPath = (path || '').replace(/^\//, '');
        return cleanedPath === '' ? baseHref : `${baseHref}${cleanedPath}`;
    }

    static resolveApiUrl(path = '') {
        const cleanedPath = (path || '').replace(/^\//, '');
        return LexBridge.resolveInAppUrl(`api/${cleanedPath}`);
    }

    static resolvePageClass(name) {
        if (typeof window === 'undefined') {
            return undefined;
        }

        const namespace = window.lexBridge && typeof window.lexBridge === 'object'
            ? window.lexBridge
            : undefined;

        if (!namespace) {
            return undefined;
        }

        const ctor = namespace[name];
        if (!ctor && window.console && typeof window.console.warn === 'function') {
            window.console.warn(`LexBridge: Page class "${name}" not found on window.lexBridge.`);
        }

        return ctor;
    }
    
    constructor() {
        const baseConfig = LexBridge.getBaseConfig();
        const origin = window.location.origin.replace(/\/$/, '');
        const basePathForEndpoint = baseConfig.basePath === '/' ? '' : baseConfig.basePath;
        this.tabManager = null;
        this.toastNotifier = null;
        this.contactsPage = null;
        this.invoicesPage = null;
        this.lineItemsPage = null;
        this.ordersPage = null;
        this.config = {
            apiEndpoint: `${origin}${basePathForEndpoint}`,
            baseHref: baseConfig.baseHref,
            basePath: baseConfig.basePath,
            debug: true
        };

        if (typeof window !== 'undefined' && window.lexBridgeConfig && typeof window.lexBridgeConfig === 'object') {
            this.configure(window.lexBridgeConfig);
        }
    }
    
    /**
     * Initialize the application
     */
    async init() {

        this.initializeToastNotifier();
        await this.initializeTabManager();
        this.setupEventListeners();
        this.attachFormHandlers();
        // checkForNotifications() is now handled by HomePage
        
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
        const lineItemsContent = document.getElementById('line-items-tab-content')?.innerHTML || '<p>Loading line items...</p>';
        const lineItemsAction = document.getElementById('line-items-filter-template')?.innerHTML || '';
        const ordersContent = document.getElementById('orders-tab-content')?.innerHTML || '<p>Loading orders...</p>';
        const ordersAction = document.getElementById('orders-filter-template')?.innerHTML || '';

        // Define tabs configuration
        const tabsConfig = [
            {
                id: 'kontakte',
                label: 'Kontakte',
                content: contactsContent,
                action: {
                    name: 'get-kontakte',
                    icon: '↻',
                    label: 'Kontakte synchronisieren'
                }
            },
            {
                id: 'rechn',
                label: 'Rechn',
                content: invoicesContent,
                action: {
                    name: 'get-rechn',
                    icon: '↻',
                    label: 'Rechnungen aktualisieren'
                }
            },
            {
                id: 'bestellg',
                label: 'Bestellg.',
                content: ordersContent,
                action: ordersAction
            },
            {
                id: 'posn',
                label: 'Posn',
                content: lineItemsContent,
                action: lineItemsAction
            }
        ];

        this.tabManager = new TabManager({
            containerId: 'tab-manager-container',
            tabs: tabsConfig,
            defaultTab: 'kontakte',
            debug: this.config.debug
        });
        
        // Wait for TabManager to be ready
        await this.tabManager.initPromise;
        
        // Set callback for tab changes
        this.tabManager.onTabChange((tabName) => {
            this.onTabChange(tabName);
            // Dispatch a custom event for tab changes (for LineItemsPage)
            const evt = new CustomEvent('tabChanged', { detail: { tabName } });
            document.dispatchEvent(evt);
        });
        
        // Manually trigger onTabChange for the initially active tab
        const activeTab = this.tabManager.getActiveTab();
        if (activeTab) {
            console.log('Triggering onTabChange for initial tab:', activeTab);
            this.onTabChange(activeTab);
        }
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
        // Note: Contact sync is now handled by ContactsPage class via AJAX
        // Note: Invoice refresh is now handled by InvoicesPage class via AJAX
        // No need for form submission handlers here
        
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
     * Handle refresh invoices start - show spinning animation
     * @param {Event} e - Submit event
     */
    handleRefreshInvoicesStart(e) {

        const form = e.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        const icon = button.querySelector('.btn-icon');
        
        if (button && icon) {
            button.disabled = true;            
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<span class="btn-icon spinning">↻</span> Refreshing...';
        }
        
        if (this.config.debug) {
            console.log('Starting invoice refresh...');
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
        
        // Initialize page-specific functionality
        if (tabName === 'kontakte' && !this.contactsPage) {
            const ContactsPageCtor = LexBridge.resolvePageClass('ContactsPage');
            if (ContactsPageCtor) {
                console.log('Creating ContactsPage instance');
                this.contactsPage = new ContactsPageCtor(this);
            }
        } else if (tabName === 'rechn' && !this.invoicesPage) {
            const InvoicesPageCtor = LexBridge.resolvePageClass('InvoicesPage');
            if (InvoicesPageCtor) {
                console.log('Creating InvoicesPage instance');
                this.invoicesPage = new InvoicesPageCtor(this);
            }
        } else if (tabName === 'bestellg' && !this.ordersPage) {
            const OrdersPageCtor = LexBridge.resolvePageClass('OrdersPage');
            if (OrdersPageCtor) {
                console.log('Creating OrdersPage instance');
                this.ordersPage = new OrdersPageCtor(this);
            }
        } else if (tabName === 'posn' && !this.lineItemsPage) {
            const LineItemsPageCtor = LexBridge.resolvePageClass('LineItemsPage');
            if (LineItemsPageCtor) {
                console.log('Creating LineItemsPage instance');
                this.lineItemsPage = new LineItemsPageCtor(this);
            }
        }
        
        this.updateActionButtons(tabName);
        
        // Re-setup event handlers after buttons are visible
        setTimeout(() => {
            if (tabName === 'kontakte' && this.contactsPage) {
                this.contactsPage.setupRefreshButtonDirect();
            } else if (tabName === 'rechn' && this.invoicesPage) {
                this.invoicesPage.setupRefreshButtonDirect();
            } else if (tabName === 'bestellg' && this.ordersPage) {
                this.ordersPage.setupFilterFormDirect();
            } else if (tabName === 'posn' && this.lineItemsPage) {
                this.lineItemsPage.setupFilterFormDirect();
            }
        }, 100);
    }
    
    /**
     * Show/hide action buttons based on active tab
     * @param {string} tabName - Active tab name
     */
    updateActionButtons(tabName) {
        const actionButtons = document.querySelectorAll('.tab-action');
        actionButtons.forEach(action => {
            const forTab = action.getAttribute('data-for');
            action.style.display = forTab === tabName ? '' : 'none';
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
        const invoicesContainer = document.querySelector('.invoices-container');
        const hasInvoices = invoicesContainer?.hasAttribute('data-invoices');
        
        // If no invoices data is present, trigger automatic load
        if (!hasInvoices) {
            if (this.config.debug) {
                console.log('No invoices loaded, triggering automatic load...');
            }
            
            // Find and submit the refresh invoices form
            const refreshForm = document.querySelector('form[name="get-rechn"]');
            if (refreshForm) {
                refreshForm.submit();
            }
        }
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
window.lexBridge = new LexBridge();
window.lexBridge.init && window.lexBridge.init();
