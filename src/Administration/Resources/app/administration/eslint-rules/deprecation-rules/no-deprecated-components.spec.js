import { MtFloatingUi } from "@contena/meteor-component-library";

const RuleTester = require('eslint').RuleTester
const rule = require('./no-deprecated-components');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
})

tester.run('no-deprecated-components', rule, {
    valid: [
        {
            name: 'Empty file',
            filename: 'test.html.twig',
            code: ''
        },
        {
            name: '"mt-button" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button>Hello</mt-button>
            </template>`
        },
        {
            name: '"mt-colorpicker" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-colorpicker>Hello</mt-colorpicker>
            </template>`
        },
        {
            name: '"mt-icon" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-icon>Hello</mt-icon>
            </template>`
        },
        {
            name: '"mt-text-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-text-field />
            </template>`
        },
        {
            name: '"mt-loader" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-loader />
            </template>`
        },
        {
            name: '"mt-tabs" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-tabs />
            </template>`
        },
        {
            name: '"mt-checkbox" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-checkbox />
            </template>`
        },
        {
            name: '"mt-textarea" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-textarea />
            </template>`
        },
        {
            name: '"mt-banner" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-banner />
            </template>`
        },
        {
            name: '"mt-email-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-email-field />
            </template>`
        },
        {
            name: '"mt-url-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-url-field />
            </template>`
        },
        {
            name: '"mt-select" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-select />
            </template>`
        },
        {
            name: '"mt-skeleton-bar" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-skeleton-bar />
            </template>`
        },
        {
            name: '"mt-switch" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-switch />
            </template>`
        },
        {
            name: '"mt-number-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-number-field />
            </template>`
        },
        {
            name: '"mt-password-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-password-field />
            </template>`
        },
        {
            name: '"mt-progress-bar" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-progress-bar />
            </template>`
        },
        {
            name: '"mt-data-table" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-data-table />
            </template>`
        },
        {
            name: '"ct-data-grid" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
    <ct-data-grid />
</template>`,
        },
        {
            name: '"mt-floating-ui" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-floating-ui />
            </template>`
        },
        {
            name: '"ct-button" usage is allowed when not in the activatedComponents list',
            filename: 'test.html.twig',
            options: [{
                fix: true,
                activatedComponents: ['ct-icon'],
            }],
            code: `
<template>
    <ct-button>Hello</ct-button>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-button - please check if everything works correctly -->
    <mt-button>Hello</mt-button>
</template>`,
            errors: [{
                message: '"ct-button" is deprecated. Please use "mt-button" instead.',
            }]
        },
    ],
    invalid: [
        {
            name: '"ct-button" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-button>Hello</ct-button>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-button - please check if everything works correctly -->
    <mt-button>Hello</mt-button>
</template>`,
            errors: [{
                message: '"ct-button" is deprecated. Please use "mt-button" instead.',
            }]
        },
        {
            name: '"ct-button" usage is not allowed when in the activatedComponents list',
            filename: 'test.html.twig',
            options: [{
                fix: true,
                activatedComponents: ['ct-button'],
            }],
            code: `
<template>
    <ct-button>Hello</ct-button>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-button - please check if everything works correctly -->
    <mt-button>Hello</mt-button>
</template>`,
            errors: [{
                message: '"ct-button" is deprecated. Please use "mt-button" instead.',
            }]
        },
        {
            name: '"ct-button" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-button>Hello</ct-button>
</template>`,
            errors: [{
                message: '"ct-button" is deprecated. Please use "mt-button" instead.',
            }]
        },
        {
            name: '"ct-icon" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-icon name="regular-times-s" />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-icon - please check if everything works correctly -->
    <mt-icon name="regular-times-s" />
</template>`,
            errors: [{
                message: '"ct-icon" is deprecated. Please use "mt-icon" instead.',
            }]
        },
        {
            name: '"ct-icon" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-icon name="regular-times-s" />
</template>`,
            errors: [{
                message: '"ct-icon" is deprecated. Please use "mt-icon" instead.',
            }]
        },
        {
            name: '"ct-card" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-card>Hello</ct-card>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-card - please check if everything works correctly -->
    <mt-card>Hello</mt-card>
</template>`,
            errors: [{
                message: '"ct-card" is deprecated. Please use "mt-card" instead.',
            }]
        },
        {
            name: '"ct-card" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-card>Hello</ct-card>
</template>`,
            errors: [{
                message: '"ct-card" is deprecated. Please use "mt-card" instead.',
            }]
        },
        {
            name: '"ct-text-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-text-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-text-field - please check if everything works correctly -->
    <mt-text-field />
</template>`,
            errors: [{
                message: '"ct-text-field" is deprecated. Please use "mt-text-field" instead.',
            }]
        },
        {
            name: '"ct-text-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-text-field />
</template>`,
            errors: [{
                message: '"ct-text-field" is deprecated. Please use "mt-text-field" instead.',
            }]
        },
        {
            name: '"ct-colorpicker" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-colorpicker />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-colorpicker - please check if everything works correctly -->
    <mt-colorpicker />
</template>`,
            errors: [{
                message: '"ct-colorpicker" is deprecated. Please use "mt-colorpicker" instead.',
            }]
        },
        {
            name: '"ct-colorpicker" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-colorpicker />
</template>`,
            errors: [{
                message: '"ct-colorpicker" is deprecated. Please use "mt-colorpicker" instead.',
            }]
        },
        {
            name: '"ct-switch-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-switch-field>Hello</ct-switch-field>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-switch-field - please check if everything works correctly -->
    <mt-switch>Hello</mt-switch>
</template>`,
            errors: [{
                message: '"ct-switch-field" is deprecated. Please use "mt-switch" instead.',
            }]
        },
        {
            name: '"ct-switch-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-switch-field>Hello</ct-switch-field>
</template>`,
            errors: [{
                message: '"ct-switch-field" is deprecated. Please use "mt-switch" instead.',
            }]
        },
        {
            name: '"ct-number-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-number-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-number-field - please check if everything works correctly -->
    <mt-number-field />
</template>`,
            errors: [{
                message: '"ct-number-field" is deprecated. Please use "mt-number-field" instead.',
            }]
        },
        {
            name: '"ct-number-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-number-field />
</template>`,
            errors: [{
                message: '"ct-number-field" is deprecated. Please use "mt-number-field" instead.',
            }]
        },
        {
            name: '"ct-loader" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-loader />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
    <mt-loader />
</template>`,
            errors: [{
                message: '"ct-loader" is deprecated. Please use "mt-loader" instead.',
            }]
        },
        {
            name: '"ct-loader" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-loader />
</template>`,
            errors: [{
                message: '"ct-loader" is deprecated. Please use "mt-loader" instead.',
            }]
        },
        {
            name: '"ct-checkbox-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-checkbox-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-checkbox-field - please check if everything works correctly -->
    <mt-checkbox />
</template>`,
            errors: [{
                message: '"ct-checkbox-field" is deprecated. Please use "mt-checkbox" instead.',
            }]
        },
        {
            name: '"ct-checkbox-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-checkbox-field />
</template>`,
            errors: [{
                message: '"ct-checkbox-field" is deprecated. Please use "mt-checkbox" instead.',
            }]
        },
        {
            name: '"ct-tabs" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-tabs />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-tabs - please check if everything works correctly -->
    <mt-tabs />
</template>`,
            errors: [{
                message: '"ct-tabs" is deprecated. Please use "mt-tabs" instead.',
            }]
        },
        {
            name: '"ct-tabs" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-tabs />
</template>`,
            errors: [{
                message: '"ct-tabs" is deprecated. Please use "mt-tabs" instead.',
            }]
        },
        {
            name: '"ct-select-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-select-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-select-field - please check if everything works correctly -->
    <mt-select />
</template>`,
            errors: [{
                message: '"ct-select-field" is deprecated. Please use "mt-select" instead.',
            }]
        },
        {
            name: '"ct-select-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-select-field />
</template>`,
            errors: [{
                message: '"ct-select-field" is deprecated. Please use "mt-select" instead.',
            }]
        },
        {
            name: '"ct-textarea-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-textarea-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-textarea-field - please check if everything works correctly -->
    <mt-textarea />
</template>`,
            errors: [{
                message: '"ct-textarea-field" is deprecated. Please use "mt-textarea" instead.',
            }]
        },
        {
            name: '"ct-textarea-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-textarea-field />
</template>`,
            errors: [{
                message: '"ct-textarea-field" is deprecated. Please use "mt-textarea" instead.',
            }]
        },
        {
            name: '"ct-alert" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-alert>Hello</ct-alert>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-alert - please check if everything works correctly -->
    <mt-banner>Hello</mt-banner>
</template>`,
            errors: [{
                message: '"ct-alert" is deprecated. Please use "mt-banner" instead.',
            }]
        },
        {
            name: '"ct-alert" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-alert>Hello</ct-alert>
</template>`,
            errors: [{
                message: '"ct-alert" is deprecated. Please use "mt-banner" instead.',
            }]
        },
        {
            name: '"ct-email-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-email-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-email-field - please check if everything works correctly -->
    <mt-email-field />
</template>`,
            errors: [{
                message: '"ct-email-field" is deprecated. Please use "mt-email-field" instead.',
            }]
        },
        {
            name: '"ct-email-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-email-field />
</template>`,
            errors: [{
                message: '"ct-email-field" is deprecated. Please use "mt-email-field" instead.',
            }]
        },
        {
            name: '"ct-skeleton-bar" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-skeleton-bar />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-skeleton-bar - please check if everything works correctly -->
    <mt-skeleton-bar />
</template>`,
            errors: [{
                message: '"ct-skeleton-bar" is deprecated. Please use "mt-skeleton-bar" instead.',
            }]
        },
        {
            name: '"ct-skeleton-bar" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-skeleton-bar />
</template>`,
            errors: [{
                message: '"ct-skeleton-bar" is deprecated. Please use "mt-skeleton-bar" instead.',
            }]
        },
        {
            name: '"ct-password-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-password-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-password-field - please check if everything works correctly -->
    <mt-password-field />
</template>`,
            errors: [{
                message: '"ct-password-field" is deprecated. Please use "mt-password-field" instead.',
            }]
        },
        {
            name: '"ct-password-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-password-field />
</template>`,
            errors: [{
                message: '"ct-password-field" is deprecated. Please use "mt-password-field" instead.',
            }]
        },
        {
            name: '"ct-url-field" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-url-field />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-url-field - please check if everything works correctly -->
    <mt-url-field />
</template>`,
            errors: [{
                message: '"ct-url-field" is deprecated. Please use "mt-url-field" instead.',
            }]
        },
        {
            name: '"ct-url-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-url-field />
</template>`,
            errors: [{
                message: '"ct-url-field" is deprecated. Please use "mt-url-field" instead.',
            }]
        },
        {
            name: '"ct-progress-bar" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-progress-bar />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-progress-bar - please check if everything works correctly -->
    <mt-progress-bar />
</template>`,
            errors: [{
                message: '"ct-progress-bar" is deprecated. Please use "mt-progress-bar" instead.',
            }]
        },
        {
            name: '"ct-progress-bar" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-progress-bar />
</template>`,
            errors: [{
                message: '"ct-progress-bar" is deprecated. Please use "mt-progress-bar" instead.',
            }]
        },
        {
            name: '"ct-data-grid" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-data-grid />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
    <ct-data-grid />
</template>`,
            errors: [{
                message: '"ct-data-grid" is deprecated. Please use "mt-data-table" instead.',
            }]
        },
        {
            name: '"ct-data-grid" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-data-grid />
</template>`,
            errors: [{
                message: '"ct-data-grid" is deprecated. Please use "mt-data-table" instead.',
            }]
        },
        {
            name: '"ct-popover" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <ct-popover />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from ct-popover - please check if everything works correctly -->
    <mt-floating-ui />
</template>`,
            errors: [{
                message: '"ct-popover" is deprecated. Please use "mt-floating-ui" instead.',
            }]
        },
        {
            name: '"ct-popover" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: [{
                fix: false,
            }],
            code: `
<template>
    <ct-popover />
</template>`,
            errors: [{
                message: '"ct-popover" is deprecated. Please use "mt-floating-ui" instead.',
            }]
        }
    ]
})
