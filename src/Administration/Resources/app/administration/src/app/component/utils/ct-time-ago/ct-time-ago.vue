<template>
    <ct-block name="ct_time_ago">
        <span
            v-tooltip="{
                message: fullDatetime,
                disabled: !isToday,
            }"
            >{{ formattedRelativeTime }}</span
        >
    </ct-block>
</template>

<script setup lang="ts">
import useUpdateClock from './updateClock';

const props = defineProps({
    date: {
        type: [
            Date,
            String,
        ] as PropType<Date | string>,
        required: true,
    },
    dateTimeFormat: {
        type: Object as PropType<Intl.DateTimeFormatOptions>,
        required: false,
        default: () => ({}),
    },
});

import { type PropType, ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const formattedRelativeTime = ref(null);
const interval = ref(null);
const now = ref(Date.now());

const dateObject = computed(() => {
    // when prop is string then convert it to date object
    if (typeof props.date === 'string') {
        return new Date(props.date);
    }

    return props.date;
});
const dateFilter = computed(() => {
    return Contena.Filter.getByName('date');
});
const fullDatetime = computed(() => {
    return dateFilter.value(dateObject.value.toString(), props.dateTimeFormat);
});
const lessThanOneMinute = computed(() => {
    const minute = 1000 * 60;
    const minuteAgo = now.value - minute;

    return dateObject.value.getTime() > minuteAgo;
});
const lessThanOneHour = computed(() => {
    const hour = 1000 * 60 * 60;
    const hourAgo = now.value - hour;

    return dateObject.value.getTime() > hourAgo;
});
const lessThanOneMinuteFromNow = computed(() => {
    const minute = 1000 * 60;
    const minuteAfter = now.value + minute;

    return dateObject.value.getTime() < minuteAfter;
});
const lessThanOneHourFromNow = computed(() => {
    const hour = 1000 * 60 * 60;
    const hourAfter = now.value + hour;

    return dateObject.value.getTime() < hourAfter;
});
const isToday = computed(() => {
    const today = new Date(Date.now());

    return (
        dateObject.value.getDate() === today.getDate() &&
        dateObject.value.getMonth() === today.getMonth() &&
        dateObject.value.getFullYear() === today.getFullYear()
    );
});

const formatRelativeTime = () => {
    const diff = Date.now() - dateObject.value.getTime();

    const secondsAgo = Math.round(diff / 1000);
    const minutesAgo = Math.round(secondsAgo / 60);

    if (diff >= 0) {
        if (lessThanOneMinute.value) {
            return t('global.ct-time-ago.justNow');
        }

        if (lessThanOneHour.value) {
            return t('global.ct-time-ago.minutesAgo', { minutesAgo }, minutesAgo);
        }
    } else {
        if (lessThanOneMinuteFromNow.value) {
            return t('global.ct-time-ago.aboutNow');
        }

        if (lessThanOneHourFromNow.value) {
            const minutesFromNow = Math.abs(minutesAgo);
            return t('global.ct-time-ago.minutesFromNow', { minutesFromNow }, minutesFromNow);
        }
    }

    if (isToday.value) {
        return dateFilter.value(dateObject.value.toString(), {
            year: undefined,
            month: undefined,
            day: undefined,
        });
    }

    return dateFilter.value(dateObject.value.toString(), props.dateTimeFormat);
};

watch(
    () => props.date,
    () => {
        formattedRelativeTime.value = formatRelativeTime();
    },
);

onMounted(() => {
    // subscriber to the updater, which updates the formatted date every 30 seconds
    useUpdateClock(() => {
        // we have to set a new date, as vue does not react to changes in the date object
        // and does not invalidate the computed cache
        // this would lead to a wrong time string, if the component is active for more than 1 minute e.g.
        now.value = Date.now();
        formattedRelativeTime.value = formatRelativeTime();
    });
});

ctDefinePublic({
    formattedRelativeTime,
    interval,
    now,
    dateObject,
    dateFilter,
    fullDatetime,
    lessThanOneMinute,
    lessThanOneHour,
    lessThanOneMinuteFromNow,
    lessThanOneHourFromNow,
    isToday,
    formatRelativeTime,
});

defineExpose({
    formattedRelativeTime,
    interval,
    now,
    dateObject,
    dateFilter,
    fullDatetime,
    lessThanOneMinute,
    lessThanOneHour,
    lessThanOneMinuteFromNow,
    lessThanOneHourFromNow,
    isToday,
    formatRelativeTime,
});
</script>
