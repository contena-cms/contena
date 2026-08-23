<template>
    <ct-block name="sw_blog_basic_form">
        <div class="ct-blog-basic-form">
            <ct-block name="sw_blog_basic_form_title">
                <mt-text-field
                    v-model="blog.name"
                    name="ct-field--blog-name"
                    :label="$t('ct-blog.basicForm.labelName')"
                    :placeholder="placeholder(blog, 'name', $t('ct-blog.basicForm.placeholderName'))"
                    :error="blogNameError"
                    :required="isTitleRequired"
                    :disabled="!allowEdit || undefined"
                />
            </ct-block>

            <ct-block name="sw_blog_basic_form_description">
                <mt-text-editor
                    v-model="blog.description"
                    sanitize-input
                    sanitize-field-name="blog_translation.description"
                    :label="$t('ct-blog.basicForm.labelDescription')"
                    :placeholder="placeholder(blog, 'description', $t('ct-blog.basicForm.placeholderDescription'))"
                    :error="blogDescriptionError"
                    :disabled="!allowEdit || undefined"
                />
            </ct-block>

            <ct-block name="sw_blog_basic_form_publication">
                <ct-container columns="1fr 1fr" gap="0 var(--scale-size-30)">
                    <mt-switch
                        v-model="blog.active"
                        :label="$t('ct-blog.basicForm.labelActive')"
                        :error="blogActiveError"
                        :disabled="!allowEdit || undefined"
                    />
                    <mt-datepicker
                        v-model="blog.releaseDate"
                        date-type="datetime-local"
                        :label="$t('ct-blog.basicForm.labelReleaseDate')"
                        :disabled="!allowEdit || undefined"
                    />
                </ct-container>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePlaceholder } from 'src/app/composables/use-placeholder';

import './ct-blog-basic-form.scss';

defineProps({
    allowEdit: {
        type: Boolean,
        default: true,
    },
});

const { t } = useI18n();
const { placeholder } = usePlaceholder();
const $t = t;

const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const getApiError = (property: string) => {
    const entity = blog.value;

    if (!entity || typeof entity.getEntityName !== 'function') {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, property);
};
const blogNameError = computed(() => getApiError('name'));
const blogDescriptionError = computed(() => getApiError('description'));
const blogActiveError = computed(() => getApiError('active'));
const isTitleRequired = computed(() => Contena.Store.get('context').isSystemDefaultLanguage);

swDefinePublic({
    blog,
    blogNameError,
    blogDescriptionError,
    blogActiveError,
    isTitleRequired,
});

defineExpose({ blog, blogNameError, blogDescriptionError, blogActiveError, isTitleRequired });
</script>
