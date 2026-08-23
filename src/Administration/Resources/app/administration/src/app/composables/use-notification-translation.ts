import { useI18n } from 'vue-i18n';

import Sanitizer from 'src/core/helper/sanitizer.helper';

import type { NotificationType } from '../store/notification.store';

/**
 * Shared rendering helpers for growl and notification-center entries.
 *
 * @private
 */
export function useNotificationTranslation(): {
    getTranslatedTitle: (notification: NotificationType) => string;
    getTranslatedMessage: (notification: NotificationType) => string;
} {
    const i18n = useI18n();

    function translate(value: string | undefined): string {
        if (!value) {
            return '';
        }

        return i18n.te(value) ? i18n.t(value) : value;
    }

    function getTranslatedTitle(notification: NotificationType): string {
        return translate(notification.title);
    }

    function getTranslatedMessage(notification: NotificationType): string {
        return Sanitizer.sanitize(translate(notification.message), {
            ALLOWED_TAGS: [
                'a',
                'b',
                'i',
                'u',
                'strong',
                'em',
                'br',
            ],
            ALLOWED_ATTR: [
                'href',
                'target',
            ],
        });
    }

    return { getTranslatedTitle, getTranslatedMessage };
}
