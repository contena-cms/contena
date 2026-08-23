import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('ct-profile-index-general', { sync: true }), {
        global: {
            stubs: {
                'ct-container': await wrapTestComponent('ct-container'),
                'ct-text-field': true,
                'ct-select-field': true,
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'mt-floating-ui': {
                    template: '<div><slot /></div>',
                },
                'ct-upload-listener': {
                    emits: [
                        'click',
                        'media-upload-finish',
                    ],
                    template: `<div
                        class="ct-upload-listener"
                        @click="$emit('click', $event)"
                        @media-upload-finish="$emit('media-upload-finish', $event)"
                    ></div>`,
                },
                'ct-media-upload-v2': {
                    emits: [
                        'media-drop',
                        'media-upload-remove-image',
                        'media-upload-sidebar-open',
                    ],
                    template: `<div
                        class="ct-media-upload-v2"
                        @media-drop="$emit('media-drop', $event)"
                        @media-upload-remove-image="$emit('media-upload-remove-image', $event)"
                        @media-upload-sidebar-open="$emit('media-upload-sidebar-open', $event)"
                    >
                        <slot name="upload"></slot>
                        <slot name="preview"></slot>
                    </div>`,
                },
                'ct-extension-component-section': true,
                'ct-ai-copilot-badge': true,
                'ct-context-button': true,
                'ct-loader': true,
                'ct-inheritance-switch': true,
                'ct-help-text': true,
                'ct-field-error': true,
            },
            provide: {
                acl: {
                    can: (key) => {
                        if (!key) {
                            return true;
                        }

                        return privileges.includes(key);
                    },
                },
            },
        },
        props: {
            user: {},
            languages: [],
            newPassword: null,
            newPasswordConfirm: null,
            avatarMediaItem: null,
            isUserLoading: false,
            languageId: null,
            isDisabled: true,
            userRepository: {
                schema: {
                    entity: '',
                },
            },
            timezoneOptions: [
                {
                    label: 'UTC',
                    value: 'UTC',
                },
            ],
        },
    });
}

describe('src/module/ct-profile/view/ct-profile-index-general', () => {
    it('should be able to change new password', async () => {
        const wrapper = await createWrapper(['user.update_profile']);
        await flushPromises();

        const changeNewPasswordField = wrapper.findByLabel('ct-profile.index.labelNewPassword');
        await changeNewPasswordField.setValue('Contena');
        await changeNewPasswordField.trigger('input');
        await flushPromises();

        expect(wrapper.emitted('new-password-change')[0][0]).toBe('Contena');
    });

    it('should be able to change new password confirm', async () => {
        const wrapper = await createWrapper(['user.update_profile']);
        await flushPromises();

        const changeNewPasswordConfirmField = wrapper.findByLabel('ct-profile.index.labelNewPasswordConfirm');
        await changeNewPasswordConfirmField.setValue('Contena');
        await changeNewPasswordConfirmField.trigger('input');
        await flushPromises();

        expect(wrapper.emitted('new-password-confirm-change')[0][0]).toBe('Contena');
    });

    it('should be able to upload media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.ct-upload-listener').trigger('media-upload-finish', { targetId: 'targetId' });

        expect(wrapper.emitted('media-upload')[0][0].targetId).toBe('targetId');
    });

    it('should be able to drop media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.ct-media-upload-v2').trigger('media-drop', { id: 'targetId' });

        expect(wrapper.emitted('media-upload')[0][0].targetId).toBe('targetId');
    });

    it('should be able to remove media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.ct-media-upload-v2').trigger('media-upload-remove-image');

        expect(wrapper.emitted('media-remove')[0]).toHaveLength(0);
    });

    it('should be able to open media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.ct-media-upload-v2').trigger('media-upload-sidebar-open');

        expect(wrapper.emitted('media-open')[0]).toHaveLength(0);
    });

    it('should be able to select timezone', async () => {
        const wrapper = await createWrapper(['user.update_profile']);
        await flushPromises();

        await selectMtSelectOptionByText(wrapper, 'UTC', '.ct-profile--timezone input');

        expect(wrapper.props('user').timeZone).toBe('UTC');
    });
});
