<template>
    <ct-block name="ct_context_menu">
        <div class="ct-context-menu">
            <ct-block name="ct_context_menu_content">
                <div class="ct-context-menu__content">
                    <slot>
                        <ct-block name="ct_context_menu_slot_default"></ct-block>
                    </slot>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-context-menu.scss';

defineProps({});

ctDefinePublic({});
</script>
