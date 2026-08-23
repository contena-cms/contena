import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('ct-loader', { sync: true }), {
        global: {
            stubs: {
                'mt-loader': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/ct-loader', () => {
    it('should render the mt-loader', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-loader');
    });
});
