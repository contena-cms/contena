import { defineComponent } from 'vue';
import { mount, shallowMount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';
import { dom } from 'src/core/service/util.service';

describe('module/ct-mail-template/page/ct-mail-template-detail', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    async function createFunctionalWrapper({ canSendMail = true } = {}) {
        const mediaAssociations = new Contena.Data.EntityCollection(
            '/mail-template-media',
            'mail_template_media',
            Contena.Context.api,
            new Contena.Data.Criteria(),
            [],
            0,
        );
        const mailTemplate = {
            id: 'template-id',
            description: 'User password recovery',
            systemDefault: true,
            mailTemplateTypeId: 'type-id',
            subject: 'Reset your password, {{ user.firstName }}',
            senderName: 'Contena',
            contentHtml: '<p>{{ userRecovery.resetUrl }}</p>',
            contentPlain: '{{ userRecovery.resetUrl }}',
            translated: {},
            media: mediaAssociations,
            isNew: jest.fn(() => false),
        } as unknown as Entity<'mail_template'>;
        const repository = {
            get: jest.fn(() => Promise.resolve(mailTemplate)),
            save: jest.fn(() => Promise.resolve()),
        };
        const preview = {
            subject: { type: 'success', content: 'Reset your password, Ada' },
            senderName: { type: 'success', content: 'Contena' },
            headerPlain: { type: 'success', content: '' },
            contentPlain: { type: 'success', content: 'https://example.com/reset' },
            footerPlain: { type: 'success', content: '' },
            headerHtml: { type: 'success', content: '' },
            contentHtml: { type: 'success', content: '<p>https://example.com/reset</p>' },
            footerHtml: { type: 'success', content: '' },
        };
        const mailService = {
            simulateMailTemplate: jest.fn(() => Promise.resolve(preview)),
            sendMailTemplate: jest.fn(() => Promise.resolve({ size: 512 })),
            loadAvailableVariables: jest.fn((_eventName: string, parentVariablePath = '') =>
                Promise.resolve(
                    parentVariablePath
                        ? [{ fieldName: 'resetUrl', hasChildren: false }]
                        : [{ fieldName: 'userRecovery', hasChildren: true }],
                ),
            ),
        };
        const businessEventService = {
            getBusinessEvents: jest.fn(() =>
                Promise.resolve([
                    { name: 'user.recovery.request', aware: ['mailAware'], data: {} },
                    { name: 'user.updated', aware: [], data: {} },
                ]),
            ),
        };
        const acl = {
            can: jest.fn(
                (privilege: string) =>
                    privilege === 'mail_templates.editor' || (privilege === 'api_send_email' && canSendMail),
            ),
        };

        const wrapper = shallowMount(await wrapTestComponent('ct-mail-template-detail', { sync: true }), {
            global: {
                provide: {
                    [routeLocationKey as symbol]: { params: { id: mailTemplate.id } },
                    [routerKey as symbol]: { push: jest.fn(), replace: jest.fn() },
                    repositoryFactory: {
                        create: jest.fn(() => repository),
                    },
                    acl,
                    mailService,
                    businessEventService,
                },
            },
        });
        await flushPromises();

        return { wrapper, mailTemplate, preview, mailService, businessEventService };
    }

    it('allows an editor to update a system-default template', async () => {
        const mediaAssociations = new Contena.Data.EntityCollection(
            '/mail-template-media',
            'mail_template_media',
            Contena.Context.api,
            new Contena.Data.Criteria(),
            [],
            0,
        );
        const mailTemplate = {
            id: 'template-id',
            description: 'User password recovery',
            systemDefault: true,
            media: mediaAssociations,
            isNew: jest.fn(() => false),
        } as unknown as Entity<'mail_template'>;
        const repository = {
            get: jest.fn(() => Promise.resolve(mailTemplate)),
            save: jest.fn(() => Promise.resolve()),
        };
        const mediaItem = { id: 'media-id', fileName: 'terms' } as Entity<'media'>;
        const mediaRepository = {
            get: jest.fn(() => Promise.resolve(mediaItem)),
        };
        const mailTemplateMedia = {
            id: 'association-id',
        } as Entity<'mail_template_media'>;
        const mailTemplateMediaRepository = {
            create: jest.fn(() => mailTemplateMedia),
        };
        const acl = {
            can: jest.fn((privilege: string) => privilege === 'mail_templates.editor'),
        };

        const wrapper = mount(await wrapTestComponent('ct-mail-template-detail', { sync: true }), {
            global: {
                provide: {
                    [routeLocationKey as symbol]: { params: { id: mailTemplate.id } },
                    [routerKey as symbol]: { push: jest.fn(), replace: jest.fn() },
                    repositoryFactory: {
                        create: jest.fn((entityName: string) =>
                            entityName === 'mail_template_media'
                                ? mailTemplateMediaRepository
                                : entityName === 'media'
                                  ? mediaRepository
                                  : repository,
                        ),
                    },
                    acl,
                    mailService: {
                        simulateMailTemplate: jest.fn(),
                        sendMailTemplate: jest.fn(),
                        loadAvailableVariables: jest.fn(() => Promise.resolve([])),
                    },
                    businessEventService: {
                        getBusinessEvents: jest.fn(() => Promise.resolve([])),
                    },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({
                        template:
                            '<div><slot name="smart-bar-actions" /><slot name="language-switch" /><slot name="content" /></div>',
                    }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': defineComponent({ template: '<section><slot /></section>' }),
                    'ct-language-info': true,
                    'ct-code-editor': true,
                    'ct-container': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-upload-listener': defineComponent({
                        name: 'ct-upload-listener',
                        props: ['uploadTag'],
                        template: '<div class="upload-listener" />',
                    }),
                    'ct-media-upload-v2': defineComponent({
                        name: 'ct-media-upload-v2',
                        props: [
                            'uploadTag',
                            'defaultFolder',
                            'variant',
                            'fileAccept',
                        ],
                        emits: [
                            'media-drop',
                            'media-upload-sidebar-open',
                        ],
                        template: '<div class="media-upload" />',
                    }),
                    'ct-data-grid': true,
                    'ct-media-preview': true,
                    'ct-sidebar': defineComponent({ template: '<aside><slot /></aside>' }),
                    'ct-sidebar-media-item': defineComponent({
                        name: 'ct-sidebar-media-item',
                        setup(_, { expose }) {
                            const openContent = jest.fn();
                            expose({ openContent });
                            return {};
                        },
                        template: '<div class="media-sidebar"><slot name="context-menu-items" /></div>',
                    }),
                    'ct-button-process': true,
                    'mt-button': true,
                    'ct-language-switch': true,
                    'ct-skeleton': true,
                    'ct-entity-single-select': true,
                    'mt-switch': true,
                    'mt-text-field': true,
                    'mt-textarea': true,
                },
            },
        });
        await flushPromises();

        const component = wrapper.vm as unknown as {
            canEdit: boolean;
            attachedMedia: Entity<'media'>[];
            onAddMedia: (mediaItem: Entity<'media'>) => boolean;
            onRemoveMedia: (mediaId: string) => void;
            successfulUpload: (event: { targetId: string }) => Promise<void>;
            onMediaDrop: (mediaItem: Entity<'media'>) => Promise<void>;
            openMediaSidebar: () => void;
            onSave: () => Promise<void>;
        };
        expect(component.canEdit).toBe(true);
        expect(wrapper.findComponent({ name: 'ct-upload-listener' }).props('uploadTag')).toBe(mailTemplate.id);
        expect(wrapper.findComponent({ name: 'ct-media-upload-v2' }).props()).toEqual(
            expect.objectContaining({
                uploadTag: mailTemplate.id,
                defaultFolder: 'mail_template',
                variant: 'regular',
            }),
        );

        mailTemplate.subject = 'Updated subject';
        await component.onSave();

        expect(repository.save).toHaveBeenCalledTimes(1);
        const savedTemplate = (repository.save.mock.calls as unknown as Array<[Entity<'mail_template'>]>)[0]?.[0];
        expect(savedTemplate?.id).toBe(mailTemplate.id);

        expect(component.onAddMedia(mediaItem)).toBe(true);
        expect(component.onAddMedia(mediaItem)).toBe(false);
        expect(component.attachedMedia).toEqual([mediaItem]);

        component.onRemoveMedia(mediaItem.id);
        expect(component.attachedMedia).toEqual([]);

        await component.successfulUpload({ targetId: mediaItem.id });
        expect(mediaRepository.get).toHaveBeenCalledWith(mediaItem.id);
        expect(component.attachedMedia).toEqual([mediaItem]);
    });

    it('loads mail-aware trigger events and variable schemas from the backend', async () => {
        const { wrapper, mailService, businessEventService } = await createFunctionalWrapper();
        const component = wrapper.vm as unknown as {
            triggerEvents: Array<{ name: string; label: string }>;
            loadedAvailableVariables: Array<{ id: string }>;
            onTriggerEventChange: (eventName: string) => void;
            onGetTreeItems: (parent: string) => void;
        };

        expect(businessEventService.getBusinessEvents).toHaveBeenCalledTimes(1);
        expect(component.triggerEvents.map((event) => event.name)).toEqual(['user.recovery.request']);
        expect(component.triggerEvents[0]?.label).toContain(' / ');

        component.onTriggerEventChange('user.recovery.request');
        expect(component.loadedAvailableVariables).toEqual([]);
        await flushPromises();
        expect(component.loadedAvailableVariables).toEqual([
            expect.objectContaining({ id: 'userRecovery' }),
        ]);

        component.onGetTreeItems('userRecovery');
        await flushPromises();
        expect(mailService.loadAvailableVariables).toHaveBeenLastCalledWith('user.recovery.request', 'userRecovery');
        expect(component.loadedAvailableVariables).toEqual(
            expect.arrayContaining([expect.objectContaining({ id: 'userRecovery.resetUrl' })]),
        );
    });

    it('simulates the original template content and identifies preview errors', async () => {
        const { wrapper, mailTemplate, preview, mailService } = await createFunctionalWrapper();
        const component = wrapper.vm as unknown as {
            mailPreview: typeof preview | null;
            onTriggerEventChange: (eventName: string) => void;
            onClickShowPreview: () => Promise<void>;
            onCancelShowPreview: () => void;
            hasPreviewErrors: (mailPreview: typeof preview) => boolean;
        };

        component.onTriggerEventChange('user.recovery.request');
        await component.onClickShowPreview();

        expect(mailService.simulateMailTemplate).toHaveBeenCalledWith(
            expect.objectContaining({
                subject: mailTemplate.subject,
                contentHtml: mailTemplate.contentHtml,
                contentPlain: mailTemplate.contentPlain,
                headerHtml: '',
                footerHtml: '',
            }),
            'user.recovery.request',
        );
        expect(component.mailPreview).toEqual(preview);
        expect(component.hasPreviewErrors(preview)).toBe(false);
        expect(
            component.hasPreviewErrors({
                ...preview,
                footerHtml: { type: 'error', content: '' },
            }),
        ).toBe(true);

        component.onCancelShowPreview();
        expect(component.mailPreview).toBeNull();
    });

    it('sends the rendered test mail and copies variables', async () => {
        const { wrapper, mailTemplate, preview, mailService } = await createFunctionalWrapper();
        const component = wrapper.vm as unknown as {
            testerMail: string;
            isSendButtonDisabled: boolean;
            onTriggerEventChange: (eventName: string) => void;
            onClickTestMailTemplate: () => Promise<void>;
            onCopyVariable: (variable: string) => Promise<void>;
        };
        const clipboardSpy = jest.spyOn(dom, 'copyStringToClipboard').mockResolvedValue();

        component.testerMail = 'admin@example.com';
        component.onTriggerEventChange('user.recovery.request');
        expect(component.isSendButtonDisabled).toBe(false);

        await component.onClickTestMailTemplate();
        expect(mailService.sendMailTemplate).toHaveBeenCalledWith(
            'admin@example.com',
            'admin@example.com',
            {
                subject: preview.subject.content,
                senderName: preview.senderName.content,
                contentHtml: preview.contentHtml.content,
                contentPlain: preview.contentPlain.content,
            },
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            expect.objectContaining({ getIds: expect.any(Function) }),
            true,
            {},
            mailTemplate.id,
        );

        await component.onCopyVariable('userRecovery.resetUrl');
        expect(clipboardSpy).toHaveBeenCalledWith('userRecovery.resetUrl');
    });

    it('disables test sending without the API send-email privilege', async () => {
        const { wrapper } = await createFunctionalWrapper({ canSendMail: false });
        const component = wrapper.vm as unknown as {
            testerMail: string;
            isSendButtonDisabled: boolean;
            onTriggerEventChange: (eventName: string) => void;
        };

        component.testerMail = 'admin@example.com';
        component.onTriggerEventChange('user.recovery.request');

        expect(component.isSendButtonDisabled).toBe(true);
    });
});
