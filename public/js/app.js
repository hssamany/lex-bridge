'use strict';

/**
 * Application Entry Point
 * Creates and initializes the LexBridge application
 */

// Create singleton instance
const app = new LexBridge();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    app.init();
});

// Export globally for console access
window.LexBridge = app;

