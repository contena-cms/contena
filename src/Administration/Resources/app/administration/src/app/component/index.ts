/**
 * @sw-package framework
 * @private
 */
export default () => {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Contena.Component.register('sw-wizard-page', () => import('src/app/component/wizard/sw-wizard-page/index'));
    Contena.Component.register(
        'sw-wizard-dot-navigation',
        () => import('src/app/component/wizard/sw-wizard-dot-navigation/index'),
    );
    Contena.Component.register('sw-wizard', () => import('src/app/component/wizard/sw-wizard/index'));
    Contena.Component.register('sw-vnode-renderer', () => import('src/app/component/utils/sw-vnode-renderer/index'));
    Contena.Component.register(
        'sw-dismissible-notices',
        () => import('src/app/component/utils/sw-dismissible-notices/index'),
    );
    Contena.Component.register('sw-verify-admin_user-modal', () => import('src/app/component/utils/sw-verify-admin_user-modal/index'));
    Contena.Component.register('sw-upload-listener', () => import('src/app/component/utils/sw-upload-listener/index'));
    Contena.Component.register('sw-time-ago', () => import('src/app/component/utils/sw-time-ago/index'));
    Contena.Component.register('sw-text-preview', () => import('src/app/component/utils/sw-text-preview/index'));
    Contena.Component.register('sw-step-item', () => import('src/app/component/utils/sw-step-item/index'));
    Contena.Component.register('sw-step-display', () => import('src/app/component/utils/sw-step-display/index'));
    Contena.Component.register('sw-status', () => import('src/app/component/utils/sw-status/index'));
    Contena.Component.register(
        'sw-skeleton-bar-deprecated',
        () => import('src/app/component/utils/sw-skeleton-bar-deprecated/index'),
    );
    Contena.Component.register('sw-skeleton-bar', () => import('src/app/component/utils/sw-skeleton-bar/index'));
    Contena.Component.register('sw-skeleton', () => import('src/app/component/utils/sw-skeleton/index'));
    Contena.Component.register(
        'sw-shortcut-overview-item',
        () => import('src/app/component/utils/sw-shortcut-overview-item/index'),
    );
    Contena.Component.register('sw-shortcut-overview', () => import('src/app/component/utils/sw-shortcut-overview/index'));
    Contena.Component.register('sw-provide', () => import('src/app/component/utils/sw-provide/index'));
    Contena.Component.register('sw-progress-bar', () => import('src/app/component/utils/sw-progress-bar/index'));
    Contena.Component.register(
        'sw-popover-deprecated',
        () => import('src/app/component/utils/sw-popover-deprecated/index'),
    );
    Contena.Component.register('sw-popover', () => import('src/app/component/utils/sw-popover/index'));
    Contena.Component.register('sw-overlay', () => import('src/app/component/utils/sw-overlay/index'));
    Contena.Component.register('sw-notifications', () => import('src/app/component/utils/sw-notifications/index'));
    Contena.Component.register(
        'sw-notification-center-item',
        () => import('src/app/component/utils/sw-notification-center-item/index'),
    );
    Contena.Component.register(
        'sw-notification-center',
        () => import('src/app/component/utils/sw-notification-center/index'),
    );
    Contena.Component.register('sw-loader-deprecated', () => import('src/app/component/utils/sw-loader-deprecated/index'));
    Contena.Component.register('sw-loader', () => import('src/app/component/utils/sw-loader/index'));
    Contena.Component.register('sw-license-violation', () => import('src/app/component/utils/sw-license-violation/index'));
    Contena.Component.register('sw-internal-link', () => import('src/app/component/utils/sw-internal-link/index'));
    Contena.Component.register('sw-inherit-wrapper', () => import('src/app/component/utils/sw-inherit-wrapper/index'));
    Contena.Component.register('sw-ignore-class', () => import('src/app/component/utils/sw-ignore-class/index'));
    Contena.Component.register('sw-external-link', () => import('src/app/component/utils/sw-external-link/index'));
    Contena.Component.register('sw-error-boundary', () => import('src/app/component/utils/sw-error-boundary/index'));
    Contena.Component.register(
        'sw-duplicated-media-v2',
        () => import('src/app/component/utils/sw-duplicated-media-v2/index'),
    );
    Contena.Component.register('sw-color-badge', () => import('src/app/component/utils/sw-color-badge/index'));
    Contena.Component.register('sw-upload-status', () => import('src/app/component/utils/sw-upload-status'));
    Contena.Component.register('sw-tree-item', () => import('src/app/component/tree/sw-tree-item/index'));
    Contena.Component.register('sw-tree-input-field', () => import('src/app/component/tree/sw-tree-input-field/index'));
    Contena.Component.register('sw-tree', () => import('src/app/component/tree/sw-tree/index'));
    Contena.Component.register('sw-skip-link', () => import('src/app/component/structure/sw-skip-link/index'));
    Contena.Component.register(
        'sw-sidebar-renderer',
        () => import('src/app/component/structure/sw-sidebar-renderer/index'),
    );
    Contena.Component.register(
        'sw-search-more-results',
        () => import('src/app/component/structure/sw-search-more-results/index'),
    );
    Contena.Component.register('sw-search-bar-item', () => import('src/app/component/structure/sw-search-bar-item/index'));
    Contena.Component.register('sw-search-bar', () => import('src/app/component/structure/sw-search-bar/index'));
    Contena.Component.register(
        'sw-sales-channel-switch',
        () => import('src/app/component/structure/sw-sales-channel-switch/index'),
    );
    Contena.Component.register(
        'sw-sales-channel-config',
        () => import('src/app/component/structure/sw-sales-channel-config/index'),
    );
    Contena.Component.register('sw-page', () => import('src/app/component/structure/sw-page/index'));
    Contena.Component.register('sw-modals-renderer', () => import('src/app/component/structure/sw-modals-renderer/index'));
    Contena.Component.register('sw-language-switch', () => import('src/app/component/structure/sw-language-switch/index'));
    Contena.Component.register('sw-language-info', () => import('src/app/component/structure/sw-language-info/index'));
    Contena.Component.register(
        'sw-inheritance-warning',
        () => import('src/app/component/structure/sw-inheritance-warning/index'),
    );
    Contena.Component.register(
        'sw-in-app-purchase-checkout',
        () => import('src/app/component/structure/sw-in-app-purchase-checkout/index'),
    );
    Contena.Component.register('sw-hidden-iframes', () => import('src/app/component/structure/sw-hidden-iframes/index'));
    Contena.Component.register('sw-error', () => import('src/app/component/structure/sw-error/index'));
    Contena.Component.register('sw-not-found', () => import('src/app/component/structure/sw-not-found/index'));
    Contena.Component.register(
        'sw-discard-changes-modal',
        () => import('src/app/component/structure/sw-discard-changes-modal/index'),
    );
    Contena.Component.register('sw-desktop', () => import('src/app/component/structure/sw-desktop/index'));
    Contena.Component.register('sw-card-view', () => import('src/app/component/structure/sw-card-view/index'));
    Contena.Component.register(
        'sw-block-parent',
        () => import('src/app/component/structure/sw-block-override/sw-block-parent/index'),
    );
    Contena.Component.register('sw-block', () => import('src/app/component/structure/sw-block-override/sw-block/index'));
    Contena.Component.register('sw-admin-menu-item', () => import('src/app/component/structure/sw-admin-menu-item/index'));
    Contena.Component.register('sw-admin-menu', () => import('src/app/component/structure/sw-admin-menu/index'));
    Contena.Component.register('sw-admin', () => import('src/app/component/structure/sw-admin/index'));
    Contena.Component.register(
        'sw-sidebar-navigation-item',
        () => import('src/app/component/sidebar/sw-sidebar-navigation-item/index'),
    );
    Contena.Component.register('sw-sidebar-item', () => import('src/app/component/sidebar/sw-sidebar-item/index'));
    Contena.Component.register('sw-sidebar', () => import('src/app/component/sidebar/sw-sidebar/index'));
    Contena.Component.register('sw-rule-modal', () => import('src/app/component/rule/sw-rule-modal/index'));
    Contena.Component.register(
        'sw-condition-unit-menu',
        () => import('src/app/component/rule/sw-condition-unit-menu/index'),
    );
    Contena.Component.register(
        'sw-condition-type-select',
        () => import('src/app/component/rule/sw-condition-type-select/index'),
    );
    Contena.Component.register(
        'sw-condition-tree-node',
        () => import('src/app/component/rule/sw-condition-tree-node/index'),
    );
    Contena.Component.register('sw-condition-tree', () => import('src/app/component/rule/sw-condition-tree/index'));
    Contena.Component.register(
        'sw-condition-or-container',
        () => import('src/app/component/rule/sw-condition-or-container/index'),
    );
    Contena.Component.register(
        'sw-condition-operator-select',
        () => import('src/app/component/rule/sw-condition-operator-select/index'),
    );
    Contena.Component.register(
        'sw-condition-value-between-date',
        () => import('src/app/component/rule/sw-condition-value-between-date/index'),
    );
    Contena.Component.register('sw-condition-modal', () => import('src/app/component/rule/sw-condition-modal/index'));
    Contena.Component.register(
        'sw-condition-field-errors',
        () => import('src/app/component/rule/sw-condition-field-errors/index'),
    );
    Contena.Component.register('sw-condition-base', () => import('src/app/component/rule/sw-condition-base/index'));
    Contena.Component.register(
        'sw-condition-and-container',
        () => import('src/app/component/rule/sw-condition-and-container/index'),
    );
    Contena.Component.register(
        'sw-condition-all-line-items-container',
        () => import('src/app/component/rule/sw-condition-all-line-items-container/index'),
    );
    Contena.Component.register('sw-arrow-field', () => import('src/app/component/rule/sw-arrow-field/index'));
    Contena.Component.register(
        'sw-search-preferences-modal',
        () => import('src/app/component/modal/sw-search-preferences-modal/index'),
    );
    Contena.Component.register(
        'sw-image-preview-modal',
        () => import('src/app/component/modal/sw-image-preview-modal/index'),
    );
    Contena.Component.register('sw-confirm-modal', () => import('src/app/component/modal/sw-confirm-modal/index'));
    Contena.Component.register('mt-text-editor', () => import('src/app/component/meteor-wrapper/mt-text-editor/index'));
    Contena.Component.register(
        'sw-text-editor-toolbar-button-link',
        () => import('src/app/component/meteor-wrapper/mt-text-editor/sw-text-editor-toolbar-button-link/index'),
    );
    Contena.Component.register('mt-tabs', () => import('src/app/component/meteor-wrapper/mt-tabs/index'));
    Contena.Component.register('mt-datepicker', () => import('src/app/component/meteor-wrapper/mt-datepicker/index'));
    Contena.Component.register('mt-card', () => import('src/app/component/meteor-wrapper/mt-card/index'));
    Contena.Component.register(
        'sw-meteor-single-select',
        () => import('src/app/component/meteor/sw-meteor-single-select/index'),
    );
    Contena.Component.register('sw-meteor-page', () => import('src/app/component/meteor/sw-meteor-page/index'));
    Contena.Component.register('sw-meteor-navigation', () => import('src/app/component/meteor/sw-meteor-navigation/index'));
    Contena.Component.register('sw-meteor-card', () => import('src/app/component/meteor/sw-meteor-card/index'));
    Contena.Component.register('sw-sortable-list', () => import('src/app/component/list/sw-sortable-list/index'));
    Contena.Component.register('sw-pagination', () => import('src/app/component/grid/sw-pagination/index'));
    Contena.Component.register('sw-grid-row', () => import('src/app/component/grid/sw-grid-row/index'));
    Contena.Component.register('sw-grid-column', () => import('src/app/component/grid/sw-grid-column/index'));
    Contena.Component.register('sw-grid', () => import('src/app/component/grid/sw-grid/index'));
    Contena.Component.register('sw-url-field', () => import('src/app/component/form/sw-url-field/index'));
    Contena.Component.register(
        'sw-textarea-field-deprecated',
        () => import('src/app/component/form/sw-textarea-field-deprecated/index'),
    );
    Contena.Component.register('sw-textarea-field', () => import('src/app/component/form/sw-textarea-field/index'));
    Contena.Component.register(
        'sw-text-field-deprecated',
        () => import('src/app/component/form/sw-text-field-deprecated/index'),
    );
    Contena.Component.register('sw-text-field', () => import('src/app/component/form/sw-text-field/index'));
    Contena.Component.register('sw-text-editor', () => import('src/app/component/form/sw-text-editor/index'));
    Contena.Component.register(
        'sw-text-editor-toolbar-table-button',
        () => import('src/app/component/form/sw-text-editor/sw-text-editor-toolbar-table-button/index'),
    );
    Contena.Component.register(
        'sw-text-editor-toolbar-button',
        () => import('src/app/component/form/sw-text-editor/sw-text-editor-toolbar-button/index'),
    );
    Contena.Component.register(
        'sw-text-editor-toolbar',
        () => import('src/app/component/form/sw-text-editor/sw-text-editor-toolbar/index'),
    );
    Contena.Component.register(
        'sw-text-editor-table-toolbar',
        () => import('src/app/component/form/sw-text-editor/sw-text-editor-table-toolbar/index'),
    );
    Contena.Component.register(
        'sw-text-editor-link-menu',
        () => import('src/app/component/form/sw-text-editor/sw-text-editor-link-menu/index'),
    );
    Contena.Component.register('sw-tagged-field', () => import('src/app/component/form/sw-tagged-field/index'));
    Contena.Component.register('sw-switch-field', () => import('src/app/component/form/sw-switch-field/index'));
    Contena.Component.register(
        'sw-snippet-field-edit-modal',
        () => import('src/app/component/form/sw-snippet-field-edit-modal/index'),
    );
    Contena.Component.register('sw-snippet-field', () => import('src/app/component/form/sw-snippet-field/index'));
    Contena.Component.register('sw-select-rule-create', () => import('src/app/component/form/sw-select-rule-create/index'));
    Contena.Component.register('sw-select-option', () => import('src/app/component/form/sw-select-option/index'));
    Contena.Component.register(
        'sw-select-field-deprecated',
        () => import('src/app/component/form/sw-select-field-deprecated/index'),
    );
    Contena.Component.register('sw-select-field', () => import('src/app/component/form/sw-select-field/index'));
    Contena.Component.register('sw-radio-field', () => import('src/app/component/form/sw-radio-field/index'));
    Contena.Component.register(
        'sw-purchase-price-field',
        () => import('src/app/component/form/sw-purchase-price-field/index'),
    );
    Contena.Component.register('sw-price-field', () => import('src/app/component/form/sw-price-field/index'));
    Contena.Component.register('sw-password-field', () => import('src/app/component/form/sw-password-field/index'));
    Contena.Component.register('sw-number-field', () => import('src/app/component/form/sw-number-field/index'));
    Contena.Component.register(
        'sw-maintain-currencies-modal',
        () => import('src/app/component/form/sw-maintain-currencies-modal/index'),
    );
    Contena.Component.register('sw-list-price-field', () => import('src/app/component/form/sw-list-price-field/index'));
    Contena.Component.register('sw-gtc-checkbox', () => import('src/app/component/form/sw-gtc-checkbox/index'));
    Contena.Component.register(
        'sw-form-field-renderer',
        () => import('src/app/component/form/sw-form-field-renderer/index'),
    );
    Contena.Component.register('sw-file-input', () => import('src/app/component/form/sw-file-input/index'));
    Contena.Component.register('sw-field-copyable', () => import('src/app/component/form/sw-field-copyable/index'));
    Contena.Component.register('sw-email-field', () => import('src/app/component/form/sw-email-field/index'));
    Contena.Component.register('sw-dynamic-url-field', () => import('src/app/component/form/sw-dynamic-url-field/index'));
    Contena.Component.register(
        'sw-custom-field-set-renderer',
        () => import('src/app/component/form/sw-custom-field-set-renderer/index'),
    );
    Contena.Component.register('sw-confirm-field', () => import('src/app/component/form/sw-confirm-field/index'));
    Contena.Component.register(
        'sw-colorpicker-deprecated',
        () => import('src/app/component/form/sw-colorpicker-deprecated/index'),
    );
    Contena.Component.register('sw-colorpicker', () => import('src/app/component/form/sw-colorpicker/index'));
    Contena.Component.register(
        'sw-checkbox-field-deprecated',
        () => import('src/app/component/form/sw-checkbox-field-deprecated/index'),
    );
    Contena.Component.register('sw-checkbox-field', () => import('src/app/component/form/sw-checkbox-field/index'));
    Contena.Component.register(
        'sw-boolean-radio-group',
        () => import('src/app/component/form/sw-boolean-radio-group/index'),
    );
    Contena.Component.register(
        'sw-entity-single-select',
        () => import('src/app/component/form/select/entity/sw-entity-single-select/index'),
    );
    Contena.Component.register(
        'sw-entity-multi-select',
        () => import('src/app/component/form/select/entity/sw-entity-multi-select/index'),
    );
    Contena.Component.register(
        'sw-entity-multi-id-select',
        () => import('src/app/component/form/select/entity/sw-entity-multi-id-select/index'),
    );
    Contena.Component.register(
        'sw-entity-many-to-many-select',
        () => import('src/app/component/form/select/entity/sw-entity-many-to-many-select/index'),
    );
    Contena.Component.register(
        'sw-entity-advanced-selection-modal',
        () => import('src/app/component/form/select/entity/sw-entity-advanced-selection-modal/index'),
    );
    Contena.Component.register(
        'sw-advanced-selection-rule',
        () => import('src/app/component/form/select/entity/advanced-selection-entities/sw-advanced-selection-rule/index'),
    );
    Contena.Component.register(
        'sw-advanced-selection-blog',
        () => import('src/app/component/form/select/entity/advanced-selection-entities/sw-advanced-selection-blog/index'),
    );
    Contena.Component.register(
        'sw-single-select',
        () => import('src/app/component/form/select/base/sw-single-select/index'),
    );
    Contena.Component.register(
        'sw-select-selection-list',
        () => import('src/app/component/form/select/base/sw-select-selection-list/index'),
    );
    Contena.Component.register(
        'sw-select-result-list',
        () => import('src/app/component/form/select/base/sw-select-result-list/index'),
    );
    Contena.Component.register(
        'sw-select-result',
        () => import('src/app/component/form/select/base/sw-select-result/index'),
    );
    Contena.Component.register('sw-select-base', () => import('src/app/component/form/select/base/sw-select-base/index'));
    Contena.Component.register(
        'sw-multi-tag-select',
        () => import('src/app/component/form/select/base/sw-multi-tag-select/index'),
    );
    Contena.Component.register('sw-multi-select', () => import('src/app/component/form/select/base/sw-multi-select/index'));
    Contena.Component.register('sw-field-error', () => import('src/app/component/form/field-base/sw-field-error/index'));
    Contena.Component.register(
        'sw-contextual-field',
        () => import('src/app/component/form/field-base/sw-contextual-field/index'),
    );
    Contena.Component.register('sw-block-field', () => import('src/app/component/form/field-base/sw-block-field/index'));
    Contena.Component.register('sw-base-field', () => import('src/app/component/form/field-base/sw-base-field/index'));
    Contena.Component.register(
        'sw-sidebar-filter-panel',
        () => import('src/app/component/filter/sw-sidebar-filter-panel/index'),
    );
    Contena.Component.register('sw-range-filter', () => import('src/app/component/filter/sw-range-filter/index'));
    Contena.Component.register('sw-number-filter', () => import('src/app/component/filter/sw-number-filter/index'));
    Contena.Component.register(
        'sw-multi-select-filter',
        () => import('src/app/component/filter/sw-multi-select-filter/index'),
    );
    Contena.Component.register('sw-filter-panel', () => import('src/app/component/filter/sw-filter-panel/index'));
    Contena.Component.register('sw-existence-filter', () => import('src/app/component/filter/sw-existence-filter/index'));
    Contena.Component.register('sw-date-filter', () => import('src/app/component/filter/sw-date-filter/index'));
    Contena.Component.register('sw-boolean-filter', () => import('src/app/component/filter/sw-boolean-filter/index'));
    Contena.Component.register('sw-base-filter', () => import('src/app/component/filter/sw-base-filter/index'));
    Contena.Component.register(
        'sw-iframe-renderer',
        () => import('src/app/component/extension-api/sw-iframe-renderer/index'),
    );
    Contena.Component.register(
        'sw-extension-teaser-sales-channel',
        () => import('src/app/component/extension-api/sw-extension-teaser-sales-channel/index'),
    );
    Contena.Component.register(
        'sw-extension-teaser-popover',
        () => import('src/app/component/extension-api/sw-extension-teaser-popover/index'),
    );
    Contena.Component.register(
        'sw-extension-component-section',
        () => import('src/app/component/extension-api/sw-extension-component-section/index'),
    );
    Contena.Component.register(
        'sw-blog-stream-grid-preview',
        () => import('src/app/component/entity/sw-blog-stream-grid-preview/index'),
    );
    Contena.Component.register(
        'sw-many-to-many-assignment-card',
        () => import('src/app/component/entity/sw-many-to-many-assignment-card/index'),
    );
    Contena.Component.register(
        'sw-category-tree-field',
        () => import('src/app/component/entity/sw-category-tree-field/index'),
    );
    Contena.Component.register('sw-bulk-edit-modal', () => import('src/app/component/entity/sw-bulk-edit-modal/index'));
    Contena.Component.register(
        'sw-data-grid-skeleton',
        () => import('src/app/component/data-grid/sw-data-grid-skeleton/index'),
    );
    Contena.Component.register(
        'sw-data-grid-settings',
        () => import('src/app/component/data-grid/sw-data-grid-settings/index'),
    );
    Contena.Component.register(
        'sw-data-grid-inline-edit',
        () => import('src/app/component/data-grid/sw-data-grid-inline-edit/index'),
    );
    Contena.Component.register(
        'sw-data-grid-column-position',
        () => import('src/app/component/data-grid/sw-data-grid-column-position/index'),
    );
    Contena.Component.register(
        'sw-data-grid-column-boolean',
        () => import('src/app/component/data-grid/sw-data-grid-column-boolean/index'),
    );
    Contena.Component.register('sw-data-grid', () => import('src/app/component/data-grid/sw-data-grid/index'));
    Contena.Component.register(
        'sw-context-menu-item',
        () => import('src/app/component/context-menu/sw-context-menu-item/index'),
    );
    Contena.Component.register(
        'sw-context-menu-divider',
        () => import('src/app/component/context-menu/sw-context-menu-divider/index'),
    );
    Contena.Component.register('sw-context-menu', () => import('src/app/component/context-menu/sw-context-menu/index'));
    Contena.Component.register('sw-context-button', () => import('src/app/component/context-menu/sw-context-button/index'));
    Contena.Component.register('sw-version', () => import('src/app/component/base/sw-version/index'));
    Contena.Component.register('sw-admin_user-card', () => import('src/app/component/base/sw-admin_user-card/index'));
    Contena.Component.register('sw-tabs-item', () => import('src/app/component/base/sw-tabs-item/index'));
    Contena.Component.register('sw-tabs-deprecated', () => import('src/app/component/base/sw-tabs-deprecated/index'));
    Contena.Component.register('sw-tabs', () => import('src/app/component/base/sw-tabs/index'));
    Contena.Component.register('sw-sorting-select', () => import('src/app/component/base/sw-sorting-select/index'));
    Contena.Component.register(
        'sw-simple-search-field',
        () => import('src/app/component/base/sw-simple-search-field/index'),
    );
    Contena.Component.register('sw-rating-stars', () => import('src/app/component/base/sw-rating-stars/index'));
    Contena.Component.register('sw-radio-panel', () => import('src/app/component/base/sw-radio-panel/index'));
    Contena.Component.register('sw-property-search', () => import('src/app/component/base/sw-property-search/index'));
    Contena.Component.register(
        'sw-blog-variant-info',
        () => import('src/app/component/base/sw-blog-variant-info/index'),
    );
    Contena.Component.register('sw-blog-image', () => import('src/app/component/base/sw-blog-image/index'));
    Contena.Component.register('sw-modal', () => import('src/app/component/base/sw-modal/index'));
    Contena.Component.register('sw-label', () => import('src/app/component/base/sw-label/index'));
    Contena.Component.register('sw-inheritance-switch', () => import('src/app/component/base/sw-inheritance-switch/index'));
    Contena.Component.register('sw-icon-deprecated', () => import('src/app/component/base/sw-icon-deprecated/index'));
    Contena.Component.register('sw-icon', () => import('src/app/component/base/sw-icon/index'));
    Contena.Component.register('sw-highlight-text', () => import('src/app/component/base/sw-highlight-text/index'));
    Contena.Component.register('sw-help-text', () => import('src/app/component/base/sw-help-text/index'));
    Contena.Component.register('sw-error-summary', () => import('src/app/component/base/sw-error-summary/index'));
    Contena.Component.register('sw-empty-state', () => import('src/app/component/base/sw-empty-state/index'));
    Contena.Component.register('sw-description-list', () => import('src/app/component/base/sw-description-list/index'));
    Contena.Component.register('sw-container', () => import('src/app/component/base/sw-container/index'));
    Contena.Component.register('sw-collapse', () => import('src/app/component/base/sw-collapse/index'));
    Contena.Component.register('sw-circle-icon', () => import('src/app/component/base/sw-circle-icon/index'));
    Contena.Component.register('sw-chart-card', () => import('src/app/component/base/sw-chart-card/index'));
    Contena.Component.register('sw-card-section', () => import('src/app/component/base/sw-card-section/index'));
    Contena.Component.register('sw-card-filter', () => import('src/app/component/base/sw-card-filter/index'));
    Contena.Component.register('sw-card-deprecated', () => import('src/app/component/base/sw-card-deprecated/index'));
    Contena.Component.register('sw-card', () => import('src/app/component/base/sw-card/index'));
    Contena.Component.register('sw-button-process', () => import('src/app/component/base/sw-button-process/index'));
    Contena.Component.register('sw-button-group', () => import('src/app/component/base/sw-button-group/index'));
    Contena.Component.register('sw-button-deprecated', () => import('src/app/component/base/sw-button-deprecated/index'));
    Contena.Component.register('sw-button', () => import('src/app/component/base/sw-button/index'));
    Contena.Component.register('sw-avatar', () => import('src/app/component/base/sw-avatar/index'));
    Contena.Component.register('sw-alert-deprecated', () => import('src/app/component/base/sw-alert-deprecated/index'));
    Contena.Component.register('sw-alert', () => import('src/app/component/base/sw-alert/index'));
    Contena.Component.register('sw-address', () => import('src/app/component/base/sw-address/index'));
    Contena.Component.register(
        'sw-app-wrong-app-url-modal',
        () => import('src/app/component/app/sw-app-wrong-app-url-modal/index'),
    );
    Contena.Component.register('sw-app-topbar-button', () => import('src/app/component/app/sw-app-topbar-button/index'));
    Contena.Component.register('sw-app-topbar-sidebar', () => import('src/app/component/app/sw-app-topbar-sidebar/index'));
    Contena.Component.register(
        'sw-app-shop-id-change-modal',
        () => import('src/app/component/app/sw-app-shop-id-change-modal/index'),
    );
    Contena.Component.register('sw-app-actions', () => import('src/app/component/app/sw-app-actions/index'));
    Contena.Component.register('sw-app-action-button', () => import('src/app/component/app/sw-app-action-button/index'));
    Contena.Component.register('sw-code-editor', () => import('src/app/component/form/sw-code-editor'));
    Contena.Component.register('sw-datepicker', () => import('src/app/component/form/sw-datepicker'));
    Contena.Component.register('sw-datepicker-deprecated', () => import('src/app/component/form/sw-datepicker-deprecated'));
    Contena.Component.register('sw-chart', () => import('src/app/component/base/sw-chart'));
    Contena.Component.register('sw-help-center-v2', () => import('src/app/component/utils/sw-help-center'));
    Contena.Component.register('sw-help-sidebar', () => import('src/app/component/sidebar/sw-help-sidebar'));
    Contena.Component.register('sw-image-slider', () => import('src/app/component/media/sw-image-slider'));
    Contena.Component.register(
        'sw-media-add-thumbnail-form',
        () => import('src/app/component/media/sw-media-add-thumbnail-form'),
    );
    Contena.Component.register('sw-media-base-item', () => import('src/app/component/media/sw-media-base-item'));
    Contena.Component.extend(
        'sw-media-compact-upload-v2',
        'sw-media-upload-v2',
        () => import('src/app/component/media/sw-media-compact-upload-v2'),
    );
    Contena.Component.register('sw-media-entity-mapper', () => import('src/app/component/media/sw-media-entity-mapper'));
    Contena.Component.register('sw-media-field', () => import('src/app/component/media/sw-media-field'));
    Contena.Component.register('sw-media-folder-content', () => import('src/app/component/media/sw-media-folder-content'));
    Contena.Component.register('sw-media-folder-item', () => import('src/app/component/media/sw-media-folder-item'));
    Contena.Component.register(
        'sw-media-folder-thumbnail',
        () => import('src/app/component/media/sw-media-folder-thumbnail'),
    );
    Contena.Component.register(
        'sw-media-list-selection-item-v2',
        () => import('src/app/component/media/sw-media-list-selection-item-v2'),
    );
    Contena.Component.register(
        'sw-media-list-selection-v2',
        () => import('src/app/component/media/sw-media-list-selection-v2'),
    );
    Contena.Component.register('sw-media-media-item', () => import('src/app/component/media/sw-media-media-item'));
    Contena.Component.register('sw-media-modal-delete', () => import('src/app/component/media/sw-media-modal-delete'));
    Contena.Component.register(
        'sw-media-modal-folder-dissolve',
        () => import('src/app/component/media/sw-media-modal-folder-dissolve'),
    );
    Contena.Component.register(
        'sw-media-modal-folder-settings',
        () => import('src/app/component/media/sw-media-modal-folder-settings'),
    );
    Contena.Component.register('sw-media-modal-move', () => import('src/app/component/media/sw-media-modal-move'));
    Contena.Component.register('sw-media-modal-replace', () => import('src/app/component/media/sw-media-modal-replace'));
    Contena.Component.register('sw-media-preview-v2', () => import('src/app/component/media/sw-media-preview-v2'));
    Contena.Component.register('sw-model-editor', () => import('src/app/component/media/sw-model-editor'));
    Contena.Component.extend(
        'sw-model-editor-collapse',
        'sw-collapse',
        () => import('src/app/component/media/sw-model-editor-collapse'),
    );
    Contena.Component.register('sw-model-viewer', () => import('src/app/component/media/sw-model-viewer'));
    Contena.Component.register('sw-vector-field', () => import('src/app/component/media/sw-vector-field'));
    Contena.Component.extend('sw-media-replace', 'sw-media-upload-v2', import('src/app/component/media/sw-media-replace'));
    Contena.Component.register('sw-media-upload-v2', () => import('src/app/component/media/sw-media-upload-v2'));
    Contena.Component.register('sw-media-url-form', () => import('src/app/component/media/sw-media-url-form'));
    Contena.Component.register('sw-sidebar-media-item', () => import('src/app/component/media/sw-sidebar-media-item'));
    Contena.Component.register('sw-extension-icon', () => import('src/app/component/extension/sw-extension-icon'));
    Contena.Component.register('sw-ai-copilot-badge', () => import('src/app/component/feedback/sw-ai-copilot-badge'));
    Contena.Component.register('sw-ai-copilot-warning', () => import('src/app/component/feedback/sw-ai-copilot-warning'));
    Contena.Component.register('sw-string-filter', () => import('src/app/component/filter/sw-string-filter'));
    Contena.Component.register(
        'sw-media-modal-renderer',
        () => import('src/app/component/structure/sw-media-modal-renderer/index'),
    );
    Contena.Component.register('sw-request-consent-modal', () => import('./structure/sw-request-consent-modal'));
    // @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal
    Contena.Component.register('sw-ui-shell-update-2026-modal', () => import('./structure/sw-ui-shell-update-2026-modal'));
    Contena.Component.extend('sw-sidebar-collapse', 'sw-collapse', () => import('./sidebar/sw-sidebar-collapse/index'));
    Contena.Component.extend(
        'sw-condition-is-net-select',
        'sw-condition-operator-select',
        () => import('./rule/sw-condition-is-net-select/index'),
    );
    Contena.Component.extend(
        'sw-condition-base-line-item',
        'sw-condition-base',
        () => import('./rule/sw-condition-base-line-item/index'),
    );
    Contena.Component.extend(
        'sw-condition-time-range',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-time-range/index'),
    );
    Contena.Component.extend(
        'sw-condition-shipping-zip-code',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-shipping-zip-code/index'),
    );
    Contena.Component.extend(
        'sw-condition-script',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-script/index'),
    );
    Contena.Component.extend(
        'sw-condition-order-custom-field',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-order-custom-field/index'),
    );
    Contena.Component.extend(
        'sw-condition-not-found',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-not-found/index'),
    );
    Contena.Component.extend(
        'sw-condition-line-item-with-quantity',
        'sw-condition-base-line-item',
        () => import('./rule/condition-type/sw-condition-line-item-with-quantity/index'),
    );
    Contena.Component.extend(
        'sw-condition-line-item-purchase-price',
        'sw-condition-base-line-item',
        () => import('./rule/condition-type/sw-condition-line-item-purchase-price/index'),
    );
    Contena.Component.extend(
        'sw-condition-line-item-property',
        'sw-condition-base-line-item',
        () => import('./rule/condition-type/sw-condition-line-item-property/index'),
    );
    Contena.Component.extend(
        'sw-condition-line-item-in-category',
        'sw-condition-base-line-item',
        () => import('./rule/condition-type/sw-condition-line-item-in-category/index'),
    );
    Contena.Component.extend(
        'sw-condition-line-item-goods-total',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-line-item-goods-total/index'),
    );
    Contena.Component.extend(
        'sw-condition-line-item-custom-field',
        'sw-condition-base-line-item',
        () => import('./rule/condition-type/sw-condition-line-item-custom-field/index'),
    );
    Contena.Component.extend(
        'sw-condition-line-item',
        'sw-condition-base-line-item',
        () => import('./rule/condition-type/sw-condition-line-item/index'),
    );
    Contena.Component.extend(
        'sw-condition-is-always-valid',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-is-always-valid/index'),
    );
    Contena.Component.extend(
        'sw-condition-goods-price',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-goods-price/index'),
    );
    Contena.Component.extend(
        'sw-condition-goods-count',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-goods-count/index'),
    );
    Contena.Component.extend(
        'sw-condition-generic-line-item',
        'sw-condition-base-line-item',
        () => import('./rule/condition-type/sw-condition-generic-line-item/index'),
    );
    Contena.Component.extend(
        'sw-condition-generic',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-generic/index'),
    );
    Contena.Component.extend(
        'sw-condition-date-range',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-date-range/index'),
    );
    Contena.Component.extend(
        'sw-condition-user-custom-field',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-user-custom-field/index'),
    );
    Contena.Component.extend(
        'sw-condition-billing-zip-code',
        'sw-condition-base',
        () => import('./rule/condition-type/sw-condition-billing-zip-code/index'),
    );
    Contena.Component.extend(
        'sw-url-field-deprecated',
        'sw-text-field-deprecated',
        () => import('./form/sw-url-field-deprecated/index'),
    );
    Contena.Component.extend(
        'sw-switch-field-deprecated',
        'sw-checkbox-field-deprecated',
        () => import('./form/sw-switch-field-deprecated/index'),
    );
    Contena.Component.extend(
        'sw-select-number-field',
        'sw-select-field-deprecated',
        () => import('./form/sw-select-number-field/index'),
    );
    Contena.Component.extend(
        'sw-password-field-deprecated',
        'sw-text-field-deprecated',
        () => import('./form/sw-password-field-deprecated/index'),
    );
    Contena.Component.extend(
        'sw-number-field-deprecated',
        'sw-text-field-deprecated',
        () => import('./form/sw-number-field-deprecated/index'),
    );
    Contena.Component.extend(
        'sw-email-field-deprecated',
        'sw-text-field-deprecated',
        () => import('./form/sw-email-field-deprecated/index'),
    );
    Contena.Component.extend(
        'sw-compact-colorpicker',
        'sw-colorpicker-deprecated',
        () => import('./form/sw-compact-colorpicker/index'),
    );
    Contena.Component.extend(
        'sw-entity-tag-select',
        'sw-entity-multi-select',
        () => import('./form/select/entity/sw-entity-tag-select/index'),
    );
    Contena.Component.extend(
        'sw-entity-advanced-selection-modal-grid',
        'sw-entity-listing',
        () => import('./form/select/entity/sw-entity-advanced-selection-modal-grid/index'),
    );
    Contena.Component.extend(
        'sw-multi-tag-ip-select',
        'sw-multi-tag-select',
        () => import('./form/select/base/sw-multi-tag-ip-select/index'),
    );
    Contena.Component.extend(
        'sw-grouped-single-select',
        'sw-single-select',
        () => import('./form/select/base/sw-grouped-single-select/index'),
    );
    Contena.Component.extend('sw-one-to-many-grid', 'sw-data-grid', () => import('./entity/sw-one-to-many-grid/index'));
    Contena.Component.extend('sw-entity-listing', 'sw-data-grid', () => import('./entity/sw-entity-listing/index'));
    Contena.Component.extend('sw-price-preview', 'sw-price-field', () => import('./base/sw-price-preview/index'));
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */
};
