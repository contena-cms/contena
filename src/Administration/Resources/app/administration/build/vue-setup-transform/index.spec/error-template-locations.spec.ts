import { captureTransformError } from './helpers';

describe('build/vue-setup-transform template diagnostic locations', () => {
    it.each([
        {
            name: 'wrong-mode identity',
            markup: '<ct-block extends="ct_example" />',
            token: 'extends',
            message: 'only valid in an override component',
        },
        {
            name: 'generated data binding',
            markup: '<ct-block name="ct_example" :data="{}" />',
            token: ':data',
            message: 'data binding',
        },
        { name: 'dynamic identity', markup: '<ct-block :name="name" />', token: ':name', message: 'Only a static "name"' },
        {
            name: 'unsupported attribute',
            markup: '<ct-block name="ct_example" class="demo" />',
            token: 'class',
            message: 'Only a static "name"',
        },
        {
            name: 'object binding',
            markup: '<ct-block name="ct_example" v-bind="props" />',
            token: 'v-bind',
            message: 'Only a static "name"',
        },
        {
            name: 'default slot',
            markup: '<ct-block name="ct_example" #default="slot" />',
            token: '#default',
            message: 'default slot scope',
        },
        {
            name: 'nested default slot',
            markup: '<ct-block name="ct_example"><template #default="slot"><div /></template></ct-block>',
            token: '<template',
            message: 'default slot scope',
        },
        {
            name: 'named slot',
            markup: '<ct-block name="ct_example"><template #header><div /></template></ct-block>',
            token: '<template',
            message: 'non-default named slot',
        },
    ])('points at the $name in the original template', ({ markup, token, message }) => {
        const source = `<script setup>\nctDefinePublic({});\n</script>\n<template>\n    ${markup}\n</template>`;
        const error = captureTransformError(source, 'ct-template.vue');

        expect(error.message).toContain(message);
        expect(error.loc).toEqual({ file: 'ct-template.vue', line: 5, column: 4 + markup.indexOf(token) });
        expect(error.index).toBe(source.lastIndexOf(token));
        expect(error.frame).toContain(`5  |      ${markup}`);
    });

    it.each([
        '<div />',
        '{{ count }}',
        'plain text',
    ])('locates unsupported override content: %s', (markup) => {
        const source = `<script setup>\nconst count = 0;\nctDefineOverride({ count });\n</script>\n<template>\n${markup}\n</template>`;
        const error = captureTransformError(source, 'ct-content.override.vue');

        expect(error.message).toContain('may only contain <ct-block extends');
        expect(error.loc).toEqual({ file: 'ct-content.override.vue', line: 6, column: 0 });
        expect(error.index).toBe(source.indexOf(markup));
        expect(error.frame?.split('\n').filter((line) => line.startsWith('   |'))).toHaveLength(1);
    });
});
