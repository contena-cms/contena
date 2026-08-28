import { extractPositionIdentifiers } from './extract-position-identifiers';

describe('extract-position-identifiers', () => {
    it.each([
        [
            'several identifiers from a twig template',
            `<ct-extension-component-section position-identifier="ct-outer-section" /><ct-extension-component-section position-identifier="ct-inner-section" />`,
            [
                'ct-outer-section',
                'ct-inner-section',
            ],
        ],
        [
            'an identifier spread over multiple attribute lines in a vue template',
            `<template><ct-extension-component-section\n position-identifier="ct-native-section"\n :data="$dataScope" /></template>`,
            ['ct-native-section'],
        ],
    ])('collects %s', (_case, code, expected) => {
        expect(extractPositionIdentifiers(code)).toEqual(expected);
    });

    it.each([
        [
            'an empty identifier',
            '<ct-extension-component-section position-identifier="" />',
        ],
        [
            'a null identifier',
            '<ct-extension-component-section position-identifier="null" />',
        ],
        [
            'a template without any identifier',
            '<template><div class="ct-card"></div></template>',
        ],
    ])('ignores %s', (_case, code) => {
        expect(extractPositionIdentifiers(code)).toEqual([]);
    });
});
