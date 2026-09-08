<template>
    <ct-block name="ct_shortcut_overview_item">
        <div v-if="showItem" class="ct-shortcut-overview-item">
            <span class="ct-shortcut-overview-item__title">{{ title }}</span>

            <span class="ct-shortcut-overview-item__keys">
                <kbd
                    v-for="(key, index) in keys"
                    :key="`${key.label}-${index}`"
                    class="ct-shortcut-overview-item__key"
                    :aria-label="key.ariaLabel"
                >
                    {{ key.label }}
                </kbd>
            </span>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue';
import { classifyPlatform, formatShortcutKey } from 'src/core/helper/shortcut-key.helper';
import type AclService from 'src/app/service/acl.service';

const props = withDefaults(
    defineProps<{
        title: string;
        content: string;
        privilege?: string | null;
    }>(),
    { privilege: null },
);

const acl = inject<AclService>('acl');

if (!acl) {
    throw new Error('ct-shortcut-overview-item requires acl');
}

const showItem = computed(() => acl.can(props.privilege));
const platform = computed(() => classifyPlatform(window.navigator.platform));
const keys = computed(() =>
    props.content
        .split(' ')
        .flatMap((key) => key.split('-'))
        .filter(Boolean)
        .map((key) => formatShortcutKey(key, platform.value)),
);

ctDefinePublic({ showItem, platform, keys });
</script>

<style lang="scss">
.ct-shortcut-overview-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    column-gap: var(--scale-size-8);
    margin: var(--scale-size-8) 0;

    &__keys {
        display: inline-flex;
        align-items: center;
        gap: var(--scale-size-8);
        justify-self: end;
        min-width: 0;
        user-select: none;
    }

    &__key {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: var(--scale-size-24);
        height: var(--scale-size-24);
        padding: 0 var(--scale-size-6);
        font-family: inherit;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        line-height: var(--font-line-height-xs);
        white-space: nowrap;
        color: var(--color-text-primary-default);
        border: 1px solid var(--color-border-primary-default);
        border-radius: var(--border-radius-2xs);
        background-color: var(--color-background-primary-default);
        box-shadow: inset 0 -1px 0 var(--color-border-primary-default);
        cursor: default;
    }

    &__title {
        min-width: 0;
        font-size: var(--font-size-xs);
        line-height: var(--font-line-height-xs);
    }
}
</style>
