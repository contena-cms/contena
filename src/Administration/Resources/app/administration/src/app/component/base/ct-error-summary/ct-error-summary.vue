<template>
    <ct-block name="ct_error_summary">
        <mt-banner
            v-if="errorCount > 0"
            class="ct-error-summary"
            variant="critical"
            :title="$t('ct-error-summary.title', {}, errorCount)"
            :show-icon="true"
        >
            <li v-for="(entry, index) in errorEntries" :key="index">
                <span class="ct-error-summary__quantity">{{ entry.count }}x</span> "{{ entry.message }}"
            </li>
        </mt-banner>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-error-summary.scss';
const { hasOwnProperty } = Contena.Utils.object;
type error = {
    _code: string;
    _detail: string;
    selfLink: string;
};

defineProps({});

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

// vue-i18n exposes methods bound to its composer; the template and computed state use them as callbacks.
// eslint-disable-next-line @typescript-eslint/unbound-method
const { t, te } = useI18n();

const errors = computed(() => {
    const allErrors = (Contena.Store.get('error').getAllApiErrors() || []) as Array<unknown>;

    // Helper function to recursively get all error objects
    const extractErrorObjects = (errors: Array<unknown>) => {
        return errors.reduce((acc: Array<unknown>, error: unknown) => {
            if (error === null || typeof error !== 'object') {
                return acc;
            }

            if (error.hasOwnProperty('selfLink') && error.hasOwnProperty('_code') && error.hasOwnProperty('_detail')) {
                acc.push(error);

                return acc;
            }

            acc.push(...extractErrorObjects(Object.values(error)));

            return acc;
        }, []);
    };

    // Retrieve all error objects and remap them to objects just containing a message
    const errorObjects = (extractErrorObjects(allErrors) as Array<error>).map((error): { message: string } => {
        let message = error._detail;

        if (te(`global.error-codes.${error._code}`)) {
            message = t(`global.error-codes.${error._code}`);
        }

        return {
            message,
        };
    });

    // Count the number of errors for each message
    return errorObjects.reduce((acc: { [key: string]: number }, error: { message: string }) => {
        if (!hasOwnProperty(acc, error.message)) {
            acc[error.message] = 1;
        } else {
            acc[error.message] += 1;
        }

        return acc;
    }, {});
});
const errorEntries = computed(() => {
    return Object.entries(errors.value).map(
        ([
            message,
            count,
        ]) => ({
            message,
            count,
        }),
    );
});
const errorCount = computed(() => {
    return Object.values(errors.value).reduce((accumulator, value) => {
        return accumulator + value;
    }, 0);
});

ctDefinePublic({
    errors,
    errorEntries,
    errorCount,
});

defineExpose({
    errors,
    errorEntries,
    errorCount,
});
</script>
