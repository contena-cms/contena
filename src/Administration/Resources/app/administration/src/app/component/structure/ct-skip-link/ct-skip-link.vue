<template>
    <ct-block name="sw_skip_link">
        <a
            href="#main"
            tabindex="0"
            class="ct-skip-link"
            :class="{ 'ct-skip-link__focussed': focussed }"
            @click.prevent="focusElement"
            @focus="setFocus(true)"
            @blur="setFocus(false)"
        >
            {{ $t('global.ct-skip-link.label') }}
        </a>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-skip-link.scss';

defineProps({});

import { ref } from 'vue';

const focussed = ref(false);

const setFocus = (isFocused: boolean) => {
    focussed.value = isFocused;
};
const focusElement = () => {
    const element = window.document.getElementById('main');

    if (!element) {
        return;
    }

    element.focus();
};

swDefinePublic({
    focussed,
    setFocus,
    focusElement,
});

defineExpose({
    focussed,
    setFocus,
    focusElement,
});
</script>
