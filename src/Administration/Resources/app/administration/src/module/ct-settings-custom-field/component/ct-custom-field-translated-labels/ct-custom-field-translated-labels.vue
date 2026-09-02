<template>
    <ct-block name="ct_custom_field_translated_labels">
        <div class="ct-custom-field-translated-labels">
            <ct-block name="ct_custom_field_translated_labels_single">
                <template v-if="localeCount === 1">
                    <div v-for="locale in locales" :key="locale" class="ct-custom-field-translated-labels__single">
                        <mt-text-field
                            v-for="(label, name) in propertyNames"
                            :key="getInlineSnippetKey(name)"
                            v-model="config[name][locale]"
                            :disabled="disabled"
                            :label="getLabel(label, locale)"
                            @update:model-value="onInput($event, name, locale)"
                        />
                    </div>
                </template>
            </ct-block>

            <ct-block name="ct_custom_field_translated_labels_translated">
                <div
                    v-if="localeCount !== 1"
                    position-identifier="ct-custom-field-translated-labels"
                    class="ct-custom-field-translated-labels__tabs"
                >
                    <mt-tabs :items="localeTabs" :default-item="fallbackLocale" @new-item-active="onTabChange" />

                    <ct-block name="ct_custom_field_translated_labels_translated_tabs" />

                    <ct-block name="ct_custom_field_translated_labels_translated_content">
                        <div class="ct-custom-field-translated-labels__content">
                            <template v-for="locale in locales" :key="locale">
                                <div v-if="(activeLocale || fallbackLocale) === locale">
                                    <ct-block name="ct_custom_field_translated_labels_translated_content_field">
                                        <mt-text-field
                                            v-for="(label, name) in propertyNames"
                                            :key="getInlineSnippetKey(name)"
                                            v-model="config[name][locale]"
                                            class="ct-custom-field-translated-labels__translated-content-field"
                                            :disabled="disabled"
                                            :label="getLabel(label, locale)"
                                            @update:model-value="onInput($event, name, locale)"
                                        />
                                    </ct-block>
                                </div>
                            </template>
                        </div>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref, toRef, watch, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';

type TranslationValue = Record<string, string | null>;
type TranslationConfig = Record<string, TranslationValue>;
type PropertyName = string | Record<string, string>;

const props = defineProps({
    locales: {
        type: Array as PropType<string[]>,
        required: true,
        default: () => [],
    },
    config: {
        type: Object as PropType<TranslationConfig>,
        required: true,
    },
    propertyNames: {
        type: Object as PropType<Record<string, PropertyName>>,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
const config = toRef(props, 'config');

const i18n = useI18n();
const { getInlineSnippet } = useInlineSnippet();
const activeLocale = ref<string | null>(null);
const fallbackLocale = computed(
    () => Contena.Context.app.fallbackLocale ?? Contena.Store.get('session').currentLocale ?? '',
);
const localeCount = computed(() => props.locales.length);
const localeTabs = computed(() =>
    props.locales.map((locale) => ({
        label: i18n.t(`locale.${locale}`),
        name: locale,
    })),
);

function onTabChange(locale: string): void {
    activeLocale.value = locale;
}

function initializeConfiguration(): void {
    Object.keys(props.propertyNames).forEach((property) => {
        if (!Object.hasOwn(config.value, property)) {
            config.value[property] = { [fallbackLocale.value]: null };
        }
    });
}

function resolveInlineSnippet(value: PropertyName): string {
    if (typeof value === 'string') {
        return value;
    }

    const snippet = getInlineSnippet(value);

    return typeof snippet === 'string' ? snippet : '';
}

function getLabel(label: PropertyName, locale: string): string {
    const snippet = resolveInlineSnippet(label);
    const language = i18n.t(`locale.${locale}`);

    return `${snippet} (${language})`;
}

function getInlineSnippetKey(propertyName: string): string {
    return resolveInlineSnippet(props.propertyNames[propertyName]);
}

function onInput(input: string, propertyName: string, locale: string): void {
    if (input === '') {
        config.value[propertyName][locale] = null;
    }
}

initializeConfiguration();
activeLocale.value = fallbackLocale.value;

watch(() => props.locales, initializeConfiguration);

ctDefinePublic({
    activeLocale,
    fallbackLocale,
    localeCount,
    localeTabs,
    onTabChange,
    initializeConfiguration,
    getLabel,
    getInlineSnippetKey,
    onInput,
});

defineExpose({
    activeLocale,
    fallbackLocale,
    localeCount,
    localeTabs,
    onTabChange,
    initializeConfiguration,
    getLabel,
    getInlineSnippetKey,
    onInput,
});
</script>

<style src="./ct-custom-field-translated-labels.scss" lang="scss"></style>
