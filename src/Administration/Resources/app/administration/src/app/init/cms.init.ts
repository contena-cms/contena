/* eslint-disable ct-deprecation-rules/private-feature-declarations, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
function findAppByOrigin(origin: string) {
    return Contena.Store.get('contenaApps').apps.find((app) => {
        const sources = [app.mainModule?.source, ...app.modules.map((module) => module.source)].filter(Boolean);
        return sources.some((source) => source?.startsWith(origin));
    });
}

export default function initializeCms(): void {
    Contena.ExtensionAPI.handle('cmsRegisterElement', (element, additionalInformation) => {
        const app = findAppByOrigin(additionalInformation._event_.origin);
        if (!app) return;

        Contena.Service('cmsService').registerCmsElement({
            ...element,
            name: element.name,
            component: 'ct-cms-el-location-renderer',
            previewComponent: 'ct-cms-el-preview-location-renderer',
            configComponent: 'ct-cms-el-config-location-renderer',
            appData: { appName: app.name },
        });
    });

    Contena.ExtensionAPI.handle('cmsRegisterBlock', (block, additionalInformation) => {
        const app = findAppByOrigin(additionalInformation._event_.origin);
        if (!app) return;

        Contena.Service('cmsService').registerCmsBlock({
            name: 'app-renderer',
            label: block.label ?? '',
            category: block.category ?? 'app',
            component: 'ct-cms-block-app-renderer',
            previewComponent: 'ct-cms-block-app-preview-renderer',
            previewImage: block.previewImage,
            appName: app.name,
            slots: block.slots.reduce((slots, slot, index) => {
                slots[`${slot.element}-${index}`] = { type: slot.element };
                return slots;
            }, {} as Record<string, { type: string }>),
            defaultConfig: {
                customFields: {
                    appBlockName: block.name,
                    slotLayout: { grid: block.slotLayout?.grid },
                },
            },
        });
    });
}
