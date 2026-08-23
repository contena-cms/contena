<template>
    <ct-block name="sw_landing_page_detail_base">
        <div class="ct-landing-page-detail-base">
            <ct-block name="sw_landing_page_detail_base_information">
                <mt-card
                    position-identifier="ct-landing-page-detail-base"
                    :title="$t('ct-landing-page.base.general.headlineInformationCard')"
                    :is-loading="isLoading"
                >
                    <ct-container columns="repeat(auto-fit, minmax(150px, 1fr))" gap="0px 30px">
                        <ct-block name="sw_landing_page_detail_base_information_name">
                            <mt-text-field
                                v-model="landingPage.name"
                                required
                                name="landingPageName"
                                validation="required"
                                :disabled="!acl.can('landing_page.editor')"
                                :label="$t('ct-landing-page.base.general.labelName')"
                                :placeholder="placeholder(landingPage, 'name')"
                                :error="landingPageNameError"
                            />
                        </ct-block>

                        <ct-block name="sw_landing_page_detail_base_information_active">
                            <mt-switch
                                v-model="landingPage.active"
                                class="ct-landing-page-detail-base__active"
                                name="landingPageActive"
                                :disabled="!acl.can('landing_page.editor')"
                                :label="$t('ct-landing-page.base.general.isActiveLabel')"
                                bordered
                            />
                        </ct-block>
                    </ct-container>

                    <ct-block name="sw_landing_page_detail_base_seo_form_channel">
                        <ct-entity-multi-select
                            v-model:entity-collection="landingPage.channels"
                            required
                            class="ct-landing-page-detail-base__channel"
                            entity-name="channel"
                            :disabled="!acl.can('landing_page.editor')"
                            :label="$t('ct-landing-page.base.seo.labelChannel')"
                            :placeholder="$t('ct-landing-page.base.seo.placeholderChannel')"
                            :error="landingPageChannelsError"
                        />
                    </ct-block>

                    <ct-block name="sw_landing_page_detail_base_information_tags">
                        <ct-entity-tag-select
                            v-if="landingPage && !isLoading"
                            v-model:entity-collection="landingPage.tags"
                            class="ct-landing-page-detail-base__tags"
                            :label="$t('ct-landing-page.base.general.labelTags')"
                            :placeholder="$t('ct-landing-page.base.general.labelTagsPlaceholder')"
                            :disabled="!acl.can('landing_page.editor')"
                        />
                    </ct-block>
                </mt-card>
            </ct-block>

            <ct-block name="sw_landing_page_detail_base_seo">
                <mt-card
                    position-identifier="ct-landing-page-detail-seo"
                    :title="$t('ct-landing-page.base.seo.title')"
                    :is-loading="isLoading"
                >
                    <ct-block name="sw_landing_page_detail_base_seo_form">
                        <div class="ct-landing-page-detail-base__seo-form">
                            <ct-block name="sw_landing_page_detail_base_seo_form_meta_title">
                                <mt-text-field
                                    v-model="landingPage.metaTitle"
                                    maxlength="255"
                                    :disabled="!acl.can('landing_page.editor')"
                                    :label="$t('ct-landing-page.base.seo.labelMetaTitle')"
                                    :help-text="$t('ct-landing-page.base.seo.helpTextMetaTitle')"
                                    :placeholder="
                                        placeholder(
                                            landingPage,
                                            'metaTitle',
                                            $t('ct-landing-page.base.seo.placeholderMetaTitle'),
                                        )
                                    "
                                />
                            </ct-block>

                            <ct-block name="sw_landing_page_detail_base_seo_form_meta_description">
                                <mt-textarea
                                    v-model="landingPage.metaDescription"
                                    maxlength="255"
                                    :disabled="!acl.can('landing_page.editor')"
                                    :label="$t('ct-landing-page.base.seo.labelMetaDescription')"
                                    :help-text="$t('ct-landing-page.base.seo.helpTextMetaDescription')"
                                    :placeholder="
                                        placeholder(
                                            landingPage,
                                            'metaDescription',
                                            $t('ct-landing-page.base.seo.placeholderMetaDescription'),
                                        )
                                    "
                                />
                            </ct-block>

                            <ct-block name="sw_landing_page_detail_base_seo_form_keywords">
                                <mt-text-field
                                    v-model="landingPage.keywords"
                                    :disabled="!acl.can('landing_page.editor')"
                                    :label="$t('ct-landing-page.base.seo.labelKeywords')"
                                    :placeholder="
                                        placeholder(
                                            landingPage,
                                            'keywords',
                                            $t('ct-landing-page.base.seo.placeholderKeywords'),
                                        )
                                    "
                                />
                            </ct-block>

                            <ct-block name="sw_landing_page_detail_base_seo_form_url">
                                <mt-text-field
                                    v-model="landingPage.url"
                                    required
                                    name="landingPageUrl"
                                    :disabled="!acl.can('landing_page.editor')"
                                    :label="$t('ct-landing-page.base.seo.labelUrl')"
                                    :placeholder="
                                        placeholder(landingPage, 'url', $t('ct-landing-page.base.seo.placeholderUrl'))
                                    "
                                    :error="landingPageUrlError"
                                />
                            </ct-block>
                        </div>
                    </ct-block>
                </mt-card>
            </ct-block>

            <ct-block name="sw_landing_page_detail_base_attribute_sets">
                <mt-card
                    v-if="customFieldSetsArray.length > 0"
                    position-identifier="ct-landing-page-detail-attribute-sets"
                    :title="$t('ct-settings-custom-field.general.mainMenuItemGeneral')"
                    :is-loading="isLoading"
                >
                    <ct-custom-field-set-renderer
                        :entity="landingPage"
                        :sets="customFieldSetsArray"
                        :disabled="!acl.can('landing_page.editor')"
                    />
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
Contena.Component.getComponentHelper();

defineProps({
    isLoading: {
        type: Boolean,
        required: true,
    },
});

import { computed, inject } from 'vue';
import { usePlaceholder } from 'src/app/composables/use-placeholder';

const { placeholder } = usePlaceholder();

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const customFieldSetsArray = computed(() => {
    return Contena.Store.get('swCategoryDetail').customFieldSets ?? [];
});
const landingPageNameError = computed(() => {
    const entity = landingPage.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const landingPageUrlError = computed(() => {
    const entity = landingPage.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'url');
});
const landingPageChannelsError = computed(() => {
    const entity = landingPage.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'channels');
});
const landingPage = computed(() => {
    return Contena.Store.get('swCategoryDetail').landingPage;
});

swDefinePublic({
    repositoryFactory,
    acl,
    customFieldSetsArray,
    landingPageNameError,
    landingPageUrlError,
    landingPageChannelsError,
    landingPage,
});

defineExpose({
    repositoryFactory,
    acl,
    customFieldSetsArray,
    landingPageNameError,
    landingPageUrlError,
    landingPageChannelsError,
    landingPage,
});
</script>
