import ApiService from '../api.service';

/**
 * Gateway for the API end point "mail"
 * @class
 * @extends ApiService
 * @sw-package framework
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
                'sw-language-id': apiContext.languageId,
            };
        }

        return super.getBasicHeaders(languageIdHeader);
    }

    sendMailTemplate(
        recipientMail,
        recipient,
        mailTemplate,
        mailTemplateMedia,
        channelId,
        testMode = false,
        documentIds = [],
        templateData = null,
        mailTemplateTypeId = null,
        mailTemplateId = null,
        additionalHeaders = {},
    ) {
        const apiRoute = `/_action/${this.getApiBasePath()}/send`;

        if (!Contena.Feature.isActive('v6.8.0.0')) {
            if (!templateData) {
                templateData = mailTemplate.mailTemplateType.templateData;
            }
        }

        return this.httpClient
            .post(
                apiRoute,
                {
                    contentHtml: mailTemplate.contentHtml ?? mailTemplate.translated?.contentHtml,
                    contentPlain: mailTemplate.contentPlain ?? mailTemplate.translated?.contentPlain,
                    mailTemplateData: templateData,
                    recipients: { [recipientMail]: recipient },
                    channelId: channelId,
                    mediaIds: mailTemplateMedia.getIds(),
                    subject: mailTemplate.subject ?? mailTemplate.translated?.subject,
                    senderMail: mailTemplate.senderMail,
                    senderName: mailTemplate.senderName ?? mailTemplate.translated?.senderName,
                    documentIds,
                    testMode,
                    mailTemplateTypeId,
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     */
    testMailTemplate(
        recipient,
        mailTemplate,
        mailTemplateMedia,
        channelId,
        mailTemplateTypeId,
        mailTemplateId,
        documentIds = [],
    ) {
        return this.sendMailTemplate(
            recipient,
            recipient,
            mailTemplate,
            mailTemplateMedia,
            channelId,
            true,
            documentIds,
            null,
            mailTemplateTypeId,
            mailTemplateId,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     */
    buildRenderPreview(mailTemplateType, mailTemplate) {
        const apiRoute = `/_action/${this.getApiBasePath()}/build`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    mailTemplateType: mailTemplateType,
                    mailTemplate: mailTemplate,
                },
                {
                    headers: this.getBasicHeaders(),
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
        channelId = null,
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
                    channelId,
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

    simulateMailTemplate(templateParts, eventName, channelId = null, strictRendering = true) {
        const apiRoute = `/_action/${this.getApiBasePath()}/simulate`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    templateParts,
                    eventName,
                    channelId,
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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MailApiService;
