<template>
    <ct-block name="ct_media_tag">
        <div class="ct-media-tag">
            <ct-media-collapse :title="$t('global.ct-tag-field.title')" :expand-on-loading="true">
                <template #content>
                    <ct-block name="ct_media_tag_input">
                        <ct-entity-tag-select
                            v-model:entity-collection="media.tags"
                            :disabled="disabled"
                            @update:entity-collection="handleChange"
                        />
                    </ct-block>
                </template>
            </ct-media-collapse>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-tag.scss';

const props = defineProps({
    media: {
        type: Object,
        required: true,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { computed, inject } from 'vue';

const media = computed(() => props.media);

const repositoryFactory = inject('repositoryFactory');

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});

const handleChange = () => {
    mediaRepository.value.save(media.value);
};

ctDefinePublic({
    repositoryFactory,
    mediaRepository,
    handleChange,
});

defineExpose({
    repositoryFactory,
    mediaRepository,
    handleChange,
});
</script>
