<template>
    <component
        :is="iconComponent"
        class="ct-icon"
        :style="iconStyle"
        :spin="props.spin"
        :role="props.label ? 'img' : undefined"
        :aria-label="props.label"
        :aria-hidden="props.label ? undefined : 'true'"
    />
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue';
import * as AntIcons from '@ant-design/icons-vue';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: number | string;
        spin?: boolean;
        label?: string;
    }>(),
    {
        size: 16,
        spin: false,
        label: undefined,
    },
);

const icons = AntIcons as unknown as Record<string, Component>;

const normalizedName = computed(() => {
    const name = props.name
        .replace(/Outlined$/, '');

    return name
        .split(/[-_\s]+/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');
});
const iconComponent = computed(() => {
    const iconName = normalizedName.value;

    return icons[iconName] ?? icons[`${iconName}Outlined`] ?? icons.AppstoreOutlined;
});
const iconStyle = computed(() => ({
    fontSize: typeof props.size === 'number' ? `${props.size}px` : props.size,
}));

swDefinePublic({
    normalizedName,
    iconComponent,
    iconStyle,
});
</script>
