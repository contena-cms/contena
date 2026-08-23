import MailApiService from 'src/core/service/api/mail.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function getMailApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Contena.Context.api);

    const mailApiService = new MailApiService(client, loginService);

    clientMock.onAny().reply(200, {
        data: null,
    });

    return { mailApiService, clientMock };
}

describe('mailApiService', () => {
    it('is registered correctly', async () => {
        const { mailApiService } = getMailApiService();
        expect(mailApiService).toBeInstanceOf(MailApiService);
    });

    it('has the correct name', async () => {
        const { mailApiService } = getMailApiService();

        expect(mailApiService.name).toBe('mailService');
    });

    describe('sendMailTemplate', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();
            const mailTemplate = {
                contentHtml: '<p>Test</p>',
                contentPlain: 'Test',
                subject: 'Test Subject',
                senderEmail: 'sender@example.com',
                senderName: 'Sender',
            };

            await mailApiService.sendMailTemplate(
                'test@example.com',
                'Test User',
                mailTemplate,
                { getIds: jest.fn().mockReturnValue(['media-id']) },
                false,
                { user: { id: 'user-id' } },
                'mail-template-id',
                { languageId: 'language-id' },
            );

            expect(clientMock.history.post[0].url).toBe('/_action/mail-template/send');
            expect(clientMock.history.post[0].headers['ct-language-id']).toBe('language-id');
            expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
                contentHtml: '<p>Test</p>',
                contentPlain: 'Test',
                mailTemplateData: { user: { id: 'user-id' } },
                recipients: { 'test@example.com': 'Test User' },
                mediaIds: ['media-id'],
                subject: 'Test Subject',
                senderEmail: 'sender@example.com',
                senderName: 'Sender',
                testMode: false,
                mailTemplateId: 'mail-template-id',
            });
        });
    });

    describe('previewMailTemplate', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            await mailApiService.previewMailTemplate(
                'mail-template-id',
                { user: 'user-id' },
                { resetUrl: 'https://example.com/reset' },
                true,
                true,
                { languageId: 'language-id' },
            );

            expect(clientMock.history.post[0].url).toBe('/_action/mail-template/preview');
            expect(clientMock.history.post[0].headers['ct-language-id']).toBe('language-id');
            expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
                mailTemplateId: 'mail-template-id',
                entities: { user: 'user-id' },
                templateData: { resetUrl: 'https://example.com/reset' },
                includeHeaderFooter: true,
                strictRendering: true,
            });
        });
    });

    describe('getDataAndSendMailTemplate', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            await mailApiService.getDataAndSendMailTemplate(
                {
                    recipients: { 'test@example.com': 'Test User' },
                    mailTemplateId: 'mail-template-id',
                    entities: { user: 'user-id' },
                    templateData: { resetUrl: 'https://example.com/reset' },
                },
                { languageId: 'language-id' },
            );

            expect(clientMock.history.post[0].url).toBe('/_action/mail-template/get-data-and-send');
            expect(clientMock.history.post[0].headers['ct-language-id']).toBe('language-id');
        });
    });

    describe('simulateMailTemplate', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            await mailApiService.simulateMailTemplate(
                { contentHtml: '<p>{{ userRecovery.user.email }}</p>' },
                'user.recovery.request',
                true,
            );

            expect(clientMock.history.post[0].url).toBe('/_action/mail-template/simulate');
            expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
                templateParts: { contentHtml: '<p>{{ userRecovery.user.email }}</p>' },
                eventName: 'user.recovery.request',
                strictRendering: true,
            });
        });
    });

    describe('loadAvailableVariables', () => {
        it('calls the correct endpoint', async () => {
            const { mailApiService, clientMock } = getMailApiService();

            await mailApiService.loadAvailableVariables('user.recovery.request', 'userRecovery');

            expect(clientMock.history.post[0].url).toBe('/_action/mail-template/available-variables');
            expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
                eventName: 'user.recovery.request',
                parentVariablePath: 'userRecovery',
            });
        });
    });
});
