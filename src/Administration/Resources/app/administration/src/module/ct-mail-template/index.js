import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-mail-template-index', () => import('./page/ct-mail-template-index'));
Contena.Component.register('ct-mail-template-list', () => import('./component/ct-mail-template-list'));
Contena.Component.register('ct-mail-header-footer-list', () => import('./component/ct-mail-header-footer-list'));
Contena.Component.register('ct-mail-template-preview-modal', () => import('./component/ct-mail-template-preview-modal'));
Contena.Component.register('ct-mail-template-create', () => import('./page/ct-mail-template-create'));
Contena.Component.register('ct-mail-template-view-templates', () => import('./view/ct-mail-template-view-templates'));
Contena.Component.register(
    'ct-mail-template-view-header-footer',
    () => import('./view/ct-mail-template-view-header-footer'),
);
Contena.Component.register('ct-mail-header-footer-create', () => import('./page/ct-mail-header-footer-create'));
Contena.Component.register('ct-mail-template-detail', () => import('./page/ct-mail-template-detail'));
Contena.Component.register('ct-mail-header-footer-detail', () => import('./page/ct-mail-header-footer-detail'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-mail-template', {
    type: 'core',
    name: 'mail-template',
    title: 'ct-mail-template.general.mainMenuItemGeneral',
    description: 'ct-mail-template.general.description',
    color: '#9AA8B5',
    icon: 'regular-envelope',
    entity: 'mail_template',

    routes: {
        index: {
            component: 'ct-mail-template-index',
            path: 'index',
            redirect: { name: 'ct.mail.template.index.templates' },
            children: {
                templates: {
                    component: 'ct-mail-template-view-templates',
                    path: 'templates',
                    meta: { parentPath: 'ct.settings.index', privilege: 'mail_templates.viewer' },
                },
                header_footer: {
                    component: 'ct-mail-template-view-header-footer',
                    path: 'header-footer',
                    meta: { parentPath: 'ct.settings.index', privilege: 'mail_templates.viewer' },
                },
            },
            meta: { parentPath: 'ct.settings.index', privilege: 'mail_templates.viewer' },
        },
        create: {
            component: 'ct-mail-template-create',
            path: 'create',
            meta: { parentPath: 'ct.mail.template.index', privilege: 'mail_templates.creator' },
        },
        detail: {
            component: 'ct-mail-template-detail',
            path: 'detail/:id',
            meta: { parentPath: 'ct.mail.template.index', privilege: 'mail_templates.viewer' },
        },
        create_head_foot: {
            component: 'ct-mail-header-footer-create',
            path: 'create-head-foot',
            meta: { parentPath: 'ct.mail.template.index', privilege: 'mail_templates.creator' },
        },
        detail_head_foot: {
            component: 'ct-mail-header-footer-detail',
            path: 'detail-header-footer/:id',
            meta: { parentPath: 'ct.mail.template.index', privilege: 'mail_templates.viewer' },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'ct.mail.template.index',
        icon: 'regular-envelope',
        privilege: 'mail_templates.viewer',
    },
});
