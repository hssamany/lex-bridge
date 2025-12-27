'use strict';

/**
 * Home Page Controller
 * Handles page-specific logic and configuration
 */
class HomePage {
    
    constructor() {
        this.config = {
            containers: {
                tabManager: 'tab-manager-container',
                toast: 'toast-container'
            },
            debug: true
        };
        
        this.lexBridge = null;
    }
    
    /**
     * Initialize the home page
     */
    async init() {
        try {
            // Initialize LexBridge
            this.lexBridge = new LexBridgeClass();
            this.lexBridge.configure({ debug: this.config.debug });
            
            // Initialize components first
            await this.lexBridge.init();
            
            // Then check for operation status from PHP (after toast notifier is ready)
            this.checkOperationStatus();
            
            if (this.config.debug) {
                console.log('Home page initialized successfully');
            }
            
        } catch (error) {
            console.error('Home page initialization error:', error);
        }
    }
    
    /**
     * Check for operation status from PHP session
     */
    checkOperationStatus() {
        const container = document.getElementById(this.config.containers.tabManager);
        const statusData = container?.dataset.operationStatus;
        
        if (statusData) {
            try {
                const operation = JSON.parse(statusData);
                
                // Show notification based on status
                if (operation.status === 'success') {
                    this.showNotification(operation.message, 'success');
                } else if (operation.status === 'error') {
                    this.showNotification(operation.message, 'error');
                }
                
                // Clean up data attribute
                delete container.dataset.operationStatus;
                
                // Clean up URL
                this.cleanupUrl();
                
            } catch (error) {
                console.error('Error parsing operation status:', error);
            }
        }
    }
    
    /**
     * Show notification to user
     */
    showNotification(message, type = 'info') {
        if (this.lexBridge?.toastNotifier) {
            this.lexBridge.toastNotifier.show(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }
    
    /**
     * Remove status parameter from URL
     */
    cleanupUrl() {
        const url = new URL(window.location.href);
        if (url.searchParams.has('status')) {
            url.searchParams.delete('status');
            window.history.replaceState({}, '', url.toString());
        }
    }
}

// Export to global scope
window.HomePage = HomePage;
