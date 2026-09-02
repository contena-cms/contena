<template>
    <ct-block name="ct_mail_template_view_header_footer">
        <div class="ct-mail-template-view-header-footer">
            <ct-block name="ct_mail_template_view_header_footer_list">
                <ct-mail-header-footer-list ref="mailHeaderFooterList" />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { ref } from 'vue';

defineProps({});
const mailHeaderFooterList = ref<{ getList?: () => Promise<void> } | null>(null);
function getList(): void {
    void mailHeaderFooterList.value?.getList?.();
}

ctDefinePublic({
    getList,
});

defineExpose({ getList });
</script>
