import useModuleIconColors, { USER_MODULE_ICON_COLORS_CONFIG_KEY } from './use-module-icon-colors';
import type UserConfigService from 'src/core/service/api/user-config.api.service';

const userConfigService = {
    search: jest.fn(() => Promise.resolve({ data: {} })),
    upsert: jest.fn(() => Promise.resolve()),
};

describe('use-module-icon-colors', () => {
    beforeAll(() => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register('userConfigService', () => userConfigService as unknown as UserConfigService);
    });

    beforeEach(() => {
        useModuleIconColors().enabled.value = false;
        userConfigService.search.mockReset().mockResolvedValue({ data: {} });
        userConfigService.upsert.mockReset().mockResolvedValue(undefined);
    });

    it('is disabled by default', () => {
        expect(useModuleIconColors().enabled.value).toBe(false);
    });

    it('shares the enabled state across consumers', () => {
        expect(useModuleIconColors().enabled).toBe(useModuleIconColors().enabled);
    });

    it('persists the selected preference', async () => {
        await useModuleIconColors().saveUserModuleIconColors(true);

        expect(useModuleIconColors().enabled.value).toBe(true);
        expect(Contena.Service('userConfigService').upsert).toHaveBeenCalledWith({
            [USER_MODULE_ICON_COLORS_CONFIG_KEY]: { enabled: true },
        });
    });

    it('loads the selected preference', async () => {
        (Contena.Service('userConfigService').search as jest.Mock).mockResolvedValue({
            data: {
                [USER_MODULE_ICON_COLORS_CONFIG_KEY]: { enabled: true },
            },
        });

        await useModuleIconColors().loadUserModuleIconColors();

        expect(Contena.Service('userConfigService').search).toHaveBeenCalledWith([
            USER_MODULE_ICON_COLORS_CONFIG_KEY,
        ]);
        expect(useModuleIconColors().enabled.value).toBe(true);
    });

    it('falls back to disabled without a stored preference', async () => {
        useModuleIconColors().enabled.value = true;

        await useModuleIconColors().loadUserModuleIconColors();

        expect(useModuleIconColors().enabled.value).toBe(false);
    });
});
