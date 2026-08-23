import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import 'src/app/mixin/notification.mixin';

const routerPush = jest.fn();

async function createWrapper() {
    return mount(
        await wrapTestComponent('ct-settings-custom-field-set-create', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $t() {
                        return 'translation';
                    },
                },
                provide: {
                    [routeLocationKey]: { params: {} },
                    [routerKey]: { push: routerPush },
                    repositoryFactory: {
                        create(repositoryName) {
                            if (repositoryName === 'custom_field') {
                                return {};
                            }

                            return {
                                get() {
                                    return Promise.resolve({});
                                },
                                create() {
                                    return Promise.resolve({ id: 'generated-id' });
                                },
                                search() {
                                    return Promise.resolve({
                                        length: 0,
                                    });
                                },
                            };
                        },
                    },
                },
                stubs: {
                    'ct-page': true,
                    'ct-custom-field-set-detail-base': true,
                    'ct-button-process': true,
                    'ct-card-view': true,
                    'ct-skeleton': true,
                },
            },
        },
    );
}

describe('src/module/ct-settings-custom-field/page/ct-settings-custom-field-set-create', () => {
    let wrapper;

    beforeEach(async () => {
        routerPush.mockClear();
        wrapper = await createWrapper();
    });

    it('should create a set without a route id', async () => {
        await flushPromises();

        expect(wrapper.vm.setId).toBe('generated-id');
        expect(wrapper.vm.set.name).toBe('custom_');
        expect(wrapper.vm.getInlineSnippet).toBeInstanceOf(Function);
    });

    it('should finish save', async () => {
        wrapper.vm.saveFinish();

        expect(routerPush).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({
            name: 'ct.settings.custom.field.detail',
            params: {
                id: wrapper.vm.setId,
            },
        });
    });

    it('should create technical name error for empty set', async () => {
        wrapper.vm.set.name = '';
        wrapper.vm.onSave();

        expect(wrapper.vm.technicalNameError).toBeTruthy();
        expect(wrapper.vm.isLoading).toBeFalsy();
        expect(wrapper.vm.technicalNameError.hasOwnProperty('detail')).toBeTruthy();
        expect(wrapper.vm.technicalNameError.detail).toBe('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3');
    });

    it('should create name not unique notification', async () => {
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);
        wrapper.vm.createNameNotUniqueNotification();

        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'ct-settings-custom-field.set.detail.messageNameNotUnique',
            }),
        );
        expect(wrapper.vm.technicalNameError).toBeTruthy();
        expect(wrapper.vm.technicalNameError.hasOwnProperty('detail')).toBeTruthy();
        expect(wrapper.vm.technicalNameError.detail).toBe('ct-settings-custom-field.set.detail.messageNameNotUnique');
    });
});
