import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { createI18n } from 'vue-i18n';

import { useNotificationTranslation } from './use-notification-translation';

function createWrapper() {
    const i18n = createI18n({
        legacy: false,
        locale: 'en-GB',
        warnHtmlMessage: false,
        messages: {
            'en-GB': {
                global: {
                    default: { error: 'Error' },
                    notification: { message: 'A <strong>translated</strong> message' },
                },
            },
        },
    });

    return mount(
        defineComponent({
            setup() {
                return useNotificationTranslation();
            },
            template: '<div />',
        }),
        {
            global: {
                plugins: [i18n],
            },
        },
    );
}

describe('useNotificationTranslation', () => {
    it('translates snippet keys and keeps plain titles', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.getTranslatedTitle({ title: 'global.default.error' })).toBe('Error');
        expect(wrapper.vm.getTranslatedTitle({ title: 'Plain title' })).toBe('Plain title');
        expect(wrapper.vm.getTranslatedTitle({})).toBe('');
    });

    it('translates and sanitizes notification messages', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.getTranslatedMessage({ message: 'global.notification.message' })).toBe(
            'A <strong>translated</strong> message',
        );
        expect(wrapper.vm.getTranslatedMessage({ message: '<script>alert(1)</script><b>ok</b>' })).toBe('<b>ok</b>');
        expect(wrapper.vm.getTranslatedMessage({})).toBe('');
    });
});
