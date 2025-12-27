'use strict';

/**
 * TabManager - Handles dynamic tab navigation and content switching
 */
class TabManager {
    
    constructor(options = {}) {

        this.config = {
            containerId: options.containerId || null,
            templateUrl: options.templateUrl || 'js/components/tab-manager/tab-manager.html',
            cssUrl: options.cssUrl || 'js/components/tab-manager/tab-manager.css',
            tabs: options.tabs || [], // Array of tab definitions
            activeClass: options.activeClass || 'active',
            defaultTab: options.defaultTab || null,
            debug: options.debug || false
        };
        
        this.container = null;
        this.tabsData = this.config.tabs;
        this.activeTab = null;
        this.onTabChangeCallback = null;
        
        this.ready = false;
        this.initPromise = this.initialize();
    }
    
    /**
     * Initialize component - load CSS, template and build tabs
     */
    async initialize() {
        try {
            await Promise.all([
                this.loadCSS(),
                this.loadTemplate()
            ]);
            
            if (this.config.containerId) {
                this.container = document.getElementById(this.config.containerId);
                if (this.container && this.tabsData.length > 0) {
                    await this.buildTabs();
                }
            }
            
            this.ready = true;
            
            if (this.config.debug) {
                console.log('TabManager initialized successfully');
            }
        } catch (error) {
            console.error('TabManager initialization error:', error);
            this.ready = false;
        }
    }
    
    /**
     * Load CSS file dynamically
     */
    async loadCSS() {
        if (document.querySelector(`link[href="${this.config.cssUrl}"]`)) {
            if (this.config.debug) {
                console.log('TabManager CSS already loaded');
            }
            return;
        }
        
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = this.config.cssUrl;
            
            link.onload = () => {
                if (this.config.debug) {
                    console.log('TabManager CSS loaded successfully');
                }
                resolve();
            };
            
            link.onerror = () => {
                reject(new Error('Failed to load TabManager CSS'));
            };
            
            document.head.appendChild(link);
        });
    }
    
    /**
     * Load template from external HTML file
     */
    async loadTemplate() {
        try {
            if (document.getElementById('tab-manager-template')) {
                if (this.config.debug) {
                    console.log('TabManager template already exists in DOM');
                }
                return;
            }
            
            const response = await fetch(this.config.templateUrl);
            if (!response.ok) {
                throw new Error(`Failed to load template: ${response.statusText}`);
            }
            
            const html = await response.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            
            // Add all templates to document
            const templates = temp.querySelectorAll('template');
            templates.forEach(template => {
                document.body.appendChild(template);
            });
            
            if (this.config.debug) {
                console.log('TabManager templates loaded successfully');
            }
            
        } catch (error) {
            console.error('Error loading TabManager template:', error);
            throw error;
        }
    }
    
    /**
     * Build tabs dynamically from configuration
     */
    async buildTabs() {
        
        if (!this.container) {
            throw new Error('Container not found');
        }
        
        const mainTemplate = document.getElementById('tab-manager-template');
        if (!mainTemplate) {
            throw new Error('Tab manager template not found');
        }
        
        // Clone main template
        const tabManager = mainTemplate.content.cloneNode(true);
        const tabNavigation = tabManager.querySelector('.tab-navigation');
        const tabActions = tabManager.querySelector('.tab-actions');
        const tabContentsWrapper = tabManager.querySelector('.tab-contents-wrapper');
        
        // Build tab buttons
        this.tabsData.forEach(tabData => {
            // Create tab button
            const tabButton = this.createTabButton(tabData);
            tabNavigation.appendChild(tabButton);
            
            // Create tab content
            const tabContent = this.createTabContent(tabData);
            tabContentsWrapper.appendChild(tabContent);
            
            // Add action button if provided
            if (tabData.action) {
                const actionElement = this.createTabAction(tabData);
                tabActions.appendChild(actionElement);
            }
        });
        
        // Clear container and add new structure
        this.container.innerHTML = '';
        this.container.appendChild(tabManager);
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Activate default tab
        const defaultTab = this.config.defaultTab || this.tabsData[0]?.id;
        if (defaultTab) {
            this.activateTab(defaultTab);
        }
        
        if (this.config.debug) {
            console.log(`TabManager built: ${this.tabsData.length} tabs created`);
        }
    }
    
    /**
     * Create tab button from template
     */
    createTabButton(tabData) {
        const template = document.getElementById('tab-button-template');
        const button = template.content.cloneNode(true).querySelector('.tab');
        
        button.dataset.tab = tabData.id;
        button.textContent = tabData.label;
        
        if (tabData.icon) {
            const icon = document.createElement('span');
            icon.className = 'tab-icon';
            icon.textContent = tabData.icon;
            button.prepend(icon);
        }
        
        return button;
    }
    
    /**
     * Create tab content from template
     */
    createTabContent(tabData) {
        const template = document.getElementById('tab-content-template');
        const content = template.content.cloneNode(true).querySelector('.tab-content');
        
        content.id = tabData.id;
        
        // If content is a string, set innerHTML
        if (typeof tabData.content === 'string') {
            content.innerHTML = tabData.content;
        }
        // If content is a function, call it to get HTML
        else if (typeof tabData.content === 'function') {
            content.innerHTML = tabData.content();
        }
        // If content is an element, append it
        else if (tabData.content instanceof HTMLElement) {
            content.appendChild(tabData.content);
        }
        
        return content;
    }
    
    /**
     * Create action button/form for tab
     */
    createTabAction(tabData) {

        const action = tabData.action;
        
        // If action is a string (HTML), create from string
        if (typeof action === 'string') {
            const temp = document.createElement('div');
            temp.innerHTML = action;
            const actionElement = temp.firstElementChild;
            actionElement.classList.add('tab-action');
            actionElement.dataset.for = tabData.id;
            actionElement.style.display = 'none';
            return actionElement;
        }
        
        // If action is an element, use it directly
        if (action instanceof HTMLElement) {
            action.classList.add('tab-action');
            action.dataset.for = tabData.id;
            action.style.display = 'none';
            return action;
        }
        
        // If action is an object with properties
        if (typeof action === 'object') {
            const form = document.createElement('form');
            form.className = 'tab-action';
            form.dataset.for = tabData.id;
            form.style.display = 'none';
            
            if (action.url) form.action = action.url;
            if (action.method) form.method = action.method;
            
            // Add hidden fields if provided
            if (action.hiddenFields) {
                Object.entries(action.hiddenFields).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
            }
            
            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'btn btn-primary';
            
            if (action.icon) {
                const icon = document.createElement('span');
                icon.className = 'btn-icon';
                icon.textContent = action.icon;
                button.appendChild(icon);
            }
            
            button.appendChild(document.createTextNode(action.label || 'Action'));
            form.appendChild(button);
            
            return form;
        }
        
        return document.createElement('div');
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {

        const tabs = this.container.querySelectorAll('.tab');

        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabName = e.currentTarget.dataset.tab;
                if (tabName) {
                    this.activateTab(tabName);
                }
            });
        });
    }
    
    /**
     * Activate a specific tab
     */
    activateTab(tabName) {

        // Deactivate all tabs        
        this.container.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove(this.config.activeClass);
        });
        
        // Hide all tab contents        
        this.container.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove(this.config.activeClass);
        });
        
        // Hide all actions
        this.container.querySelectorAll('.tab-action').forEach(action => {
            action.style.display = 'none';
        });
        
        // Activate selected tab
        this.container.querySelector(`[data-tab="${tabName}"]`)?.classList.add(this.config.activeClass);        
        this.container.querySelector(`#${tabName}`)?.classList.add(this.config.activeClass);        
        this.container.querySelector(`.tab-action[data-for="${tabName}"]`).style.display = 'block';
        
        this.activeTab = tabName;
        
        // Trigger callback
        if (this.onTabChangeCallback && typeof this.onTabChangeCallback === 'function') {
            this.onTabChangeCallback(tabName);
        }
        
        if (this.config.debug) {
            console.log(`Tab activated: ${tabName}`);
        }
    }
    
    /**
     * Get currently active tab
     */
    getActiveTab() {
        return this.activeTab;
    }
    
    /**
     * Set callback for tab change events
     */
    onTabChange(callback) {
        this.onTabChangeCallback = callback;
    }
    
    /**
     * Add a new tab dynamically
     */
    addTab(tabData) {
        this.tabsData.push(tabData);
        if (this.ready && this.container) {
            // Rebuild tabs
            this.buildTabs();
        }
    }
    
    /**
     * Remove a tab
     */
    removeTab(tabId) {
        this.tabsData = this.tabsData.filter(tab => tab.id !== tabId);
        if (this.ready && this.container) {
            this.buildTabs();
        }
    }
    
    /**
     * Destroy tab manager
     */
    destroy() {
        if (this.container) {
            this.container.innerHTML = '';
        }
        this.tabsData = [];
        this.activeTab = null;
        this.onTabChangeCallback = null;
        
        if (this.config.debug) {
            console.log('TabManager destroyed');
        }
    }
}

// Export to global scope
window.TabManager = TabManager;
