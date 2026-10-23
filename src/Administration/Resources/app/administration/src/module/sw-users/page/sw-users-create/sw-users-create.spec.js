import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const { Context } = Contena;
const { EntityCollection } = Contena.Data;

async function createWrapper({ userRepositorySaveMock, languageRepositorySearchIdsMock } = {}) {
    return mount(await wrapTestComponent('sw-user-create', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-language-switch': true,
                'sw-user-address-form': true,
                'sw-user-base-form': true,
                'sw-card-view': true,
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-loader': true,
                'router-link': true,
            },
            provide: {
                numberRangeService: {},
                systemConfigApiService: {
                    getValues: () =>
                        Promise.resolve({
                            'core.register.minPasswordLength': 8,
                        }),
                },
                userValidationService: {},
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'user') {
                            return {
                                create: () => {
                                    return {
                                        id: '63e27affb5804538b5b06cb4e344b130',
                                        addresses: new EntityCollection(
                                            '/user_address',
                                            'user_address',
                                            Context.api,
                                            null,
                                            [],
                                        ),
                                    };
                                },
                                save: userRepositorySaveMock,
                            };
                        }

                        if (entity === 'language') {
                            return {
                                searchIds:
                                    languageRepositorySearchIdsMock ??
                                    (() =>
                                        Promise.resolve({
                                            total: 1,
                                            data: ['1'],
                                        })),
                            };
                        }

                        if (entity === 'salutation') {
                            return {
                                searchIds: () =>
                                    Promise.resolve({
                                        total: 1,
                                        data: ['salutationId'],
                                    }),
                            };
                        }

                        return {
                            create: () => Promise.resolve(),
                        };
                    },
                },
            },
        },
    });
}

describe('module/sw-user/page/sw-user-create', () => {
    it('should have valid email validation response when no email is given', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            user: {
                id: '1',
                email: null,
                boundSalesChannelId: null,
            },
        });

        const response = await wrapper.vm.validateEmail();

        expect(response.isValid).toBe(true);
    });

    it('should have company validation when user type is commercial', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));
        const notificationMock = wrapper.vm.createNotificationError;

        await wrapper.setData({
            user: {
                id: '1',
                email: 'admin_user@domain.com',
                accountType: 'business',
                password: 'contena',
            },
            address: {
                company: '',
            },
        });
        const saveButton = wrapper.find('.sw-user-create__save-action');
        await saveButton.trigger('click');
        await wrapper.vm.$nextTick();

        expect(notificationMock).toHaveBeenCalledTimes(2);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-user.detail.messageSaveError',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should override context when the sales channel does not exist language compared to the API language', async () => {
        const userRepositorySaveMock = jest.fn((user, context) => Promise.resolve(context));
        const wrapper = await createWrapper({ userRepositorySaveMock });
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Contena.Context.api.languageId);

        await wrapper.setData({
            user: {
                id: '1',
                email: 'admin_user@domain.com',
                accountType: 'business',
                password: 'contena',
                channelId: 'a7921464677a4ef591683d144beecd24',
            },
            address: {
                company: 'Contena',
            },
        });

        expect(await wrapper.vm.languageId).toBe('1');

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toBe('1');
    });

    it('should keep context when sales channel exists language compared to API language', async () => {
        const userRepositorySaveMock = jest.fn((user, context) => Promise.resolve(context));
        const languageRepositorySearchIdsMock = jest.fn(() =>
            Promise.resolve({
                total: 1,
                data: [Contena.Context.api.languageId],
            }),
        );

        const wrapper = await createWrapper({ userRepositorySaveMock, languageRepositorySearchIdsMock });
        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Contena.Context.api.languageId);

        await wrapper.setData({
            user: {
                id: '1',
                email: 'admin_user@domain.com',
                accountType: 'business',
                password: 'contena',
                channelId: 'a7921464677a4ef591683d144beecd24',
            },
            address: {
                company: 'Contena',
            },
        });

        expect(await wrapper.vm.languageId).toEqual(Contena.Context.api.languageId);

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toEqual(Contena.Context.api.languageId);
    });

    it('should default the user language to the API context language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.user.languageId).toBe(Contena.Context.api.languageId);
    });

    it('should update the user language when the sales channel changes', async () => {
        const languageRepositorySearchIdsMock = jest.fn(() =>
            Promise.resolve({
                total: 1,
                data: ['channelLanguageId'],
            }),
        );
        const wrapper = await createWrapper({ languageRepositorySearchIdsMock });
        await flushPromises();

        await wrapper.setData({
            user: {
                ...wrapper.vm.user,
                channelId: 'a7921464677a4ef591683d144beecd24',
            },
        });
        await flushPromises();

        expect(wrapper.vm.user.languageId).toBe('channelLanguageId');
    });

    it('should fall back to the API context language when the sales channel has no matching language', async () => {
        const languageRepositorySearchIdsMock = jest.fn(() =>
            Promise.resolve({
                total: 0,
                data: [],
            }),
        );
        const wrapper = await createWrapper({ languageRepositorySearchIdsMock });
        await flushPromises();

        await wrapper.setData({
            user: {
                ...wrapper.vm.user,
                channelId: 'a7921464677a4ef591683d144beecd24',
            },
        });
        await flushPromises();

        expect(wrapper.vm.user.languageId).toBe(Contena.Context.api.languageId);
    });

    it('should get default salutation is value not specified', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.user.salutationId).toBe('salutationId');
        expect(wrapper.vm.address.salutationId).toBe('salutationId');
    });

    it('should not render sw-user-base-form and sw-user-address-form if user is null', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            user: null,
        });

        expect(wrapper.find('sw-user-base-form-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-user-address-form-stub').exists()).toBeFalsy();
    });

    it('should throw exception when the user creation fails', async () => {
        const userRepositorySaveMock = jest.fn(() =>
            Promise.reject({
                response: {
                    data: {
                        errors: [
                            {
                                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                                detail: 'This value should not be blank.',
                                status: '400',
                                template: 'This value should not be blank.',
                            },
                        ],
                    },
                },
            }),
        );

        const wrapper = await createWrapper({ userRepositorySaveMock });
        await wrapper.setData({
            user: {
                id: '1',
                email: 'ytn@contena.cn',
                boundSalesChannelId: null,
            },
        });

        try {
            await wrapper.vm.onSave();
        } catch (e) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(e.response.data.errors[0]).toEqual({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            });
        }
    });
});
