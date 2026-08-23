import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';

/** @private */
export interface CustomFieldOption {
    label: Record<string, string>;
    value: string;
}

/** @private */
export interface CustomFieldConfig {
    componentName?: string;
    config?: {
        time_24hr?: boolean;
        [key: string]: unknown;
    };
    dateType?: string;
    enableMultiSelection?: boolean;
    entity?: string;
    labelProperty?: string[];
    max?: number | null;
    min?: number | null;
    numberType?: string;
    options?: CustomFieldOption[];
    step?: number | null;
    [key: string]: unknown;
}

/** @private */
export interface CustomField {
    _isNew?: boolean;
    config: CustomFieldConfig;
    type?: string;
    [key: string]: unknown;
}

/** @private */
export interface CustomFieldSet {
    config: {
        translated?: boolean;
        [key: string]: unknown;
    };
    [key: string]: unknown;
}

/** @private */
export interface CustomFieldTypeProps {
    currentCustomField: CustomField;
    set: CustomFieldSet;
    disabled?: boolean;
}

type PropertyNameDefinition = readonly [property: string, snippet: string];

/** @private */
export function useCustomFieldType(props: CustomFieldTypeProps, propertyNameDefinitions: readonly PropertyNameDefinition[]) {
    const acl = inject<AclService>('acl');
    const i18n = useI18n();

    if (!acl) {
        throw new Error('The ACL service is required by custom field type components.');
    }

    const propertyNames = ref(
        Object.fromEntries(
            propertyNameDefinitions.map(
                ([
                    property,
                    snippet,
                ]) => [
                    property,
                    i18n.t(snippet),
                ],
            ),
        ),
    );

    const locales = computed(() => {
        if (props.set.config.translated === true) {
            // Only full locale codes (e.g. zh-CN, en-GB) represent real Administration languages.
            const availableLocales = i18n.availableLocales.filter((locale) => locale.includes('-'));
            const fallbackLocale = Contena.Context.app.fallbackLocale ?? Contena.Store.get('session').currentLocale;

            if (fallbackLocale && availableLocales.includes(fallbackLocale)) {
                return [
                    fallbackLocale,
                    ...availableLocales.filter((locale) => locale !== fallbackLocale),
                ];
            }

            return availableLocales;
        }

        const fallbackLocale = Contena.Context.app.fallbackLocale ?? Contena.Store.get('session').currentLocale;

        return fallbackLocale ? [fallbackLocale] : [];
    });

    function translate(snippet: string): string {
        return i18n.t(snippet);
    }

    return {
        acl,
        locales,
        propertyNames,
        translate,
    };
}
