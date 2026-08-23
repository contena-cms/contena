<template>
    <ct-block name="sw_inheritance_warning">
        <div class="ct-inheritance-warning">
            <ct-block name="sw_inheritance_warning_icon">
                <mt-icon size="16px" name="regular-link-horizontal" />
            </ct-block>

            <ct-block name="sw_inheritance_warning_text">
                <p>
                    {{ $t('ct-inheritance-warning.infoTextInheritedField', { moduleName: name }) }}
                </p>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-inheritance-warning.scss';

defineProps({
    name: {
        type: String,
        required: true,
    },
});

swDefinePublic({});
</script>
