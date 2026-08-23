/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-data-dictionary', () => {
    beforeAll(async () => {
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                }) as unknown as PrivilegesService,
        );
        await import('./index');
    });

    it('registers the data dictionary pages and system navigation item', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-data-dictionary-list')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-data-dictionary-detail')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-data-dictionary');
        expect(module?.manifest.navigation).toContainEqual(
            expect.objectContaining({
                id: 'ct-data-dictionary',
                path: 'ct.data.dictionary.index',
                parent: 'ct-system',
                privilege: 'data_dictionary.viewer',
            }),
        );
    });

    it('protects list and detail routes with viewer privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-data-dictionary');

        expect(module?.routes.get('ct.data.dictionary.index')).toMatchObject({
            meta: { privilege: 'data_dictionary.viewer' },
        });
        expect(module?.routes.get('ct.data.dictionary.detail')).toMatchObject({
            meta: { privilege: 'data_dictionary.viewer' },
        });
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
