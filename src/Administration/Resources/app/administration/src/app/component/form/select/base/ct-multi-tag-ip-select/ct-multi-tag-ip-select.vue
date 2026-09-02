<template>
    <ct-block name="ct_multi_tag_ip_select">
        <ct-multi-tag-select
            :value="value"
            :validate="validate"
            :error-code="errorCode"
            v-bind="$attrs"
            @update:value="emit('update:value', $event)"
        >
            <template #message-add-data>
                <span>{{ t('global.ct-multi-tag-ip-select.addIpAddress') }}</span>
            </template>

            <template #message-enter-valid-data>
                <span>{{ t('global.ct-multi-tag-ip-select.enterValidIp') }}</span>
            </template>

            <template #validation-options="{ onSearchTermChange, addItem }">
                <button
                    v-for="knownIp in validUnselectedKnownIps"
                    :key="knownIp.value"
                    class="ct-multi-tag-select-valid ct-multi-tag-ip-select__known-ip"
                    type="button"
                    @click="addSpecific(knownIp.value, onSearchTermChange, addItem)"
                >
                    {{ knownIp.value }} ({{ translateKnownIpName(knownIp.name) }})
                </button>
            </template>

            <template #selection-label-property="{ item }">
                <template v-if="getKnownIp(item.value)">
                    {{ item.value }} ({{ translateKnownIpName(getKnownIp(item.value)?.name ?? '') }})
                </template>
                <template v-else>
                    {{ item.value }}
                </template>
            </template>
        </ct-multi-tag-select>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import './ct-multi-tag-ip-select.scss';

type KnownIp = { name: string; value: string };

type Validate = (_term: string) => boolean;
type SetSearchTerm = (_term: string) => void;
type AddItem = () => void;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    value: { type: Array as PropType<string[]>, required: true },
    validate: {
        type: Function as PropType<Validate>,
        default: (searchTerm: string) => Contena.Utils.string.isValidIp(searchTerm),
    },
    knownIps: { type: Array as PropType<KnownIp[]>, default: () => [] },
    errorCode: { type: String, default: 'CONTENA_INVALID_IP' },
});
const emit = defineEmits<{ 'update:value': [value: string[]] }>();
// vue-i18n exposes methods bound to its composer; the template and computed state use them as callbacks.
// eslint-disable-next-line @typescript-eslint/unbound-method
const { t, te } = useI18n();
const validKnownIps = computed(() => props.knownIps.filter((ip) => props.validate(ip.value)));
const validUnselectedKnownIps = computed(() => {
    return validKnownIps.value.filter((ip) => !props.value.includes(ip.value));
});
const addSpecific = (value: string, setSearchTerm: SetSearchTerm, addItem: AddItem): void => {
    setSearchTerm(value);
    addItem();
};
const getKnownIp = (ip: string): KnownIp | null => {
    return validKnownIps.value.find((knownIp) => knownIp.value === ip) ?? null;
};
const translateKnownIpName = (name: string): string => (te(name) ? t(name) : name);

ctDefinePublic({
    validKnownIps,
    validUnselectedKnownIps,
    addSpecific,
    getKnownIp,
    translateKnownIpName,
});

defineExpose({ validKnownIps, validUnselectedKnownIps, addSpecific, getKnownIp, translateKnownIpName });
</script>
