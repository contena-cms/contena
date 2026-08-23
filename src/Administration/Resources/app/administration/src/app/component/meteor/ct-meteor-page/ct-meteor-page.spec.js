import { mount } from '@vue/test-utils';
import { h } from 'vue';
import { routeLocationKey, routerKey } from 'vue-router';
import 'src/app/component/meteor/ct-meteor-page';

const pageTabsSlot = `
<ct-tabs-item :route="{ name: 'tab.one' }">Tab 1</ct-tabs-item>
<ct-tabs-item :route="{ name: 'tab.two' }">Tab 2</ct-tabs-item>
<ct-tabs-item :route="{ name: 'tab.three' }">Tab 3</ct-tabs-item>
`;

async function createWrapper(slotsData = {}, { routeName = undefined } = {}) {
    const router = {
        push: jest.fn(),
        resolve: jest.fn(() => ({ matched: [] })),
    };

    return mount(await wrapTestComponent('ct-meteor-page', { sync: true }), {
        global: {
            provide: {
                [routeLocationKey]: {
                    name: routeName,
                    meta: {
                        $module: {
                            icon: 'regular-plug',
                            title: 'ct.example.title',
                            color: '#189EFF',
                        },
                    },
                },
                [routerKey]: router,
            },
            stubs: {
                'ct-search-bar': true,
                'ct-notification-center': true,
                'ct-help-center-v2': true,
                'ct-meteor-page-context': true,
                'ct-meteor-navigation': {
                    props: ['fromLink'],
                    template: '<div class="ct-meteor-navigation"></div>',
                },
                'ct-tabs-item': {
                    name: 'ct-tabs-item',
                    props: [
                        'name',
                        'title',
                        'route',
                        'hasError',
                        'hasWarning',
                        'disabled',
                    ],
                    template: '<div><slot /></div>',
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: [
                        'defaultItem',
                        'items',
                        'positionIdentifier',
                    ],
                    template: '<div class="mt-tabs"></div>',
                },
                'router-link': {
                    template: '<div class="router-link"><slot></slot></div>',
                },
                'ct-extension-component-section': true,
            },
            mocks: {
                $route: {
                    name: routeName,
                    meta: {
                        $module: {
                            icon: 'regular-plug',
                            title: 'ct.example.title',
                            color: '#189EFF',
                        },
                    },
                },
                $router: {
                    ...router,
                },
            },
        },
        slots: slotsData,
        props: {
            fromLink: {
                name: 'path.to.from.link',
            },
        },
    });
}

describe('src/app/component/meteor/ct-meteor-page', () => {
    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (message) =>
                typeof message === 'string' && message.includes('Slot "page-tabs" invoked outside of the render function'),
        });
    });

    it('should be in full width', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            fullWidth: true,
        });

        expect(wrapper.get('.ct-meteor-page').classes()).toContain('ct-meteor-page--full-width');
    });

    it('should hide the icon', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            hideIcon: true,
        });

        const iconComponent = wrapper.find('.mt-icon');
        expect(iconComponent.exists()).toBe(false);
    });

    it('should hide the smart bar', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-meteor-page__smart-bar').exists()).toBe(true);

        await wrapper.setProps({ hideSmartBar: true });

        expect(wrapper.get('.ct-meteor-page').classes()).toContain('ct-meteor-page--hide-smart-bar');
        expect(wrapper.find('.ct-meteor-page__smart-bar').exists()).toBe(false);
    });

    it('should render the module icon when slot "smart-bar-icon" is not filled', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const iconComponent = wrapper.findComponent('.mt-icon');
        expect(iconComponent.vm.name).toContain('regular-plug');
        expect(iconComponent.vm.color).toBe('#189EFF');
    });

    [
        'smart-bar-back',
        'smart-bar-icon',
        'smart-bar-header',
        'smart-bar-header-meta',
        'smart-bar-description',
        'smart-bar-actions',
        'smart-bar-context-buttons',
    ].forEach((slotName) => {
        it(`should render the content of the slot "${slotName}"`, async () => {
            const wrapper = await createWrapper({
                [slotName]: '<div id="test-slot">This slot works</div>',
            });
            await flushPromises();

            const testSlot = wrapper.find('#test-slot');

            expect(testSlot.exists()).toBe(true);
            expect(testSlot.text()).toBe('This slot works');
        });
    });

    it('should render the meteor navigation component when the slot "smart-bar-back" is not used', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const navigationComponent = wrapper.findComponent('.ct-meteor-navigation');

        expect(navigationComponent.exists()).toBe(true);

        expect(navigationComponent.props('fromLink')).toEqual({
            name: 'path.to.from.link',
        });
    });

    it('should not render the meteor navigation component when the slot "smart-bar-back" is not used', async () => {
        const wrapper = await createWrapper({
            'smart-bar-back': '<div id="test-slot">This slot works</div>',
        });
        await flushPromises();

        const navigationComponent = wrapper.find('ct-meteor-navigation-stub');
        expect(navigationComponent.exists()).toBe(false);
    });

    it('should render the title of the page when slot "smart-bar-header" is not filled', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const title = wrapper.find('.ct-meteor-page__smart-bar-title');

        expect(title.exists()).toBe(true);
        expect(title.text()).toBe('ct.example.title');
    });

    it('should render the content', async () => {
        const wrapper = await createWrapper({
            default: '<p>Lorem Ipsum</p>',
        });
        await flushPromises();

        const pageContent = wrapper.find('.ct-meteor-page__content');
        expect(pageContent.text()).toBe('Lorem Ipsum');
    });

    it('renders page-level search, notification and help controls in the page frame', async () => {
        const wrapper = await createWrapper({
            'search-bar': '<div class="local-search">Local search</div>',
        });

        expect(wrapper.find('.ct-meteor-page__head-area-top-bar-content .local-search').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'ct-notification-center' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'ct-help-center-v2' }).exists()).toBe(true);
    });

    it('maps page tabs to Meteor tabs', async () => {
        const wrapper = await createWrapper({
            'page-tabs': '<ct-tabs-item name="overview" title="Overview" />',
        });

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(true);
        expect(wrapper.vm.tabItems).toEqual([{ label: 'Overview', name: 'overview' }]);
    });

    it('maps routed page tabs to Meteor tabs and selects the current route', async () => {
        const wrapper = await createWrapper({ 'page-tabs': pageTabsSlot }, { routeName: 'tab.two' });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('ct-meteor-page');
        expect(tabs.props('defaultItem')).toBe('tab.two');
        expect(tabs.props('items').map(({ label, name }) => ({ label, name }))).toEqual([
            { label: 'Tab 1', name: 'tab.one' },
            { label: 'Tab 2', name: 'tab.two' },
            { label: 'Tab 3', name: 'tab.three' },
        ]);

        tabs.props('items')[1].onClick();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'tab.two' });

        await tabs.vm.$emit('new-item-active', 'tab.three');
        expect(wrapper.emitted('new-item-active')).toEqual([['tab.three']]);
    });

    it('prefers visible tab text and maps Meteor tab state', async () => {
        const wrapper = await createWrapper();
        const tabItem = h(
            { name: 'ct-tabs-item' },
            { name: 'tab.one', title: 'Tooltip text', hasError: true, hasWarning: true, disabled: true },
            { default: () => [h('span', 'Visible tab text')] },
        );

        expect(wrapper.vm.createTabItem(tabItem)).toEqual({
            label: 'Visible tab text',
            name: 'tab.one',
            hasError: true,
            disabled: true,
            badge: 'warning',
        });
    });

    it('does not render Meteor tabs when the page-tabs slot is absent', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });
});
