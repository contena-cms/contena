<template>
    <ct-block name="ct_version">
        <div class="ct-version">
            <ct-block name="ct_version_info">
                <div class="ct-version__info">
                    <ct-block name="ct_version_info_text">
                        {{ version }}
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-version.scss';

defineProps({});

import { computed } from 'vue';

const version = computed(() => {
    let output = '';
    const version = Contena.Context.app.config.version;

    // https://regex101.com/r/oRuJjS/1
    const match = version.match(/(\d+)\.?(\d+)\.?(\d+)?\.?(\d+)?-?([a-z]+)?(\d+(.\d+)*)?/i);

    if (match === null) {
        return version;
    }

    // Get rid of whole regex match for example "6.4.99999.9999999-dev"
    match.shift();

    // Iterate version parts and append to output
    match.forEach((versionPart, index) => {
        if (typeof versionPart !== 'string') {
            return;
        }

        const hrt = getHumanReadableText(versionPart);

        if (hrt !== versionPart) {
            output += ` ${hrt}`;

            return;
        }

        // Special case for the first version part. Don't append a dot to the string
        if (index === 0) {
            output += `${hrt}`;

            return;
        }

        // Add dot and version part to output
        output += `.${hrt}`;
    });

    return output;
});

function getHumanReadableText(text) {
    if (text === 'dp') {
        return 'Developer Preview';
    }
    if (text === 'rc') {
        return 'Release Candidate';
    }
    if (text === 'dev') {
        return 'Developer Version';
    }
    if (text === 'ea') {
        return 'Early Access';
    }
    return text;
}

ctDefinePublic({
    version,
    getHumanReadableText,
});

defineExpose({
    version,
    getHumanReadableText,
});
</script>
