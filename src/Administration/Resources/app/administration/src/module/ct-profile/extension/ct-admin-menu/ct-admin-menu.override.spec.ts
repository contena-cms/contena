import { mount } from '@vue/test-utils';
import profileMenuOverride from './ct-admin-menu.override.vue';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';

type ProfileOverrideState = {
    acl: {
        can: (privilege: string) => boolean;
    };
};

function createWrapper(canUpdateProfile: boolean) {
    const wrapper = mount(profileMenuOverride, {
        global: {
            provide: {
                acl: {
                    can: () => canUpdateProfile,
                },
            },
            stubs: {
                'ct-block': true,
                'ct-block-parent': true,
            },
        },
    });
    const override = _overridesMap['ct-admin-menu']?.[0];

    if (!override) {
        throw new Error('The profile menu override was not registered.');
    }

    const overrideResult = wrapper.vm.$.appContext.app.runWithContext(() => override({ _private: {} }, {}, {})) as {
        __ctOverride: Record<symbol, ProfileOverrideState>;
    };
    const namespace = Reflect.ownKeys(overrideResult.__ctOverride)[0] as symbol;

    return {
        wrapper,
        state: overrideResult.__ctOverride[namespace],
    };
}

describe('module/ct-profile/extension/ct-admin-menu', () => {
    beforeEach(() => {
        delete _overridesMap['ct-admin-menu'];
    });

    it('registers the profile action state for an administrator', () => {
        const { wrapper, state } = createWrapper(true);

        expect(state.acl.can('user.update_profile')).toBe(true);

        wrapper.unmount();
    });

    it('keeps the profile action hidden when profile editing is forbidden', () => {
        const { wrapper, state } = createWrapper(false);

        expect(state.acl.can('user.update_profile')).toBe(false);

        wrapper.unmount();
    });
});
