import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

let mockTranslationExists = (key) => key && typeof key === 'string' && key.includes('.');
let mockTranslate = (key) => `Translated: ${key}`;
let mockSanitize = (value) => value;

jest.mock('src/app/composables/use-notification-translation', () => ({
    useNotificationTranslation: () => ({
        getTranslatedTitle: ({ title }) => (mockTranslationExists(title) ? mockTranslate(title) : (title ?? '')),
        getTranslatedMessage: ({ message }) =>
            mockSanitize(mockTranslationExists(message) ? mockTranslate(message) : (message ?? ''), {
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
            }),
    }),
}));

async function createWrapper(notificationData = {}, mocks = {}) {
    mockTranslationExists = mocks.$te ?? ((key) => key && typeof key === 'string' && key.includes('.'));
    mockTranslate = mocks.$t ?? ((key) => `Translated: ${key}`);
    mockSanitize = mocks.$sanitize ?? ((value) => value);

    const defaultNotification = {
        uuid: '018d0c7c90f47a228894d117c9b442bc',
        title: 'Test Title',
        message: 'Test Message',
        timestamp: new Date('2024-01-15T09:38:26.676Z'),
        variant: 'info',
        visited: false,
        actions: [],
        metadata: {},
        isLoading: false,
        ...notificationData,
    };

    return mount(await wrapTestComponent('ct-notification-center-item', { sync: true }), {
        props: {
            notification: defaultNotification,
        },
        global: {
            stubs: {
                'ct-time-ago': true,
                'ct-loader': true,
                'mt-icon': true,
                'mt-button': true,
            },
        },
    });
}

describe('src/app/component/utils/ct-notification-center-item', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    describe('title translation', () => {
        it('should translate title when it is a translation key', async () => {
            const wrapper = await createWrapper({
                title: 'global.notification.successTitle',
            });

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            expect(titleElement.text()).toBe('Translated: global.notification.successTitle');
        });

        it('should display plain text title when it is not a translation key', async () => {
            const wrapper = await createWrapper({
                title: 'Plain Text Title',
            });

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            expect(titleElement.text()).toBe('Plain Text Title');
        });

        it('should handle empty title', async () => {
            const wrapper = await createWrapper({
                title: '',
            });

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            expect(titleElement.text()).toBe('');
        });
    });

    describe('message translation', () => {
        it('should translate message when it is a translation key', async () => {
            const wrapper = await createWrapper({
                message: 'global.notification.successMessage',
            });

            const messageElement = wrapper.find('.ct-notification-center-item__message');
            expect(messageElement.element.innerHTML).toContain('Translated: global.notification.successMessage');
        });

        it('should display plain text message when it is not a translation key', async () => {
            const wrapper = await createWrapper({
                message: 'Plain text message',
            });

            const messageElement = wrapper.find('.ct-notification-center-item__message');
            expect(messageElement.element.innerHTML).toContain('Plain text message');
        });

        it('should sanitize translated message', async () => {
            const sanitizeMock = jest.fn((value) => value);
            const wrapper = await createWrapper(
                {
                    message: 'global.notification.htmlMessage',
                },
                {
                    $sanitize: sanitizeMock,
                },
            );

            await flushPromises();

            expect(wrapper.find('.ct-notification-center-item__message').exists()).toBe(true);
            expect(sanitizeMock).toHaveBeenCalledWith('Translated: global.notification.htmlMessage', {
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
        });

        it('should sanitize non-translated message', async () => {
            const sanitizeMock = jest.fn((value) => value);
            const wrapper = await createWrapper(
                {
                    message: 'Plain message with <script>alert("xss")</script>',
                },
                {
                    $sanitize: sanitizeMock,
                },
            );

            await flushPromises();

            expect(wrapper.find('.ct-notification-center-item__message').exists()).toBe(true);
            expect(sanitizeMock).toHaveBeenCalledWith('Plain message with <script>alert("xss")</script>', {
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
        });

        it('should handle empty message', async () => {
            const wrapper = await createWrapper({
                message: '',
            });

            const messageElement = wrapper.find('.ct-notification-center-item__message');
            expect(messageElement.element.innerHTML).toBe('');
        });
    });

    describe('translation with $te returning false', () => {
        it('should not translate when $te returns false for title', async () => {
            const wrapper = await createWrapper(
                {
                    title: 'global.notification.title',
                },
                {
                    $te: () => false,
                    $t: (key) => `Translated: ${key}`,
                },
            );

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            expect(titleElement.text()).toBe('global.notification.title');
        });

        it('should not translate when $te returns false for message', async () => {
            const sanitizeMock = jest.fn((value) => value);
            const wrapper = await createWrapper(
                {
                    message: 'global.notification.message',
                },
                {
                    $te: () => false,
                    $t: (key) => `Translated: ${key}`,
                    $sanitize: sanitizeMock,
                },
            );

            await flushPromises();

            expect(wrapper.find('.ct-notification-center-item__message').exists()).toBe(true);
            expect(sanitizeMock).toHaveBeenCalledWith('global.notification.message', {
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
        });
    });

    describe('translation with $te returning true', () => {
        it('should translate when $te returns true for title', async () => {
            const wrapper = await createWrapper(
                {
                    title: 'Some title',
                },
                {
                    $te: () => true,
                    $t: (key) => `Translated: ${key}`,
                },
            );

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            expect(titleElement.text()).toBe('Translated: Some title');
        });

        it('should translate when $te returns true for message', async () => {
            const sanitizeMock = jest.fn((value) => value);
            const wrapper = await createWrapper(
                {
                    message: 'Some message',
                },
                {
                    $te: () => true,
                    $t: (key) => `Translated: ${key}`,
                    $sanitize: sanitizeMock,
                },
            );

            await flushPromises();

            expect(wrapper.find('.ct-notification-center-item__message').exists()).toBe(true);
            expect(sanitizeMock).toHaveBeenCalledWith('Translated: Some message', {
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
        });
    });

    describe('combined title and message translation', () => {
        it('should translate both title and message when both are translation keys', async () => {
            const wrapper = await createWrapper({
                title: 'global.notification.title',
                message: 'global.notification.message',
            });

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            const messageElement = wrapper.find('.ct-notification-center-item__message');

            expect(titleElement.text()).toBe('Translated: global.notification.title');
            expect(messageElement.element.innerHTML).toContain('Translated: global.notification.message');
        });

        it('should handle mixed translation key and plain text', async () => {
            const wrapper = await createWrapper({
                title: 'global.notification.title',
                message: 'Plain text message',
            });

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            const messageElement = wrapper.find('.ct-notification-center-item__message');

            expect(titleElement.text()).toBe('Translated: global.notification.title');
            expect(messageElement.element.innerHTML).toContain('Plain text message');
        });

        it('should handle plain text title and translation key message', async () => {
            const wrapper = await createWrapper({
                title: 'Plain Title',
                message: 'global.notification.message',
            });

            const titleElement = wrapper.find('.ct-notification-center-item__title');
            const messageElement = wrapper.find('.ct-notification-center-item__message');

            expect(titleElement.text()).toBe('Plain Title');
            expect(messageElement.element.innerHTML).toContain('Translated: global.notification.message');
        });
    });
});
