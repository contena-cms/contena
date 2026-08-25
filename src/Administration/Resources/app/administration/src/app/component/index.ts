/**
 * @private
 */
export default () => {
    /* eslint-disable ct-deprecation-rules/private-feature-declarations */
    Contena.Component.register('ct-vnode-renderer', () => import('src/app/component/utils/ct-vnode-renderer/index'));
    Contena.Component.register('ct-upload-listener', () => import('src/app/component/utils/ct-upload-listener/index'));
    Contena.Component.register('ct-time-ago', () => import('src/app/component/utils/ct-time-ago/index'));
    Contena.Component.register('ct-text-preview', () => import('src/app/component/utils/ct-text-preview/index'));
    Contena.Component.register('ct-skeleton-bar', () => import('src/app/component/utils/ct-skeleton-bar/index'));
    Contena.Component.register('ct-skeleton', () => import('src/app/component/utils/ct-skeleton/index'));
    Contena.Component.register('ct-provide', () => import('src/app/component/utils/ct-provide/index'));
    Contena.Component.register('ct-notifications', () => import('src/app/component/utils/ct-notifications/index'));
    Contena.Component.register(
        'ct-notification-center-item',
        () => import('src/app/component/utils/ct-notification-center-item/index'),
    );
    Contena.Component.register(
        'ct-notification-center',
        () => import('src/app/component/utils/ct-notification-center/index'),
    );
    Contena.Component.register('ct-loader', () => import('src/app/component/utils/ct-loader/index'));
    Contena.Component.register('ct-internal-link', () => import('src/app/component/utils/ct-internal-link/index'));
    Contena.Component.register('ct-inherit-wrapper', () => import('src/app/component/utils/ct-inherit-wrapper/index'));
    Contena.Component.register('ct-ignore-class', () => import('src/app/component/utils/ct-ignore-class/index'));
    Contena.Component.register('ct-external-link', () => import('src/app/component/utils/ct-external-link/index'));
    Contena.Component.register('ct-error-boundary', () => import('src/app/component/utils/ct-error-boundary/index'));
    Contena.Component.register(
        'ct-duplicated-media-v2',
        () => import('src/app/component/utils/ct-duplicated-media-v2/index'),
    );
    Contena.Component.register('ct-color-badge', () => import('src/app/component/utils/ct-color-badge/index'));
    Contena.Component.register('ct-upload-status', () => import('src/app/component/utils/ct-upload-status'));
    Contena.Component.register('ct-tree-item', () => import('src/app/component/tree/ct-tree-item/index'));
    Contena.Component.register('ct-tree-input-field', () => import('src/app/component/tree/ct-tree-input-field/index'));
    Contena.Component.register('ct-tree', () => import('src/app/component/tree/ct-tree/index'));
    Contena.Component.register('ct-skip-link', () => import('src/app/component/structure/ct-skip-link/index'));
    Contena.Component.register(
        'ct-search-more-results',
        () => import('src/app/component/structure/ct-search-more-results/index'),
    );
    Contena.Component.register('ct-search-bar-item', () => import('src/app/component/structure/ct-search-bar-item/index'));
    Contena.Component.register('ct-search-bar', () => import('src/app/component/structure/ct-search-bar/index'));
    Contena.Component.register('ct-page', () => import('src/app/component/structure/ct-page/index'));
    Contena.Component.register('ct-language-switch', () => import('src/app/component/structure/ct-language-switch/index'));
    Contena.Component.register('ct-channel-switch', () => import('src/app/component/structure/ct-channel-switch/index'));
    Contena.Component.register('ct-language-info', () => import('src/app/component/structure/ct-language-info/index'));
    Contena.Component.register(
        'ct-inheritance-warning',
        () => import('src/app/component/structure/ct-inheritance-warning/index'),
    );
    Contena.Component.register('ct-error', () => import('src/app/component/structure/ct-error/index'));
    Contena.Component.register(
        'ct-discard-changes-modal',
        () => import('src/app/component/structure/ct-discard-changes-modal/index'),
    );
    Contena.Component.register('ct-desktop', () => import('src/app/component/structure/ct-desktop/index'));
    Contena.Component.register('ct-card-view', () => import('src/app/component/structure/ct-card-view/index'));
    Contena.Component.register(
        'ct-block-parent',
        () => import('src/app/component/structure/ct-block-override/ct-block-parent/index'),
    );
    Contena.Component.register('ct-block', () => import('src/app/component/structure/ct-block-override/ct-block/index'));
    Contena.Component.register('ct-admin-menu-item', () => import('src/app/component/structure/ct-admin-menu-item/index'));
    Contena.Component.register('ct-admin-menu', () => import('src/app/component/structure/ct-admin-menu/index'));
    Contena.Component.register('ct-admin', () => import('src/app/component/structure/ct-admin/index'));
    Contena.Component.register(
        'ct-sidebar-navigation-item',
        () => import('src/app/component/sidebar/ct-sidebar-navigation-item/index'),
    );
    Contena.Component.register('ct-sidebar-item', () => import('src/app/component/sidebar/ct-sidebar-item/index'));
    Contena.Component.register('ct-sidebar', () => import('src/app/component/sidebar/ct-sidebar/index'));
    Contena.Component.register(
        'ct-search-preferences-modal',
        () => import('src/app/component/modal/ct-search-preferences-modal/index'),
    );
    Contena.Component.register('ct-confirm-modal', () => import('src/app/component/modal/ct-confirm-modal/index'));
    Contena.Component.register('mt-text-editor', () => import('src/app/component/meteor-wrapper/mt-text-editor/index'));
    Contena.Component.register(
        'ct-text-editor-toolbar-button-link',
        () => import('src/app/component/meteor-wrapper/mt-text-editor/ct-text-editor-toolbar-button-link/index'),
    );
    Contena.Component.register('mt-tabs', () => import('src/app/component/meteor-wrapper/mt-tabs/index'));
    Contena.Component.register('mt-datepicker', () => import('src/app/component/meteor-wrapper/mt-datepicker/index'));
    Contena.Component.register('mt-card', () => import('src/app/component/meteor-wrapper/mt-card/index'));
    Contena.Component.register('ct-meteor-page', () => import('src/app/component/meteor/ct-meteor-page/index'));
    Contena.Component.register('ct-meteor-navigation', () => import('src/app/component/meteor/ct-meteor-navigation/index'));
    Contena.Component.register('ct-meteor-card', () => import('src/app/component/meteor/ct-meteor-card/index'));
    Contena.Component.register('ct-pagination', () => import('src/app/component/grid/ct-pagination/index'));
    Contena.Component.register('ct-grid-row', () => import('src/app/component/grid/ct-grid-row/index'));
    Contena.Component.register('ct-grid-column', () => import('src/app/component/grid/ct-grid-column/index'));
    Contena.Component.register('ct-grid', () => import('src/app/component/grid/ct-grid/index'));
    Contena.Component.register('ct-tagged-field', () => import('src/app/component/form/ct-tagged-field/index'));
    Contena.Component.register('ct-gtc-checkbox', () => import('src/app/component/form/ct-gtc-checkbox/index'));
    Contena.Component.register(
        'ct-form-field-renderer',
        () => import('src/app/component/form/ct-form-field-renderer/index'),
    );
    Contena.Component.register('ct-file-input', () => import('src/app/component/form/ct-file-input/index'));
    Contena.Component.register('ct-field-copyable', () => import('src/app/component/form/ct-field-copyable/index'));
    Contena.Component.register(
        'ct-custom-field-set-renderer',
        () => import('src/app/component/form/ct-custom-field-set-renderer/index'),
    );
    Contena.Component.register('ct-confirm-field', () => import('src/app/component/form/ct-confirm-field/index'));
    Contena.Component.register(
        'ct-entity-single-select',
        () => import('src/app/component/form/select/entity/ct-entity-single-select/index'),
    );
    Contena.Component.register(
        'ct-entity-multi-select',
        () => import('src/app/component/form/select/entity/ct-entity-multi-select/index'),
    );
    Contena.Component.register(
        'ct-entity-advanced-selection-modal',
        () => import('src/app/component/form/select/entity/ct-entity-advanced-selection-modal/index'),
    );
    Contena.Component.register(
        'ct-select-selection-list',
        () => import('src/app/component/form/select/base/ct-select-selection-list/index'),
    );
    Contena.Component.register(
        'ct-select-result-list',
        () => import('src/app/component/form/select/base/ct-select-result-list/index'),
    );
    Contena.Component.register(
        'ct-select-result',
        () => import('src/app/component/form/select/base/ct-select-result/index'),
    );
    Contena.Component.register('ct-select-base', () => import('src/app/component/form/select/base/ct-select-base/index'));
    Contena.Component.register(
        'ct-multi-tag-select',
        () => import('src/app/component/form/select/base/ct-multi-tag-select/index'),
    );
    Contena.Component.register(
        'ct-multi-tag-ip-select',
        () => import('src/app/component/form/select/base/ct-multi-tag-ip-select'),
    );
    Contena.Component.register('ct-field-error', () => import('src/app/component/form/field-base/ct-field-error/index'));
    Contena.Component.register(
        'ct-contextual-field',
        () => import('src/app/component/form/field-base/ct-contextual-field/index'),
    );
    Contena.Component.register('ct-block-field', () => import('src/app/component/form/field-base/ct-block-field/index'));
    Contena.Component.register('ct-base-field', () => import('src/app/component/form/field-base/ct-base-field/index'));
    Contena.Component.register(
        'ct-data-dictionary-select',
        () => import('src/app/component/form/ct-data-dictionary-select/index'),
    );
    Contena.Component.register(
        'ct-sidebar-filter-panel',
        () => import('src/app/component/filter/ct-sidebar-filter-panel/index'),
    );
    Contena.Component.register('ct-range-filter', () => import('src/app/component/filter/ct-range-filter/index'));
    Contena.Component.register('ct-number-filter', () => import('src/app/component/filter/ct-number-filter/index'));
    Contena.Component.register(
        'ct-multi-select-filter',
        () => import('src/app/component/filter/ct-multi-select-filter/index'),
    );
    Contena.Component.register('ct-filter-panel', () => import('src/app/component/filter/ct-filter-panel/index'));
    Contena.Component.register('ct-existence-filter', () => import('src/app/component/filter/ct-existence-filter/index'));
    Contena.Component.register('ct-date-filter', () => import('src/app/component/filter/ct-date-filter/index'));
    Contena.Component.register('ct-boolean-filter', () => import('src/app/component/filter/ct-boolean-filter/index'));
    Contena.Component.register('ct-base-filter', () => import('src/app/component/filter/ct-base-filter/index'));
    Contena.Component.register('ct-bulk-edit-modal', () => import('src/app/component/entity/ct-bulk-edit-modal/index'));
    Contena.Component.register(
        'ct-category-tree-field',
        () => import('src/app/component/entity/ct-category-tree-field/index'),
    );
    Contena.Component.register(
        'ct-data-grid-skeleton',
        () => import('src/app/component/data-grid/ct-data-grid-skeleton/index'),
    );
    Contena.Component.register(
        'ct-data-grid-settings',
        () => import('src/app/component/data-grid/ct-data-grid-settings/index'),
    );
    Contena.Component.register(
        'ct-data-grid-inline-edit',
        () => import('src/app/component/data-grid/ct-data-grid-inline-edit/index'),
    );
    Contena.Component.register(
        'ct-data-grid-column-boolean',
        () => import('src/app/component/data-grid/ct-data-grid-column-boolean/index'),
    );
    Contena.Component.register('ct-data-grid', () => import('src/app/component/data-grid/ct-data-grid/index'));
    Contena.Component.register(
        'ct-context-menu-item',
        () => import('src/app/component/context-menu/ct-context-menu-item/index'),
    );
    Contena.Component.register(
        'ct-context-menu-divider',
        () => import('src/app/component/context-menu/ct-context-menu-divider/index'),
    );
    Contena.Component.register('ct-context-menu', () => import('src/app/component/context-menu/ct-context-menu/index'));
    Contena.Component.register('ct-context-button', () => import('src/app/component/context-menu/ct-context-button/index'));
    Contena.Component.register('ct-version', () => import('src/app/component/base/ct-version/index'));
    Contena.Component.register(
        'ct-simple-search-field',
        () => import('src/app/component/base/ct-simple-search-field/index'),
    );
    Contena.Component.register('ct-rating-stars', () => import('src/app/component/base/ct-rating-stars/index'));
    Contena.Component.register('ct-modal', () => import('src/app/component/base/ct-modal/index'));
    Contena.Component.register('ct-label', () => import('src/app/component/base/ct-label/index'));
    Contena.Component.register('ct-inheritance-switch', () => import('src/app/component/base/ct-inheritance-switch/index'));
    Contena.Component.register('ct-highlight-text', () => import('src/app/component/base/ct-highlight-text/index'));
    Contena.Component.register('ct-help-text', () => import('src/app/component/base/ct-help-text/index'));
    Contena.Component.register('ct-error-summary', () => import('src/app/component/base/ct-error-summary/index'));
    Contena.Component.register('ct-container', () => import('src/app/component/base/ct-container/index'));
    Contena.Component.register('ct-collapse', () => import('src/app/component/base/ct-collapse/index'));
    Contena.Component.register('ct-card-section', () => import('src/app/component/base/ct-card-section/index'));
    Contena.Component.register('ct-card-filter', () => import('src/app/component/base/ct-card-filter/index'));
    Contena.Component.register('ct-button-process', () => import('src/app/component/base/ct-button-process/index'));
    Contena.Component.register('ct-button-group', () => import('src/app/component/base/ct-button-group/index'));
    Contena.Component.register('ct-help-center-v2', () => import('src/app/component/utils/ct-help-center'));
    Contena.Component.register('ct-image-slider', () => import('src/app/component/media/ct-image-slider'));
    Contena.Component.register(
        'ct-media-add-thumbnail-form',
        () => import('src/app/component/media/ct-media-add-thumbnail-form'),
    );
    Contena.Component.register('ct-media-base-item', () => import('src/app/component/media/ct-media-base-item'));
    Contena.Component.extend(
        'ct-media-compact-upload-v2',
        'ct-media-upload-v2',
        () => import('src/app/component/media/ct-media-compact-upload-v2'),
    );
    Contena.Component.register('ct-media-entity-mapper', () => import('src/app/component/media/ct-media-entity-mapper'));
    Contena.Component.register('ct-media-field', () => import('src/app/component/media/ct-media-field'));
    Contena.Component.register('ct-media-folder-content', () => import('src/app/component/media/ct-media-folder-content'));
    Contena.Component.register('ct-media-folder-item', () => import('src/app/component/media/ct-media-folder-item'));
    Contena.Component.register(
        'ct-media-list-selection-item-v2',
        () => import('src/app/component/media/ct-media-list-selection-item-v2'),
    );
    Contena.Component.register(
        'ct-media-list-selection-v2',
        () => import('src/app/component/media/ct-media-list-selection-v2'),
    );
    Contena.Component.register('ct-media-media-item', () => import('src/app/component/media/ct-media-media-item'));
    Contena.Component.register('ct-media-modal-delete', () => import('src/app/component/media/ct-media-modal-delete'));
    Contena.Component.register(
        'ct-media-modal-folder-dissolve',
        () => import('src/app/component/media/ct-media-modal-folder-dissolve'),
    );
    Contena.Component.register(
        'ct-media-modal-folder-settings',
        () => import('src/app/component/media/ct-media-modal-folder-settings'),
    );
    Contena.Component.register('ct-media-modal-move', () => import('src/app/component/media/ct-media-modal-move'));
    Contena.Component.register('ct-media-modal-replace', () => import('src/app/component/media/ct-media-modal-replace'));
    Contena.Component.register('ct-media-preview-v2', () => import('src/app/component/media/ct-media-preview-v2'));
    Contena.Component.extend(
        'ct-media-replace',
        'ct-media-upload-v2',
        () => import('src/app/component/media/ct-media-replace'),
    );
    Contena.Component.register('ct-media-upload-v2', () => import('src/app/component/media/ct-media-upload-v2'));
    Contena.Component.register('ct-media-url-form', () => import('src/app/component/media/ct-media-url-form'));
    Contena.Component.register('ct-sidebar-media-item', () => import('src/app/component/media/ct-sidebar-media-item'));
    Contena.Component.register('ct-extension-icon', () => import('src/app/component/extension/ct-extension-icon'));
    Contena.Component.register('ct-ai-copilot-badge', () => import('src/app/component/feedback/ct-ai-copilot-badge'));
    Contena.Component.register('ct-ai-copilot-warning', () => import('src/app/component/feedback/ct-ai-copilot-warning'));
    Contena.Component.register('ct-string-filter', () => import('src/app/component/filter/ct-string-filter'));
    Contena.Component.register(
        'ct-media-modal-renderer',
        () => import('src/app/component/structure/ct-media-modal-renderer/index'),
    );
    Contena.Component.extend('ct-sidebar-collapse', 'ct-collapse', () => import('./sidebar/ct-sidebar-collapse/index'));
    Contena.Component.extend(
        'ct-entity-tag-select',
        'ct-entity-multi-select',
        () => import('./form/select/entity/ct-entity-tag-select/index'),
    );
    Contena.Component.extend(
        'ct-entity-advanced-selection-modal-grid',
        'ct-entity-listing',
        () => import('./form/select/entity/ct-entity-advanced-selection-modal-grid/index'),
    );
    Contena.Component.extend('ct-one-to-many-grid', 'ct-data-grid', () => import('./entity/ct-one-to-many-grid/index'));
    Contena.Component.extend('ct-entity-listing', 'ct-data-grid', () => import('./entity/ct-entity-listing/index'));
    /* eslint-enable ct-deprecation-rules/private-feature-declarations */
};
