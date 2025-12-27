'use strict';

/**
 * ToastNotifier - Handles toast notifications
 */
class ToastNotifier {
    
    constructor(options = {}) {
        this.config = {
            containerId: options.containerId || 'toast-container',
            templateId: options.templateId || 'toast-msg-template',
            templateUrl: options.templateUrl || 'js/components/toast-notifier/toast-notifier.html',
            cssUrl: options.cssUrl || 'js/components/toast-notifier/toast-notifier.css',
            defaultDuration: options.defaultDuration || 5000,
            debug: options.debug || false
        };
        
        this.icons = {
            success: '✓',
            error: '✕',
            info: 'ℹ',
            warning: '⚠'
        };
        
        this.defaultTitles = {
            success: 'Success',
            warning: 'Warning',
            error: 'Error',
            info: 'Info'
        };
        
        this.ready = false;
        this.initPromise = this.initialize();
    }
    
    /**
     * Initialize component - load template and CSS
     */
    async initialize() {
        try {
            await Promise.all([
                this.loadTemplate(),
                this.loadCSS()
            ]);
            this.ready = true;
            
            if (this.config.debug) {
                console.log('ToastNotifier initialized successfully');
            }
        } catch (error) {
            console.error('ToastNotifier initialization error:', error);
            this.ready = false;
        }
    }
    
    /**
     * Load template from external HTML file
     */
    async loadTemplate() {
        try {
            // Check if template already exists in DOM
            if (document.getElementById(this.config.templateId)) {
                if (this.config.debug) {
                    console.log('Toast template already exists in DOM');
                }
                return;
            }
            
            const response = await fetch(this.config.templateUrl);
            if (!response.ok) {
                throw new Error(`Failed to load template: ${response.statusText}`);
            }
            
            const html = await response.text();
            
            // Create a temporary container to parse the HTML
            const temp = document.createElement('div');
            temp.innerHTML = html;
            
            // Find the template element and add it to the document
            const template = temp.querySelector('template');
            if (template) {
                document.body.appendChild(template);
                
                if (this.config.debug) {
                    console.log('Toast template loaded successfully');
                }
            } else {
                throw new Error('Template element not found in HTML file');
            }
            
        } catch (error) {
            console.error('Error loading toast template:', error);
            throw error;
        }
    }
    
    /**
     * Load CSS file dynamically
     */
    async loadCSS() {
        // Check if CSS already loaded
        if (document.querySelector(`link[href="${this.config.cssUrl}"]`)) {
            if (this.config.debug) {
                console.log('Toast CSS already loaded');
            }
            return;
        }
        
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = this.config.cssUrl;
            
            link.onload = () => {
                if (this.config.debug) {
                    console.log('Toast CSS loaded successfully');
                }
                resolve();
            };
            
            link.onerror = () => {
                reject(new Error('Failed to load toast CSS'));
            };
            
            document.head.appendChild(link);
        });
    }
    
    /**
     * Show toast notification
     * @param {string} message - Message to display
     * @param {string} type - Type of notification (success, error, info, warning)
     * @param {string} title - Optional title
     * @param {number} duration - Duration in milliseconds
     */
    async show(message, type = 'info', title = '', duration = null) {
        // Wait for template and CSS to load
        await this.initPromise;
        
        if (!this.ready) {
            console.error('Toast template not ready');
            return;
        }
        
        try {
            const container = document.getElementById(this.config.containerId);
            if (!container) {
                throw new Error('Toast container not found');
            }
            
            const template = document.getElementById(this.config.templateId);
            if (!template) {
                throw new Error('Toast template not found');
            }
            
            const toast = template.content.cloneNode(true).querySelector('.toast');
            if (!toast) {
                throw new Error('Toast element not found in template');
            }
            
            toast.classList.add(type);
            
            // Fill in the content
            toast.querySelector('.toast-icon').textContent = this.icons[type] || this.icons.info;
            toast.querySelector('.toast-title').textContent = title || this.defaultTitles[type];
            toast.querySelector('.toast-message').textContent = message;
            
            // Add close button handler
            const closeBtn = toast.querySelector('.toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.remove(toast);
                });
            }
            
            // Add to container
            container.appendChild(toast);
            
            // Auto remove after duration
            const finalDuration = duration || this.config.defaultDuration;
            setTimeout(() => {
                this.remove(toast);
            }, finalDuration);
            
            if (this.config.debug) {
                console.log(`Toast shown: [${type}] ${message}`);
            }
            
        } catch (error) {
            console.error('Toast notification error:', error.message);
            if (this.config.debug) {
                alert(`Toast Error: ${error.message}\nMessage: ${message}`);
            }
        }
    }
    
    /**
     * Remove toast with animation
     * @param {HTMLElement} toast - Toast element to remove
     */
    remove(toast) {
        if (!toast || !toast.parentElement) return;
        
        toast.classList.add('removing');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 300);
    }
    
    /**
     * Convenience methods for different toast types
     */
    success(message, title = '', duration = null) {
        this.show(message, 'success', title, duration);
    }
    
    error(message, title = '', duration = null) {
        this.show(message, 'error', title, duration);
    }
    
    info(message, title = '', duration = null) {
        this.show(message, 'info', title, duration);
    }
    
    warning(message, title = '', duration = null) {
        this.show(message, 'warning', title, duration);
    }
}

// Export to global scope
window.ToastNotifier = ToastNotifier;
