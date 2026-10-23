import CheckoutCustomerStorageResetPlugin from 'src/plugin/checkout/checkout-user-storage-reset.plugin';
import SessionStorage from 'src/helper/storage/session-storage.helper';

describe('CheckoutCustomerStorageResetPlugin tests', () => {
    const storageKey = 'checkoutCustomerStorage';

    beforeEach(() => {
        document.body.innerHTML = '<div data-checkout-user-storage-reset="true"></div>';

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({
                get: jest.fn().mockReturnValue([]),
            }),
        };

        SessionStorage.clear();
    });

    afterEach(() => {
        SessionStorage.clear();
    });

    function createPlugin() {
        const element = document.querySelector('[data-checkout-user-storage-reset]');

        new CheckoutCustomerStorageResetPlugin(element, {}, 'CheckoutCustomerStorageReset');
    }

    test('drops the persisted checkout data of every user', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
                tos: true,
            },
            userB: {
                tos: true,
            },
        }));

        createPlugin();

        expect(SessionStorage.getItem(storageKey)).toBeNull();
    });

    test('keeps storage entries of other features', () => {
        SessionStorage.setItem('unrelatedKey', 'unrelated value');

        createPlugin();

        expect(SessionStorage.getItem('unrelatedKey')).toBe('unrelated value');
    });
});
