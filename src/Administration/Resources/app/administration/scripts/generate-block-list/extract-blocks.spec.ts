import { extractBlocks } from './extract-blocks';

describe('extract-blocks', () => {
    it.each([
        [
            'nested twig blocks',
            '{% block ct_outer %}{% block ct_inner %}{% endblock %}{% endblock %}',
            [
                'ct_outer',
                'ct_inner',
            ],
        ],
        [
            'nested ct-block declarations in a vue template',
            `<template><ct-block name="ct_outer"><ct-block name="ct_inner">Content</ct-block></ct-block></template>`,
            [
                'ct_outer',
                'ct_inner',
            ],
        ],
        [
            'the block an override extends',
            '<template><ct-block extends="ct_outer">Replacement</ct-block></template>',
            ['ct_outer'],
        ],
        [
            'a block name spread over multiple lines',
            `<template><ct-block\n name="ct_outer"\n :data="$dataScope">Content</ct-block></template>`,
            ['ct_outer'],
        ],
        [
            'both dialects, twig first',
            '{% block ct_legacy %}{% endblock %}<ct-block name="ct_native">Content</ct-block>',
            [
                'ct_legacy',
                'ct_native',
            ],
        ],
    ])('collects %s', (_case, code, expected) => {
        expect(extractBlocks(code)).toEqual(expected);
    });

    it.each([
        [
            'ct-block-field, whose bound name is not a block name',
            '<ct-block-field v-model:value="value" :name="formFieldName">Content</ct-block-field>',
        ],
        [
            'ct-block-parent',
            '<ct-block-parent />',
        ],
        [
            'a bound name on ct-block',
            '<ct-block :name="blockName">Content</ct-block>',
        ],
        [
            'a v-bind name on ct-block',
            '<ct-block v-bind:name="blockName">Content</ct-block>',
        ],
        [
            'a closing tag',
            '</ct-block>',
        ],
    ])('ignores %s', (_case, template) => {
        expect(extractBlocks(`<template>${template}</template>`)).toEqual([]);
    });
});
