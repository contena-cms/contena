import { mount } from '@vue/test-utils';

const createWrapper = async (options) => {
    return mount(await wrapTestComponent('ct-field-error', { sync: true }), {
        global: {
            mocks: {
                $t: (key, number, value) => {
                    if (!value || Object.keys(value).length < 1) {
                        return key;
                    }
                    return key + JSON.stringify(value);
                },
            },
        },
        ...options,
    });
};
describe('src/app/component/form/field-base/ct-field-error', () => {
    it('should render error message when error is provided', async () => {
        const errorMessage = 'This is an error message';
        const wrapper = await createWrapper({
            props: {
                error: {
                    code: 'SOME_ERROR_CODE',
                    detail: errorMessage,
                },
            },
        });

        expect(wrapper.find('.ct-field__error').exists()).toBe(true);
        expect(wrapper.find('.ct-field__error').text()).toContain(errorMessage);
    });

    it('should not render error message when error is not provided', async () => {
        const wrapper = await createWrapper({
            props: {
                error: null,
            },
        });

        expect(wrapper.find('.ct-field__error').exists()).toBe(false);
    });

    it('should format parameters correctly', async () => {
        const errorMessage = 'This is an error message with parameter: Test Parameter';
        const wrapper = await createWrapper({
            props: {
                error: {
                    code: 'SOME_ERROR_CODE',
                    detail: errorMessage,
                    parameters: {
                        '{{ parameter }}': 'Test Parameter',
                    },
                },
            },
        });

        expect(wrapper.find('.ct-field__error').exists()).toBe(true);
        expect(wrapper.find('.ct-field__error').text()).toContain(
            'global.error-codes.SOME_ERROR_CODE{"parameter":"Test Parameter"}',
        );
    });
});
