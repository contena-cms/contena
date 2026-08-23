import { mount } from '@vue/test-utils';
import channelAdminMenuExtension from './ct-admin-menu.override.vue';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';

function createWrapper(canViewChannels: boolean) {
    const wrapper = mount(channelAdminMenuExtension, {
        global: {
            provide: {
                acl: { can: () => canViewChannels },
            },
            stubs: {
                'ct-block': true,
            },
        },
    });
    const override = _overridesMap['ct-admin-menu'][0];
    const overrideResult = wrapper.vm.$.appContext.app.runWithContext(() => override({ _private: {} }, {}, {})) as {
        __swOverride: Record<symbol, { canViewChannels: { value: boolean } }>;
    };
    const namespace = Reflect.ownKeys(overrideResult.__swOverride)[0] as symbol;
    const overrideState = overrideResult.__swOverride[namespace];

    return {
        wrapper,
        canViewChannels: overrideState.canViewChannels,
    };
}

describe('module/ct-channel/component/structure/ct-admin-menu-extension', () => {
    beforeEach(() => {
        delete _overridesMap['ct-admin-menu'];
    });

    it('registers visible Channel menu state on the admin menu', () => {
        const { wrapper, canViewChannels } = createWrapper(true);

        expect(_overridesMap['ct-admin-menu-extension']).toBeUndefined();
        expect(canViewChannels.value).toBe(true);

        wrapper.unmount();
    });

    it('registers hidden Channel menu state when access is forbidden', () => {
        const { wrapper, canViewChannels } = createWrapper(false);

        expect(canViewChannels.value).toBe(false);

        wrapper.unmount();
    });
});
