const addPrivilegeMappingEntryMock = jest.fn();

const originalContenaService = Contena.Service;

describe('src/module/ct-permissions/acl/index.js', () => {
    beforeAll(() => {
        Contena.Service = () => {
            return {
                addPrivilegeMappingEntry: addPrivilegeMappingEntryMock,
            };
        };
    });

    beforeEach(async () => {
        jest.resetAllMocks();
        jest.resetModules();

        await import('./index');
    });

    afterAll(() => {
        Contena.Service = originalContenaService;
    });

    it('should register privilege mapping entry', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(1, {
            category: 'permissions',
            parent: 'settings',
            key: 'users_and_permissions',
            roles: expect.any(Object),
        });
    });

    it('should include read privileges needed by the users and permissions viewer pages', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenCalledTimes(1);

        const registeredRoles = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        expect(registeredRoles.viewer.privileges).toEqual(
            expect.arrayContaining([
                'api_acl_privileges_additional_get',
                'media_folder:read',
                'tag:read',
            ]),
        );
    });

    it('should include write privileges needed by the user tag association', () => {
        const registeredRoles = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        expect(registeredRoles.editor.privileges).toEqual(
            expect.arrayContaining([
                'tag:create',
                'user_tag:create',
                'user_tag:delete',
            ]),
        );
    });
});
