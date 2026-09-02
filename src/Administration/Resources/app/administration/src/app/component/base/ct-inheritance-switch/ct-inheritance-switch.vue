<template>
    <ct-block name="ct_inheritance_switch">
        <div
            class="ct-inheritance-switch"
            :class="{
                'ct-inheritance-switch--disabled': disabled,
                'ct-inheritance-switch--is-inherited': isInherited,
                'ct-inheritance-switch--is-not-inherited': !isInherited,
            }"
            :aria-label="
                isInherited ? $t('global.ct-field.ariaUnlinkInheritance') : $t('global.ct-field.ariaLinkInheritance')
            "
        >
            <ct-block name="ct_inheritance_switch_inherit_icon">
                <mt-icon
                    v-if="isInherited"
                    key="inherit-icon"
                    v-tooltip="{ message: $t('global.ct-field.tooltipRemoveInheritance'), disabled: disabled }"
                    name="regular-link-horizontal"
                    size="16px"
                    @click="onClickRemoveInheritance"
                />
            </ct-block>
            <ct-block name="ct_inheritance_switch_uninherit_icon">
                <template v-if="isInherited"><!-- Keeps the conditional chain connected across ct-block. --></template>
                <mt-icon
                    v-else
                    key="uninherit-icon"
                    v-tooltip="{ message: $t('global.ct-field.tooltipRestoreInheritance'), disabled: disabled }"
                    :class="unInheritClasses"
                    name="regular-link-horizontal-slash"
                    size="16px"
                    @click="onClickRestoreInheritance"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-inheritance-switch.scss';

const props = defineProps({
    isInherited: {
        type: Boolean,
        required: true,
        default: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'inheritance-restore',
    'inheritance-remove',
]);

import { computed, inject } from 'vue';

const restoreInheritanceHandler = inject('restoreInheritanceHandler', null);
const removeInheritanceHandler = inject('removeInheritanceHandler', null);

const unInheritClasses = computed(() => {
    return { 'is--clickable': !props.disabled };
});

const onClickRestoreInheritance = () => {
    if (props.disabled) {
        return;
    }
    emit('inheritance-restore');

    if (restoreInheritanceHandler) {
        restoreInheritanceHandler();
    }
};
const onClickRemoveInheritance = () => {
    if (props.disabled) {
        return;
    }
    emit('inheritance-remove');

    if (removeInheritanceHandler) {
        removeInheritanceHandler();
    }
};

ctDefinePublic({
    restoreInheritanceHandler,
    removeInheritanceHandler,
    unInheritClasses,
    onClickRestoreInheritance,
    onClickRemoveInheritance,
});

defineExpose({
    restoreInheritanceHandler,
    removeInheritanceHandler,
    unInheritClasses,
    onClickRestoreInheritance,
    onClickRemoveInheritance,
});
</script>
