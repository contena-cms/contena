import { mount } from '@vue/test-utils';
import findByText from '../../../../../test/_helper_/find-by-text';

/**
 * @sw-package checkout
 */
const {
    Classes: { ContenaError },
} = Contena;

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-order-create-address-modal', {
            sync: true,
        }),
        {
            attachTo: document.body,
            global: {
                stubs: {
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-user-address-form': await wrapTestComponent('sw-user-address-form'),
                    'sw-user-address-form-options': await wrapTestComponent('sw-user-address-form-options'),
                    'sw-ignore-class': true,
                    'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                    'sw-card-filter': await wrapTestComponent('sw-card-filter'),
                    'sw-empty-state': true,
                    'sw-address': await wrapTestComponent('sw-address'),
                    'sw-loader': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': true,
                    'sw-tabs-item': true,
                    'sw-tabs': true,
                    'sw-iframe-renderer': true,
                    'router-link': true,
                    'sw-simple-search-field': true,
                    'sw-text-field': true,
                    'sw-entity-single-select': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([]);
                            },
                        }),
                    },
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                },
            },
            props: {
                user: {
                    id: 'id',
                    company: null,
                },
                address: {},
                addAddressModalTitle: '',
                editAddressModalTitle: '',
                cart: {},
            },
        },
    );
}

describe('src/module/sw-order/component/sw-order-create-address-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should dispatch error with invalid company field', async () => {
        await wrapper.setData({
            addresses: [
                { id: '12345', isNew: () => {}, getEntityName: () => 'user_address' },
                { id: '02', isNew: () => {}, getEntityName: () => 'user_address' },
            ],
        });

        const btn = wrapper.findAll('.sw-order-create-address-modal__edit-btn')[0];
        await btn.trigger('click');

        const swModalEditAddress = wrapper.findAll('.sw-modal')[0];

        expect(Contena.Store.get('error').api.user_address).toBeUndefined();

        // submit form
        await findByText(swModalEditAddress, 'button', 'sw-user.detailAddresses.buttonSaveAndSelect').trigger('click');

        expect(Contena.Store.get('error').api).toHaveProperty('user_address.12345.company');
        expect(Contena.Store.get('error').api.user_address['12345'].company).toBeInstanceOf(ContenaError);
    });
});
