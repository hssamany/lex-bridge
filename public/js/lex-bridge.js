'use strict';

/**
 * LEX Bridge - Main Application Class
 * Handles initialization and global application state
 */
class LexBridge {
    // Static version property
    static version = '1.0.0';
    
    constructor() {
        this.tabManager = null;
        this.config = {
            apiEndpoint: window.location.origin,
            debug: true
        };
    }
    
    /**
     * Initialize the application
     */
    init() {
        this.initializeTabManager();
        this.setupEventListeners();
        
        if (this.config.debug) {
            console.log('LexBridge application initialized');
        }
    }
    
    /**
     * Initialize TabManager component
     */
    initializeTabManager() {
        this.tabManager = new TabManager({
            tabSelector: '.tab',
            contentSelector: '.tab-content',
            urlHash: true,
            defaultTab: 'contacts',
            onChange: (tabName) => this.onTabChange(tabName)
        });
    }
    
    /**
     * Set up application-wide event listeners
     */
    setupEventListeners() {
        // Listen for custom events from components
        document.addEventListener('contactUpdated', (e) => {
            this.notify('Contact updated successfully!', 'success');
        });
        
        document.addEventListener('invoiceCreated', (e) => {
            this.notify('Invoice created successfully!', 'success');
        });
    }
    
    /**
     * Handle tab change events
     * @param {string} tabName - Name of activated tab
     */
    onTabChange(tabName) {
        if (this.config.debug) {
            console.log('Tab changed to:', tabName);
        }
        
        // Lazy load content or perform actions based on tab
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
     */
    notify(message, type = 'info') {
        // Simple console notification for now
        const emoji = {
            success: '✅',
            error: '❌',
            info: 'ℹ️',
            warning: '⚠️'
        };
        
        console.log(`${emoji[type] || 'ℹ️'} [${type.toUpperCase()}]`, message);
        
        // Future: Integrate with toast notification library
        // this.showToast(message, type);
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
    
    /**
     * Update application configuration
     * @param {object} newConfig - Configuration options to update
     */
    configure(newConfig) {
        this.config = { ...this.config, ...newConfig };
    }
    
    /**
     * Get current configuration
     * @returns {object} Current configuration
     */
    getConfig() {
        return { ...this.config };
    }
    
    /**
     * Destroy application instance (cleanup)
     */
    destroy() {
        if (this.tabManager) {
            this.tabManager.destroy();
        }
        
        console.log('LexBridge application destroyed');
    }
}

// Export to global scope
window.LexBridgeClass = LexBridge;
