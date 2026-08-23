import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('ct-skeleton-bar', { sync: true }), {
        global: {
            stubs: {
                'mt-skeleton-bar': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/ct-skeleton-bar', () => {
    it('should render the mt-skeleton-bar', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-skeleton-bar');
    });
});
