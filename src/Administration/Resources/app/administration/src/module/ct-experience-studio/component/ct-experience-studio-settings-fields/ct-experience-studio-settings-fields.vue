<template>
    <ct-block name="sw_experience_studio_settings_fields">
        <div class="ct-experience-studio-settings-fields">
            <mt-collapsible
                v-for="panel in fieldPanels"
                :key="panel.key"
                class="ct-experience-studio-settings-fields__panel"
                :class="{ 'is--plain': !showPanels }"
                :default-open="!showPanels || isPanelExpandedByDefault(panel)"
            >
                <template #default="{ open }">
                    <mt-collapsible-trigger v-if="showPanels" as-child>
                        <div class="ct-experience-studio-settings-fields__panel-header">
                            <span class="ct-experience-studio-settings-fields__panel-title">
                                {{ getPanelTitle(panel) }}
                            </span>
                            <mt-icon
                                name="regular-chevron-right-xs"
                                size="12px"
                                class="ct-experience-studio-settings-fields__panel-indicator"
                                :class="{ 'is--expanded': open }"
                            />
                        </div>
                    </mt-collapsible-trigger>

                    <mt-collapsible-content>
                        <div class="ct-experience-studio-settings-fields__panel-content">
                            <div
                                v-for="field in panel.fields"
                                :key="field.key"
                                class="ct-experience-studio-settings-fields__field"
                            >
                                <template v-if="isBreakpointAwareField(field)">
                                    <div class="ct-experience-studio-settings-fields__responsive-control">
                                        <div
                                            v-if="getControlType(field.property) === 'responsive-number'"
                                            class="ct-experience-studio-settings-fields__responsive-slider"
                                        >
                                            <mt-slider
                                                class="ct-experience-studio-settings-fields__responsive-slider-input"
                                                :label="field.property.title"
                                                :min="getResponsiveLimits(field.property).min"
                                                :max="getResponsiveLimits(field.property).max"
                                                :step="getResponsiveLimits(field.property).step"
                                                :mark-count="0"
                                                :disabled="
                                                    !allowEdit || isResponsiveViewportMode(field.key, field) || undefined
                                                "
                                                :model-value="getResponsiveGlobalValue(field.key, field.property, field)"
                                                @update:model-value="
                                                    onUpdateResponsiveGlobalProperty(
                                                        field.key,
                                                        field.property,
                                                        field,
                                                        $event,
                                                    )
                                                "
                                            />
                                        </div>

                                        <mt-switch
                                            v-else-if="getControlType(field.property) === 'switch'"
                                            v-bind="getControlProps(field.property)"
                                            :label="field.property.title"
                                            :help-text="getPropertyHelpText(field.property)"
                                            :model-value="getResponsiveGlobalValue(field.key, field.property, field)"
                                            :disabled="!allowEdit || isResponsiveViewportMode(field.key, field) || undefined"
                                            @update:model-value="
                                                onUpdateResponsiveGlobalProperty(field.key, field.property, field, $event)
                                            "
                                        />

                                        <mt-select
                                            v-else-if="getControlType(field.property) === 'select'"
                                            v-bind="getControlProps(field.property)"
                                            :label="field.property.title"
                                            :help-text="getPropertyHelpText(field.property)"
                                            :model-value="getResponsiveGlobalValue(field.key, field.property, field)"
                                            :options="getSelectOptions(field.property)"
                                            :disabled="!allowEdit || isResponsiveViewportMode(field.key, field) || undefined"
                                            @update:model-value="
                                                onUpdateResponsiveGlobalProperty(field.key, field.property, field, $event)
                                            "
                                        />

                                        <div
                                            v-else-if="getControlType(field.property) === 'radio-panel'"
                                            class="ct-experience-studio-settings-fields__radio-panel-group mt-field"
                                        >
                                            <label
                                                class="ct-experience-studio-settings-fields__radio-panel-label mt-field__label label"
                                                :for="getRadioPanelLabelTargetId(field.key, field.property, field)"
                                                >{{ field.property.title }}</label
                                            >

                                            <div class="ct-experience-studio-settings-fields__radio-panel-options">
                                                <mt-button
                                                    v-for="option in getRadioPanelOptions(field.property)"
                                                    :id="getRadioPanelOptionId(field.key, option.value)"
                                                    :key="option.value"
                                                    square
                                                    size="small"
                                                    class="ct-experience-studio-settings-fields__radio-panel-option"
                                                    :variant="
                                                        String(
                                                            getResponsiveGlobalValue(field.key, field.property, field) ?? '',
                                                        ) === option.value
                                                            ? 'primary'
                                                            : 'secondary'
                                                    "
                                                    :disabled="
                                                        !allowEdit ||
                                                        isResponsiveViewportMode(field.key, field) ||
                                                        option.disabled ||
                                                        undefined
                                                    "
                                                    :aria-label="option.label"
                                                    :title="option.label"
                                                    @click="
                                                        onUpdateResponsiveGlobalProperty(
                                                            field.key,
                                                            field.property,
                                                            field,
                                                            option.value,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        v-if="option.cornerRadius"
                                                        class="ct-experience-studio-settings-fields__corner-radius-preview"
                                                        :style="{ borderRadius: option.cornerRadius }"
                                                    />
                                                    <mt-icon v-else :name="option.icon || 'regular-square'" size="16px" />
                                                </mt-button>
                                            </div>
                                        </div>

                                        <ct-experience-studio-box-spacing-field
                                            v-else-if="getControlType(field.property) === 'box-spacing'"
                                            :field-key="field.key"
                                            :label="field.property.title"
                                            :help-text="getPropertyHelpText(field.property)"
                                            :model-value="
                                                String(getResponsiveGlobalValue(field.key, field.property, field) ?? '')
                                            "
                                            :disabled="!allowEdit || isResponsiveViewportMode(field.key, field) || undefined"
                                            @update:model-value="
                                                onUpdateResponsiveGlobalProperty(field.key, field.property, field, $event)
                                            "
                                        />

                                        <mt-text-field
                                            v-else
                                            v-bind="getControlProps(field.property)"
                                            :label="field.property.title"
                                            :help-text="getPropertyHelpText(field.property)"
                                            :model-value="getResponsiveGlobalValue(field.key, field.property, field)"
                                            :disabled="!allowEdit || isResponsiveViewportMode(field.key, field) || undefined"
                                            @update:model-value="
                                                onUpdateResponsiveGlobalProperty(field.key, field.property, field, $event)
                                            "
                                        />

                                        <button
                                            type="button"
                                            class="ct-experience-studio-settings-fields__viewport-toggle"
                                            :disabled="!allowEdit"
                                            @click="onToggleResponsiveViewportMode(field.key, field.property, field)"
                                        >
                                            {{ $t('ct-experience-studio.detail.elementSettings.viewportSettings') }}
                                        </button>

                                        <div
                                            v-if="isResponsiveViewportMode(field.key, field)"
                                            class="ct-experience-studio-settings-fields__viewport-settings"
                                        >
                                            <div
                                                v-for="viewport in getResponsiveViewports(field)"
                                                :key="viewport"
                                                class="ct-experience-studio-settings-fields__viewport-row"
                                            >
                                                <mt-icon
                                                    :name="getViewportIcon(viewport)"
                                                    size="16px"
                                                    class="ct-experience-studio-settings-fields__viewport-icon"
                                                />
                                                <span class="ct-experience-studio-settings-fields__viewport-label">{{
                                                    viewport.toUpperCase()
                                                }}</span>

                                                <ct-experience-studio-box-spacing-field
                                                    v-if="getControlType(field.property) === 'box-spacing'"
                                                    class="ct-experience-studio-settings-fields__viewport-control"
                                                    compact
                                                    :field-key="`${field.key}-${viewport}`"
                                                    :model-value="
                                                        String(
                                                            getResponsiveViewportValue(
                                                                field.key,
                                                                viewport,
                                                                field.property,
                                                                field,
                                                            ) ?? '',
                                                        )
                                                    "
                                                    :disabled="!allowEdit || undefined"
                                                    @update:model-value="
                                                        onUpdateResponsiveViewportProperty(
                                                            field.key,
                                                            field.property,
                                                            field,
                                                            viewport,
                                                            $event,
                                                        )
                                                    "
                                                />

                                                <mt-slider
                                                    v-else-if="getControlType(field.property) === 'responsive-number'"
                                                    class="ct-experience-studio-settings-fields__responsive-slider-input"
                                                    label=""
                                                    :min="getResponsiveLimits(field.property).min"
                                                    :max="getResponsiveLimits(field.property).max"
                                                    :step="getResponsiveLimits(field.property).step"
                                                    :mark-count="0"
                                                    :disabled="!allowEdit || undefined"
                                                    :model-value="
                                                        getResponsiveViewportValue(
                                                            field.key,
                                                            viewport,
                                                            field.property,
                                                            field,
                                                        )
                                                    "
                                                    @update:model-value="
                                                        onUpdateResponsiveViewportProperty(
                                                            field.key,
                                                            field.property,
                                                            field,
                                                            viewport,
                                                            $event,
                                                        )
                                                    "
                                                />

                                                <mt-switch
                                                    v-else-if="getControlType(field.property) === 'switch'"
                                                    class="ct-experience-studio-settings-fields__viewport-control"
                                                    remove-top-margin
                                                    :model-value="
                                                        getResponsiveViewportValue(
                                                            field.key,
                                                            viewport,
                                                            field.property,
                                                            field,
                                                        )
                                                    "
                                                    :disabled="!allowEdit || undefined"
                                                    @update:model-value="
                                                        onUpdateResponsiveViewportProperty(
                                                            field.key,
                                                            field.property,
                                                            field,
                                                            viewport,
                                                            $event,
                                                        )
                                                    "
                                                />

                                                <mt-select
                                                    v-else-if="getControlType(field.property) === 'select'"
                                                    class="ct-experience-studio-settings-fields__viewport-control"
                                                    label=""
                                                    small
                                                    :model-value="
                                                        getResponsiveViewportValue(
                                                            field.key,
                                                            viewport,
                                                            field.property,
                                                            field,
                                                        )
                                                    "
                                                    :options="getSelectOptions(field.property)"
                                                    :disabled="!allowEdit || undefined"
                                                    @update:model-value="
                                                        onUpdateResponsiveViewportProperty(
                                                            field.key,
                                                            field.property,
                                                            field,
                                                            viewport,
                                                            $event,
                                                        )
                                                    "
                                                />

                                                <div
                                                    v-else-if="getControlType(field.property) === 'radio-panel'"
                                                    class="ct-experience-studio-settings-fields__radio-panel-options ct-experience-studio-settings-fields__viewport-control"
                                                >
                                                    <mt-button
                                                        v-for="option in getRadioPanelOptions(field.property)"
                                                        :id="getRadioPanelOptionId(`${field.key}-${viewport}`, option.value)"
                                                        :key="option.value"
                                                        square
                                                        size="small"
                                                        class="ct-experience-studio-settings-fields__radio-panel-option"
                                                        :variant="
                                                            String(
                                                                getResponsiveViewportValue(
                                                                    field.key,
                                                                    viewport,
                                                                    field.property,
                                                                    field,
                                                                ) ?? '',
                                                            ) === option.value
                                                                ? 'primary'
                                                                : 'secondary'
                                                        "
                                                        :disabled="!allowEdit || option.disabled || undefined"
                                                        :aria-label="option.label"
                                                        :title="option.label"
                                                        @click="
                                                            onUpdateResponsiveViewportProperty(
                                                                field.key,
                                                                field.property,
                                                                field,
                                                                viewport,
                                                                option.value,
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            v-if="option.cornerRadius"
                                                            class="ct-experience-studio-settings-fields__corner-radius-preview"
                                                            :style="{ borderRadius: option.cornerRadius }"
                                                        />
                                                        <mt-icon
                                                            v-else
                                                            :name="option.icon || 'regular-square'"
                                                            size="16px"
                                                        />
                                                    </mt-button>
                                                </div>

                                                <mt-text-field
                                                    v-else
                                                    class="ct-experience-studio-settings-fields__viewport-control"
                                                    label=""
                                                    size="small"
                                                    :model-value="
                                                        getResponsiveViewportValue(
                                                            field.key,
                                                            viewport,
                                                            field.property,
                                                            field,
                                                        )
                                                    "
                                                    :disabled="!allowEdit || undefined"
                                                    @update:model-value="
                                                        onUpdateResponsiveViewportProperty(
                                                            field.key,
                                                            field.property,
                                                            field,
                                                            viewport,
                                                            $event,
                                                        )
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <mt-switch
                                        v-if="getControlType(field.property) === 'switch'"
                                        v-bind="getControlProps(field.property)"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :mark-count="0"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <mt-number-field
                                        v-else-if="getControlType(field.property) === 'number'"
                                        v-bind="getControlProps(field.property)"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <mt-slider
                                        v-else-if="getControlType(field.property) === 'slider'"
                                        v-bind="getControlProps(field.property)"
                                        class="ct-experience-studio-settings-fields__responsive-slider-input"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <mt-colorpicker
                                        v-else-if="getControlType(field.property) === 'color'"
                                        v-bind="getControlProps(field.property)"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <mt-select
                                        v-else-if="getControlType(field.property) === 'select'"
                                        v-bind="getControlProps(field.property)"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :options="getSelectOptions(field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <div
                                        v-else-if="getControlType(field.property) === 'radio-panel'"
                                        class="ct-experience-studio-settings-fields__radio-panel-group mt-field"
                                    >
                                        <label
                                            class="ct-experience-studio-settings-fields__radio-panel-label mt-field__label label"
                                            :for="getRadioPanelLabelTargetId(field.key, field.property)"
                                            >{{ field.property.title }}</label
                                        >

                                        <div class="ct-experience-studio-settings-fields__radio-panel-options">
                                            <mt-button
                                                v-for="option in getRadioPanelOptions(field.property)"
                                                :id="getRadioPanelOptionId(field.key, option.value)"
                                                :key="option.value"
                                                square
                                                size="small"
                                                class="ct-experience-studio-settings-fields__radio-panel-option"
                                                :variant="
                                                    String(getPropertyValue(field.key, field.property) ?? '') ===
                                                    option.value
                                                        ? 'primary'
                                                        : 'secondary'
                                                "
                                                :disabled="!allowEdit || option.disabled || undefined"
                                                :aria-label="option.label"
                                                :title="option.label"
                                                @click="onUpdateRadioPanelProperty(field.key, option.value)"
                                            >
                                                <span
                                                    v-if="option.cornerRadius"
                                                    class="ct-experience-studio-settings-fields__corner-radius-preview"
                                                    :style="{ borderRadius: option.cornerRadius }"
                                                />
                                                <mt-icon v-else :name="option.icon || 'regular-square'" size="16px" />
                                            </mt-button>
                                        </div>
                                    </div>

                                    <mt-entity-select
                                        v-else-if="
                                            getControlType(field.property) === 'entity' && getEntityName(field.property)
                                        "
                                        v-bind="getControlProps(field.property)"
                                        :entity="getEntityName(field.property)"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <ct-media-field
                                        v-else-if="getControlType(field.property) === 'media'"
                                        v-bind="getControlProps(field.property)"
                                        :label="field.property.title"
                                        :value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:value="onUpdateField(field.key, $event)"
                                    />

                                    <ct-experience-studio-media-collection-field
                                        v-else-if="getControlType(field.property) === 'media-collection'"
                                        v-bind="getControlProps(field.property)"
                                        :label="field.property.title"
                                        :value="getRawPropertyValue(field.key)"
                                        :disabled="!allowEdit || undefined"
                                        @update:value="onUpdateField(field.key, $event)"
                                    />

                                    <div
                                        v-else-if="isInlineTextProperty(field.key, field.property)"
                                        class="ct-experience-studio-settings-fields__inline-text-hint"
                                    >
                                        <div class="ct-experience-studio-settings-fields__inline-text-title">
                                            {{ field.property.title }}
                                        </div>
                                        <div class="ct-experience-studio-settings-fields__inline-text-description">
                                            {{ $t('ct-experience-studio.detail.elementSettings.inlineTextHint') }}
                                        </div>
                                        <div
                                            v-if="isInlineEditingActive"
                                            class="ct-experience-studio-settings-fields__inline-text-state"
                                        >
                                            {{ $t('ct-experience-studio.detail.elementSettings.inlineTextEditingActive') }}
                                        </div>
                                    </div>

                                    <mt-text-editor
                                        v-else-if="getControlType(field.property) === 'richtext'"
                                        v-bind="getControlProps(field.property)"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <ct-experience-studio-box-spacing-field
                                        v-else-if="getControlType(field.property) === 'box-spacing'"
                                        :field-key="field.key"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :model-value="String(getPropertyValue(field.key, field.property) ?? '')"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />

                                    <mt-text-field
                                        v-else
                                        v-bind="getControlProps(field.property)"
                                        :label="field.property.title"
                                        :help-text="getPropertyHelpText(field.property)"
                                        :model-value="getPropertyValue(field.key, field.property)"
                                        :disabled="!allowEdit || undefined"
                                        @update:model-value="onUpdateField(field.key, $event)"
                                    />
                                </template>
                            </div>
                        </div>
                    </mt-collapsible-content>
                </template>
            </mt-collapsible>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import type {
    ContentSystemElementTypeProperty,
    ContentSystemElementTypeSpecification,
} from 'src/core/service/api/content-system-element-type.api.service';
import {
    getAdminUiHelpText,
    getAdminUiProps as getPropertyAdminUiProps,
    getInitialPropertyValue,
    getPropertyControlType,
} from '../../util/element-settings.util';
import { normalizeBoxSpacingCSSValue } from '../../util/box-spacing.util';
import { isViewportSpecificBreakpointMap } from '../../util/style-settings.util';
import './ct-experience-studio-settings-fields.scss';
type PrimitiveValue = string | number | boolean | null | Record<string, unknown>;
type ResponsiveViewport = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'xxl';
type ResponsiveValue = Record<ResponsiveViewport, PrimitiveValue>;
type SettingsFieldPanel = {
    key: string;
    technicalName: string | null;
    fields: SettingsFieldDefinition[];
};
const DEFAULT_PANEL_KEY = '__default__';
const DEFAULT_PANEL_SNIPPET = 'ct-experience-studio.detail.elementSettings.panelGeneral';
function getStructuredPropertyDefault(property: ContentSystemElementTypeProperty): string | number | boolean | null {
    const defaults = Object.values(property.properties ?? {})
        .map((nestedProperty) => nestedProperty.default)
        .filter((value): value is string | number | boolean => value !== null && value !== undefined);

    if (defaults.length === 0 || !defaults.every((value) => value === defaults[0])) {
        return null;
    }

    return defaults[0];
}
type SettingsFieldDefinition = {
    key: string;
    property: ContentSystemElementTypeProperty;
    breakpointAware?: boolean;
};

const props = defineProps({
    fields: {
        type: Array as PropType<SettingsFieldDefinition[]>,
        required: true,
    },
    values: {
        type: Object as PropType<Record<string, unknown>>,
        required: true,
    },
    allowEdit: {
        type: Boolean,
        required: false,
        default: false,
    },
    selectedElementType: {
        type: Object,
        required: false,
        default: null,
    },
    isInlineEditingActive: {
        type: Boolean,
        required: false,
        default: false,
    },
    showInlineTextHints: {
        type: Boolean,
        required: false,
        default: false,
    },
    showPanels: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits(['update-field']);

import { type PropType, ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

// vue-i18n exposes methods bound to its composer; the template and computed state use them as callbacks.
// eslint-disable-next-line @typescript-eslint/unbound-method
const { t, te } = useI18n();
const expandedResponsiveProperties = ref({} as Record<string, boolean>);
const responsiveGlobalSnapshots = ref({} as Record<string, PrimitiveValue>);
const touchedBreakpointAwareProperties = ref({} as Record<string, boolean>);

const fieldPanels = computed(() => {
    if (!props.showPanels) {
        return [
            {
                key: DEFAULT_PANEL_KEY,
                technicalName: null,
                fields: props.fields,
            },
        ];
    }

    const panels = new Map<string, SettingsFieldPanel>();

    for (const field of props.fields) {
        const technicalName = getFieldPanelTechnicalName(field);
        const key = technicalName ?? DEFAULT_PANEL_KEY;
        const panel = panels.get(key);

        if (panel) {
            panel.fields.push(field);
            continue;
        }

        panels.set(key, {
            key,
            technicalName,
            fields: [field],
        });
    }

    return Array.from(panels.values());
});

const getFieldPanelTechnicalName = (field: SettingsFieldDefinition) => {
    const panel = field.property.adminUI?.panel;

    return typeof panel === 'string' && panel.length > 0 ? panel : null;
};
const getPanelSnippetKey = (panel: SettingsFieldPanel) => {
    if (panel.technicalName === null) {
        return DEFAULT_PANEL_SNIPPET;
    }

    const selectedElementType = props.selectedElementType as ContentSystemElementTypeSpecification | null;
    const elementType = Contena.Utils.string.kebabCase(selectedElementType?.name ?? '');

    return `ct-experience-studio.elements.${elementType}.panels.${panel.technicalName}`;
};
const getPanelTitle = (panel: SettingsFieldPanel) => {
    return t(getPanelSnippetKey(panel));
};
const isPanelExpandedByDefault = (panel: SettingsFieldPanel) => {
    return !props.showPanels || panel.technicalName === null || panel.technicalName === 'general';
};
const getControlType = (property: ContentSystemElementTypeProperty) => {
    return getPropertyControlType(property);
};
const isBreakpointAwareField = (field: SettingsFieldDefinition) => {
    if (field.breakpointAware === true) {
        return true;
    }

    return getControlType(field.property) === 'responsive-number';
};
const isInlineTextProperty = (key: string, property: ContentSystemElementTypeProperty) => {
    const selectedElementType = props.selectedElementType as ContentSystemElementTypeSpecification | null;

    if (!props.showInlineTextHints || !selectedElementType || key !== 'text') {
        return false;
    }

    const matchesTextType = selectedElementType.name.endsWith(':text');
    const matchesTextProperty = Boolean(
        selectedElementType.properties.text && getControlType(selectedElementType.properties.text) === 'richtext',
    );

    return (matchesTextType || matchesTextProperty) && getControlType(property) === 'richtext';
};
const getPropertyValue = (key: string, property: ContentSystemElementTypeProperty) => {
    const currentValue = props.values[key];
    const value = getInitialPropertyValue(property, currentValue);

    if (value === null && (getControlType(property) === 'number' || getControlType(property) === 'responsive-number')) {
        return getResponsiveFallbackValue(property);
    }

    return value;
};
const getRawPropertyValue = (key: string) => {
    return props.values[key];
};
const getResponsiveLimits = (property: ContentSystemElementTypeProperty) => {
    const adminProps = getControlProps(property);
    const min = toNumberOrFallback(adminProps.min, 1);
    const max = toNumberOrFallback(adminProps.max, 12);
    const step = toNumberOrFallback(adminProps.step, 1);

    return {
        min,
        max,
        step,
    };
};
const getResponsiveViewports = (field: SettingsFieldDefinition) => {
    if (field.breakpointAware === true) {
        return [
            'xs',
            'sm',
            'md',
            'lg',
            'xl',
            'xxl',
        ];
    }

    return [
        'xs',
        'sm',
        'md',
        'lg',
        'xl',
    ];
};
const getViewportIcon = (viewport: ResponsiveViewport) => {
    if (viewport === 'xs' || viewport === 'sm') {
        return 'regular-mobile';
    }

    if (viewport === 'md') {
        return 'regular-tablet';
    }

    return 'regular-desktop';
};
const isResponsiveViewportMode = (key: string, field: SettingsFieldDefinition) => {
    if (expandedResponsiveProperties.value[key]) {
        return true;
    }

    if (field.breakpointAware === true) {
        return isViewportSpecificBreakpointMap(getRawPropertyValue(key), getResponsiveViewports(field));
    }

    return isBreakpointAwareField(field) && isResponsiveObjectValue(getRawPropertyValue(key));
};
const getResponsiveGlobalValue = (
    key: string,
    property: ContentSystemElementTypeProperty,
    field?: SettingsFieldDefinition,
) => {
    if (field && isResponsiveViewportMode(key, field)) {
        const globalSnapshot = responsiveGlobalSnapshots.value[key];

        if (globalSnapshot !== undefined) {
            return normalizeResponsiveValue(property, globalSnapshot);
        }
    }

    return resolveResponsiveGlobalValueFromStorage(key, property);
};
const resolveResponsiveGlobalValueFromStorage = (key: string, property: ContentSystemElementTypeProperty) => {
    const rawValue = getRawPropertyValue(key);

    if (isResponsiveObjectValue(rawValue)) {
        const fallbackOrder: ResponsiveViewport[] = [
            'xs',
            'sm',
            'md',
            'lg',
            'xl',
            'xxl',
        ];
        for (const viewport of fallbackOrder) {
            const candidate = rawValue[viewport];
            if (candidate !== undefined) {
                return normalizeResponsiveValue(property, candidate);
            }
        }
    }

    if (rawValue !== undefined && !isResponsiveObjectValue(rawValue)) {
        return normalizeResponsiveValue(property, rawValue);
    }

    return getResponsiveFallbackValue(property);
};
const deriveGlobalSnapshotFromBreakpointMap = (
    property: ContentSystemElementTypeProperty,
    field: SettingsFieldDefinition,
    rawValue: Partial<ResponsiveValue>,
) => {
    const resolvedValues = getResponsiveViewports(field).map((viewport) => {
        const viewportValue = rawValue[viewport];

        if (viewportValue !== undefined) {
            return normalizeResponsiveValue(property, viewportValue);
        }

        return getResponsiveFallbackValue(property);
    });

    const firstValue = resolvedValues[0];

    if (resolvedValues.every((value) => value === firstValue)) {
        return firstValue;
    }

    return getResponsiveFallbackValue(property);
};
const getResponsiveViewportValue = (
    key: string,
    viewport: ResponsiveViewport,
    property: ContentSystemElementTypeProperty,
    field: SettingsFieldDefinition,
) => {
    const rawValue = getRawPropertyValue(key);

    if (isResponsiveObjectValue(rawValue)) {
        const viewportValue = rawValue[viewport];
        if (viewportValue !== undefined) {
            return normalizeResponsiveValue(property, viewportValue);
        }

        if (field.breakpointAware === true && isViewportSpecificBreakpointMap(rawValue, getResponsiveViewports(field))) {
            return getResponsiveFallbackValue(property);
        }
    }

    return resolveResponsiveGlobalValueFromStorage(key, property);
};
const syncLoadedResponsiveState = () => {
    for (const field of props.fields) {
        if (field.breakpointAware !== true) {
            continue;
        }

        const rawValue = getRawPropertyValue(field.key);

        if (rawValue === undefined) {
            continue;
        }

        touchedBreakpointAwareProperties.value[field.key] = true;

        if (isViewportSpecificBreakpointMap(rawValue, getResponsiveViewports(field))) {
            expandedResponsiveProperties.value[field.key] = true;

            if (responsiveGlobalSnapshots.value[field.key] === undefined) {
                responsiveGlobalSnapshots.value[field.key] = deriveGlobalSnapshotFromBreakpointMap(
                    field.property,
                    field,
                    rawValue as Partial<ResponsiveValue>,
                );
            }
        }
    }
};
const onToggleResponsiveViewportMode = (
    key: string,
    property: ContentSystemElementTypeProperty,
    field: SettingsFieldDefinition,
) => {
    const nextState = !isResponsiveViewportMode(key, field);

    if (nextState) {
        responsiveGlobalSnapshots.value[key] = resolveResponsiveGlobalValueFromStorage(key, property);
        expandedResponsiveProperties.value[key] = true;

        const currentValue = getRawPropertyValue(key);

        if (isResponsiveObjectValue(currentValue)) {
            return;
        }

        if (field.breakpointAware === true && currentValue === undefined && !touchedBreakpointAwareProperties.value[key]) {
            return;
        }

        const globalValue = responsiveGlobalSnapshots.value[key];
        const responsiveValue = getResponsiveViewports(field).reduce<ResponsiveValue>((accumulator, viewport) => {
            accumulator[viewport] = normalizeResponsiveValue(property, globalValue);

            return accumulator;
        }, {} as ResponsiveValue);

        onUpdateField(key, responsiveValue);

        return;
    }

    const snapshot = responsiveGlobalSnapshots.value[key];
    const globalValue =
        snapshot !== undefined
            ? normalizeResponsiveValue(property, snapshot)
            : resolveResponsiveGlobalValueFromStorage(key, property);

    delete responsiveGlobalSnapshots.value[key];
    expandedResponsiveProperties.value[key] = false;
    persistBreakpointAwareValue(key, property, field, globalValue);
};
const onUpdateResponsiveGlobalProperty = (
    key: string,
    property: ContentSystemElementTypeProperty,
    field: SettingsFieldDefinition,
    rawValue: unknown,
) => {
    if (isResponsiveViewportMode(key, field)) {
        return;
    }

    if (field.breakpointAware === true) {
        touchedBreakpointAwareProperties.value[key] = true;
    }

    const normalizedValue = normalizeResponsiveValue(property, rawValue);
    persistBreakpointAwareValue(key, property, field, normalizedValue);
};
const onUpdateResponsiveViewportProperty = (
    key: string,
    property: ContentSystemElementTypeProperty,
    field: SettingsFieldDefinition,
    viewport: ResponsiveViewport,
    rawValue: unknown,
) => {
    if (field.breakpointAware === true) {
        touchedBreakpointAwareProperties.value[key] = true;
    }

    const value = normalizeResponsiveValue(property, rawValue);
    const current = getRawPropertyValue(key);
    const base = isResponsiveObjectValue(current) ? { ...current } : {};

    if (field.breakpointAware === true && isEffectiveUnsetViewportStyleValue(property, value)) {
        delete base[viewport];
    } else {
        base[viewport] = value;
    }

    if (Object.keys(base).length === 0 || isEffectiveUnsetBreakpointMap(field, property, base)) {
        onUpdateField(key, null);
        return;
    }

    onUpdateField(key, base);
};
const getSelectOptions = (property: ContentSystemElementTypeProperty) => {
    if (!Array.isArray(property.enum)) {
        return [];
    }

    return property.enum.map((value) => ({
        value,
        label: String(value),
    }));
};
const getEntityName = (property: ContentSystemElementTypeProperty) => {
    const entity = property.adminUI?.entity;

    return typeof entity === 'string' && entity.length > 0 ? entity : null;
};
const getControlProps = (property: ContentSystemElementTypeProperty) => {
    return getPropertyAdminUiProps(property);
};
const getPropertyHelpText = (property: ContentSystemElementTypeProperty) => {
    const helpText = getAdminUiHelpText(property);

    if (!helpText) {
        return undefined;
    }

    return te(helpText) ? t(helpText) : helpText;
};
const getRadioPanelOptions = (property: ContentSystemElementTypeProperty) => {
    const adminProps = getControlProps(property);
    const options = adminProps.options;

    if (Array.isArray(options)) {
        return options
            .filter((option): option is Record<string, unknown> => typeof option === 'object' && option !== null)
            .map((option) => {
                const value = typeof option.value === 'string' ? option.value : '';
                const label = typeof option.label === 'string' ? option.label : value;

                return {
                    value,
                    label,
                    icon: typeof option.icon === 'string' ? option.icon : undefined,
                    cornerRadius: typeof option.cornerRadius === 'string' ? option.cornerRadius : undefined,
                    description: typeof option.description === 'string' ? option.description : undefined,
                    disabled: option.disabled === true,
                };
            })
            .filter((option) => option.value.length > 0);
    }

    if (!Array.isArray(property.enum)) {
        return [];
    }

    return property.enum.map((value) => ({
        value: String(value),
        label: String(value),
    }));
};
const getRadioPanelOptionId = (key: string, optionValue: string) => {
    const normalizedKey = key.replace(/[^a-zA-Z0-9_-]/g, '-');
    const normalizedOptionValue = optionValue.replace(/[^a-zA-Z0-9_-]/g, '-');

    return `ct-experience-studio-radio-panel-${normalizedKey}-${normalizedOptionValue}`;
};
const getRadioPanelLabelTargetId = (
    key: string,
    property: ContentSystemElementTypeProperty,
    field?: SettingsFieldDefinition,
) => {
    const options = getRadioPanelOptions(property);

    if (options.length === 0) {
        return undefined;
    }

    const currentValue =
        field && isBreakpointAwareField(field)
            ? getResponsiveGlobalValue(key, property, field)
            : getPropertyValue(key, property);
    const normalizedCurrentValue =
        typeof currentValue === 'object' && currentValue !== null ? '' : String(currentValue ?? '');
    const selectedOption = options.find((option) => option.value === normalizedCurrentValue) ?? options[0];

    return getRadioPanelOptionId(key, selectedOption.value);
};
const onUpdateField = (key: string, value: PrimitiveValue) => {
    if (!props.allowEdit) {
        return;
    }

    emit('update-field', {
        key,
        value,
    });
};
const onUpdateRadioPanelProperty = (key: string, value: string) => {
    onUpdateField(key, value);
};
const toNumberOrFallback = (value: unknown, fallback: number) => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string') {
        const parsed = Number(value);

        if (Number.isFinite(parsed)) {
            return parsed;
        }
    }

    return fallback;
};
const clampResponsiveValue = (value: number, min: number, max: number) => {
    return Math.min(max, Math.max(min, Math.round(value)));
};
const normalizeResponsiveValue = (property: ContentSystemElementTypeProperty, value: unknown) => {
    if (getControlType(property) === 'box-spacing') {
        return normalizeBoxSpacingCSSValue(value);
    }

    if (getControlType(property) === 'responsive-number' || getControlType(property) === 'number') {
        const limits = getResponsiveLimits(property);

        return clampResponsiveValue(toNumberOrFallback(value, limits.min), limits.min, limits.max);
    }

    if (getControlType(property) === 'switch') {
        return value === true;
    }

    if (getControlType(property) === 'radio-panel' || getControlType(property) === 'select') {
        if (typeof value === 'string') {
            return value;
        }

        if (value === null || value === undefined) {
            return getResponsiveFallbackValue(property);
        }
    }

    if (value === null || value === undefined) {
        return getResponsiveFallbackValue(property);
    }

    return value as PrimitiveValue;
};
const getResponsiveFallbackValue = (property: ContentSystemElementTypeProperty) => {
    const initialValue = getInitialPropertyValue(property, undefined);

    if (getControlType(property) === 'box-spacing') {
        const structuredDefault = getStructuredPropertyDefault(property);
        if (typeof structuredDefault === 'string' || typeof structuredDefault === 'number') {
            return normalizeBoxSpacingCSSValue(structuredDefault);
        }

        if (typeof initialValue === 'string' || typeof initialValue === 'number') {
            return normalizeBoxSpacingCSSValue(initialValue);
        }

        return '';
    }

    if (getControlType(property) === 'responsive-number' || getControlType(property) === 'number') {
        const limits = getResponsiveLimits(property);

        if (typeof initialValue === 'number') {
            return clampResponsiveValue(initialValue, limits.min, limits.max);
        }

        if (typeof property.default === 'number') {
            return clampResponsiveValue(property.default, limits.min, limits.max);
        }

        return limits.min;
    }

    if (typeof property.default === 'string' || typeof property.default === 'boolean') {
        return property.default;
    }

    return initialValue;
};
const isResponsiveObjectValue = (value: unknown) => {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
};
const isEffectiveUnsetStyleValue = (
    field: SettingsFieldDefinition,
    property: ContentSystemElementTypeProperty,
    value: PrimitiveValue,
) => {
    if (field.breakpointAware !== true) {
        return false;
    }

    if (value === null || value === undefined || value === '') {
        return true;
    }

    if (property.default !== null && property.default !== undefined) {
        return value === property.default;
    }

    if (getControlType(property) === 'responsive-number') {
        const limits = getResponsiveLimits(property);

        return value === limits.min;
    }

    return false;
};
const isEffectiveUnsetViewportStyleValue = (property: ContentSystemElementTypeProperty, value: PrimitiveValue) => {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    if (getControlType(property) === 'responsive-number') {
        const limits = getResponsiveLimits(property);

        return value === limits.min;
    }

    return false;
};
const isEffectiveUnsetBreakpointMap = (
    field: SettingsFieldDefinition,
    property: ContentSystemElementTypeProperty,
    value: Record<string, unknown>,
) => {
    if (field.breakpointAware !== true) {
        return false;
    }

    const entries = Object.entries(value).filter(
        ([
            ,
            entryValue,
        ]) => entryValue !== null && entryValue !== undefined,
    );

    if (entries.length === 0) {
        return true;
    }

    return entries.every(
        ([
            ,
            entryValue,
        ]) => isEffectiveUnsetViewportStyleValue(property, normalizeResponsiveValue(property, entryValue)),
    );
};
const shouldPersistBreakpointAwareValue = (
    key: string,
    field: SettingsFieldDefinition,
    property: ContentSystemElementTypeProperty,
    value: PrimitiveValue,
) => {
    if (field.breakpointAware !== true) {
        return true;
    }

    const storedValue = getRawPropertyValue(key);

    if (storedValue !== undefined) {
        return !isEffectiveUnsetStyleValue(field, property, value);
    }

    return touchedBreakpointAwareProperties.value[key] === true && !isEffectiveUnsetStyleValue(field, property, value);
};
const persistBreakpointAwareValue = (
    key: string,
    property: ContentSystemElementTypeProperty,
    field: SettingsFieldDefinition,
    value: PrimitiveValue,
) => {
    if (field.breakpointAware === true) {
        if (!shouldPersistBreakpointAwareValue(key, field, property, value)) {
            if (getRawPropertyValue(key) !== undefined) {
                onUpdateField(key, null);
            }

            return;
        }

        onUpdateField(key, wrapBreakpointAwareGlobalValue(field, value));
        return;
    }

    onUpdateField(key, value);
};
const wrapBreakpointAwareGlobalValue = (field: SettingsFieldDefinition, value: PrimitiveValue) => {
    return getResponsiveViewports(field).reduce<Partial<ResponsiveValue>>((accumulator, viewport) => {
        accumulator[viewport] = value;

        return accumulator;
    }, {});
};

watch(
    () => props.fields,
    () => {
        syncLoadedResponsiveState();
    },
    { immediate: true },
);
watch(
    () => props.values,
    () => {
        syncLoadedResponsiveState();
    },
);

swDefinePublic({
    expandedResponsiveProperties,
    responsiveGlobalSnapshots,
    touchedBreakpointAwareProperties,
    fieldPanels,
    getFieldPanelTechnicalName,
    getPanelSnippetKey,
    getPanelTitle,
    isPanelExpandedByDefault,
    getControlType,
    isBreakpointAwareField,
    isInlineTextProperty,
    getPropertyValue,
    getRawPropertyValue,
    getResponsiveLimits,
    getResponsiveViewports,
    getViewportIcon,
    isResponsiveViewportMode,
    getResponsiveGlobalValue,
    resolveResponsiveGlobalValueFromStorage,
    deriveGlobalSnapshotFromBreakpointMap,
    getResponsiveViewportValue,
    syncLoadedResponsiveState,
    onToggleResponsiveViewportMode,
    onUpdateResponsiveGlobalProperty,
    onUpdateResponsiveViewportProperty,
    getSelectOptions,
    getEntityName,
    getControlProps,
    getPropertyHelpText,
    getRadioPanelOptions,
    getRadioPanelOptionId,
    getRadioPanelLabelTargetId,
    onUpdateField,
    onUpdateRadioPanelProperty,
    toNumberOrFallback,
    clampResponsiveValue,
    normalizeResponsiveValue,
    getResponsiveFallbackValue,
    isResponsiveObjectValue,
    isEffectiveUnsetStyleValue,
    isEffectiveUnsetViewportStyleValue,
    isEffectiveUnsetBreakpointMap,
    shouldPersistBreakpointAwareValue,
    persistBreakpointAwareValue,
    wrapBreakpointAwareGlobalValue,
});

defineExpose({
    expandedResponsiveProperties,
    responsiveGlobalSnapshots,
    touchedBreakpointAwareProperties,
    fieldPanels,
    getFieldPanelTechnicalName,
    getPanelSnippetKey,
    getPanelTitle,
    isPanelExpandedByDefault,
    getControlType,
    isBreakpointAwareField,
    isInlineTextProperty,
    getPropertyValue,
    getRawPropertyValue,
    getResponsiveLimits,
    getResponsiveViewports,
    getViewportIcon,
    isResponsiveViewportMode,
    getResponsiveGlobalValue,
    resolveResponsiveGlobalValueFromStorage,
    deriveGlobalSnapshotFromBreakpointMap,
    getResponsiveViewportValue,
    syncLoadedResponsiveState,
    onToggleResponsiveViewportMode,
    onUpdateResponsiveGlobalProperty,
    onUpdateResponsiveViewportProperty,
    getSelectOptions,
    getEntityName,
    getControlProps,
    getPropertyHelpText,
    getRadioPanelOptions,
    getRadioPanelOptionId,
    getRadioPanelLabelTargetId,
    onUpdateField,
    onUpdateRadioPanelProperty,
    toNumberOrFallback,
    clampResponsiveValue,
    normalizeResponsiveValue,
    getResponsiveFallbackValue,
    isResponsiveObjectValue,
    isEffectiveUnsetStyleValue,
    isEffectiveUnsetViewportStyleValue,
    isEffectiveUnsetBreakpointMap,
    shouldPersistBreakpointAwareValue,
    persistBreakpointAwareValue,
    wrapBreakpointAwareGlobalValue,
});
</script>
