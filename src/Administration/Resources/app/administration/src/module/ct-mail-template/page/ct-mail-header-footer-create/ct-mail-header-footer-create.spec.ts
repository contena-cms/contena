import { defineComponent } from 'vue';
import { shallowMount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-mail-template/page/ct-mail-header-footer-create', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('resets the Administration context to the system default language', async () => {
        const resetLanguageToDefault = jest.fn();
        jest.spyOn(Contena.Store, 'get').mockReturnValue({
            isSystemDefaultLanguage: false,
            resetLanguageToDefault,
        } as never);

        shallowMount(await wrapTestComponent('ct-mail-header-footer-create', { sync: true }), {
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-language-switch': true,
                    'ct-mail-header-footer-detail': true,
                },
            },
        });

        expect(resetLanguageToDefault).toHaveBeenCalledTimes(1);
    });
});
