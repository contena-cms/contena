<template>
    <ct-block name="sw_privilege_error">
        <ct-page class="ct-privilege-error">
            <template #content>
                <ct-block name="sw_privilege_error_inner">
                    <ct-block name="sw_privilege_error_inner_content">
                        <div class="ct-privilege-error__content">
                            <ct-block name="sw_privilege_error_inner_content_image">
                                <img
                                    class="ct-privilege-error__access-denied-image"
                                    :src="
                                        assetFilter('administration/administration/static/img/privileges/access-denied.svg')
                                    "
                                    alt=""
                                />
                            </ct-block>

                            <ct-block name="sw_privilege_error_inner_content_headline">
                                <h1>{{ $t('ct-privilege-error.general.mainMenuItemGeneral') }}</h1>
                            </ct-block>

                            <ct-block name="sw_privilege_error_inner_content_description">
                                <p>{{ $t('ct-privilege-error.general.descriptionTextModule') }}</p>
                            </ct-block>

                            <ct-block name="sw_privilege_error_inner_content_back_button">
                                <mt-button variant="primary" class="ct-privilege-error__back-button" @click="routerGoBack">
                                    {{ $t('ct-privilege-error.general.goBack') }}
                                </mt-button>
                            </ct-block>
                        </div>
                    </ct-block>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-privilege-error.scss';

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },
});

defineProps({});

import { computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});

const routerGoBack = () => {
    router.go(-1);
};

swDefinePublic({
    assetFilter,
    routerGoBack,
});

defineExpose({
    assetFilter,
    routerGoBack,
});
</script>
