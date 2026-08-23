import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import CaptchaSelect from './ct-settings-captcha-select-v2.vue';

function createCaptchaConfiguration() {
    return {
        honeypot: { name: 'Honeypot', isActive: false },
        basicCaptcha: { name: 'basicCaptcha', isActive: false },
        googleReCaptchaV2: {
            name: 'googleReCaptchaV2',
            isActive: false,
            config: { siteKey: '', secretKey: '', invisible: false },
        },
        googleReCaptchaV3: {
            name: 'googleReCaptchaV3',
            isActive: false,
            config: { siteKey: '', secretKey: '', thresholdScore: 0.5 },
        },
    };
}

function createWrapper() {
    const value = createCaptchaConfiguration();
    const list = jest.fn((callback: (captchas: string[]) => void) => {
        callback([
            'honeypot',
            'basicCaptcha',
            'googleReCaptchaV2',
            'googleReCaptchaV3',
        ]);
    });

    const wrapper = mount(CaptchaSelect, {
        props: { value },
        attrs: {
            label: 'Active CAPTCHAS',
            placeholder: 'None',
        },
        global: {
            provide: {
                captchaService: { list },
            },
            stubs: {
                'mt-select': true,
                'mt-text-field': true,
                'mt-number-field': true,
                'mt-switch': true,
                'mt-banner': true,
            },
        },
    });

    return { wrapper, value, list };
}

describe('module/ct-settings-basic-information/component/ct-settings-captcha-select-v2', () => {
    it('loads and translates all available captcha options', () => {
        const { wrapper, list } = createWrapper();
        const vm = wrapper.vm as unknown as {
            availableCaptchas: Array<{ label: string; value: string }>;
            attributes: Record<string, unknown>;
        };

        expect(list).toHaveBeenCalledTimes(1);
        expect(vm.availableCaptchas).toEqual([
            {
                label: 'ct-settings-basic-information.captcha.label.honeypot',
                value: 'honeypot',
            },
            {
                label: 'ct-settings-basic-information.captcha.label.basicCaptcha',
                value: 'basicCaptcha',
            },
            {
                label: 'ct-settings-basic-information.captcha.label.googleReCaptchaV2',
                value: 'googleReCaptchaV2',
            },
            {
                label: 'ct-settings-basic-information.captcha.label.googleReCaptchaV3',
                value: 'googleReCaptchaV3',
            },
        ]);
        expect(vm.attributes).toMatchObject({
            label: 'Active CAPTCHAS',
            placeholder: 'None',
        });
    });

    it('maps the multi-select value to the captcha configuration', async () => {
        const { wrapper, value } = createWrapper();
        const vm = wrapper.vm as unknown as {
            activeCaptchaSelect: string[];
        };

        vm.activeCaptchaSelect = [
            'basicCaptcha',
            'googleReCaptchaV3',
        ];
        await nextTick();

        expect(value.honeypot.isActive).toBe(false);
        expect(value.basicCaptcha.isActive).toBe(true);
        expect(value.googleReCaptchaV2.isActive).toBe(false);
        expect(value.googleReCaptchaV3.isActive).toBe(true);
        expect(wrapper.emitted('update:value')).toBeDefined();
    });
});
