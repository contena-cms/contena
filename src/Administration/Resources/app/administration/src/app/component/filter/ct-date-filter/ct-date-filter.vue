<template>
    <ct-block name="sw_date_filter">
        <ct-range-filter
            :class="'ct-date-filter' + (filter.showTimeframe ? ' --has-timeframe' : '')"
            :value="dateValue"
            :title="filter.label"
            :active="active"
            :show-reset-button="!!dateValue.from || !!dateValue.to"
            :is-show-divider="showDivider"
            :property="filter.property"
            @filter-update="updateFilter"
            @filter-reset="resetFilter"
        >
            <ct-block name="sw_date_filter_timeframe">
                <mt-select
                    v-if="filter.showTimeframe"
                    v-model="dateValue.timeframe"
                    class="ct-date-filter__timeframe"
                    :placeholder="$t('ct-date-filter.selectTimeframe.placeholder')"
                    :options="timeframeOptions"
                    @update:model-value="onTimeframeSelect"
                />
            </ct-block>

            <template #from-field>
                <ct-block name="sw_date_filter_from_field">
                    <mt-datepicker
                        v-model="dateValue.from"
                        v-bind="$attrs"
                        class="ct-date-filter__from"
                        :date-type="dateType"
                        :placeholder="filter.fromPlaceholder"
                        :label="fromToFieldLabel('from')"
                        @update:model-value="resetTimeframe"
                    />
                </ct-block>
            </template>

            <template #to-field>
                <ct-block name="sw_date_filter_to_field">
                    <mt-datepicker
                        v-model="dateValue.to"
                        v-bind="$attrs"
                        class="ct-date-filter__to"
                        :date-type="dateType"
                        :placeholder="filter.toPlaceholder"
                        :label="fromToFieldLabel('to')"
                        @update:model-value="resetTimeframe"
                    />
                </ct-block>
            </template>
        </ct-range-filter>
    </ct-block>
</template>

<script setup>
import { zonedTimeToUtc } from 'date-fns-tz';
import './ct-date-filter.scss';
const { Criteria } = Contena.Data;

const props = defineProps({
    filter: {
        type: Object,
        required: true,
    },

    active: {
        type: Boolean,
        required: true,
    },
});
const emit = defineEmits([
    'filter-reset',
    'filter-update',
]);

import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const feature = inject('feature');

const dateValue = ref({
    from: null,
    to: null,
    timeframe: null,
});
const timeframeOptions = ref([
    {
        label: $t('ct-date-filter.options.today'),
        value: 'today',
    },
    {
        label: $t('ct-date-filter.options.yesterday'),
        value: 'yesterday',
    },
    {
        label: $t('ct-date-filter.options.currentWeek'),
        value: 'currentWeek',
    },
    {
        label: $t('ct-date-filter.options.lastWeek'),
        value: -7,
    },
    {
        label: $t('ct-date-filter.options.lastCalendarWeek'),
        value: 'lastCalendarWeek',
    },
    {
        label: $t('ct-date-filter.options.currentMonth'),
        value: 'currentMonth',
    },
    {
        label: $t('ct-date-filter.options.lastMonth'),
        value: -30,
    },
    {
        label: $t('ct-date-filter.options.lastCalendarMonth'),
        value: 'lastCalendarMonth',
    },
    {
        label: $t('ct-date-filter.options.currentQuarter'),
        value: 'currentQuarter',
    },
    {
        label: $t('ct-date-filter.options.lastQuarter'),
        value: 'lastQuarter',
    },
    {
        label: $t('ct-date-filter.options.last3Months'),
        value: 'last3Months',
    },
    {
        label: $t('ct-date-filter.options.last6Months'),
        value: 'last6Months',
    },
    {
        label: $t('ct-date-filter.options.last12Months'),
        value: 'last12Months',
    },
    {
        label: $t('ct-date-filter.options.currentYear'),
        value: 'currentYear',
    },
    {
        label: $t('ct-date-filter.options.previousYear'),
        value: 'previousYear',
    },
    {
        label: $t('ct-date-filter.options.custom'),
        value: 'custom',
        hidden: true,
    },
]);

const dateType = computed(() => {
    if (
        [
            'time',
            'date',
            'datetime',
            'datetime-local',
        ].includes(props.filter.dateType)
    ) {
        return props.filter.dateType;
    }

    return 'date';
});
const isDateTimeType = computed(() => {
    return dateType.value === 'datetime' || dateType.value === 'datetime-local';
});
const showDivider = computed(() => {
    return !isDateTimeType.value && !props.filter.showTimeframe;
});
const userTimeZone = computed(() => {
    return Contena.Store.get('session').currentUser?.timeZone ?? 'Asia/Shanghai';
});

const fromToFieldLabel = (type) => {
    const key = `${type}FieldLabel`;

    if (!props.filter.hasOwnProperty(key)) {
        return $t(`global.default.${type}`);
    }

    const label = props.filter[key];

    if (!label) {
        return null;
    }

    return label;
};
const updateFilter = () => {
    if (!dateValue.value.from && !dateValue.value.to) {
        emit('filter-reset', props.filter.name);
        return;
    }

    const normalizedDateValue = getNormalizedDateValue(dateValue.value);

    const { value } = props.filter;
    if (value && value.from === normalizedDateValue.from && value.to === normalizedDateValue.to) {
        return;
    }

    const params = {
        ...(normalizedDateValue.from ? { gte: normalizedDateValue.from } : {}),
        ...(normalizedDateValue.to ? { lte: normalizedDateValue.to } : {}),
    };

    emit('filter-update', props.filter.name, [Criteria.range(props.filter.property, params)], normalizedDateValue);
};
const onTimeframeSelect = (timeframe) => {
    if (!timeframe) {
        return;
    }

    const resolved = aliasLegacyTimeframe(timeframe);

    if (!timeframeOptions.value.some((t) => t.value === resolved)) {
        console.error(`Timeframe ${timeframe} is not allowed for ct-date-filter component`);
        return;
    }

    resetFilter();

    const { startDate: from, endDate: to } = getTimeframeDates(resolved);

    const normalizedDateValue = getNormalizedDateValue({
        from: formatDateParts(from),
        to: formatDateParts(to),
        timeframe: resolved,
    });

    const params = {
        gte: normalizedDateValue.from,
        lte: normalizedDateValue.to,
    };

    const filterCriteria = [
        Criteria.range(props.filter.property, params),
    ];

    dateValue.value = normalizedDateValue;

    emit('filter-update', props.filter.name, filterCriteria, dateValue.value);
};
function aliasLegacyTimeframe(timeframe) {
    // Legacy values that no longer appear in timeframeOptions are mapped
    // to their closest current equivalents so saved filter states keep
    // resolving to a labelled dropdown entry.
    const aliases = {
        '-1': 'yesterday',
        '-365': 'last12Months',
    };
    return aliases[String(timeframe)] ?? timeframe;
}
function getTimeframeDates(timeframe) {
    if (typeof timeframe === 'number') {
        const endDate = getTodayInUserTimezone();
        const startDate = createDateParts(endDate.year, endDate.month, endDate.date + timeframe);
        return {
            startDate,
            endDate,
        };
    }
    switch (timeframe) {
        case 'today':
            return getTodayDates();
        case 'yesterday':
            return getYesterdayDates();
        case 'currentWeek':
            return getCurrentCalendarWeekDates();
        case 'lastCalendarWeek':
            return getPreviousCalendarWeekDates();
        case 'currentMonth':
            return getCurrentCalendarMonthDates();
        case 'lastCalendarMonth':
            return getPreviousCalendarMonthDates();
        case 'currentQuarter':
            return getCurrentQuarterDates();
        case 'lastQuarter':
            return getPreviousQuarterDates();
        case 'last3Months':
            return getLastNMonthsDates(3);
        case 'last6Months':
            return getLastNMonthsDates(6);
        case 'last12Months':
            return getLastNMonthsDates(12);
        case 'currentYear':
            return getCurrentYearDates();
        case 'previousYear':
            return getPreviousYearDates();
        default:
            return getTodayDates();
    }
}
function getTodayDates() {
    const day = getTodayInUserTimezone();
    return {
        startDate: day,
        endDate: day,
    };
}
function getYesterdayDates() {
    const today = getTodayInUserTimezone();
    const yesterday = createDateParts(today.year, today.month, today.date - 1);
    return {
        startDate: yesterday,
        endDate: yesterday,
    };
}
function getCurrentCalendarWeekDates() {
    const today = getTodayInUserTimezone();
    // ISO week: Monday = 0 ... Sunday = 6
    const isoDayIndex = (today.day + 6) % 7;
    const startDate = createDateParts(today.year, today.month, today.date - isoDayIndex);
    return {
        startDate: startDate,
        endDate: today,
    };
}
function getCurrentCalendarMonthDates() {
    const today = getTodayInUserTimezone();
    const startDate = createDateParts(today.year, today.month, 1);
    return {
        startDate: startDate,
        endDate: today,
    };
}
function getCurrentQuarterDates() {
    const today = getTodayInUserTimezone();
    const quarter = Math.floor(today.month / 3);
    const startDate = createDateParts(today.year, quarter * 3, 1);
    return {
        startDate: startDate,
        endDate: today,
    };
}
function getLastNMonthsDates(months) {
    const today = getTodayInUserTimezone();
    const targetMonth = today.month - months;
    let startDate = createDateParts(today.year, targetMonth, today.date);
    const expectedMonth = createDateParts(today.year, targetMonth, 1).month;

    // Clamp to the last day of the target month if JS overflowed
    // (e.g., asking for May 31 - 3 months yields Feb 31 -> Mar 3).
    if (startDate.month !== expectedMonth) {
        startDate = createDateParts(today.year, targetMonth + 1, 0);
    }
    return {
        startDate: startDate,
        endDate: today,
    };
}
function getCurrentYearDates() {
    const today = getTodayInUserTimezone();
    const startDate = createDateParts(today.year, 0, 1);
    return {
        startDate: startDate,
        endDate: today,
    };
}
function getPreviousYearDates() {
    const today = getTodayInUserTimezone();
    const startDate = createDateParts(today.year - 1, 0, 1);
    const endDate = createDateParts(today.year - 1, 11, 31);
    return {
        startDate: startDate,
        endDate: endDate,
    };
}
function resetFilter() {
    dateValue.value = {
        from: null,
        to: null,
        timeframe: null,
    };
    emit('filter-reset', props.filter.name, dateValue.value);
}
const resetTimeframe = () => {
    dateValue.value.timeframe = 'custom';
};
function getPreviousCalendarMonthDates() {
    const today = getTodayInUserTimezone();
    const startDate = createDateParts(today.year, today.month - 1, 1);
    const endDate = createDateParts(today.year, today.month, 0);
    return {
        startDate: startDate,
        endDate: endDate,
    };
}
function getPreviousCalendarWeekDates() {
    const today = getTodayInUserTimezone();
    // ISO week: Monday = 0 ... Sunday = 6
    const isoDayIndex = (today.day + 6) % 7;
    const startDate = createDateParts(today.year, today.month, today.date - isoDayIndex - 7);
    const endDate = createDateParts(today.year, today.month, today.date - isoDayIndex - 1);
    return {
        startDate: startDate,
        endDate: endDate,
    };
}
function getPreviousQuarterDates() {
    const today = getTodayInUserTimezone();
    const quarter = Math.floor(today.month / 3);
    const startDate = createDateParts(today.year, quarter * 3 - 3, 1);
    const endDate = createDateParts(startDate.year, startDate.month + 3, 0);
    return {
        startDate: startDate,
        endDate: endDate,
    };
}
function getNormalizedDateValue(dateValue) {
    return {
        from: dateValue.from ? getUserTimeZoneDateBoundary(dateValue.from, '00:00:00.000') : null,
        to: dateValue.to ? getUserTimeZoneDateBoundary(dateValue.to, '23:59:59.000') : null,
        timeframe: dateValue.timeframe,
    };
}
function getTodayInUserTimezone() {
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: userTimeZone.value,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
    const parts = formatter.formatToParts(new Date());
    const getPart = (type) => Number(parts.find((part) => part.type === type).value);
    return createDateParts(getPart('year'), getPart('month') - 1, getPart('day'));
}
function createDateParts(year, month, date) {
    const utcDate = new Date(0);
    utcDate.setUTCFullYear(year, month, date);
    utcDate.setUTCHours(0, 0, 0, 0);
    return {
        year: utcDate.getUTCFullYear(),
        month: utcDate.getUTCMonth(),
        date: utcDate.getUTCDate(),
        day: utcDate.getUTCDay(),
    };
}
function formatDateParts({ year, month, date }) {
    return [
        String(year).padStart(4, '0'),
        String(month + 1).padStart(2, '0'),
        String(date).padStart(2, '0'),
    ].join('-');
}
function getUserTimeZoneDateBoundary(value, time) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    const localDate = getUserTimeZoneDate(date, value);
    return zonedTimeToUtc(`${localDate}T${time}`, userTimeZone.value).toISOString();
}
function getUserTimeZoneDate(date, value) {
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return value;
    }
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: userTimeZone.value,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
    const parts = formatter.formatToParts(date);
    const year = parts.find((part) => part.type === 'year').value;
    const month = parts.find((part) => part.type === 'month').value;
    const day = parts.find((part) => part.type === 'day').value;
    return `${year}-${month}-${day}`;
}

watch(
    () => props.filter.value,
    () => {
        if (props.filter.value) {
            dateValue.value = {
                ...props.filter.value,
                timeframe: aliasLegacyTimeframe(props.filter.value.timeframe),
            };
        }
    },
);

swDefinePublic({
    feature,
    dateValue,
    timeframeOptions,
    dateType,
    isDateTimeType,
    showDivider,
    userTimeZone,
    fromToFieldLabel,
    updateFilter,
    onTimeframeSelect,
    aliasLegacyTimeframe,
    getTimeframeDates,
    getTodayDates,
    getYesterdayDates,
    getCurrentCalendarWeekDates,
    getCurrentCalendarMonthDates,
    getCurrentQuarterDates,
    getLastNMonthsDates,
    getCurrentYearDates,
    getPreviousYearDates,
    resetFilter,
    resetTimeframe,
    getPreviousCalendarMonthDates,
    getPreviousCalendarWeekDates,
    getPreviousQuarterDates,
    getNormalizedDateValue,
    getTodayInUserTimezone,
    createDateParts,
    formatDateParts,
    getUserTimeZoneDateBoundary,
    getUserTimeZoneDate,
});

defineExpose({
    feature,
    dateValue,
    timeframeOptions,
    dateType,
    isDateTimeType,
    showDivider,
    userTimeZone,
    fromToFieldLabel,
    updateFilter,
    onTimeframeSelect,
    aliasLegacyTimeframe,
    getTimeframeDates,
    getTodayDates,
    getYesterdayDates,
    getCurrentCalendarWeekDates,
    getCurrentCalendarMonthDates,
    getCurrentQuarterDates,
    getLastNMonthsDates,
    getCurrentYearDates,
    getPreviousYearDates,
    resetFilter,
    resetTimeframe,
    getPreviousCalendarMonthDates,
    getPreviousCalendarWeekDates,
    getPreviousQuarterDates,
    getNormalizedDateValue,
    getTodayInUserTimezone,
    createDateParts,
    formatDateParts,
    getUserTimeZoneDateBoundary,
    getUserTimeZoneDate,
});
</script>
