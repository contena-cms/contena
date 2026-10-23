/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import swSettingsFrontendConfiguration from './index';

Contena.Component.register('sw-settings-frontend-configuration', swSettingsFrontendConfiguration);

describe('sw-settings-frontend-configuration', () => {
    /**
     * @deprecated tag:v6.8.0 - This test will be removed with the deprecated component.
     */
    it('renders with required frontend settings', async () => {
        const component = await Contena.Component.build('sw-settings-frontend-configuration');

        const wrapper = shallowMount(component, {
            props: {
                frontendSettings: {
                    'core.frontendSettings.iconCache': true,
                },
            },
            global: {
                stubs: {
                    'mt-switch': true,
                },
                provide: {
                    feature: {},
                },
            },
        });

        expect(wrapper.props('frontendSettings')).toEqual({
            'core.frontendSettings.iconCache': true,
        });
    });
});
