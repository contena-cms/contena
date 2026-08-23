import defaultSearchConfiguration from './default-search-configuration';
import experienceStudioCreateRoute from './page/ct-experience-studio-create';

import './acl';
import './store/experience-studio-editor.store';
import './store/experience-studio-element-type.store';
import './store/experience-studio-style-option.store';

/**
 * @private
 */
Contena.Component.register('ct-experience-studio-list', () => import('./page/ct-experience-studio-list'));

/**
 * @private
 */
Contena.Component.register('ct-experience-studio-detail', () => import('./page/ct-experience-studio-detail'));

/**
 * @private
 */
Contena.Component.register('ct-experience-studio-toolbar', () => import('./component/ct-experience-studio-toolbar'));

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-sidebar-tree',
    () => import('./component/ct-experience-studio-sidebar-tree'),
);

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-sidebar-tree-node',
    () => import('./component/ct-experience-studio-sidebar-tree-node'),
);

/**
 * @private
 */
Contena.Component.register('ct-experience-studio-preview', () => import('./component/ct-experience-studio-preview'));

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-preview-node',
    () => import('./component/ct-experience-studio-preview-node'),
);

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-element-settings',
    () => import('./component/ct-experience-studio-element-settings'),
);

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-settings-fields',
    () => import('./component/ct-experience-studio-settings-fields'),
);

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-box-spacing-field',
    () => import('./component/ct-experience-studio-box-spacing-field'),
);

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-media-collection-field',
    () => import('./component/ct-experience-studio-media-collection-field'),
);

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-element-picker',
    () => import('./component/ct-experience-studio-element-picker'),
);

/**
 * @private
 */
Contena.Component.register(
    'ct-experience-studio-create-wizard',
    () => import('./component/ct-experience-studio-create-wizard'),
);

/**
 * @private
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-experience-studio', {
    type: 'core',
    name: 'experience-studio',
    title: 'ct-experience-studio.general.mainMenuItemGeneral',
    description: 'ct-experience-studio.general.descriptionTextModule',
    color: 'var(--color-pink-500)',
    icon: 'regular-layout',
    favicon: 'icon-module-content.png',
    entity: 'content_layout',

    routes: {
        index: {
            component: 'ct-experience-studio-list',
            path: 'index',
            meta: {
                privilege: 'experience_studio.viewer',
            },
        },
        detail: {
            component: 'ct-experience-studio-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'ct.experience.studio.index',
                privilege: 'experience_studio.viewer',
            },
        },
        create: {
            component: 'ct-experience-studio-detail',
            path: 'create/:id?',
            beforeEnter: experienceStudioCreateRoute.beforeEnter,
            meta: {
                parentPath: 'ct.experience.studio.index',
                privilege: 'experience_studio.creator',
            },
        },
    },

    navigation: [
        {
            id: 'ct-experience-studio',
            label: 'ct-experience-studio.general.mainMenuItemGeneral',
            color: 'var(--color-pink-500)',
            path: 'ct.experience.studio.index',
            icon: 'regular-layout',
            position: 5,
            parent: 'ct-content',
            privilege: 'experience_studio.viewer',
        },
    ],

    defaultSearchConfiguration,
});
