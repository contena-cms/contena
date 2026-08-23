import { mount } from '@vue/test-utils';
import 'src/app/component/meteor/ct-meteor-card';

async function createWrapper(customConfig = {}) {
    return mount(await wrapTestComponent('ct-meteor-card', { sync: true }), {
        props: {},
        global: {
            stubs: {
                'ct-loader': true,
                'ct-extension-component-section': true,
                'router-link': true,
            },
            provide: {},
        },
        ...customConfig,
    });
}

describe('src/app/component/meteor/ct-meteor-card', () => {
    it('should render the content of the default slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>',
            },
        });

        const contentWrapper = wrapper.find('.ct-meteor-card__content-wrapper');
        expect(contentWrapper.text()).toBe('I am in the default slot');
    });

    it('should render the content of the default scoped slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>',
            },
        });

        const contentWrapper = wrapper.find('.ct-meteor-card__content-wrapper');
        expect(contentWrapper.text()).toBe('I am in the default slot');
    });

    it('should render the title as prop', async () => {
        const wrapper = await createWrapper({
            props: {
                title: 'Welcome to Contena',
            },
        });

        const title = wrapper.find('.ct-meteor-card__title');
        expect(title.text()).toBe('Welcome to Contena');
    });

    it('should render as hero card', async () => {
        const wrapper = await createWrapper({
            props: {
                hero: true,
            },
        });

        expect(wrapper.classes()).toContain('ct-meteor-card--hero');
    });

    it('should render a loading indicator', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>Lorem Ipsum</p>',
            },
        });

        let loader = wrapper.find('ct-loader-stub');
        expect(loader.exists()).toBe(false);

        await wrapper.setProps({ isLoading: true });

        loader = wrapper.find('ct-loader-stub');
        expect(loader.exists()).toBe(true);
        expect(loader.isVisible()).toBe(true);
    });

    it('should render a large card', async () => {
        const wrapper = await createWrapper({
            props: {
                large: true,
            },
        });

        expect(wrapper.classes()).toContain('ct-meteor-card--large');
    });

    it('should render a something in the toolbar slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                toolbar: '<p>I am in the toolbar slot</p>',
            },
        });

        const toolbarSlot = wrapper.find('.ct-meteor-card__toolbar');
        expect(toolbarSlot.text()).toBe('I am in the toolbar slot');
    });

    it('should render a something in the footer slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                footer: '<p>I am in the footer slot</p>',
            },
        });

        const footerSlot = wrapper.find('.ct-meteor-card__footer');
        expect(footerSlot.text()).toBe('I am in the footer slot');
    });

    it('should render a something in the grid slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                grid: '<p>I am in the grid slot</p>',
            },
        });

        const contentWrapper = wrapper.find('.ct-meteor-card__content');
        expect(contentWrapper.text()).toBe('I am in the grid slot');
    });

    it('should render a something in the action slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                action: '<p>I am in the action slot</p>',
            },
        });

        const actionsSlot = wrapper.find('.ct-meteor-card__header-action');
        expect(actionsSlot.text()).toBe('I am in the action slot');
    });
});
