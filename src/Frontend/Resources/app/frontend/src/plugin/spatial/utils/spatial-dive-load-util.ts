declare global {
    interface Window {
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEClass: typeof import('@contena-ag/dive').DIVE;
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEARPlugin: typeof import('@contena-ag/dive/ar');
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEQuickViewPlugin: typeof import('@contena-ag/dive/quickview');
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEAnimationPlugin: typeof import('@contena-ag/dive/animation');
        loadDiveUtil: {
            promise: Promise<void> | null;
        };
    }
}

export async function loadDIVE(): Promise<void> {
    if (!window.loadDiveUtil) {
        window.loadDiveUtil = {
            promise: null,
        };
    }

    if (window.DIVEClass) {
        return Promise.resolve();
    }

    if (window.DIVEARPlugin) {
        return Promise.resolve();
    }

    if (window.DIVEQuickViewPlugin) {
        return Promise.resolve();
    }

    if (window.DIVEAnimationPlugin) {
        return Promise.resolve();
    }

    if (!window.loadDiveUtil.promise) {
        window.loadDiveUtil.promise = new Promise((resolve) => {
            const diveModule = import('@contena-ag/dive');
            const arPlugin = import('@contena-ag/dive/ar');
            const quickViewPlugin = import('@contena-ag/dive/quickview');
            const animationPlugin = import('@contena-ag/dive/animation');

            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            Promise.all([diveModule, arPlugin, quickViewPlugin, animationPlugin]).then(([diveModule, arPlugin, quickViewPlugin, animationPlugin]) => {
                window.DIVEClass = diveModule.DIVE;
                window.DIVEARPlugin = arPlugin;
                window.DIVEQuickViewPlugin = quickViewPlugin;
                window.DIVEAnimationPlugin = animationPlugin;
                resolve();
            });
        });
    }


    return window.loadDiveUtil.promise;
}
