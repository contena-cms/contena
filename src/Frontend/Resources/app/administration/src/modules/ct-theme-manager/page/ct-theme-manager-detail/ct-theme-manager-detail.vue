<template>
    <ct-block name="ct_theme_manager_detail">
        <ct-page class="ct-theme-manager-detail">
            <template #search-bar>
                <ct-block name="ct_theme_manager_detail_search_bar">
                    <ct-search-bar
                        :placeholder="$t('ct-theme-manager.general.placeholderSearchBar')"
                        :entity-service="themeRepository"
                        @search="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_theme_manager_detail_smart_bar_header">
                    <ct-block name="ct_theme_manager_detail_smart_bar_header_title">
                        <h2 v-if="theme">
                            <ct-block name="ct_theme_manager_detail_smart_bar_header_title_text">
                                {{ theme.name }}
                            </ct-block>
                        </h2>

                        <h2 v-else>
                            <ct-block name="ct_theme_manager_detail_smart_bar_header_title_text_default">
                                {{ $t('ct-theme-manager.list.textThemeOverview') }}
                            </ct-block>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_theme_manager_detail_smart_bar_actions">
                    <ct-button-group
                        v-tooltip.bottom="{
                            message: $t('ct-privileges.tooltip.warning'),
                            disabled: acl.can('theme.editor'),
                            showOnDisabledElements: true,
                        }"
                        class="ct-theme-manager-detail__save-button-group"
                        :split-button="true"
                    >
                        <ct-block name="ct_theme_manager_detail_smart_bar_actions_save">
                            <ct-button-process
                                v-tooltip.bottom="{
                                    message: $t('ct-privileges.tooltip.warning'),
                                    disabled: acl.can('theme.editor'),
                                    showOnDisabledElements: true,
                                }"
                                class="ct_theme_manager_detail__save-action"
                                :is-loading="isLoading"
                                :process-success="isSaveSuccessful"
                                variant="primary"
                                :disabled="isLoading || !acl.can('theme.editor')"
                                @process-finish="saveFinish"
                                @click.prevent="onSave"
                            >
                                {{ $t('global.default.save') }}
                            </ct-button-process>
                        </ct-block>

                        <ct-block name="ct_theme_manager_detail_smart_bar_actions_save_context_menu">
                            <ct-context-button>
                                <template #button>
                                    <mt-button
                                        class="ct_theme_manager_detail__button-context-menu"
                                        square
                                        variant="primary"
                                        :disabled="isLoading || !acl.can('theme.editor')"
                                        size="default"
                                    >
                                        <mt-icon name="regular-chevron-down-xs" size="16" />
                                    </mt-button>
                                </template>

                                <ct-block name="ct_theme_manager_detail_smart_bar_actions_save_context_menu_actions">
                                    <ct-block name="ct_theme_manager_detail_smart_bar_actions_save_clean">
                                        <ct-context-menu-item
                                            v-tooltip.top="{
                                                message: $t('ct-theme-manager.actions.saveCleanToolTip'),
                                                disabled: !acl.can('theme.editor'),
                                                showOnDisabledElements: true,
                                            }"
                                            class="ct_theme_manager_detail__save-clean-action"
                                            :disabled="!acl.can('theme.editor')"
                                            @click="onSaveClean"
                                        >
                                            {{ $t('ct-theme-manager.actions.saveClean') }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_theme_manager_detail_smart_bar_actions_validate">
                                        <ct-context-menu-item
                                            v-tooltip.top="{
                                                message: $t('ct-theme-manager.actions.validateToolTip'),
                                                disabled: !acl.can('theme.editor'),
                                                showOnDisabledElements: true,
                                            }"
                                            class="ct_theme_manager_detail__validate-action"
                                            :disabled="!acl.can('theme.editor')"
                                            @click="onValidate"
                                        >
                                            {{ $t('ct-theme-manager.actions.validate') }}
                                        </ct-context-menu-item>
                                    </ct-block>
                                </ct-block>
                            </ct-context-button>
                        </ct-block>
                    </ct-button-group>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_theme_manager_detail_content">
                    <div v-if="!shouldShowContent" class="ct-theme-manager-detail__content-skeleton">
                        <ct-skeleton />
                        <ct-skeleton />
                    </div>

                    <div v-else-if="theme" class="ct-theme-manager-detail__content">
                        <mt-tabs
                            v-if="tabItems.length > 1"
                            class="ct-theme-manager-detail__tabs"
                            position-identifier="theme-manager-detail-tabs"
                            :default-item="activeTab"
                            :items="tabItems"
                            :use-routes-for-extensions="false"
                            :small="true"
                            @new-item-active="onChangeTab"
                        />

                        <template v-if="activeTab === 'default'">
                            <template v-if="isDerived">
                                <div class="ct-theme-manager-detail__inheritance">
                                    <mt-icon size="20" name="regular-link-horizontal" />

                                    <p v-if="parentTheme" class="ct-theme-manager-detail__inheritance-text">
                                        {{
                                            $t('ct-theme-manager.detail.inheritanceInfo', {
                                                parentThemeName: parentTheme.name,
                                            })
                                        }}
                                    </p>
                                    <p v-else-if="defaultTheme" class="ct-theme-manager-detail__inheritance-text">
                                        {{
                                            $t('ct-theme-manager.detail.inheritanceInfo', {
                                                parentThemeName: defaultTheme.name,
                                            })
                                        }}
                                    </p>
                                </div>
                            </template>

                            <mt-card
                                class="ct-theme-manager-detail__info-card"
                                position-identifier="theme-manager-detail-info"
                            >
                                <div class="ct-theme-manager-detail__info">
                                    <div class="ct-theme-manager-detail__info-image" :style="previewMedia"></div>

                                    <div class="ct-theme-manager-detail__info-content">
                                        <div class="ct-theme-manager-detail__info-name">
                                            {{ theme.name }}
                                        </div>
                                        <div class="ct-theme-manager-detail__info-author">
                                            {{ theme.author }}
                                        </div>
                                        <div v-if="theme.description" class="ct-theme-manager-detail__info-description">
                                            <p class="ct-theme-manager-detail__info-description-title">
                                                {{ $t('ct-theme-manager.detail.description') }}:
                                            </p>
                                            <p>
                                                {{ truncateFilter(theme.description, 140) }}
                                            </p>
                                        </div>

                                        <ct-entity-multi-select
                                            v-model:entity-collection="theme.channels"
                                            class="ct-theme-manager-detail__channels-select"
                                            :label="$t('ct-theme-manager.detail.channel')"
                                            :disabled="!acl.can('theme.editor')"
                                            :help-text="
                                                isDefaultTheme ? $t('ct-theme-manager.detail.channelHelpText') : null
                                            "
                                            :placeholder="$t('ct-theme-manager.detail.placeholder.selectChannel')"
                                            :selection-disabling-method="selectionDisablingMethod"
                                        >
                                            <template #result-item="{ item }">
                                                <span v-if="!isThemeCompatible(item)"></span>
                                            </template>
                                        </ct-entity-multi-select>
                                    </div>
                                </div>

                                <ct-context-button class="ct-theme-manager-detail__context-button" :z-index="1100">
                                    <ct-context-menu-item :disabled="!acl.can('theme.editor')" @click="onRenameTheme(theme)">
                                        {{ $t('ct-theme-manager.actions.rename') }}
                                    </ct-context-menu-item>

                                    <ct-context-menu-item
                                        v-if="theme.technicalName"
                                        :disabled="!acl.can('theme.creator')"
                                        @click="onDuplicateTheme(theme)"
                                    >
                                        {{ $t('global.default.duplicate') }}
                                    </ct-context-menu-item>

                                    <ct-context-menu-item
                                        v-if="theme.configValues !== null"
                                        variant="danger"
                                        :disabled="!acl.can('theme.editor')"
                                        @click="onReset"
                                    >
                                        {{ $t('ct-theme-manager.actions.buttonReset') }}
                                    </ct-context-menu-item>

                                    <ct-context-menu-item
                                        v-if="!theme.technicalName"
                                        v-tooltip.right="deleteDisabledToolTip"
                                        class="ct-theme-manager-detail__option-delete"
                                        variant="danger"
                                        :disabled="!acl.can('theme.deleter') || theme.channels.length > 0"
                                        @click="onDeleteTheme(theme)"
                                    >
                                        {{ $t('global.default.delete') }}
                                    </ct-context-menu-item>
                                </ct-context-button>
                            </mt-card>
                        </template>

                        <template v-for="(tab, tabName) in structuredThemeFields.tabs">
                            <template v-if="tabName === activeTab">
                                <mt-card
                                    v-for="(block, blockName) in tab.blocks"
                                    :key="blockName"
                                    class="ct-theme-manager-detail__area"
                                    position-identifier="theme-manager-detail-content"
                                    :title="getSnippet(block.labelSnippetKey)"
                                >
                                    <ct-card-section v-for="(section, sectionName) in block.sections" :key="sectionName">
                                        <div
                                            v-if="$t(section.labelSnippetKey) !== section.labelSnippetKey"
                                            class="ct-theme-manager-detail__content-section-title"
                                        >
                                            {{ getSnippet(section.labelSnippetKey) }}
                                        </div>

                                        <ct-container class="ct-theme-manager-detail__content-section-field">
                                            <template v-for="(field, fieldName) in section.fields">
                                                <div
                                                    v-if="
                                                        themeConfig[fieldName] &&
                                                        baseThemeConfig[fieldName] &&
                                                        themeConfig[fieldName].editable !== false
                                                    "
                                                    :key="fieldName"
                                                    class="ct-theme-manager-detail__content-section-field"
                                                    :class="{
                                                        'ct-theme-manager-detail__content-section-field-full-width':
                                                            field.fullWidth,
                                                    }"
                                                >
                                                    <template v-if="mapCtFieldTypes(field.type) === 'select'">
                                                        <ct-inherit-wrapper
                                                            :ref="`wrapper-${fieldName}`"
                                                            v-model:value="currentThemeConfig[fieldName].value"
                                                            :class="'ct-field-id-' + fieldName"
                                                            :has-parent="theme.baseConfig?.fields?.[fieldName] == null"
                                                            :inherited-value="baseThemeConfig[fieldName].value"
                                                            :label="getFieldLabel(field, fieldName)"
                                                            :help-text="getHelpText(field)"
                                                            :custom-inheritation-check-function="
                                                                checkInheritanceFunction(fieldName)
                                                            "
                                                            @update:value="handleInheritanceInput($event, fieldName)"
                                                        >
                                                            <template
                                                                #content="{ currentValue, updateCurrentValue, isInherited }"
                                                            >
                                                                <mt-select
                                                                    :model-value="currentValue"
                                                                    :placeholder="field.placeholder"
                                                                    :options="field.options"
                                                                    :disabled="isInherited || !acl.can('theme.editor')"
                                                                    @update:model-value="updateCurrentValue"
                                                                />
                                                            </template>
                                                        </ct-inherit-wrapper>
                                                    </template>

                                                    <template v-else-if="field.type === 'url'">
                                                        <ct-inherit-wrapper
                                                            :ref="`wrapper-${fieldName}`"
                                                            v-model:value="currentThemeConfig[fieldName].value"
                                                            :class="'ct-field-id-' + fieldName"
                                                            :has-parent="theme.baseConfig?.fields?.[fieldName] == null"
                                                            :inherited-value="baseThemeConfig[fieldName].value"
                                                            :label="getFieldLabel(field, fieldName)"
                                                            :help-text="getHelpText(field)"
                                                            :custom-inheritation-check-function="
                                                                checkInheritanceFunction(fieldName)
                                                            "
                                                            @update:value="handleInheritanceInput($event, fieldName)"
                                                        >
                                                            <template
                                                                #content="{ currentValue, updateCurrentValue, isInherited }"
                                                            >
                                                                <mt-url-field
                                                                    :model-value="currentValue"
                                                                    :disabled="isInherited || !acl.can('theme.editor')"
                                                                    @update:model-value="updateCurrentValue"
                                                                />
                                                            </template>
                                                        </ct-inherit-wrapper>
                                                    </template>
                                                    <div v-else-if="field.type === 'media'">
                                                        <ct-upload-listener
                                                            :upload-tag="
                                                                tabName +
                                                                '-' +
                                                                blockName +
                                                                '-' +
                                                                sectionName +
                                                                '-' +
                                                                fieldName
                                                            "
                                                            auto-upload
                                                            @media-upload-finish="
                                                                successfulUpload($event, currentThemeConfig[fieldName])
                                                            "
                                                        />

                                                        <ct-inherit-wrapper
                                                            :ref="`wrapper-${fieldName}`"
                                                            v-model:value="currentThemeConfig[fieldName].value"
                                                            :class="`ct-field-id-${fieldName} ct-theme-manager-detail__inherit-wrapper-media`"
                                                            :has-parent="theme.baseConfig?.fields?.[fieldName] == null"
                                                            :inherited-value="baseThemeConfig[fieldName].value"
                                                            :label="getFieldLabel(field, fieldName)"
                                                            :help-text="getHelpText(field)"
                                                            :custom-inheritation-check-function="
                                                                checkInheritanceFunction(fieldName)
                                                            "
                                                            @update:value="handleInheritanceInput($event, fieldName)"
                                                        >
                                                            <template
                                                                #content="{
                                                                    currentValue,
                                                                    updateCurrentValue,
                                                                    isInherited,
                                                                    removeInheritance,
                                                                }"
                                                            >
                                                                <ct-media-upload-v2
                                                                    :ref="
                                                                        tabName +
                                                                        '-' +
                                                                        blockName +
                                                                        '-' +
                                                                        sectionName +
                                                                        '-' +
                                                                        fieldName
                                                                    "
                                                                    :source="currentValue"
                                                                    :upload-tag="
                                                                        tabName +
                                                                        '-' +
                                                                        blockName +
                                                                        '-' +
                                                                        sectionName +
                                                                        '-' +
                                                                        fieldName
                                                                    "
                                                                    :default-folder="themeRepository.schema.entity"
                                                                    :allow-multi-select="false"
                                                                    :disabled="!acl.can('theme.editor')"
                                                                    @media-drop="
                                                                        onDropMedia($event, currentThemeConfig[fieldName])
                                                                    "
                                                                    @media-upload-sidebar-open="onOpenMediaModal(fieldName)"
                                                                    @media-upload-remove-image="
                                                                        removeMediaItem(
                                                                            fieldName,
                                                                            updateCurrentValue,
                                                                            isInherited,
                                                                            removeInheritance,
                                                                        )
                                                                    "
                                                                />
                                                            </template>
                                                        </ct-inherit-wrapper>
                                                    </div>

                                                    <template v-else-if="mapCtFieldTypes(field.type)">
                                                        <ct-inherit-wrapper
                                                            :ref="`wrapper-${fieldName}`"
                                                            v-model:value="currentThemeConfig[fieldName].value"
                                                            :class="'ct-field-id-' + fieldName"
                                                            :has-parent="theme.baseConfig?.fields?.[fieldName] == null"
                                                            :inherited-value="baseThemeConfig[fieldName].value"
                                                            :label="getFieldLabel(field, fieldName)"
                                                            :help-text="getHelpText(field)"
                                                            :custom-inheritation-check-function="
                                                                checkInheritanceFunction(fieldName)
                                                            "
                                                            @update:value="handleInheritanceInput($event, fieldName)"
                                                        >
                                                            <template
                                                                #content="{ currentValue, updateCurrentValue, isInherited }"
                                                            >
                                                                <mt-colorpicker
                                                                    v-if="mapCtFieldTypes(field.type) === 'colorpicker'"
                                                                    :disabled="isInherited || !acl.can('theme.editor')"
                                                                    :model-value="currentValue"
                                                                    :error="themeConfigErrors[fieldName]"
                                                                    :z-index="100"
                                                                    @update:model-value="updateCurrentValue"
                                                                />

                                                                <mt-text-field
                                                                    v-else-if="
                                                                        mapCtFieldTypes(field.type) === 'text' ||
                                                                        mapCtFieldTypes(field.type) === null
                                                                    "
                                                                    :disabled="isInherited || !acl.can('theme.editor')"
                                                                    :model-value="cssValue(currentValue)"
                                                                    :error="themeConfigErrors[fieldName]"
                                                                    @update:model-value="updateCurrentValue"
                                                                />
                                                            </template>
                                                        </ct-inherit-wrapper>
                                                    </template>

                                                    <template v-else>
                                                        <ct-inherit-wrapper
                                                            :ref="`wrapper-${fieldName}`"
                                                            v-model:value="currentThemeConfig[fieldName].value"
                                                            :class="'ct-field-id-' + fieldName"
                                                            :has-parent="theme.baseConfig?.fields?.[fieldName] == null"
                                                            :inherited-value="baseThemeConfig[fieldName].value"
                                                            :label="getFieldLabel(field, fieldName)"
                                                            :help-text="getHelpText(field)"
                                                            :custom-inheritation-check-function="
                                                                checkInheritanceFunction(fieldName)
                                                            "
                                                            @update:value="handleInheritanceInput($event, fieldName)"
                                                        >
                                                            <template #content="inheritance">
                                                                <ct-form-field-renderer
                                                                    v-bind="
                                                                        getBind(
                                                                            field,
                                                                            inheritance,
                                                                            baseThemeConfig[fieldName].value,
                                                                        )
                                                                    "
                                                                    :value="inheritance.currentValue"
                                                                    :disabled="
                                                                        inheritance.isInherited || !acl.can('theme.editor')
                                                                    "
                                                                    :error="themeConfigErrors[fieldName]"
                                                                    bordered
                                                                    v-on="getElementEventListeners(field, inheritance)"
                                                                    @update:value="inheritance.updateCurrentValue"
                                                                />
                                                            </template>
                                                        </ct-inherit-wrapper>
                                                    </template>
                                                </div>
                                            </template>
                                        </ct-container>
                                    </ct-card-section>
                                </mt-card>
                            </template>
                        </template>
                    </div>

                    <ct-block name="ct_theme_manager_reset_modal">
                        <ct-modal
                            v-if="showResetModal && theme"
                            variant="small"
                            :title="$t('ct-theme-manager.modal.modalTitleReset')"
                            @modal-close="onCloseResetModal"
                        >
                            <ct-block name="ct_theme_manager_reset_modal_reset_text">
                                <p class="ct_theme_manager__confirm-reset-text">
                                    {{ $t('ct-theme-manager.modal.modalTextResetInfo') }}
                                </p>
                                <p v-if="theme.channels.length > 0" class="ct_theme_manager__confirm-reset-text">
                                    {{ $t('ct-theme-manager.modal.modalTextResetAssigned') }}
                                </p>
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="ct_theme_manager_reset_modal_footer">
                                    <ct-block name="ct_theme_manager_reset_modal_cancel">
                                        <mt-button size="small" variant="primary" @click="onCloseResetModal">
                                            {{ $t('global.default.cancel') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="ct_theme_manager_reset_modal_confirm">
                                        <mt-button size="small" variant="critical" @click="onConfirmThemeReset">
                                            {{ $t('ct-theme-manager.actions.buttonReset') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>

                    <ct-block name="ct_theme_manager_detail_delete_modal">
                        <ct-modal
                            v-if="showDeleteModal && theme"
                            :title="$t('global.default.warning')"
                            variant="small"
                            @modal-close="onCloseDeleteModal"
                        >
                            <ct-block name="ct_theme_manager_detail_delete_modal_info">
                                <div class="ct_theme_manager__confirm-delete-text">
                                    {{ $t('ct-theme-manager.modal.textDeleteInfo', { themeName: theme.name }) }}
                                </div>
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="ct_theme_manager_detail_delete_modal_footer">
                                    <ct-block name="ct_theme_manager_detail_delete_modal_cancel">
                                        <mt-button variant="secondary" size="small" @click="onCloseDeleteModal">
                                            {{ $t('global.default.cancel') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="ct_theme_manager_detail_delete_modal_confirm">
                                        <mt-button variant="critical" size="small" @click="onConfirmThemeDelete">
                                            {{ $t('global.default.delete') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>

                    <ct-block name="ct_theme_manager_detail_save_modal">
                        <ct-modal
                            v-if="showSaveModal && theme && defaultTheme"
                            class="ct-theme-manager-detail-modal"
                            variant="large"
                            :title="$t('ct-theme-manager.modal.modalTitleSave')"
                            @modal-close="onCloseSaveModal"
                        >
                            <ct-block name="ct_theme_manager_detail_save_modal_info">
                                <div class="ct_theme_manager__confirm-save-text">
                                    {{ $t('ct-theme-manager.modal.textSaveInfo', { themeName: theme.name }) }}
                                </div>
                            </ct-block>

                            <ct-block name="ct_theme_manager_detail_save_modal_already_assigned_warning">
                                <mt-banner v-if="overwrittenChannelAssignments.length > 0" variant="attention">
                                    <div
                                        v-if="overwrittenChannelAssignments.length === 1"
                                        class="ct-theme-manager-detail__channel-warning"
                                    >
                                        <span
                                            v-html="
                                                $t('ct-theme-manager.modal.channelAlreadyAssignedModal.descriptionSingle', {
                                                    newThemeName: theme.name,
                                                })
                                            "
                                        ></span>
                                    </div>

                                    <div v-else class="ct-theme-manager-detail__channel-warning">
                                        <span
                                            v-html="
                                                $t(
                                                    'ct-theme-manager.modal.channelAlreadyAssignedModal.descriptionMultiple',
                                                    {
                                                        newThemeName: theme.name,
                                                    },
                                                )
                                            "
                                        ></span>
                                    </div>

                                    <div v-for="channel in overwrittenChannelAssignments" :key="channel.id">
                                        <b>{{ channel.oldThemeName }}</b>
                                        ({{ channel.channelName }})
                                    </div>
                                </mt-banner>
                            </ct-block>

                            <ct-block name="ct_theme_manager_detail_save_modal_removed_warning">
                                <mt-banner v-if="removedChannels.length > 0" variant="attention">
                                    <div
                                        v-if="removedChannels.length === 1"
                                        class="ct-theme-manager-detail__channel-warning"
                                    >
                                        <span
                                            v-html="
                                                $t('ct-theme-manager.modal.channelRemovedModal.descriptionSingle', {
                                                    defaultThemeName: defaultTheme.name,
                                                })
                                            "
                                        ></span>
                                    </div>

                                    <div v-else class="ct-theme-manager-detail__channel-warning">
                                        <span
                                            v-html="
                                                $t('ct-theme-manager.modal.channelRemovedModal.descriptionMultiple', {
                                                    defaultThemeName: defaultTheme.name,
                                                })
                                            "
                                        ></span>
                                    </div>

                                    <div v-for="channel in removedChannels" :key="channel.id">
                                        <b>{{ theme.name }}</b> ({{ channel.name }})
                                    </div>
                                </mt-banner>
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="ct_theme_manager_detail_save_modal_footer">
                                    <ct-block name="ct_theme_manager_detail_save_modal_cancel">
                                        <mt-button variant="secondary" size="small" @click="onCloseSaveModal">
                                            {{ $t('global.default.cancel') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="ct_theme_manager_detail_save_modal_confirm">
                                        <mt-button variant="primary" size="small" @click="onConfirmThemeSave">
                                            {{ $t('global.default.save') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>

                    <ct-block name="ct_theme_manager_detail_duplicate_modal">
                        <ct-modal
                            v-if="showDuplicateModal"
                            class="ct_theme_manager__duplicate-modal"
                            :title="$t('ct-theme-manager.modal.modalTitleDuplicate')"
                            variant="small"
                            @modal-close="onCloseDuplicateModal"
                        >
                            <ct-block name="ct_theme_manager_detail_duplicate_modal_name_input">
                                <div class="ct_theme_manager__duplicate-info">
                                    {{ $t('ct-theme-manager.modal.textDuplicateInfo') }}
                                </div>

                                <mt-text-field
                                    v-model="newThemeName"
                                    name="ct-field--duplicate-theme-name"
                                    :label="$t('ct-theme-manager.modal.labelDuplicateThemeName')"
                                    :placeholder="$t('ct-theme-manager.modal.placeholderDuplicateThemeName')"
                                />
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="ct_theme_manager_detail_duplicate_modal_footer">
                                    <ct-block name="ct_theme_manager_detail_duplicate_modal_cancel">
                                        <mt-button variant="primary" size="small" @click="onCloseDuplicateModal">
                                            {{ $t('global.default.cancel') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="ct_theme_manager_detail_duplicate_modal_confirm">
                                        <mt-button
                                            variant="primary"
                                            :disabled="newThemeName.length < 3"
                                            size="small"
                                            @click="onConfirmThemeDuplicate"
                                        >
                                            {{ $t('global.default.duplicate') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>

                    <ct-block name="ct_theme_manager_detail_rename_modal">
                        <ct-modal
                            v-if="showRenameModal"
                            class="ct_theme_manager__rename-modal"
                            :title="$t('ct-theme-manager.modal.modalTitleRename')"
                            variant="small"
                            @modal-close="onCloseRenameModal"
                        >
                            <ct-block name="ct_theme_manager_detail_rename_modal_name_input">
                                <div class="ct_theme_manager__rename-info">
                                    {{ $t('ct-theme-manager.modal.textRenameInfo') }}
                                </div>

                                <mt-text-field
                                    v-model="newThemeName"
                                    name="ct-field--rename-theme-name"
                                    :label="$t('ct-theme-manager.modal.labelRenameThemeName')"
                                    :placeholder="$t('ct-theme-manager.modal.placeholderRenameThemeName')"
                                />
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="ct_theme_manager_detail_rename_modal_footer">
                                    <ct-block name="ct_theme_manager_detail_rename_modal_cancel">
                                        <mt-button variant="secondary" size="small" @click="onCloseRenameModal">
                                            {{ $t('global.default.cancel') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="ct_theme_manager_detail_rename_modal_confirm">
                                        <mt-button
                                            variant="primary"
                                            :disabled="newThemeName.length < 3"
                                            size="small"
                                            @click="onConfirmThemeRename"
                                        >
                                            {{ $t('global.default.save') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>

                    <ct-block name="ct_theme_manager_detail_error_modal">
                        <ct-modal
                            v-if="errorModalMessage"
                            :title="$t('ct-theme-manager.modal.errorModalTitle')"
                            variant="large"
                            @modal-close="onCloseErrorModal"
                        >
                            <ct-block name="ct_theme_manager_detail_error_modal_message">
                                <pre style="white-space: pre-line">{{ errorModalMessage }}</pre>
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="ct_theme_manager_detail_error_modal_footer">
                                    <ct-block name="ct_theme_manager_detail_error_modal_close">
                                        <mt-button variant="secondary" size="small" @click="onCloseErrorModal">
                                            {{ $t('global.default.close') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>

                    <ct-block name="ct_theme_manager_detail_media_modal">
                        <ct-media-modal-v2
                            v-if="showMediaModal"
                            :initial-folder-id="defaultMediaFolderId"
                            :allow-multi-select="false"
                            @media-modal-selection-change="onMediaChange"
                            @modal-close="onCloseMediaModal"
                        />
                    </ct-block>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-theme-manager-detail.scss';
const Criteria = Contena.Data.Criteria;
const { mapInheritanceSlotPropsToMeteorProps } = Contena.Utils;
const { getObjectDiff, cloneDeep, deepMergeObject } = Contena.Utils.object;
const { isArray } = Contena.Utils.types;

import { computed, getCurrentInstance, inject, ref, shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import { useTheme, type ThemeEntity } from '../../composable/use-theme';

interface ThemeConfigOption extends Record<string, unknown> {
    label?: string | null;
    labelSnippetKey: string;
}

interface ThemeFieldCustomConfig extends Record<string, unknown> {
    componentName?: string;
    options?: ThemeConfigOption[];
}

interface ThemeField extends Record<string, unknown> {
    type: string;
    value?: unknown;
    isInherited?: boolean;
    editable?: boolean;
    fullWidth?: boolean;
    labelSnippetKey?: string;
    helpTextSnippetKey?: string;
    custom?: ThemeFieldCustomConfig;
}

type ThemeConfig = Record<string, ThemeField>;

interface ThemeSection {
    labelSnippetKey: string;
    fields: Record<string, ThemeField>;
}

interface ThemeBlock {
    labelSnippetKey: string;
    sections: Record<string, ThemeSection>;
}

interface ThemeTab {
    labelSnippetKey: string;
    blocks: Record<string, ThemeBlock>;
}

interface StructuredThemeFields {
    tabs: Record<string, ThemeTab>;
    configInheritance?: string[];
    themeTechnicalName: string;
}

interface ThemeBaseConfig extends Record<string, unknown> {
    configInheritance?: string[];
    fields?: Record<string, unknown>;
}

type DetailTheme = Omit<ThemeEntity, 'baseConfig' | 'configValues' | 'channels'> & {
    baseConfig?: ThemeBaseConfig | null;
    configValues?: Record<string, unknown> | null;
    channels: EntityCollection<'channel'>;
};

interface InheritanceContext extends Record<string, unknown> {
    value: unknown;
    currentValue?: unknown;
    isInheritField?: boolean;
    isInherited: boolean;
    updateCurrentValue: (value: unknown) => void;
    removeInheritance: (value?: unknown) => void;
    restoreInheritance: (value?: unknown) => void;
}

interface InheritanceWrapper {
    isInherited: boolean;
}

interface MediaUploadResult {
    targetId: string;
}

interface AddedChannelChange {
    id: string;
}

interface RemovedChannelChange {
    key: string;
}

interface OverwrittenChannelAssignment {
    id: string;
    channelName?: string;
    oldThemeName?: string;
}

interface RemovedChannel {
    id: string;
    name?: string;
}

interface ThemeValidationError {
    code?: string;
    detail?: string;
    meta: {
        parameters: Record<string, unknown> & { name?: string };
    };
    parameters?: Record<string, unknown>;
}

interface ThemeApiError {
    response?: {
        data?: {
            errors?: ThemeValidationError[];
        };
    };
}

interface ThemeService {
    getStructuredFields(themeId: string): Promise<StructuredThemeFields>;
    getConfiguration(themeId: string): Promise<{
        currentFields: ThemeConfig;
        fields: ThemeConfig;
        baseThemeFields: ThemeConfig;
    }>;
    resetTheme(themeId: string): Promise<void>;
    validateFields(config: Record<string, unknown>): Promise<void>;
    updateTheme(themeId: string, payload: Record<string, unknown>, options: Record<string, boolean>): Promise<void>;
    assignTheme(themeId: string, channelId: string): Promise<void>;
}

const route = useRoute();
const { t } = useI18n();
const componentInstance = getCurrentInstance();

defineOptions({
    metaInfo(this: { $createTitle: (identifier?: string | null) => string; themeName?: string }) {
        return {
            title: this.$createTitle(this.themeName),
        };
    },
});

const $t = t;
const injectedRepositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const injectedThemeService = inject<ThemeService>('themeService');
const injectedAcl = inject<AclService>('acl');
if (!injectedRepositoryFactory || !injectedThemeService || !injectedAcl) {
    throw new Error('The repositoryFactory, themeService and acl services are required.');
}

const repositoryFactory = injectedRepositoryFactory;
const themeService = injectedThemeService;
const acl = injectedAcl;

const { createNotificationError, createNotificationSuccess } = useNotification();

const theme = shallowRef<DetailTheme | null>(null);
const parentTheme = shallowRef<ThemeEntity | null>(null);
const inheritedSnippetPrefixes = ref<string[]>([]);
const defaultMediaFolderId = ref<string | null>(null);
const structuredThemeFields = ref<StructuredThemeFields>({ tabs: {}, themeTechnicalName: '' });
const themeConfig = ref<ThemeConfig>({});
const currentThemeConfig = ref<ThemeConfig>({});
const showResetModal = ref(false);
const showSaveModal = ref(false);
const errorModalMessage = ref<string | null>(null);
const baseThemeConfig = ref<ThemeConfig>({});
const currentThemeConfigInitial = ref<ThemeConfig>({});
const inheritanceChanged = ref<Record<string, boolean>>({});
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const mappedFields = ref<Record<string, string>>({
    color: 'colorpicker',
    fontFamily: 'text',
});
const defaultTheme = shallowRef<ThemeEntity | null>(null);
const themeCompatibleChannels = ref<string[]>([]);
const channelsWithTheme = ref<EntityCollection<'channel'> | null>(null);
const newAssignedChannels = ref<string[]>([]);
const overwrittenChannelAssignments = ref<OverwrittenChannelAssignment[]>([]);
const removedChannels = ref<RemovedChannel[]>([]);
const localShowMediaModal = ref(false);
const activeMediaField = ref<string | null>(null);
const activeTab = ref('default');
const themeConfigErrors = ref<Record<string, ThemeValidationError>>({});
const term = ref<string | null>(null);
const truncateFilter = computed(() => Contena.Filter.getByName('truncate') as (value: string, length: number) => string);
const mediaRepository = computed(() => repositoryFactory.create('media'));
const defaultFolderRepository = computed(() => repositoryFactory.create('media_default_folder'));
const channelRepository = computed(() => repositoryFactory.create('channel'));
const themeApi = useTheme({ isLoading });
const { themeRepository } = themeApi;

const themeName = computed(() => {
    if (theme.value) {
        return theme.value.name;
    }

    return '';
});
const isDerived = computed(() => {
    if (!theme.value) {
        return false;
    }
    if (theme.value.technicalName === 'Frontend') {
        return false;
    }
    if (parentTheme.value) {
        return true;
    }
    if (
        isArray(theme.value?.baseConfig?.configInheritance) &&
        !theme.value.baseConfig.configInheritance.includes('@Frontend')
    ) {
        return false;
    }
    return true;
});
const previewMedia = computed(() => {
    if (theme.value && theme.value.previewMedia && theme.value.previewMedia.id && theme.value.previewMedia.url) {
        return {
            'background-image': `url('${theme.value.previewMedia.url}')`,
            'background-size': 'cover',
        };
    }

    return {
        'background-image': defaultThemeAsset.value,
    };
});
const defaultThemeAsset = computed(() => {
    const assetFilter = Contena.Filter.getByName('asset');
    const previewUrl = assetFilter('administration/static/img/theme/default_theme_preview.jpg');

    return `url(${previewUrl})`;
});
const deleteDisabledToolTip = computed(() => {
    return {
        showDelay: 300,
        message: t('ct-theme-manager.actions.deleteDisabledToolTip'),
        disabled: (theme.value?.channels?.length ?? 0) === 0,
    };
});
const themeId = computed<string | null>(() => {
    return typeof route.params.id === 'string' ? route.params.id : null;
});
const shouldShowContent = computed(() => {
    return Object.values(structuredThemeFields.value).length > 0 && !isLoading.value;
});
const isDefaultTheme = computed(() => {
    return Boolean(theme.value && defaultTheme.value && theme.value.id === defaultTheme.value.id);
});
const orderedTabs = computed(() => {
    const tabs = structuredThemeFields.value?.tabs || {};
    if (!Object.prototype.hasOwnProperty.call(tabs, 'default')) {
        return tabs;
    }

    const { default: defaultTab, ...nonDefaultTabs } = tabs;
    return {
        default: defaultTab,
        ...nonDefaultTabs,
    };
});
const tabItems = computed(() => {
    const entries = Object.entries(orderedTabs.value);

    return entries.map(
        ([
            name,
            tab,
        ]) => ({
            name,
            label: getTabLabel(tab.labelSnippetKey) || name,
        }),
    );
});

const cssValue = (value: unknown): string => {
    // Be careful what to filter here because many characters are allowed
    if (!value) {
        return '';
    }

    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return '';
    }

    return value.toString().replace(/`|´/g, '');
};
const checkInheritanceFunction = (fieldName: string): (() => boolean | undefined) => {
    return () => currentThemeConfig.value[fieldName].isInherited;
};
const handleInheritanceInput = (value: unknown, fieldName: string): void => {
    currentThemeConfig.value[fieldName].isInherited = value === null;
};
const onAddMediaToTheme = (mediaItem: Pick<Entity<'media'>, 'id'>, context: ThemeField): void => {
    setMediaItem(mediaItem, context);
};
const onDropMedia = (dragData: Pick<Entity<'media'>, 'id'>, context: ThemeField): void => {
    setMediaItem(dragData, context);
};
const setMediaItem = (mediaItem: Pick<Entity<'media'>, 'id'>, context: ThemeField): void => {
    context.value = mediaItem.id;
};
const removeMediaItem = (
    field: string,
    updateCurrentValue: (value: unknown) => void,
    isInherited: boolean,
    removeInheritance: (value: unknown) => void,
): void => {
    currentThemeConfig.value[field].value = null;
    themeConfig.value[field].value = null;
    if (isInherited) {
        updateCurrentValue(null);
    } else {
        removeInheritance(null);
    }
    currentThemeConfigInitial.value[field].value = false;
};
const onReset = () => {
    if (!acl.can('theme.editor')) {
        return;
    }

    if (!theme.value || theme.value.configValues === null) {
        return;
    }

    showResetModal.value = true;
};
const onCloseResetModal = () => {
    showResetModal.value = false;
};
const onCloseErrorModal = () => {
    errorModalMessage.value = null;
};
const onCloseSaveModal = () => {
    showSaveModal.value = false;
};
const getCurrentChangeset = (clean = false): Record<string, unknown> => {
    if (!theme.value) {
        return {};
    }

    // Get actual changes since load, then merge the changes into the full config set
    const newValues = getObjectDiff(currentThemeConfigInitial.value, currentThemeConfig.value);
    const allValues = theme.value.configValues ?? {};
    Object.assign(allValues, newValues);
    if (!clean) {
        return allValues;
    }

    // Remove unused fields from changeset (defined by not set at all in the themeConfig or the type is not set)
    const filtered: Record<string, unknown> = {};
    for (const [
        key,
        value,
    ] of Object.entries(allValues)) {
        if (
            themeConfig.value[key] === undefined ||
            themeConfig.value[key].type === undefined ||
            themeConfig.value[key].type === null
        ) {
            continue;
        }
        filtered[key] = value;
    }

    return filtered;
};
const getInheritanceWrapper = (key: string): InheritanceWrapper | null => {
    const wrapper = componentInstance?.proxy?.$refs[`wrapper-${key}`];
    if (!isArray(wrapper) || wrapper[0] === undefined) {
        return null;
    }

    return wrapper[0] as InheritanceWrapper;
};
const removeInheritedFromChangeset = (allValues: Record<string, unknown>): void => {
    for (const key of Object.keys(allValues)) {
        const wrapper = getInheritanceWrapper(key);
        if (wrapper?.isInherited) {
            // Remove fields which are set to inheritance
            delete allValues[`${key}`];
            continue;
        }
        if (
            !wrapperIsVisible(key) &&
            inheritanceChanged.value[`wrapper-${key}`] !== undefined &&
            inheritanceChanged.value[`wrapper-${key}`] === true
        ) {
            delete allValues[`${key}`];
        }
    }
};
const wrapperIsVisible = (key: string): boolean => {
    return getInheritanceWrapper(key) !== null;
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const onChangeTab = (activeTabValue: string | null = null): void => {
    if (typeof activeTabValue === 'string') {
        activeTab.value = activeTabValue;
    }
    for (const [
        key,
        item,
    ] of Object.entries(componentInstance?.proxy?.$refs ?? {})) {
        if (key.startsWith('wrapper-') && item !== undefined && isArray(item) && item[0] !== undefined) {
            inheritanceChanged.value[key] = (item[0] as InheritanceWrapper).isInherited;
        }
    }
};
const mapCtFieldTypes = (field: string): string | null => {
    return !mappedFields.value[field] ? null : mappedFields.value[field];
};
const getBind = (
    field: ThemeField,
    inheritance: InheritanceContext | null = null,
    inheritedValue: unknown = null,
): { type: string; config: Record<string, unknown> } => {
    const config: Partial<ThemeField> & Record<string, unknown> = Object.assign({}, field);

    if (!isFieldHandlingLabelAndHelpText(field)) {
        config.label = undefined;
        config.labelSnippetKey = undefined;
        config.helpText = undefined;
        config.helpTextSnippetKey = undefined;
    }

    delete config.type;

    if (config.custom) {
        Object.assign(config, config.custom);
    }

    const componentName = config.custom?.componentName;
    if (
        componentName &&
        [
            'ct-single-select',
            'ct-multi-select',
        ].includes(componentName)
    ) {
        config.custom?.options?.forEach((option) => {
            option.label = getSnippet(option.labelSnippetKey);
        });
    }

    if (config.custom?.componentName !== 'ct-switch-field' && config.custom?.componentName !== 'ct-checkbox-field') {
        delete config.custom;
    }

    if (inheritance && isFieldHandlingLabelAndHelpText(field)) {
        Object.assign(config, mapInheritanceSlotPropsToMeteorProps(inheritance, inheritedValue));
        config.mapInheritance = inheritance;
    }

    return { type: field.type, config };
};
const getElementEventListeners = (
    field: ThemeField,
    inheritance: InheritanceContext | null = null,
): Record<string, (value?: unknown) => void> => {
    if (!inheritance || !isFieldHandlingLabelAndHelpText(field)) {
        return {};
    }

    return {
        'inheritance-remove': inheritance.removeInheritance,
        'inheritance-restore': inheritance.restoreInheritance,
    };
};
const getSnippet = (key?: string): string | null => {
    if (!key) {
        return null;
    }

    for (const themeName of inheritedSnippetPrefixes.value) {
        const snippetKey = `ct-theme.${themeName}.${key}`;
        const snippet = t(snippetKey);

        if (snippet !== snippetKey) {
            return snippet;
        }
    }

    return null;
};
const isFieldHandlingLabelAndHelpText = (field: ThemeField): boolean => {
    const componentName = field.custom?.componentName;

    return (
        [
            'switch',
            'checkbox',
        ].includes(field.type) ||
        (componentName !== undefined &&
            [
                'ct-switch-field',
                'ct-checkbox-field',
            ].includes(componentName))
    );
};
const getFieldLabel = (field: ThemeField, fieldName: string): string | null => {
    if (isFieldHandlingLabelAndHelpText(field)) {
        return null;
    }

    const label = getSnippet(field.labelSnippetKey) || '';

    if (label.length < 1 || label === fieldName) {
        return fieldName;
    }

    return label;
};
const getHelpText = (field: ThemeField): string | null => {
    if (isFieldHandlingLabelAndHelpText(field)) {
        return null;
    }

    const helpText = getSnippet(field.helpTextSnippetKey);

    if (typeof helpText === 'string' && helpText.length > 0) {
        return helpText;
    }

    return null;
};
const getTabLabel = (key: string): string => {
    const snippet = getSnippet(key);
    if (typeof snippet === 'string' && snippet.length >= 1) {
        return snippet;
    }

    return t('ct-theme-manager.general.defaultTab');
};
const selectionDisablingMethod = (selection: Entity<'channel'>): boolean => {
    if (!isDefaultTheme.value || !theme.value) {
        return false;
    }

    const origin = theme.value.getOrigin() as DetailTheme;
    return origin.channels.has(selection.id);
};
const isThemeCompatible = (item: Entity<'channel'>): boolean => {
    return themeCompatibleChannels.value.includes(item.id);
};
const onOpenMediaModal = (fieldName: string): void => {
    localShowMediaModal.value = true;
    activeMediaField.value = fieldName;
};
const onCloseMediaModal = () => {
    localShowMediaModal.value = false;
    activeMediaField.value = null;
};
const onMediaChange = (items: Entity<'media'>[]): void => {
    if (!items.length || !activeMediaField.value) {
        return;
    }

    onAddMediaToTheme(items[0], currentThemeConfig.value[activeMediaField.value]);
};

const getTheme = async (): Promise<void> => {
    if (!themeId.value) {
        return;
    }

    isLoading.value = true;
    const criteria = new Criteria();
    criteria.addAssociation('previewMedia');
    criteria.addAssociation('channels');

    try {
        theme.value = (await themeRepository.value.get(themeId.value, Contena.Context.api, criteria)) as DetailTheme;
        await getThemeConfig();
        if (theme.value?.parentThemeId) {
            await getParentTheme();
        }
    } finally {
        isLoading.value = false;
    }
};

const getThemeConfig = async (): Promise<void> => {
    if (!theme.value || !themeId.value) {
        return;
    }

    structuredThemeFields.value = { tabs: {}, themeTechnicalName: '' };
    currentThemeConfig.value = {};
    themeConfig.value = {};
    baseThemeConfig.value = {};
    currentThemeConfigInitial.value = {};

    const fields = await themeService.getStructuredFields(themeId.value);
    structuredThemeFields.value = fields;
    const configInheritance = fields.configInheritance || [];
    inheritedSnippetPrefixes.value = [...configInheritance].reverse().reduce(
        (accumulator, name) => {
            accumulator.push(name.replace('@', ''));
            return accumulator;
        },
        [fields.themeTechnicalName],
    );

    const config = await themeService.getConfiguration(themeId.value);
    currentThemeConfig.value = config.currentFields;
    currentThemeConfigInitial.value = cloneDeep(currentThemeConfig.value);
    themeConfig.value = config.fields;
    baseThemeConfig.value = cloneDeep(config.baseThemeFields);
};

const getParentTheme = async (): Promise<void> => {
    if (theme.value?.parentThemeId) {
        parentTheme.value = (await themeRepository.value.get(theme.value.parentThemeId)) as ThemeEntity;
    }
};

const getDefaultTheme = async (): Promise<ThemeEntity | null> => {
    const criteria = new Criteria();
    criteria.addFilter(Criteria.equals('technicalName', 'Frontend'));
    const response = await themeRepository.value.search(criteria);
    return response.first() as ThemeEntity | null;
};

const getDefaultFolderId = async (): Promise<string | null> => {
    const criteria = new Criteria(1, 1);
    criteria.addAssociation('folder');
    criteria.addFilter(Criteria.equals('entity', themeRepository.value.schema.entity));
    const result = await defaultFolderRepository.value.search(criteria, {
        cacheKey: [
            'media-default-folder',
            themeRepository.value.schema.entity,
        ],
    });
    const folder = result.first()?.folder;
    return folder?.id ?? null;
};

const getThemeCompatibleChannels = async (): Promise<string[]> => {
    const criteria = new Criteria();
    criteria.addAssociation('type');
    criteria.addFilter(
        Criteria.equalsAny('type.name', [
            'Frontend',
            'Headless',
        ]),
    );
    const result = await channelRepository.value.search(criteria);
    return result.getIds();
};

const getChannelsWithTheme = async (): Promise<EntityCollection<'channel'>> => {
    const criteria = new Criteria();
    criteria.addAssociation('themes');
    criteria.addFilter(Criteria.not('or', [Criteria.equals('themes.id', null)]));
    return channelRepository.value.search(criteria);
};

const setPageContext = async (): Promise<void> => {
    [
        defaultTheme.value,
        defaultMediaFolderId.value,
        themeCompatibleChannels.value,
        channelsWithTheme.value,
    ] = await Promise.all([
        getDefaultTheme(),
        getDefaultFolderId(),
        getThemeCompatibleChannels(),
        getChannelsWithTheme(),
    ]);
};

const createdComponent = (): void => {
    void getTheme();
    void setPageContext();
};

const successfulUpload = async (mediaItem: MediaUploadResult, context: ThemeField): Promise<boolean> => {
    const media = await mediaRepository.value.get(mediaItem.targetId);
    if (!media) {
        return false;
    }

    setMediaItem(media, context);
    return true;
};

const onConfirmThemeReset = async (): Promise<void> => {
    if (!acl.can('theme.editor') || !themeId.value) {
        return;
    }
    await themeService.resetTheme(themeId.value);
    showResetModal.value = false;
    await getTheme();
};

const findAddedChannels = (channels: AddedChannelChange[]): void => {
    if (!theme.value) {
        return;
    }

    channels.forEach((channel) => {
        newAssignedChannels.value.push(channel.id);
        const overwrittenChannel = channelsWithTheme.value?.get(channel.id);
        if (overwrittenChannel !== null && overwrittenChannel !== undefined) {
            const assignedChannel = theme.value?.channels.get(channel.id);
            const overwrittenThemes = overwrittenChannel.extensions?.themes as { name?: string }[] | undefined;
            overwrittenChannelAssignments.value.push({
                id: channel.id,
                channelName: assignedChannel?.translated?.name,
                oldThemeName: overwrittenThemes?.[0]?.name,
            });
        }
    });
};

const findRemovedChannels = (channels: RemovedChannelChange[]): void => {
    if (!theme.value) {
        return;
    }

    const origin = theme.value.getOrigin() as DetailTheme;
    channels.forEach((channel) => {
        removedChannels.value.push({
            id: channel.key,
            name: origin.channels.get(channel.key)?.translated?.name,
        });
    });
};

const findChangedChannels = (): void => {
    newAssignedChannels.value = [];
    removedChannels.value = [];
    overwrittenChannelAssignments.value = [];
    if (!theme.value) {
        return;
    }

    const diff = themeRepository.value.getSyncChangeset([theme.value as Entity<'theme'>] as EntityCollection<'theme'>);
    const changeset = diff.changeset[0] as { changes?: { channels?: AddedChannelChange[] } } | undefined;
    if (changeset?.changes?.channels) {
        findAddedChannels(changeset.changes.channels);
    }
    if (diff.deletions.length > 0) {
        findRemovedChannels(diff.deletions as RemovedChannelChange[]);
    }
};

const saveChannels = async (): Promise<void> => {
    if (!themeId.value || !defaultTheme.value) {
        return;
    }

    const currentThemeId = themeId.value;
    const defaultThemeId = defaultTheme.value.id;
    const promises = [
        ...newAssignedChannels.value.map((channelId) => themeService.assignTheme(currentThemeId, channelId)),
        ...removedChannels.value.map((channel) => themeService.assignTheme(defaultThemeId, channel.id)),
    ];
    await Promise.all(promises);
};

const saveThemeConfig = async (clean = false): Promise<void> => {
    if (!themeId.value) {
        return;
    }

    const allValues = getCurrentChangeset(clean);
    removeInheritedFromChangeset(allValues);
    await themeService.updateTheme(themeId.value, { config: allValues }, { reset: true, validate: true });
};

const onSaveTheme = async (clean = false): Promise<void> => {
    if (!acl.can('theme.editor')) {
        return;
    }
    isSaveSuccessful.value = false;
    isLoading.value = true;
    try {
        await saveThemeConfig(clean);
        await saveChannels();
        await getTheme();
        themeConfigErrors.value = {};
    } catch (error) {
        const apiError = error as ThemeApiError;
        const validationErrors = apiError.response?.data?.errors ?? [];
        const errorObject = validationErrors[0];
        if (errorObject?.code === 'THEME__COMPILING_ERROR') {
            createNotificationError({
                title: t('ct-theme-manager.detail.error.themeCompile.title'),
                message: t('ct-theme-manager.detail.error.themeCompile.message'),
                autoClose: false,
                actions: [
                    {
                        label: t('ct-theme-manager.detail.showFullError'),
                        method: () => (errorModalMessage.value = errorObject.detail ?? null),
                    },
                ],
            });
        } else if (errorObject?.code === 'THEME__INVALID_SCSS_VAR') {
            createNotificationError({
                title: t('ct-theme-manager.detail.error.invalidConfiguration.title'),
                message: t('ct-theme-manager.detail.error.invalidConfiguration.message'),
                autoClose: true,
            });
            validationErrors.forEach((validationError) => {
                validationError.parameters = validationError.meta.parameters;
                const fieldName = validationError.meta.parameters.name;
                if (fieldName) {
                    themeConfigErrors.value[fieldName] = validationError;
                }
            });
        } else {
            createNotificationError({
                message: errorObject?.detail ?? String(error),
                autoClose: true,
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const onSave = (): Promise<void> | undefined => {
    if (!theme.value) {
        return;
    }

    findChangedChannels();
    if (theme.value.channels.length > 0 || removedChannels.value.length > 0) {
        showSaveModal.value = true;
        return;
    }
    return onSaveTheme();
};
const onSaveClean = (): Promise<void> | undefined => {
    if (!theme.value) {
        return;
    }

    findChangedChannels();
    if (theme.value.channels.length > 0 || removedChannels.value.length > 0) {
        showSaveModal.value = true;
        return;
    }
    return onSaveTheme(true);
};
const onConfirmThemeSave = (): void => {
    void onSaveTheme();
    showSaveModal.value = false;
};
const onSearch = (value = ''): void => {
    term.value = value.length > 0 ? value : null;
};
const onValidate = async (): Promise<void> => {
    if (!acl.can('theme.editor')) {
        return;
    }

    isLoading.value = true;
    const allValues = getCurrentChangeset();
    removeInheritedFromChangeset(allValues);
    try {
        await themeService.validateFields(deepMergeObject(themeConfig.value, allValues));
        createNotificationSuccess({
            title: t('ct-theme-manager.detail.validate.success'),
            message: t('ct-theme-manager.detail.validate.successMessage'),
            autoClose: true,
        });
    } catch (error) {
        const apiError = error as ThemeApiError;
        const errorObject = apiError.response?.data?.errors?.[0];

        if (errorObject?.code === 'THEME__INVALID_SCSS_VAR') {
            createNotificationError({
                title: t('ct-theme-manager.detail.validate.failed'),
                message: t('ct-theme-manager.detail.validate.failedMessage'),
                autoClose: false,
                actions: [
                    {
                        label: t('ct-theme-manager.detail.showFullError'),
                        method: () => (errorModalMessage.value = errorObject.detail ?? null),
                    },
                ],
            });

            return;
        }

        createNotificationError({
            message: errorObject?.detail ?? String(error),
            autoClose: true,
        });
    } finally {
        isLoading.value = false;
    }
};

createdComponent();
watch(themeId, () => void getTheme());

const {
    showMediaModal,
    showDeleteModal,
    showRenameModal,
    showDuplicateModal,
    newThemeName,
    modalTheme,
    onDeleteTheme,
    onCloseDeleteModal,
    onConfirmThemeDelete,
    deleteTheme,
    onDuplicateTheme,
    onCloseDuplicateModal,
    onConfirmThemeDuplicate,
    duplicateTheme,
    onRenameTheme,
    onCloseRenameModal,
    onConfirmThemeRename,
    RenameTheme,
} = themeApi;

ctDefinePublic({
    acl,
    theme,
    parentTheme,
    inheritedSnippetPrefixes,
    defaultMediaFolderId,
    structuredThemeFields,
    themeConfig,
    currentThemeConfig,
    showResetModal,
    showSaveModal,
    errorModalMessage,
    baseThemeConfig,
    currentThemeConfigInitial,
    inheritanceChanged,
    isLoading,
    isSaveSuccessful,
    mappedFields,
    defaultTheme,
    themeCompatibleChannels,
    channelsWithTheme,
    newAssignedChannels,
    overwrittenChannelAssignments,
    removedChannels,
    activeMediaField,
    activeTab,
    themeConfigErrors,
    term,
    truncateFilter,
    themeName,
    isDerived,
    previewMedia,
    defaultThemeAsset,
    deleteDisabledToolTip,
    themeId,
    shouldShowContent,
    isDefaultTheme,
    orderedTabs,
    tabItems,
    cssValue,
    checkInheritanceFunction,
    handleInheritanceInput,
    onAddMediaToTheme,
    onDropMedia,
    setMediaItem,
    removeMediaItem,
    onReset,
    onCloseResetModal,
    onCloseErrorModal,
    onCloseSaveModal,
    mediaRepository,
    defaultFolderRepository,
    channelRepository,
    themeRepository,
    createNotificationError,
    createNotificationSuccess,
    createdComponent,
    getTheme,
    getThemeConfig,
    setPageContext,
    getParentTheme,
    successfulUpload,
    onConfirmThemeReset,
    onSave,
    onSaveClean,
    onConfirmThemeSave,
    onValidate,
    onSaveTheme,
    saveChannels,
    findChangedChannels,
    saveThemeConfig,
    onSearch,
    getThemeCompatibleChannels,
    getChannelsWithTheme,
    getDefaultFolderId,
    getDefaultTheme,
    findAddedChannels,
    findRemovedChannels,
    getCurrentChangeset,
    removeInheritedFromChangeset,
    wrapperIsVisible,
    saveFinish,
    onChangeTab,
    mapCtFieldTypes,
    getBind,
    getElementEventListeners,
    getSnippet,
    isFieldHandlingLabelAndHelpText,
    getFieldLabel,
    getHelpText,
    getTabLabel,
    selectionDisablingMethod,
    isThemeCompatible,
    onOpenMediaModal,
    onCloseMediaModal,
    onMediaChange,
    showMediaModal,
    showDeleteModal,
    showRenameModal,
    showDuplicateModal,
    newThemeName,
    modalTheme,
    onDeleteTheme,
    onCloseDeleteModal,
    onConfirmThemeDelete,
    deleteTheme,
    onDuplicateTheme,
    onCloseDuplicateModal,
    onConfirmThemeDuplicate,
    duplicateTheme,
    onRenameTheme,
    onCloseRenameModal,
    onConfirmThemeRename,
    RenameTheme,
});
defineExpose({
    acl,
    theme,
    parentTheme,
    inheritedSnippetPrefixes,
    defaultMediaFolderId,
    structuredThemeFields,
    themeConfig,
    currentThemeConfig,
    showResetModal,
    showSaveModal,
    errorModalMessage,
    baseThemeConfig,
    currentThemeConfigInitial,
    inheritanceChanged,
    isLoading,
    isSaveSuccessful,
    mappedFields,
    defaultTheme,
    themeCompatibleChannels,
    channelsWithTheme,
    newAssignedChannels,
    overwrittenChannelAssignments,
    removedChannels,
    showMediaModal,
    activeMediaField,
    activeTab,
    themeConfigErrors,
    truncateFilter,
    themeName,
    isDerived,
    previewMedia,
    defaultThemeAsset,
    deleteDisabledToolTip,
    themeId,
    shouldShowContent,
    isDefaultTheme,
    orderedTabs,
    tabItems,
    cssValue,
    checkInheritanceFunction,
    handleInheritanceInput,
    onAddMediaToTheme,
    onDropMedia,
    setMediaItem,
    removeMediaItem,
    onReset,
    onCloseResetModal,
    onCloseErrorModal,
    onCloseSaveModal,
    mediaRepository,
    defaultFolderRepository,
    channelRepository,
    themeRepository,
    createNotificationError,
    createNotificationSuccess,
    createdComponent,
    getTheme,
    getThemeConfig,
    setPageContext,
    getParentTheme,
    successfulUpload,
    onConfirmThemeReset,
    onSave,
    onSaveClean,
    onConfirmThemeSave,
    onValidate,
    onSaveTheme,
    saveChannels,
    findChangedChannels,
    saveThemeConfig,
    onSearch,
    getThemeCompatibleChannels,
    getChannelsWithTheme,
    getDefaultFolderId,
    getDefaultTheme,
    findAddedChannels,
    findRemovedChannels,
    getCurrentChangeset,
    removeInheritedFromChangeset,
    wrapperIsVisible,
    saveFinish,
    onChangeTab,
    mapCtFieldTypes,
    getBind,
    getElementEventListeners,
    getSnippet,
    isFieldHandlingLabelAndHelpText,
    getFieldLabel,
    getHelpText,
    getTabLabel,
    selectionDisablingMethod,
    isThemeCompatible,
    onOpenMediaModal,
    onCloseMediaModal,
    onMediaChange,
    term,
    showDeleteModal,
    showRenameModal,
    showDuplicateModal,
    newThemeName,
    modalTheme,
    onDeleteTheme,
    onCloseDeleteModal,
    onConfirmThemeDelete,
    deleteTheme,
    onDuplicateTheme,
    onCloseDuplicateModal,
    onConfirmThemeDuplicate,
    duplicateTheme,
    onRenameTheme,
    onCloseRenameModal,
    onConfirmThemeRename,
    RenameTheme,
});
</script>
