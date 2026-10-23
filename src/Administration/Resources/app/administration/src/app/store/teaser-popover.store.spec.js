/**
 * @sw-package innovation
 */

import './teaser-popover.store';

describe('teaser-popover.store', () => {
    let store;

    beforeEach(() => {
        store = Contena.Store.get('teaserPopover');
    });

    afterEach(() => {
        store.identifier = {};
    });

    it('has initial state', () => {
        expect(store.identifier).toStrictEqual({});
    });

    it('can add teaser popover', () => {
        store.addPopoverComponent({
            positionId: 'positionId',
            src: 'http://localhost:8080',
            component: 'button',
            props: {
                locationId: 'locationId',
                label: 'Ask AI Copilot',
            },
        });

        expect(JSON.stringify(store.identifier)).toBe(
            JSON.stringify({
                positionId: {
                    positionId: 'positionId',
                    src: 'http://localhost:8080',
                    component: 'button',
                    props: {
                        locationId: 'locationId',
                        label: 'Ask AI Copilot',
                    },
                },
            }),
        );
    });

    it('can update teaser sales channel', () => {
        store.addSalesChannel({
            positionId: 'positionId',
            channel: {
                title: 'Facebook',
                description: 'Sell blogs on Facebook',
                iconName: 'facebook',
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

        expect(store.channels).toStrictEqual([
            {
                positionId: 'positionId',
                channel: {
                    title: 'Facebook',
                    description: 'Sell blogs on Facebook',
                    iconName: 'facebook',
                },
                popoverComponent: {
                    src: 'http://localhost:8080',
                    component: 'button',
                    props: {
                        locationId: 'locationId',
                        label: 'Ask AI Copilot',
                    },
                },
            },
        ]);
    });
});
