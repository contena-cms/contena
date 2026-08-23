<template>
    <ct-block name="sw_member_base_info">
        <ct-member-base-form v-if="memberEditMode" :member="member" />
        <dl v-else class="ct-member-base-info">
            <div>
                <dt>{{ $t('ct-member.baseForm.labelEmail') }}</dt>
                <dd>
                    <a :href="mailTo">{{ member.email }}</a>
                </dd>
            </div>
            <div>
                <dt>{{ $t('ct-member.detailBase.labelGroup') }}</dt>
                <dd>{{ member.group?.translated?.name || member.group?.name || '-' }}</dd>
            </div>
            <div>
                <dt>{{ $t('ct-member.detailBase.labelChannel') }}</dt>
                <dd>{{ member.channel?.translated?.name || member.channel?.name || '-' }}</dd>
            </div>
            <div>
                <dt>{{ $t('ct-member.detailBase.labelMemberSince') }}</dt>
                <dd>{{ member.createdAt || '-' }}</dd>
            </div>
            <div>
                <dt>{{ $t('ct-member.detailBase.labelLastLogin') }}</dt>
                <dd>{{ member.lastLogin || '-' }}</dd>
            </div>
        </dl>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import type { PropType } from 'vue';
import { computed } from 'vue';

import './ct-member-base-info.scss';

const props = defineProps({
    member: { type: Object as PropType<Entity<'member'>>, required: true },
    memberEditMode: { type: Boolean, default: false },
    isLoading: { type: Boolean, default: false },
});
const mailTo = computed(() => `mailto:${props.member.email}`);

swDefinePublic({
    mailTo,
});

defineExpose({ mailTo });
</script>
