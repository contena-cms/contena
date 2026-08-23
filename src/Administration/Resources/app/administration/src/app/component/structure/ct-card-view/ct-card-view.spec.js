import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-card-view', { sync: true }), {
        global: {
            stubs: {
                'ct-error-summary': true,
            },
        },
        props: {
            showErrorSummary: true,
        },
    });
}

describe('src/app/component/structure/ct-card-view', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be able to turn off the error summary component', async () => {
        expect(wrapper.find('ct-error-summary-stub').exists()).toBeTruthy();

        await wrapper.setProps({
            showErrorSummary: false,
        });

        expect(wrapper.find('ct-error-summary-stub').exists()).toBeFalsy();
    });
});
