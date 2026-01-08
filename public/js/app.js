'use strict';

/**
 * Application Entry Point
 */

// Initialize the application when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Create and initialize LexBridge
        const lexBridge = new LexBridge();
        await lexBridge.init();
        
        // Store reference globally for console access
        window.LexBridgeApp = lexBridge;
        
    } catch (error) {
        console.error('Application initialization failed:', error);
    }
});

