/**
 * @sw-package framework
 */
import { ContenaInstance } from 'src/core/contena';

// IIFE
void (async () => {
    // Set the global Contena instance
    window.Contena = ContenaInstance;

    if (window._swLoginOverrides) {
        window._swLoginOverrides.forEach((script) => {
            script();
        });
    }

    // Import the main file
    await import('src/app/main');

    // Start the main application
    window.startApplication();
})();
