/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-extension-teaser-sales-channel', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-extension-teaser-popover': true,
                },
            },
        },
    );
}

describe('src/app/component/extension-api/sw-extension-teaser-sales-channel', () => {
    let wrapper = null;
    let store = null;

    beforeEach(async () => {
        store = Contena.Store.get('teaserPopover');
        store.channels = [];
    });

    it('should render correctly', async () => {
        store.addSalesChannel({
            positionId: 'positionId',
            channel: {
                title: 'Facebook',
                description: 'Sell blogs on Facebook',
                iconName: 'regular-facebook',
            },
            popoverComponent: {
                src: 'http://localhost:8080',
                component: 'button',
                props: {
                    locationId: 'locationId',
                    label: 'Ask AI Copilot',
                },
            },
        });

        wrapper = await createWrapper();
        const channels = wrapper.findAll('.sw-extension-teaser-sales-channel');

        expect(channels).toHaveLength(1);

        const channel = channels[0];
        expect(channel.findComponent('.mt-icon').vm.name).toBe('regular-facebook');
        expect(channel.find('.sw-extension-teaser-sales-channel__item-name').text()).toBe('Facebook');
        expect(channel.find('.sw-extension-teaser-sales-channel__item-description').text()).toBe(
            'Sell blogs on Facebook',
        );
    });
});
