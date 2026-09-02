import { ContenaInstance } from 'src/core/contena';
import profileMenuOverride from 'src/module/ct-profile/extension/ct-admin-menu/ct-admin-menu.override.vue';

// IIFE
void (async () => {
    // Set the global Contena instance
    window.Contena = ContenaInstance;
    ContenaInstance.Component.registerOverrideComponent(profileMenuOverride);

    if (window._ctLoginOverrides) {
        window._ctLoginOverrides.forEach((script) => {
            script();
        });
    }

    // Import the main file
    await import('src/app/main');

    // Start the main application
    window.startApplication();
})();
