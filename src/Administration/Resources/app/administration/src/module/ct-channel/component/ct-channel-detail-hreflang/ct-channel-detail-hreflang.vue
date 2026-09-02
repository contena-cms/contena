<template>
    <!-- eslint-disable vue/no-mutating-props -->
    <ct-block name="ct_channel_detail_hreflang">
        <mt-card
            :title="t('ct-channel.detail.hreflang.title')"
            class="ct-channel-detail-hreflang"
            position-identifier="ct-channel-detail-hreflang"
        >
            <ct-block name="ct_channel_detail_hreflang_title">
                <h4>
                    <span class="ct-channel-detail-domains__headline-text ct-channel-detail-base__headline-text">
                        {{ t('ct-channel.detail.hreflang.titleCard') }}
                    </span>
                </h4>
            </ct-block>

            <ct-block name="ct_channel_detail_hreflang_content">
                <div class="ct-channel-detail-base__description-text">
                    {{ t('ct-channel.detail.hreflang.titleDescription') }}
                </div>

                <mt-switch
                    v-model="channel.hreflangActive"
                    :label="t('ct-channel.detail.hreflang.enableCheckbox')"
                    :disabled="disabled || undefined"
                    @update:model-value="channel.hreflangDefaultDomainId = null"
                />

                <mt-entity-select
                    v-model="channel.hreflangDefaultDomainId"
                    :repository="domainRepositoryFactory"
                    :disabled="!channel.hreflangActive || disabled || undefined"
                    :label="t('ct-channel.detail.hreflang.defaultDomain')"
                    entity="channel_domain"
                    label-property="url"
                />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

type CriteriaType = InstanceType<typeof Contena.Data.Criteria>;

const props = defineProps({
    channel: { type: Object as PropType<Entity<'channel'>>, required: true },
    disabled: { type: Boolean, default: false },
});

const { t } = useI18n();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) {
    throw new Error('The repository factory is unavailable.');
}

const domainCriteria = computed(() => {
    return new Contena.Data.Criteria(1, 25).addFilter(Contena.Data.Criteria.equals('channelId', props.channel.id));
});
const domainRepositoryFactory = () => {
    const repository = repositoryFactory.create('channel_domain');

    return new Proxy(repository, {
        get(target, property, receiver) {
            if (property === 'search') {
                return (criteria: CriteriaType, context?: typeof Contena.Context.api) => {
                    criteria.filters.push(...domainCriteria.value.filters);

                    return target.search(criteria, context);
                };
            }

            const value = Reflect.get(target, property, receiver);

            return typeof value === 'function' ? value.bind(target) : value;
        },
    });
};

ctDefinePublic({
    domainCriteria,
    domainRepositoryFactory,
});

defineExpose({ domainCriteria, domainRepositoryFactory });
</script>
