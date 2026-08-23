<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="sw_category_link_settings">
        <mt-card
            class="ct-category-link-settings"
            position-identifier="ct-category-link-settings"
            :title="$t('ct-category.base.link.title')"
            :is-loading="isLoading"
        >
            <ct-block name="sw_category_detail_link_type_select_main">
                <ct-single-select
                    v-model:value="mainType"
                    class="ct-category-link-settings__type"
                    :disabled="!acl.can('category.editor') || undefined"
                    :label="$t('ct-category.base.link.mainTypeLabel')"
                    :placeholder="$t('ct-category.base.link.mainTypePlaceholder')"
                    :options="linkTypeValues"
                    show-clearable-button
                />
            </ct-block>

            <ct-block name="sw_category_detail_link_type_select_entity">
                <ct-single-select
                    v-if="isInternal"
                    v-model:value="category.linkType"
                    class="ct-category-link-settings__entity"
                    :disabled="!acl.can('category.editor') || undefined"
                    :label="$t('ct-category.base.link.internalTypeLabel')"
                    :placeholder="$t('ct-category.base.link.internalTypePlaceholder')"
                    :options="entityValues"
                    show-clearable-button
                    @update:value="changeEntity"
                />
            </ct-block>

            <ct-block name="sw_category_detail_link_type_select_entity_category">
                <template v-if="category.linkType === 'category'">
                    <ct-category-tree-field
                        :allowed-types="allowedCategoryTypes"
                        :categories-collection="categoriesCollection"
                        :placeholder="categoryLinkPlaceholder"
                        :category-criteria="categoryCriteria"
                        :single-select="true"
                        :label="$t('global.entities.category')"
                        :help-text="categoryLinkHelpText"
                        class="ct-category-link-settings__selection-category"
                        @selection-add="onSelectionAdd"
                        @selection-remove="onSelectionRemove"
                    />
                </template>
            </ct-block>

            <ct-block name="sw_category_detail_link_type_select_entity_blog">
                <ct-entity-single-select
                    v-if="category.linkType === 'blog'"
                    v-model:value="category.internalLink"
                    class="ct-category-link-settings__selection-blog"
                    :disabled="!acl.can('category.editor') || undefined"
                    :label="$t('global.entities.blog')"
                    :placeholder="$t('ct-category.base.link.blogPlaceholder')"
                    entity="blog"
                    show-clearable-button
                />
            </ct-block>

            <ct-block name="sw_category_detail_link_type_select_entity_landing_page">
                <ct-entity-single-select
                    v-if="category.linkType === 'landing_page'"
                    v-model:value="category.internalLink"
                    class="ct-category-link-settings__selection-landing-page"
                    :disabled="!acl.can('category.editor') || undefined"
                    :label="$t('global.entities.landing_page')"
                    :placeholder="$t('ct-category.base.link.landingPagePlaceholder')"
                    entity="landing_page"
                    show-clearable-button
                />
            </ct-block>

            <ct-block name="sw_category_detail_link_field">
                <component
                    :is="linkHasProtocol ? 'mt-url-field' : 'mt-text-field'"
                    v-if="isExternal"
                    v-model="category.externalLink"
                    class="ct-category-link-settings__external-link"
                    :disabled="!acl.can('category.editor') || undefined"
                    :label="$t('ct-category.base.link.linkLabel')"
                    :placeholder="$t('ct-category.base.link.linkLabel')"
                />
            </ct-block>

            <mt-switch
                v-if="isExternal"
                v-model="linkHasProtocol"
                class="ct-category-link-settings__link-has-protocol"
                :disabled="!acl.can('category.editor') || undefined"
                :label="$t('ct-category.base.link.linkHasProtocol')"
            />

            <ct-block name="sw_category_detail_link_new_tab">
                <mt-switch
                    v-model="category.linkNewTab"
                    class="ct-category-link-settings__link-new-tab"
                    :disabled="!acl.can('category.editor') || undefined"
                    :label="$t('ct-category.base.link.linkNewTabLabel')"
                />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup>
/* eslint-disable vue/no-mutating-props */
import './ct-category-link-settings.scss';
const { Criteria } = Contena.Data;

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },

    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const $t = t;
const acl = inject('acl');
const repositoryFactory = inject('repositoryFactory');

const categoriesCollection = ref([]);
const linkHasProtocol = ref(false);

const linkTypeValues = computed(() => {
    return [
        {
            value: 'external',
            label: t('ct-category.base.link.type.external'),
        },
        {
            value: 'internal',
            label: t('ct-category.base.link.type.internal'),
        },
    ];
});
const entityValues = computed(() => {
    return [
        {
            value: 'category',
            label: t('global.entities.category'),
        },
        {
            value: 'blog',
            label: t('global.entities.blog'),
        },
        {
            value: 'landing_page',
            label: t('global.entities.landing_page'),
        },
    ];
});
const mainType = computed({
    get: () => {
        if (isExternal.value || !props.category.linkType) {
            return props.category.linkType;
        }

        return 'internal';
    },
    set: (value) => {
        if (value === 'external') {
            props.category.internalLink = null;
        } else {
            props.category.externalLink = null;
        }

        props.category.linkType = value;
    },
});
const isExternal = computed(() => {
    return props.category.linkType === 'external';
});
const isInternal = computed(() => {
    return !!props.category.linkType && props.category.linkType !== 'external';
});
const categoryCriteria = computed(() => {
    return new Criteria(1, null);
});
const internalLinkCriteria = computed(() => {
    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equals('id', props.category.internalLink));

    return criteria;
});
const categoryRepository = computed(() => {
    return repositoryFactory.create('category');
});
const categoryLinkPlaceholder = computed(() => {
    return props.category.internalLink ? '' : t('ct-category.base.link.categoryPlaceholder');
});
const allowedCategoryTypes = computed(() => {
    return ['page'];
});
const categoryLinkHelpText = computed(() => {
    return t('ct-category.base.link.categoryHelpText', {
        types: allowedCategoryTypes.value
            .map((type) => {
                return t(`ct-category.base.general.types.${type}`);
            })
            .join(', '),
    });
});

const createdComponent = () => {
    if (!props.category.linkType && props.category.externalLink) {
        props.category.linkType = 'external';
    }

    linkHasProtocol.value = props.category.externalLink?.startsWith('http') || props.category.externalLink === null;
    createCategoryCollection();
};
const changeEntity = () => {
    if (!props.category.linkType) {
        props.category.linkType = 'internal';
    }

    props.category.internalLink = null;
};
const createCategoryCollection = () => {
    categoryRepository.value.search(internalLinkCriteria.value, Contena.Context.api).then((result) => {
        categoriesCollection.value = result;
    });
};
const onSelectionAdd = (item) => {
    props.category.internalLink = item.id;
};
const onSelectionRemove = () => {
    props.category.internalLink = null;
};

createdComponent();

swDefinePublic({
    acl,
    repositoryFactory,
    categoriesCollection,
    linkHasProtocol,
    linkTypeValues,
    entityValues,
    mainType,
    isExternal,
    isInternal,
    categoryCriteria,
    internalLinkCriteria,
    categoryRepository,
    categoryLinkPlaceholder,
    allowedCategoryTypes,
    categoryLinkHelpText,
    createdComponent,
    changeEntity,
    createCategoryCollection,
    onSelectionAdd,
    onSelectionRemove,
});

defineExpose({
    acl,
    repositoryFactory,
    categoriesCollection,
    linkHasProtocol,
    linkTypeValues,
    entityValues,
    mainType,
    isExternal,
    isInternal,
    categoryCriteria,
    internalLinkCriteria,
    categoryRepository,
    categoryLinkPlaceholder,
    allowedCategoryTypes,
    categoryLinkHelpText,
    createdComponent,
    changeEntity,
    createCategoryCollection,
    onSelectionAdd,
    onSelectionRemove,
});
</script>
