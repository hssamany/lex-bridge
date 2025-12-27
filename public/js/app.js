'use strict';

/**
 * Application Entry Point
 */

// Initialize the home page when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Create and initialize home page
        const homePage = new HomePage();
        await homePage.init();
        
        // Store reference globally for console access
        window.LexBridge = homePage.lexBridge;
        
    } catch (error) {
        console.error('Application initialization failed:', error);
    }
});

