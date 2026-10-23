import { mount } from '@vue/test-utils';

const admin_userInfo = {
    avatarUrl: 'https://avatar.url',
    email: 'max@muster.com',
    name: 'Max Muster',
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-extension-my-extensions-account', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-text-field': {
                        props: ['value'],
                        template: `
                    <input type="text" :value="value" @input="$emit('update:value', $event.target.value)" />
                `,
                    },
                    'sw-skeleton': true,
                    'sw-avatar': true,
                    'sw-meteor-card': {
                        template: '<div><slot></slot></div>',
                    },
                },
                provide: {
                    contenaExtensionService: {
                        checkLogin: () => {
                            return Promise.resolve({
                                admin_userInfo,
                            });
                        },
                    },
                    systemConfigApiService: {
                        getValues: () => {
                            return Promise.resolve({
                                'core.store.apiUri': 'https://api.contena.cn',
                                'core.store.licenseHost': 'sw6.test.contena.in',
                                'core.store.shopSecret': 'very.s3cret',
                            });
                        },
                    },
                    storeService: {
                        login: (contenaId, password) => {
                            if (contenaId !== 'max@muster.com') {
                                return Promise.reject();
                            }
                            if (password !== 'v3ryS3cret') {
                                return Promise.reject();
                            }

                            Contena.Store.get('contenaExtensions').admin_userInfo = admin_userInfo;

                            return Promise.resolve();
                        },
                        logout: () => {
                            Contena.Store.get('contenaExtensions').admin_userInfo = null;

                            return Promise.resolve();
                        },
                    },
                },
            },
        },
    );
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/page/sw-extension-my-extensions-account', () => {
    beforeEach(async () => {
        Contena.Store.get('contenaExtensions').admin_userInfo = null;
    });

    it('should show the login fields when not logged in', async () => {
        const wrapper = await createWrapper();

        const contenaIdField = wrapper.find('.sw-extension-my-extensions-account__contena-id-field');
        const passwordField = wrapper.find('.sw-extension-my-extensions-account__password-field');
        const loginButton = wrapper.find('.sw-extension-my-extensions-account__login-button');

        // check if fields exists when admin_user is not logged in
        expect(contenaIdField.isVisible()).toBe(true);
        expect(passwordField.isVisible()).toBe(true);
        expect(loginButton.isVisible()).toBe(true);
    });

    it('should login when admin_user clicks login', async () => {
        const wrapper = await createWrapper();

        // check if login status is not visible
        let loginStatus = wrapper.find('.sw-extension-my-extensions-account__wrapper-content-login-status-id');
        expect(loginStatus.exists()).toBe(false);

        // get fields
        const contenaIdField = wrapper.get('.sw-extension-my-extensions-account__contena-id-field input');
        const passwordField = wrapper.findByLabel('sw-extension.my-extensions.account.passwordLabel');
        const loginButton = wrapper.find('.sw-extension-my-extensions-account__login-button');

        // enter credentials
        await contenaIdField.setValue('max@muster.com');
        await passwordField.setValue('v3ryS3cret');

        await wrapper.vm.$nextTick();
        await flushPromises();

        // login
        await loginButton.trigger('click');
        await flushPromises();

        // check if layout switches
        loginStatus = wrapper.find('.sw-extension-my-extensions-account__wrapper-content-login-status-id');
        expect(loginStatus.exists()).toBe(true);
        expect(loginStatus.text()).toBe('max@muster.com');
    });

    it('should show the logged in view when logged in', async () => {
        Contena.Store.get('contenaExtensions').admin_userInfo = admin_userInfo;

        // create component with logged in view
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if layout shows the logged in information
        const loginStatus = wrapper.find('.sw-extension-my-extensions-account__wrapper-content-login-status-id');
        expect(loginStatus.exists()).toBe(true);
        expect(loginStatus.text()).toBe('max@muster.com');
    });

    it('should logout when admin_user clicks logout button', async () => {
        Contena.Store.get('contenaExtensions').admin_userInfo = admin_userInfo;

        // create component with logged in view
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if logout button exists
        let logoutButton = wrapper.find('.sw-extension-my-extensions-account__logout-button');
        expect(logoutButton.exists()).toBe(true);

        // click on logout
        await logoutButton.trigger('click');

        // check if logout button disappears
        logoutButton = wrapper.find('.sw-extension-my-extensions-account__logout-button');
        expect(logoutButton.exists()).toBe(false);

        // check if admin_user is sees login view
        const loginButton = wrapper.find('.sw-extension-my-extensions-account__login-button');
        expect(loginButton.exists()).toBe(true);
    });
});
