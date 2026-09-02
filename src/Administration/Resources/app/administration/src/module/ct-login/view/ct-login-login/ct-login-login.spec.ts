import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';
import { defineComponent } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import { STORAGE_KEYS } from '../../service/login.constants';
import '../../index';

interface LoginPageVm {
    username: string;
    password: string;
}

const loginService = {
    loginByUsername: jest.fn(),
    setRememberMe: jest.fn(),
};

const router = {
    push: jest.fn(),
};

async function createWrapper(): Promise<VueWrapper<LoginPageVm>> {
    localStorage.setItem(STORAGE_KEYS.ADMIN_LOCALE, 'en-GB');
    loginService.loginByUsername.mockResolvedValue(undefined);

    return mount(await wrapTestComponent('ct-login-login', { sync: true }), {
        global: {
            provide: {
                loginService,
                [routerKey as symbol]: router,
            },
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'router-link': true,
                'mt-banner': true,
                'mt-text-field': true,
                'mt-password-field': true,
                'mt-checkbox': true,
                'mt-button': true,
            },
        },
    }) as unknown as VueWrapper<LoginPageVm>;
}

describe('module/ct-login/view/ct-login-login', () => {
    const source = readFileSync(__dirname + '/ct-login-login.vue', 'utf8');
    const { descriptor, errors } = parse(source);
    const template = descriptor.template?.content ?? '';

    it('uses the large button size for the login action', () => {
        expect(errors).toHaveLength(0);
        expect(template).toContain('class="ct-login-credentials__title"');
        expect(template).toContain('<ct-block name="ct_login_login_form_icon" :data="$dataScope">');
        expect(template).toContain("assetFilter('/administration/administration/static/img/contena-logo-v4.svg')");
        expect(template).toContain('ct-login.credentials.description');
        expect(template).toContain('class="ct-login-credentials__login-button"');
        expect(template).toContain('size="large"');
        expect(template).toContain('<ct-block name="ct_login_login_support" :data="$dataScope">');
    });

    it('reloads immediately after login when the login shell requested a rebuild', async () => {
        const wrapper = await createWrapper();
        const navigationError = jest.spyOn(console, 'error').mockImplementation(() => undefined);
        sessionStorage.setItem(STORAGE_KEYS.SHOULD_RELOAD, 'true');
        wrapper.vm.username = 'admin';
        wrapper.vm.password = 'secret';

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.vm.password).toBe('');
        expect(sessionStorage.getItem(STORAGE_KEYS.SHOULD_RELOAD)).toBeNull();
        expect(router.push).not.toHaveBeenCalled();
        navigationError.mockRestore();
        wrapper.unmount();
    });

    it('forwards to the administration when no rebuild was requested', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.username = 'admin';
        wrapper.vm.password = 'secret';

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(router.push).toHaveBeenCalledWith({ name: 'core' });
        wrapper.unmount();
    });
});
