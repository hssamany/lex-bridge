'use strict';

/**
 * LEX Bridge - Application JavaScript
 * Handles tab navigation and UI interactions
 */

/**
 * Application module using IIFE to avoid global namespace pollution
 */
const LexBridge = (() => {
    /**
     * Switch between tabs
     * @param {Event} evt - Click event
     * @param {string} tabName - ID of tab to show
     */
    const openTab = (evt, tabName) => {
        try {
            // Hide all tab content using modern array methods
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show current tab and mark button as active
            const targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.classList.add('active');
                evt.currentTarget.classList.add('active');
            } else {
                console.error(`Tab with ID "${tabName}" not found`);
            }
        } catch (error) {
            console.error('Error switching tabs:', error);
        }
    };

    // Public API
    return {
        openTab
    };
})();

// Make openTab available globally for onclick handlers
window.openTab = LexBridge.openTab;
