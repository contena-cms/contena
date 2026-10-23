import CheckoutCustomerStoragePlugin from 'src/plugin/checkout/checkout-user-storage.plugin';
import SessionStorage from 'src/helper/storage/session-storage.helper';
import template from './checkout-user-storage.plugin.template.html';

describe('CheckoutCustomerStoragePlugin tests', () => {
    const storageKey = 'checkoutCustomerStorage';

    beforeEach(() => {
        document.body.innerHTML = template;

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({
                get: jest.fn().mockReturnValue([]),
            }),
        };

        SessionStorage.clear();
        window.localStorage.clear();
    });

    afterEach(() => {
        SessionStorage.clear();
        window.localStorage.clear();
    });

    function createPlugin(userId) {
        const form = document.querySelector('#confirmOrderForm');

        form.setAttribute('data-checkout-user-storage-options', JSON.stringify({ userId }));

        new CheckoutCustomerStoragePlugin(form, {}, 'CheckoutCustomerStorage');

        return {
            userComment: document.querySelector('#userComment'),
            tos: document.querySelector('#tos'),
            revocation: document.querySelector('#revocation'),
        };
    }

    function storedCustomers() {
        return JSON.parse(SessionStorage.getItem(storageKey));
    }

    test('restores the comment for the active user only', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
            },
            userB: {
                userComment: 'comment from user B',
            },
        }));

        const { userComment } = createPlugin('userB');

        expect(userComment.value).toBe('comment from user B');
    });

    test('restores tos for the active user only', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
            },
            userB: {
                tos: true,
            },
        }));

        const { tos } = createPlugin('userB');

        expect(tos.checked).toBe(true);
    });

    test('restores revocation for the active user only', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
            },
            userB: {
                revocation: true,
            },
        }));

        const { revocation } = createPlugin('userB');

        expect(revocation.checked).toBe(true);
    });

    test('restores both consent boxes when the checkout page is loaded again', () => {
        const { tos, revocation } = createPlugin('userA');

        tos.checked = true;
        tos.dispatchEvent(new Event('change'));
        revocation.checked = true;
        revocation.dispatchEvent(new Event('change'));

        document.body.innerHTML = template;

        const restored = createPlugin('userA');

        expect(restored.tos.checked).toBe(true);
        expect(restored.revocation.checked).toBe(true);
    });

    test('persists into the session storage and never into the local storage', () => {
        const { tos } = createPlugin('userA');

        tos.checked = true;
        tos.dispatchEvent(new Event('change'));

        expect(window.sessionStorage.getItem(storageKey)).not.toBeNull();
        expect(window.localStorage.getItem(storageKey)).toBeNull();
    });

    test('updates only the active user comment entry', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
            },
            userB: {
                userComment: 'comment from user B',
                tos: true,
            },
        }));

        const { userComment } = createPlugin('userA');

        userComment.value = 'updated comment';
        userComment.dispatchEvent(new Event('input'));

        expect(storedCustomers()).toEqual({
            userA: {
                userComment: 'updated comment',
            },
            userB: {
                userComment: 'comment from user B',
                tos: true,
            },
        });
    });

    test('updates only the active user tos entry', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
            },
            userB: {
                userComment: 'comment from user B',
            },
        }));

        const { tos } = createPlugin('userA');

        tos.checked = true;
        tos.dispatchEvent(new Event('change'));

        expect(storedCustomers()).toEqual({
            userA: {
                userComment: 'comment from user A',
                tos: true,
            },
            userB: {
                userComment: 'comment from user B',
            },
        });
    });

    test('removes tos when it is unchecked', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
                tos: true,
            },
        }));

        const { tos } = createPlugin('userA');

        tos.checked = false;
        tos.dispatchEvent(new Event('change'));

        expect(storedCustomers()).toEqual({
            userA: {
                userComment: 'comment from user A',
            },
        });
    });

    test('removes revocation when it is unchecked', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
                revocation: true,
            },
        }));

        const { revocation } = createPlugin('userA');

        revocation.checked = false;
        revocation.dispatchEvent(new Event('change'));

        expect(storedCustomers()).toEqual({
            userA: {
                userComment: 'comment from user A',
            },
        });
    });

    test('removes the whole storage key when the last user entry is cleared', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
            },
        }));

        const { userComment } = createPlugin('userA');

        userComment.value = '';
        userComment.dispatchEvent(new Event('change'));

        expect(SessionStorage.getItem(storageKey)).toBeNull();
    });

    test.each(['submit', 'reset'])('clears only the active user entry on %s', (eventType) => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
                tos: true,
            },
            userB: {
                userComment: 'comment from user B',
            },
        }));

        createPlugin('userA');

        const form = document.querySelector('#confirmOrderForm');
        form.dispatchEvent(new Event(eventType));

        expect(storedCustomers()).toEqual({
            userB: {
                userComment: 'comment from user B',
            },
        });
    });

    test('ignores malformed storage payloads safely', () => {
        SessionStorage.setItem(storageKey, 'not-json');

        const { userComment, tos, revocation } = createPlugin('userA');

        expect(userComment.value).toBe('');
        expect(tos.checked).toBe(false);
        expect(revocation.checked).toBe(false);
        expect(SessionStorage.getItem(storageKey)).toBe('not-json');
    });

    test('does not restore shared checkout data if the user id is missing', () => {
        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                userComment: 'comment from user A',
                tos: true,
                revocation: true,
            },
        }));

        const form = document.querySelector('#confirmOrderForm');
        new CheckoutCustomerStoragePlugin(form, {}, 'CheckoutCustomerStorage');

        expect(document.querySelector('#userComment').value).toBe('');
        expect(document.querySelector('#tos').checked).toBe(false);
        expect(document.querySelector('#revocation').checked).toBe(false);
    });

    test('keeps working when the revocation checkbox is not rendered', () => {
        document.querySelector('#revocation').remove();

        SessionStorage.setItem(storageKey, JSON.stringify({
            userA: {
                revocation: true,
                tos: true,
            },
        }));

        const { tos } = createPlugin('userA');

        expect(tos.checked).toBe(true);
    });
});
