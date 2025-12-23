'use strict';

/**
 * TabManager Component
 * Manages tab navigation behavior for existing HTML structure
 * Fully reusable across different pages and applications
 */
class TabManager {
    /**
     * Initialize TabManager
     * @param {Object} options - Configuration options
     * @param {string} options.tabSelector - CSS selector for tab buttons (default: '.tab')
     * @param {string} options.contentSelector - CSS selector for tab content (default: '.tab-content')
     * @param {boolean} options.urlHash - Enable URL hash navigation (default: false)
     * @param {Function} options.onChange - Callback when tab changes
     * @param {string} options.defaultTab - Default tab to activate (default: first tab)
     */
    constructor(options = {}) {
        this.tabSelector = options.tabSelector || '.tab';
        this.contentSelector = options.contentSelector || '.tab-content';
        this.urlHash = options.urlHash || false;
        this.onChange = options.onChange || null;
        this.defaultTab = options.defaultTab || null;
        this.activeTab = null;
        this.validTabs = []; // Auto-detected from DOM
        
        this.init();
    }

    /**
     * Initialize the tab manager
     */
    init() {
        this.tabs = document.querySelectorAll(this.tabSelector);
        this.contents = document.querySelectorAll(this.contentSelector);
        
        if (this.tabs.length === 0) {
            console.warn('TabManager: No tabs found with selector:', this.tabSelector);
            return;
        }

        // Auto-detect valid tabs from DOM
        this.validTabs = Array.from(this.tabs).map(tab => 
            tab.getAttribute('data-tab')
        ).filter(Boolean);

        this.attachEventListeners();
        
        // Handle URL hash navigation if enabled
        if (this.urlHash) {
            this.setupUrlHashNavigation();
        }
        
        this.activateInitialTab();
    }

    /**
     * Attach click event listeners to all tab buttons
     */
    attachEventListeners() {
        this.tabs.forEach(tab => {
            tab.addEventListener('click', (evt) => {
                const tabName = evt.currentTarget.getAttribute('data-tab');
                if (tabName) {
                    this.openTab(evt, tabName);
                } else {
                    console.error('TabManager: Tab button missing data-tab attribute');
                }
            });
        });
    }

    /**
     * Activate the initial tab based on priority:
     * 1. URL hash (if enabled)
     * 2. Default tab option
     * 3. First tab in DOM
     */
    activateInitialTab() {
        let targetTab = null;

        // Priority 1: URL hash
        if (this.urlHash) {
            const hash = window.location.hash.substring(1);
            if (hash && this.validTabs.includes(hash)) {
                targetTab = hash;
            }
        }

        // Priority 2: Default tab option
        if (!targetTab && this.defaultTab && this.validTabs.includes(this.defaultTab)) {
            targetTab = this.defaultTab;
        }

        // Priority 3: First tab
        if (!targetTab && this.tabs.length > 0) {
            targetTab = this.tabs[0].getAttribute('data-tab');
        }

        // Activate the determined tab
        if (targetTab) {
            const targetButton = Array.from(this.tabs).find(
                tab => tab.getAttribute('data-tab') === targetTab
            );
            if (targetButton) {
                this.activateTab(targetButton, targetTab);
            }
        }
    }

    /**
     * Activate a tab without triggering events
     * @param {HTMLElement} button - Tab button element
     * @param {string} tabName - Tab name
     */
    activateTab(button, tabName) {
        const content = document.getElementById(tabName);
        if (content) {
            content.classList.add('active');
            button.classList.add('active');
            this.activeTab = tabName;
        }
    }

    /**
     * Switch to a specific tab
     * @param {Event} evt - Click event
     * @param {string} tabName - ID of tab content to show
     */
    openTab(evt, tabName) {
        try {
            // Hide all tab content
            this.contents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            this.tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            const targetContent = document.getElementById(tabName);
            if (!targetContent) {
                throw new Error(`Tab content with ID "${tabName}" not found`);
            }

            targetContent.classList.add('active');
            evt.currentTarget.classList.add('active');
            this.activeTab = tabName;
            
            // Emit custom event for other components to listen
            this.emitTabChangeEvent(tabName);
            
        } catch (error) {
            console.error('TabManager: Error switching tabs:', error);
        }
    }

    /**
     * Emit custom event when tab changes
     * @param {string} tabName - Name of the activated tab
     */
    emitTabChangeEvent(tabName) {
        // Call onChange callback if provided
        if (typeof this.onChange === 'function') {
            this.onChange(tabName);
        }

        // Emit custom event for other components
        const event = new CustomEvent('tabChanged', {
            detail: { 
                tab: tabName,
                timestamp: Date.now()
            }
        });
        document.dispatchEvent(event);

        // Update URL hash if enabled
        if (this.urlHash) {
            history.replaceState(null, null, `#${tabName}`);
        }
    }

    /**
     * Setup URL hash navigation listeners
     */
    setupUrlHashNavigation() {
        window.addEventListener('hashchange', () => {
            const hash = window.location.hash.substring(1);
            if (hash && this.validTabs.includes(hash)) {
                this.switchTo(hash);
            }
        });
    }

    /**
     * Get the currently active tab
     * @returns {string|null} Active tab name or null if none active
     */
    getActiveTab() {
        return this.activeTab;
    }

    /**
     * Programmatically switch to a tab by name
     * @param {string} tabName - ID of tab to activate
     */
    switchTo(tabName) {
        const targetButton = Array.from(this.tabs).find(
            tab => tab.getAttribute('data-tab') === tabName
        );
        
        if (targetButton) {
            targetButton.click();
        } else {
            console.error(`TabManager: Cannot switch to tab "${tabName}" - button not found`);
        }
    }

    /**
     * Refresh the tab manager (useful if DOM changes)
     */
    refresh() {
        this.init();
    }

    /**
     * Get list of valid tabs
     * @returns {string[]} Array of valid tab names
     */
    getValidTabs() {
        return [...this.validTabs];
    }

    /**
     * Check if a tab exists
     * @param {string} tabName - Tab name to check
     * @returns {boolean}
     */
    hasTab(tabName) {
        return this.validTabs.includes(tabName);
    }

    /**
     * Destroy the tab manager and cleanup
     */
    destroy() {
        // Remove event listeners
        this.tabs.forEach(tab => {
            tab.replaceWith(tab.cloneNode(true));
        });
        
        if (this.urlHash) {
            window.removeEventListener('hashchange', this.setupUrlHashNavigation);
        }
    }
}

// Export to global scope
window.TabManager = TabManager;
