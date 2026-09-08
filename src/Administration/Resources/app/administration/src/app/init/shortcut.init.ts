/**
 * @ct-package framework
 */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeShortcutService() {
    const factoryContainer = Contena.Application.getContainer('factory');
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const shortcutFactory = factoryContainer.shortcut;
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const shortcutService = Contena.Service('shortcutService');
    const loginService = Contena.Service('loginService');

    // Register default Shortcuts
    const defaultShortcuts = defaultShortcutMap();
    defaultShortcuts.forEach((sc) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        shortcutFactory.register(sc.combination, sc.path);
    });

    // Initializes the global event listener
    if (loginService.isLoggedIn()) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        shortcutService.startEventListener();
    } else {
        loginService.addOnTokenChangedListener(() => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            shortcutService.startEventListener();
        });
    }

    // Release global event listener on logout
    loginService.addOnLogoutListener(() => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        shortcutService.stopEventListener();
    });

    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return shortcutFactory;
}

function defaultShortcutMap() {
    return [
        // Add an entity
        { combination: 'AP', path: '/ct/blog/create/base' },
        { combination: 'AC', path: '/ct/category/index' },
        { combination: 'AU', path: '/ct/member/create' },
        { combination: 'AR', path: '/ct/settings/rule/create' },

        // Go to ...
        { combination: 'GH', path: '/ct/dashboard/index' },
        { combination: 'GP', path: '/ct/blog/index' },
        { combination: 'GC', path: '/ct/category/index' },
        { combination: 'GU', path: '/ct/member/index' },
        { combination: 'GE', path: '/ct/experience/studio/index' },
        { combination: 'GME', path: '/ct/media/index' },
        { combination: 'GS', path: '/ct/settings/index' },
        { combination: 'GSN', path: '/ct/settings/snippet/index' },
        { combination: 'GSR', path: '/ct/settings/rule/index' },
        { combination: 'GA', path: '/ct/extension/my-extensions/listing/plugin' },
    ];
}
