'use strict';

/**
 * Shared Filter Button State Manager
 * Handles loading/idle states for filter form buttons across all tabs
 */
const FilterButtonManager = {
    ICON_SEARCH: '<span class="btn-icon" aria-hidden="true">⌕</span>',
    ICON_LOADING: '<span class="btn-icon spinning">↻</span>',
    
    /**
     * Set button to loading state
     * @param {HTMLButtonElement} button 
     */
    setLoading(button) {
        if (!button) return;
        button.disabled = true;
        button.innerHTML = this.ICON_LOADING;
    },
    
    /**
     * Reset button to idle state
     * @param {HTMLButtonElement} button 
     */
    setIdle(button) {
        if (!button) return;
        button.disabled = false;
        button.innerHTML = this.ICON_SEARCH;
    },
    
    /**
     * Find submit button in a filter form
     * @param {HTMLFormElement} form 
     * @returns {HTMLButtonElement|null}
     */
    getSubmitButton(form) {
        return form?.querySelector('button[type="submit"]') || null;
    },
    
    /**
     * Find submit button by form name
     * @param {string} formName 
     * @returns {HTMLButtonElement|null}
     */
    getSubmitButtonByFormName(formName) {
        const form = document.querySelector(`form[name="${formName}"]`);
        return this.getSubmitButton(form);
    }
};

// Expose globally for page scripts
if (typeof window !== 'undefined') {
    window.FilterButtonManager = FilterButtonManager;
}
