import ApiService from '../api.service';

/**
 * Gateway for the API endpoint "mail-template".
 *
 * @class
 * @extends ApiService
 */
class MailApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mail-template') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mailService';
    }

    getBasicHeaders(additionalHeaders) {
        const apiContext = {
            ...Contena.Context.api,
            ...additionalHeaders,
        };

        let languageIdHeader = {};

        if (self?.Contena && typeof apiContext.languageId === 'string') {
            languageIdHeader = {
                'ct-language-id': apiContext.languageId,
            };
        }

        return super.getBasicHeaders(languageIdHeader);
    }

    sendMailTemplate(
        recipientMail,
        recipient,
        mailTemplate,
        mailTemplateMedia,
        testMode = false,
        templateData = {},
        mailTemplateId = null,
        additionalHeaders = {},
    ) {
        const apiRoute = `/_action/${this.getApiBasePath()}/send`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    contentHtml: mailTemplate.contentHtml ?? mailTemplate.translated?.contentHtml,
                    contentPlain: mailTemplate.contentPlain ?? mailTemplate.translated?.contentPlain,
                    mailTemplateData: templateData,
                    recipients: { [recipientMail]: recipient },
                    mediaIds: mailTemplateMedia.getIds(),
                    subject: mailTemplate.subject ?? mailTemplate.translated?.subject,
                    senderEmail: mailTemplate.senderEmail ?? mailTemplate.senderMail,
                    senderName: mailTemplate.senderName ?? mailTemplate.translated?.senderName,
                    testMode,
                    mailTemplateId,
                },
                {
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    previewMailTemplate(
        mailTemplateId,
        entities = {},
        templateData = {},
        includeHeaderFooter = false,
        strictRendering = true,
        additionalHeaders = {},
    ) {
        const apiRoute = `/_action/${this.getApiBasePath()}/preview`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    mailTemplateId,
                    entities,
                    templateData,
                    includeHeaderFooter,
                    strictRendering,
                },
                {
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getDataAndSendMailTemplate(payload, additionalHeaders = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/get-data-and-send`;

        return this.httpClient
            .post(apiRoute, payload, {
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    simulateMailTemplate(templateParts, eventName, strictRendering = true) {
        const apiRoute = `/_action/${this.getApiBasePath()}/simulate`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    templateParts,
                    eventName,
                    strictRendering,
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    loadAvailableVariables(eventName, parentVariablePath = '') {
        const apiRoute = `/_action/${this.getApiBasePath()}/available-variables`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    eventName,
                    parentVariablePath,
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default MailApiService;
