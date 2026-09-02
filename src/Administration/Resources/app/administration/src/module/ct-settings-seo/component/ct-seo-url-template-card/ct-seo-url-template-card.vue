<template>
    <ct-block name="ct_seo_url_template_card">
        <mt-card
            class="ct-seo-url-template-card"
            position-identifier="ct-seo-url-template-card"
            :title="$t('ct-seo-url-template-card.general.titleCard')"
            :is-loading="isLoading"
        >
            <template #toolbar>
                <ct-channel-switch
                    :label="$t('ct-seo-url-template-card.general.labelChannelSelect')"
                    @change-channel-id="onChannelChanged"
                />
            </template>

            <ct-block name="ct_seo_url_template_card_info_box">
                <mt-banner variant="info" :title="$t('global.default.info')">
                    <span>{{ $t('ct-seo-url-template-card.general.textInfoMessageBoxEmptyProperties') }}</span>
                </mt-banner>
            </ct-block>

            <ct-block name="ct_seo_url_template_card_entries">
                <template v-if="!isLoading && !channelIsHeadless">
                    <div
                        v-for="(seoUrlTemplate, index) in getTemplatesForChannel(channelId)"
                        :key="index"
                        class="ct-seo-url-template-card__seo-url"
                    >
                        <ct-container columns="3fr 1fr" gap="var(--scale-size-6)">
                            <ct-inherit-wrapper
                                v-model:value="seoUrlTemplate.template"
                                :has-parent="seoUrlTemplate.channelId !== null"
                                :inherited-value="getPlaceholder(seoUrlTemplate)"
                                @update:value="onInput(seoUrlTemplate)"
                            >
                                <template #content="inheritanceProps">
                                    <mt-text-field
                                        :is-inheritance-field="inheritanceProps.isInheritField"
                                        :is-inherited="inheritanceProps.isInherited"
                                        :model-value="inheritanceProps.currentValue"
                                        :disabled="inheritanceProps.isInherited"
                                        :error="seoUrlTemplatesTemplateError[index]"
                                        :name="`ct-field--seo-url-template-${seoUrlTemplate.entityName}`"
                                        :label="getLabel(seoUrlTemplate)"
                                        :placeholder="getPlaceholder(seoUrlTemplate)"
                                        @update:model-value="inheritanceProps.updateCurrentValue"
                                        @inheritance-restore="inheritanceProps.restoreInheritance"
                                        @inheritance-remove="inheritanceProps.removeInheritance"
                                    >
                                        <template #suffix>
                                            <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                                            <mt-loader
                                                v-if="previewLoadingStates[seoUrlTemplate.id]"
                                                size="var(--scale-size-16)"
                                            />
                                            <mt-icon
                                                v-else-if="errorMessages[seoUrlTemplate.id]"
                                                v-tooltip="$t('ct-seo-url-template-card.general.tooltipInvalidTemplate')"
                                                name="regular-times"
                                            />
                                            <mt-icon
                                                v-else-if="noEntityError.includes(seoUrlTemplate.id)"
                                                v-tooltip="
                                                    $t(
                                                        'ct-seo-url-template-card.general.textUrlNoEntitiesForPreview',
                                                        {
                                                            entity: $t(`global.entities.${seoUrlTemplate.entityName}`, 0),
                                                        },
                                                        0,
                                                    )
                                                "
                                                name="regular-exclamation-triangle"
                                            />
                                            <mt-icon
                                                v-else-if="!inheritanceProps.currentValue"
                                                v-tooltip="
                                                    $t(
                                                        'ct-seo-url-template-card.general.textUrlPreviewEmptyTemplate',
                                                        {
                                                            entity: $t(`global.entities.${seoUrlTemplate.entityName}`, 0),
                                                        },
                                                        1,
                                                    )
                                                "
                                                name="regular-exclamation-triangle"
                                            />
                                            <mt-icon
                                                v-else
                                                v-tooltip="$t('ct-seo-url-template-card.general.tooltipValidTemplate')"
                                                name="regular-checkmark"
                                            />
                                        </template>
                                    </mt-text-field>
                                </template>
                            </ct-inherit-wrapper>
                            <mt-select
                                v-if="getVariableOptions(seoUrlTemplate.id) && !noEntityError.includes(seoUrlTemplate.id)"
                                :model-value="selectedProperty"
                                value-property="name"
                                label-property="name"
                                :options="getVariableOptions(seoUrlTemplate.id) || []"
                                :placeholder="$t('ct-seo-url-template-card.general.placeholderSelectVariables')"
                                :label="$t('ct-seo-url-template-card.general.labelPossibleValues')"
                                @update:model-value="(propertyName) => onSelectInput(propertyName, seoUrlTemplate)"
                            />
                        </ct-container>
                        <div v-if="seoUrlTemplate.template" class="ct-seo-url-template-card__preview">
                            <span class="ct-seo-url-template-card__preview-label">
                                {{ $t('ct-seo-url-template-card.general.preview') }}
                            </span>
                            <div class="ct-seo-url-template-card__preview-item">
                                <span v-if="previews[seoUrlTemplate.id] && previews[seoUrlTemplate.id].length > 0">
                                    {{ previews[seoUrlTemplate.id][0].seoPathInfo }}
                                </span>
                                <span v-else>
                                    {{ $t('ct-seo-url-template-card.general.textUrlPreviewNotPossible') }}
                                </span>
                                <span v-if="noEntityError.includes(seoUrlTemplate.id)">
                                    {{
                                        $t(
                                            'ct-seo-url-template-card.general.textUrlNoEntitiesForPreview',
                                            { entity: $t(`global.entities.${seoUrlTemplate.entityName}`, 0) },
                                            0,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
                <div v-if="channelIsHeadless && !isLoading">
                    {{ $t('ct-seo-url.textSeoUrlsDisallowedForHeadless') }}
                </div>
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */

import type Repository from 'src/core/data/repository.data';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

import './ct-seo-url-template-card.scss';

const EntityCollectionConstructor = Contena.Data.EntityCollection;
const Criteria = Contena.Data.Criteria;
const utils = Contena.Utils;

type SeoUrl = Entity<'seo_url'>;
type SeoUrlTemplate = Entity<'seo_url_template'> & { criteria?: ReturnType<InstanceType<typeof Criteria>['parse']> };
type Channel = Entity<'channel'>;
type VariableOption = { name: string };
type ApiError = { response: { data: { errors: Array<{ detail: string }> } } };

interface SeoUrlTemplateService {
    getContext: (_template: SeoUrlTemplate) => Promise<Record<string, unknown>>;
    preview: (_template: SeoUrlTemplate) => Promise<SeoUrl[] | null>;
}

defineProps({});

const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();

const $t = t;
const seoUrlTemplateService = inject<SeoUrlTemplateService>('seoUrlTemplateService')!;
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;

const defaultSeoUrlTemplates = ref<EntityCollection<'seo_url_template'> | null>(null);
const seoUrlTemplates = ref<EntityCollection<'seo_url_template'> | null>(null);
const seoUrlPreviewCriteria = ref<Record<string, InstanceType<typeof Criteria>>>({});
const isLoading = ref(true);
const debouncedPreviews = ref<Record<string, () => void>>({});
const previewLoadingStates = ref<Record<string, boolean>>({});
const errorMessages = ref<Record<string, string | null>>({});
const previews = ref<Record<string, SeoUrl[]>>({});
const noEntityError = ref<string[]>([]);
const variableStores = ref<Record<string, VariableOption[]>>({});
const seoUrlTemplateRepository = ref<Repository<'seo_url_template'> | null>(null);
const channelId = ref<string | null>(null);
const channels = ref<EntityCollection<'channel'> | Channel[]>([]);
const selectedProperty = ref<string | null>(null);

const channelRepository = computed(() => {
    return repositoryFactory.create('channel');
});
const channelIsHeadless = computed(() => {
    const currentChannel = channels.value.find((entity) => {
        return entity.id === channelId.value;
    });

    if (!currentChannel) {
        return false;
    }

    return currentChannel.typeId === Contena.Defaults.apiChannelTypeId;
});
const seoUrlTemplatesTemplateError = computed(() => {
    if (!Array.isArray(seoUrlTemplates.value)) {
        return [];
    }

    return seoUrlTemplates.value?.map((entity) => Contena.Store.get('error').getApiError(entity, 'template')) ?? [];
});

const createdComponent = (): void => {
    seoUrlTemplateRepository.value = repositoryFactory.create('seo_url_template');
    seoUrlTemplates.value = new EntityCollectionConstructor<'seo_url_template'>(
        seoUrlTemplateRepository.value.route,
        seoUrlTemplateRepository.value.schema.entity,
        Contena.Context.api,
        new Criteria(1, 25),
    );

    defaultSeoUrlTemplates.value = new EntityCollectionConstructor<'seo_url_template'>(
        seoUrlTemplateRepository.value.route,
        seoUrlTemplateRepository.value.schema.entity,
        Contena.Context.api,
        new Criteria(1, 25),
    );

    seoUrlPreviewCriteria.value['frontend.navigation.page'] = new Criteria(1, 25).addFilter(
        Criteria.not('and', [Criteria.equals('path', null)]),
    );

    fetchChannels();
    fetchSeoUrlTemplates();
};
const fetchSeoUrlTemplates = (selectedChannelId: string | null = null): void => {
    const criteria = new Criteria(1, 25);

    const scopedChannelId = selectedChannelId || null;
    criteria.addFilter(Criteria.equals('channelId', scopedChannelId));

    isLoading.value = true;

    void seoUrlTemplateRepository.value?.search(criteria).then((response) => {
        response.forEach((entity) => {
            if (!seoUrlTemplates.value.has(entity.id)) {
                seoUrlTemplates.value.add(entity);
            }
        });

        if (!scopedChannelId) {
            // Save the defaults as blueprint for creating dynamically new entities
            response.forEach((entity) => {
                if (!defaultSeoUrlTemplates.value.has(entity)) {
                    defaultSeoUrlTemplates.value.add(entity);
                }
            });
        } else {
            createSeoUrlTemplatesFromDefaultRoutes(scopedChannelId);
        }
        isLoading.value = false;

        seoUrlTemplates.value.forEach((seoUrlTemplate) => {
            // Fetch preview / validate seo url template
            fetchSeoUrlPreview(seoUrlTemplate);

            // Create stores for the possible variables
            if (!variableStores.value.hasOwnProperty(seoUrlTemplate.id)) {
                void seoUrlTemplateService.getContext(seoUrlTemplate).then((data) => {
                    createVariableOptions(seoUrlTemplate.id, data);
                });
            }
        });
    });
};
const createSeoUrlTemplatesFromDefaultRoutes = (selectedChannelId: string): void => {
    // Iterate over the default SEO URL templates and create channel-specific entities when needed.
    // if they do not exist
    defaultSeoUrlTemplates.value.forEach((defaultEntity) => {
        const entityAlreadyExists = seoUrlTemplates.value.some((entity) => {
            return entity.routeName === defaultEntity.routeName && entity.channelId === selectedChannelId;
        });

        if (!entityAlreadyExists) {
            const entity = seoUrlTemplateRepository.value?.create();
            if (!entity) {
                return;
            }
            entity.routeName = defaultEntity.routeName;
            entity.channelId = selectedChannelId;
            entity.entityName = defaultEntity.entityName;
            entity.template = null;
            seoUrlTemplates.value.add(entity);
        }
    });
};
const createVariableOptions = (id: string, data: Record<string, unknown>): void => {
    const storeOptions: VariableOption[] = [];

    Object.entries(data).forEach(
        ([
            property,
            value,
        ]) => {
            storeOptions.push({ name: `${property}` });

            if (value instanceof Object) {
                Object.keys(value).forEach((innerProperty) => {
                    storeOptions.push({
                        name: `${property}.${innerProperty}`,
                    });
                });
            }
        },
    );

    variableStores.value[id] = storeOptions;
};
const getVariableOptions = (id: string): VariableOption[] | false => {
    if (variableStores.value.hasOwnProperty(id)) {
        return variableStores.value[id];
    }
    return false;
};
const getLabel = (seoUrlTemplate: SeoUrlTemplate): string => {
    const routeName = seoUrlTemplate.routeName.replace(/\./g, '-');
    if (t(`ct-seo-url-template-card.routeNames.${routeName}`)) {
        return t(`ct-seo-url-template-card.routeNames.${routeName}`);
    }

    return seoUrlTemplate.routeName;
};
const getPlaceholder = (seoUrlTemplate: SeoUrlTemplate): string | null | undefined => {
    if (!seoUrlTemplate.channelId) {
        return null;
    }

    const defaultEntity = Object.values(defaultSeoUrlTemplates.value).find((entity) => {
        return entity.routeName === seoUrlTemplate.routeName;
    });

    return defaultEntity?.template;
};
const createSaveErrorNotification = (): void => {
    createNotificationError({
        title: t('global.default.error'),
        message: t('ct-seo-url-template-card.general.messageSaveError'),
    });
};
const createSaveSuccessNotification = (): void => {
    createNotificationSuccess({
        title: t('global.default.success'),
        message: t('ct-seo-url-template-card.general.messageSaveSuccess'),
    });
};
const onClickSave = (): void => {
    const hasError = Object.values(errorMessages.value).some((error) => error !== null);

    if (hasError) {
        createSaveErrorNotification();
        return;
    }

    seoUrlTemplates.value.forEach((entry) => {
        if (entry.template === null) {
            seoUrlTemplates.value.remove(entry.id);
        }
    });

    seoUrlTemplateRepository.value
        .sync(seoUrlTemplates.value)
        .then(() => {
            seoUrlTemplates.value = new EntityCollectionConstructor<'seo_url_template'>(
                seoUrlTemplateRepository.value.route,
                seoUrlTemplateRepository.value.schema.entity,
                Contena.Context.api,
                new Criteria(1, 25),
            );
            fetchSeoUrlTemplates(channelId.value);
            createSaveSuccessNotification();
        })
        .catch(createSaveErrorNotification);
};
const onSelectInput = (propertyName: string | null, entity: SeoUrlTemplate): void => {
    if (propertyName === null) {
        return;
    }
    const templateValue = entity.template ? `${entity.template}/` : '';
    entity.template = `${templateValue}{{ ${propertyName} }}`;
    fetchSeoUrlPreview(entity);
};
const onInput = (entity: SeoUrlTemplate): void => {
    debouncedPreviewSeoUrlTemplate(entity);
};
const debouncedPreviewSeoUrlTemplate = (entity: SeoUrlTemplate): void => {
    if (!debouncedPreviews.value[entity.id]) {
        debouncedPreviews.value[entity.id] = utils.debounce(() => {
            if (entity.template && entity.template !== '') {
                fetchSeoUrlPreview(entity);
            } else {
                setErrorMessagesForEntity(entity);
            }
        }, 400);
    } else {
        setErrorMessagesForEntity(entity);
    }

    debouncedPreviews.value[entity.id]();
};
const setErrorMessagesForEntity = (entity: SeoUrlTemplate, value: string | null = null): void => {
    errorMessages.value[entity.id] = value;
};
const fetchSeoUrlPreview = (entity: SeoUrlTemplate): void => {
    previewLoadingStates.value[entity.id] = true;

    const criteria = seoUrlPreviewCriteria.value[entity.routeName]
        ? seoUrlPreviewCriteria.value[entity.routeName]
        : new Criteria(1, 25);
    entity.criteria = criteria.parse();
    seoUrlTemplateService
        .preview(entity)
        .then((response) => {
            noEntityError.value = noEntityError.value.filter((elem) => {
                return elem !== entity.id;
            });

            previews.value[entity.id] = response;

            if (response === null) {
                noEntityError.value.push(entity.id);
            } else {
                setErrorMessagesForEntity(entity);
            }
            previewLoadingStates.value[entity.id] = false;
        })
        .catch((err: ApiError) => {
            setErrorMessagesForEntity(entity, err.response.data.errors[0].detail);

            previews.value[entity.id] = [];

            previewLoadingStates.value[entity.id] = false;
        });
};
const fetchChannels = (): void => {
    void channelRepository.value.search(new Criteria(1, 25)).then((response) => {
        channels.value = response;
    });
};
const onChannelChanged = (channelIdValue: string | null): void => {
    channelId.value = channelIdValue;
    fetchSeoUrlTemplates(channelIdValue);
};
const getTemplatesForChannel = (selectedChannelId: string | null): SeoUrlTemplate[] => {
    return (
        seoUrlTemplates.value?.filter((templateEntity) => {
            return templateEntity.channelId === selectedChannelId;
        }) ?? []
    );
};

createdComponent();

ctDefinePublic({
    seoUrlTemplateService,
    repositoryFactory,
    defaultSeoUrlTemplates,
    seoUrlTemplates,
    seoUrlPreviewCriteria,
    isLoading,
    debouncedPreviews,
    previewLoadingStates,
    errorMessages,
    previews,
    noEntityError,
    variableStores,
    seoUrlTemplateRepository,
    channelId,
    channels,
    selectedProperty,
    channelRepository,
    channelIsHeadless,
    seoUrlTemplatesTemplateError,
    createdComponent,
    fetchSeoUrlTemplates,
    createSeoUrlTemplatesFromDefaultRoutes,
    createVariableOptions,
    getVariableOptions,
    getLabel,
    getPlaceholder,
    onClickSave,
    createSaveErrorNotification,
    createSaveSuccessNotification,
    onSelectInput,
    onInput,
    debouncedPreviewSeoUrlTemplate,
    setErrorMessagesForEntity,
    fetchSeoUrlPreview,
    fetchChannels,
    onChannelChanged,
    getTemplatesForChannel,
});

defineExpose({
    seoUrlTemplateService,
    repositoryFactory,
    defaultSeoUrlTemplates,
    seoUrlTemplates,
    seoUrlPreviewCriteria,
    isLoading,
    debouncedPreviews,
    previewLoadingStates,
    errorMessages,
    previews,
    noEntityError,
    variableStores,
    seoUrlTemplateRepository,
    channelId,
    channels,
    selectedProperty,
    channelRepository,
    channelIsHeadless,
    seoUrlTemplatesTemplateError,
    createdComponent,
    fetchSeoUrlTemplates,
    createSeoUrlTemplatesFromDefaultRoutes,
    createVariableOptions,
    getVariableOptions,
    getLabel,
    getPlaceholder,
    onClickSave,
    createSaveErrorNotification,
    createSaveSuccessNotification,
    onSelectInput,
    onInput,
    debouncedPreviewSeoUrlTemplate,
    setErrorMessagesForEntity,
    fetchSeoUrlPreview,
    fetchChannels,
    onChannelChanged,
    getTemplatesForChannel,
});
</script>
