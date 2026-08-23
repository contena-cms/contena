<template>
    <ct-block name="sw_mail_template_view_templates">
        <div class="ct-mail-template-view-templates">
            <ct-block name="sw_mail_template_view_templates_list">
                <ct-mail-template-list ref="mailTemplateList" />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { ref } from 'vue';

defineProps({});
const mailTemplateList = ref<{ getList?: () => Promise<void> } | null>(null);
function getList(): void {
    void mailTemplateList.value?.getList?.();
}

swDefinePublic({
    getList,
});

defineExpose({ getList });
</script>
