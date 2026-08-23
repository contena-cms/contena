<template>
    <ct-block name="sw_settings_search_example_modal">
        <mt-modal-root :is-open="true" @change="closeModal">
            <mt-modal :title="t('ct-settings-search.generalTab.titleExampleModal')" width="l">
                <p class="ct-settings-search__example-text">{{ t('ct-settings-search.generalTab.modal.textExplain') }}</p>
                <ul class="ct-settings-search__searchable-content-example-list">
                    <li v-for="result in exampleResults" :key="result.textBlogRankedScore">
                        <div class="ct-settings-search__searchable-content-example-wrapper">
                            <div class="ct-settings-search__searchable-content-example-detail">
                                <div>{{ result.textTitle }}: {{ result.textSuperBlogName }}</div>
                                <div>{{ result.scoreSuperBlogName }}</div>
                                <div>{{ result.textDescription }}: {{ result.textBlogName }}</div>
                                <div>{{ result.scoreBlogName }}</div>
                                <div>{{ result.textTag }}: {{ result.textDetailName }}</div>
                                <div>{{ result.scoreDetail }}</div>
                                <div>{{ result.textTotal }}:</div>
                                <div>{{ result.scoreTotal }}</div>
                            </div>
                            <p>{{ result.textBlogRankedScore }}</p>
                        </div>
                    </li>
                </ul>
                <template #footer>
                    <mt-button variant="primary" @click="closeModal">{{ t('global.default.close') }}</mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import './ct-settings-search-example-modal.scss';

const emit = defineEmits<{ 'modal-close': [] }>();
const { t } = useI18n();
const exampleResults = computed(() =>
    [
        [
            'textSuperBlog',
            100,
            'textFancyBlog',
            50,
            20,
            170,
            'textBlogRankedFirstScore',
        ],
        [
            'textSuperBlog',
            100,
            'textFancyContent',
            0,
            20,
            120,
            'textBlogRankedSecondScore',
        ],
        [
            'textSuperContent',
            0,
            'textFancyContent',
            0,
            20,
            20,
            'textBlogRankedThirdScore',
        ],
    ].map(
        ([
            superBlog,
            superScore,
            blog,
            blogScore,
            detailScore,
            total,
            explanation,
        ]) => ({
            textTitle: t('ct-settings-search.generalTab.modal.textTitle'),
            textSuperBlogName: t(`ct-settings-search.generalTab.modal.${String(superBlog)}`),
            scoreSuperBlogName: superScore,
            textDescription: t('ct-settings-search.generalTab.modal.textDescription'),
            textBlogName: t(`ct-settings-search.generalTab.modal.${String(blog)}`),
            scoreBlogName: blogScore,
            textTag: t('ct-settings-search.generalTab.modal.textTag'),
            textDetailName: t('ct-settings-search.generalTab.modal.textJeans'),
            scoreDetail: detailScore,
            textTotal: t('ct-settings-search.generalTab.modal.textTotal'),
            scoreTotal: total,
            textBlogRankedScore: t(`ct-settings-search.generalTab.modal.${String(explanation)}`),
        }),
    ),
);
const closeModal = (): void => emit('modal-close');

swDefinePublic({
    exampleResults,
    closeModal,
});

defineExpose({ exampleResults, closeModal });
</script>
