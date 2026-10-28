/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
import createWrapper, { registerAdminModules } from './create-wrapper';

describe('src/app/component/structure/sw-admin-menu: shortcuts', () => {
    let wrapper;

    beforeAll(() => {
        Contena.Store.get('session').currentLocale = 'en-GB';
        Contena.Context.app.fallbackLocale = 'en-GB';

        registerAdminModules();
    });

    beforeEach(async () => {
        config.global.stubs = {
            ...config.global.stubs,
            transition: false,
        };

        jest.spyOn(Contena.Utils.debug, 'error').mockImplementation(() => true);

        Contena.Store.get('session').setCurrentUser(null);
        Contena.Store.get('settingsItems').settingsGroups.shop = [];
        Contena.Store.get('settingsItems').settingsGroups.system = [];
        Contena.Store.get('contenaApps').apps = [];

        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should toggle the sidebar with the S shortcut on desktop viewports only', async () => {
        const shortcut = wrapper.vm.$options.shortcuts.S;

        expect(shortcut.method).toBe('onToggleSidebar');

        wrapper.vm.viewportWidth = 1920;
        expect(shortcut.active.call(wrapper.vm, { key: 's' })).toBe(true);

        wrapper.vm.viewportWidth = 1280;
        expect(shortcut.active.call(wrapper.vm, { key: 's' })).toBe(false);
    });

    it('should not toggle the sidebar with the S shortcut while a modifier key is held', async () => {
        const shortcut = wrapper.vm.$options.shortcuts.S;
        wrapper.vm.viewportWidth = 1920;

        expect(shortcut.active.call(wrapper.vm, { key: 's', ctrlKey: true })).toBe(false);
        expect(shortcut.active.call(wrapper.vm, { key: 's', metaKey: true })).toBe(false);
        expect(shortcut.active.call(wrapper.vm, { key: 's', altKey: true })).toBe(false);
    });
});
