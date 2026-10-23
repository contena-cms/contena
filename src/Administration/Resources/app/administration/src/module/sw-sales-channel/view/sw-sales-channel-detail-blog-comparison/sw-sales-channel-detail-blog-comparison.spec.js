/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-sales-channel-detail-blog-comparison', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'sw-code-editor': {
                        template: '<div class="sw-code-editor"></div>',
                        props: ['disabled'],
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-button-process': {
                        template: '<button class="sw-button-process" :disabled="disabled"><slot></slot></button>',
                        props: ['disabled'],
                    },
                    'sw-sales-channel-detail-blog-comparison-preview': true,
                },
                provide: {
                    channelService: {},
                    blogExportService: {},
                    entityMappingService: {},
                    repositoryFactory: {},
                },
            },
            props: {
                blogExport: {},
                channel: {},
                ...props,
            },
        },
    );
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-blog-comparison', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have codeEditors disabled when the admin_user has no privileges', async () => {
        const wrapper = await createWrapper();

        const codeEditors = wrapper.findAllComponents('.sw-code-editor');

        expect(codeEditors.length).toBeGreaterThan(0);
        codeEditors.forEach((codeEditor) => {
            expect(codeEditor.props('disabled')).toBe(true);
        });
    });

    it('should have codeEditors enabled when the admin_user has privileges', async () => {
        global.activeAclRoles = ['channel.editor'];

        const wrapper = await createWrapper();

        const codeEditors = wrapper.findAllComponents('.sw-code-editor');

        expect(codeEditors.length).toBeGreaterThan(0);
        codeEditors.forEach((codeEditor) => {
            expect(codeEditor.attributes().disabled).toBeUndefined();
        });
    });

    it('should have template actions disabled when the admin_user has no privileges', async () => {
        const wrapper = await createWrapper({
            blogExport: { bodyTemplate: 'blog' },
        });

        const actions = wrapper.findAll('.sw-button-process');

        expect(actions).toHaveLength(2);
        actions.forEach((action) => {
            expect(action.attributes('disabled')).toBeDefined();
        });
    });

    it('should have template actions enabled when the admin_user has privileges', async () => {
        global.activeAclRoles = ['channel.editor'];

        const wrapper = await createWrapper({
            blogExport: { bodyTemplate: 'blog' },
        });

        const actions = wrapper.findAll('.sw-button-process');

        expect(actions).toHaveLength(2);
        actions.forEach((action) => {
            expect(action.attributes('disabled')).toBeUndefined();
        });
    });
});
