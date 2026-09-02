import { mount } from '@vue/test-utils';

const deviceMock = {
    onResize: jest.fn(),
    removeResizeListener: jest.fn(),
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('ct-sidebar', {
            sync: true,
        }),
        {
            slots: {
                default: `
<ct-sidebar-item title="First sidebar item" icon="regular-image">
    <p class="first-sidebar-item-content">The content of the first sidebar item</p>
</ct-sidebar-item>
<ct-sidebar-item title="Filter sidebar item" icon="regular-filter" />
            `,
            },
            global: {
                stubs: {
                    'ct-block': {
                        props: [
                            'name',
                            'data',
                        ],
                        template: '<div><slot /></div>',
                    },
                    'mt-icon': true,
                    'ct-sidebar-item': await wrapTestComponent('ct-sidebar-item', { sync: true }),
                    'ct-sidebar-navigation-item': await wrapTestComponent('ct-sidebar-navigation-item', { sync: true }),
                },
                mocks: {
                    $device: deviceMock,
                },
                provide: {
                    setCtPageSidebarOffset: () => {},
                    removeCtPageSidebarOffset: () => {},
                },
            },
        },
    );
}

describe('src/app/component/sidebar/ct-sidebar/index.js', () => {
    /** @type VueWrapper */
    let wrapper;

    beforeEach(async () => {
        deviceMock.onResize.mockClear();
        deviceMock.removeResizeListener.mockClear();

        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should open the sidebar', async () => {
        // Check if the content of the first sidebar item is not visible
        let firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.exists()).toBe(false);

        // Open the sidebar
        const firstSidebarNavigationItem = await wrapper.find(
            'button.ct-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        // Check if the content of the first sidebar item is visible
        firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.text()).toBe('The content of the first sidebar item');
    });

    it('should close the sidebar', async () => {
        // Open the sidebar
        const firstSidebarNavigationItem = await wrapper.find(
            'button.ct-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        // Check if the content of the first sidebar item is visible
        let firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.text()).toBe('The content of the first sidebar item');

        // Close the sidebar
        const closeButton = await wrapper.find('button[aria-label="ct-sidebar.ariaLabelButtonClose"]');
        await closeButton.trigger('click');

        // Check if the content of the first sidebar item is not visible
        firstSidebarItemContent = await wrapper.find('.first-sidebar-item-content');
        expect(firstSidebarItemContent.exists()).toBe(false);
    });

    it('should keep the active navigation item after resizing', async () => {
        const firstSidebarNavigationItem = await wrapper.find(
            'button.ct-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        await firstSidebarNavigationItem.trigger('click');

        expect(firstSidebarNavigationItem.classes()).toContain('is--active');

        wrapper.vm.onResize();
        await flushPromises();

        const resizedSidebarNavigationItem = await wrapper.find(
            'button.ct-sidebar-navigation-item[aria-label="First sidebar item"]',
        );
        expect(resizedSidebarNavigationItem.classes()).toContain('is--active');
    });

    it('should render the navigation item with a tooltip', async () => {
        const button = wrapper.find('button.ct-sidebar-navigation-item[aria-label="First sidebar item"]');

        expect(button.attributes('tooltip-mock-id')).toBeDefined();
        expect(button.attributes('tooltip-mock-message')).toBe('First sidebar item');
        expect(button.attributes('title')).toBeUndefined();
    });
});
