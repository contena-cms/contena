<template>
    <ct-block name="sw_channel_defaults_select">
        <div class="ct-channel-defaults-select">
            <template v-if="channel && propertyEntityName">
                <mt-entity-select
                    :model-value="propertyIds"
                    :repository="propertyRepositoryFactory"
                    :entity="propertyEntityName"
                    enable-multi-selection
                    :disabled="disabled || undefined"
                    :class="multiSelectClass"
                    :label="propertyLabel"
                    :help-text="helpText"
                    @update:model-value="updateCollection"
                >
                    <template v-if="shouldShowActiveState" #result-label-preview="{ item }">
                        <mt-icon
                            class="ct-channel-defaults-select__active-icon"
                            size="6px"
                            :color="getActiveIconColor(item)"
                            name="solid-circle"
                        />
                    </template>
                </mt-entity-select>

                <mt-entity-select
                    :model-value="defaultId"
                    :repository="propertyRepositoryFactory"
                    :entity="propertyEntityName"
                    :disabled="disabled || undefined"
                    :class="singleSelectClass"
                    :label="defaultPropertyLabel"
                    :help-text="helpText"
                    required
                    @update:model-value="updateDefault"
                />
            </template>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntitySchema */
/* global Entity, EntitySchema */
import { computed, inject, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-channel-defaults-select.scss';

type SelectEntity = Entity<keyof EntitySchema.Entities>;

type SelectCollection = {
    entity: keyof EntitySchema.Entities;
    length: number;
    getIds: () => string[];
    has: (id: string) => boolean;
    add: (entity: SelectEntity) => void;
    remove: (id: string) => void;
    find: (callback: (entity: SelectEntity) => boolean) => SelectEntity | undefined;
};

type CriteriaType = InstanceType<typeof Contena.Data.Criteria>;

const props = defineProps({
    channel: { type: Object as PropType<Entity<'channel'> | null>, default: null },
    propertyName: { type: String, required: true },
    propertyLabel: { type: String, required: true },
    defaultPropertyName: { type: String, required: true },
    defaultPropertyLabel: { type: String, required: true },
    propertyNameInDomain: { type: String, default: null },
    helpText: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    criteria: { type: Object as PropType<CriteriaType | undefined>, default: undefined },
    disabledTooltipMessage: { type: String, default: '' },
    shouldShowActiveState: { type: Boolean, default: false },
});

const { t } = useI18n();
const { createNotificationError } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) {
    throw new Error('The repository factory is unavailable.');
}

const channelData = computed<Record<string, unknown> | null>(() => {
    return props.channel as unknown as Record<string, unknown> | null;
});
const propertyCollection = computed<SelectCollection | null>({
    get: () => (channelData.value?.[props.propertyName] as SelectCollection | undefined) ?? null,
    set: (collection) => {
        if (channelData.value) {
            channelData.value[props.propertyName] = collection;
        }
    },
});
const propertyIds = computed(() => propertyCollection.value?.getIds() ?? []);
const defaultId = computed<string | null>({
    get: () => (channelData.value?.[props.defaultPropertyName] as string | null | undefined) ?? null,
    set: (id) => {
        if (channelData.value) {
            channelData.value[props.defaultPropertyName] = id;
        }
    },
});
const propertyEntityName = computed(() => propertyCollection.value?.entity ?? null);
const propertyNameKebabCase = computed(() => Contena.Utils.string.kebabCase(props.propertyName));
const multiSelectClass = computed(() => `ct-channel-detail__select-${propertyNameKebabCase.value}`);
const singleSelectClass = computed(() => `ct-channel-detail__assign-${propertyNameKebabCase.value}`);

const propertyRepositoryFactory = () => {
    const entityName = propertyEntityName.value;
    if (!entityName) {
        throw new Error('The property entity is unavailable.');
    }

    const repository = repositoryFactory.create(entityName);

    return new Proxy(repository, {
        get(target, property, receiver) {
            if (property === 'search' && props.criteria) {
                return (criteria: CriteriaType, context?: typeof Contena.Context.api) => {
                    criteria.filters.push(...props.criteria!.filters);
                    criteria.sortings.push(...props.criteria!.sortings);

                    return target.search(criteria, context);
                };
            }

            const value = Reflect.get(target, property, receiver);

            return typeof value === 'function' ? value.bind(target) : value;
        },
    });
};

const getNotInCollection = (collectionWith: string[], collectionWithout: string[]): string | null => {
    return collectionWith.find((id) => !collectionWithout.includes(id)) ?? null;
};
const cloneCollection = (): SelectCollection | null => {
    if (!propertyCollection.value) {
        return null;
    }

    return Contena.Data.EntityCollection.fromCollection(propertyCollection.value) as unknown as SelectCollection;
};
const addItem = async (id: string): Promise<void> => {
    const collection = cloneCollection();
    const entityName = propertyEntityName.value;
    if (!collection || !entityName) {
        return;
    }

    const entity = (await repositoryFactory.create(entityName).get(id, Contena.Context.api)) as SelectEntity;
    collection.add(entity);
    propertyCollection.value = collection;

    if (collection.length === 1) {
        defaultId.value = id;
    }
};
const getDomainUsingValue = (id: string): Entity<'channel_domain'> | null => {
    if (!props.propertyNameInDomain || !props.channel?.domains) {
        return null;
    }

    return (
        props.channel.domains.find((domain) => {
            return (domain as unknown as Record<string, unknown>)[props.propertyNameInDomain] === id;
        }) ?? null
    );
};
const removeItem = (id: string): void => {
    const domain = getDomainUsingValue(id);
    if (domain) {
        createNotificationError({
            message: t('ct-channel.ct-channel-defaults-select.messageError', { url: domain.url }),
        });
        return;
    }

    const collection = cloneCollection();
    if (!collection) {
        return;
    }

    collection.remove(id);
    propertyCollection.value = collection;

    if (defaultId.value === id) {
        defaultId.value = null;
    }
};
const updateCollection = async (ids: string[]): Promise<void> => {
    const currentIds = propertyIds.value;
    const addedId = getNotInCollection(ids, currentIds);
    if (addedId) {
        await addItem(addedId);
        return;
    }

    const removedId = getNotInCollection(currentIds, ids);
    if (removedId) {
        removeItem(removedId);
    }
};
const updateDefault = async (id: string | null): Promise<void> => {
    defaultId.value = id;

    if (id && !propertyCollection.value?.has(id)) {
        await addItem(id);
    }
};
const isDisabledItem = (item: SelectEntity): boolean => item.active === false;
const getActiveIconColor = (item: SelectEntity): string => {
    return isDisabledItem(item) ? 'var(--color-icon-secondary-default)' : 'var(--color-icon-positive-default)';
};

swDefinePublic({
    propertyCollection,
    propertyIds,
    defaultId,
    propertyEntityName,
    propertyNameKebabCase,
    multiSelectClass,
    singleSelectClass,
    propertyRepositoryFactory,
    updateCollection,
    getNotInCollection,
    addItem,
    removeItem,
    getDomainUsingValue,
    updateDefault,
    isDisabledItem,
    getActiveIconColor,
});

defineExpose({
    propertyCollection,
    propertyIds,
    defaultId,
    propertyEntityName,
    updateCollection,
    addItem,
    removeItem,
    updateDefault,
    getDomainUsingValue,
});
</script>
