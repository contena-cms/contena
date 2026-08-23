const addPrivilegeMappingEntryMock = jest.fn();
const originalContenaService = Contena.Service;

describe('src/module/ct-extension/acl', () => {
    beforeAll(() => {
        Contena.Service = () => ({
            addPrivilegeMappingEntry: addPrivilegeMappingEntryMock,
        });
    });

    beforeEach(async () => {
        jest.resetAllMocks();
        jest.resetModules();

        await import('./index');
    });

    afterAll(() => {
        Contena.Service = originalContenaService;
    });

    it('registers separate plugin management and store permissions', () => {
        const mapping = addPrivilegeMappingEntryMock.mock.calls[0][0];

        expect(mapping).toMatchObject({
            category: 'additional_permissions',
            key: 'system',
        });
        expect(mapping.roles.plugin_maintain.privileges).toEqual(
            expect.arrayContaining([
                'system.plugin_maintain',
                'system.plugin_upload',
                'plugin:read',
                'plugin:update',
            ]),
        );
        expect(mapping.roles.extension_store.dependencies).toEqual(['system.plugin_maintain']);
    });
});
