import { mount } from '@vue/test-utils';
import { routerKey } from 'vue-router';

describe('src/module/ct-privilege-error/page/ct-privilege-error', () => {
    let wrapper;
    let router;

    beforeEach(async () => {
        router = {
            go: jest.fn(),
        };
        wrapper = mount(await wrapTestComponent('ct-privilege-error', { sync: true }), {
            global: {
                provide: {
                    [routerKey]: router,
                },
                stubs: {
                    'ct-page': {
                        template: '<div><slot name="content"></slot></div>',
                    },
                },
            },
        });
    });

    it('should show a back button', async () => {
        const backButton = wrapper.find('.ct-privilege-error__back-button');

        expect(backButton.text()).toContain('ct-privilege-error.general.goBack');
    });

    it('should go a page back when button is clicked', async () => {
        const backButton = wrapper.find('.ct-privilege-error__back-button');

        expect(router.go).not.toHaveBeenCalled();

        await backButton.trigger('click');

        expect(router.go).toHaveBeenCalledWith(-1);
    });
});
