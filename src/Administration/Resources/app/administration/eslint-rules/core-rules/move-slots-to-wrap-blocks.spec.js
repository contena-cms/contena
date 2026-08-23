const RulerTester = require('eslint').RuleTester;
const rule = require('./move-slots-to-wrap-blocks');

const tester = new RulerTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
        sourceType: 'module',
    },
});

tester.run('move-slots-to-wrap-blocks', rule, {
    valid: [
        {
            name: 'block has multiple children',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="block-name">
                        <template #default>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                        <template #body>
                            <h1>Title 2</h1>
                            <p>{{ message2 }}</p>
                        </template>
                        <template #footer>
                            <h1>Title 3</h1>
                            <p>{{ message3 }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
        },
    ],
    invalid: [
        {
            name: 'block has only one child template with slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="block-name">
                        <template #default>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <template #default>
                        <ct-block name="block-name">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </ct-block>
                    </template>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<ct-block> with single child should move v-slot attribute to the <ct-block> element',
            }],
        },
        {
            name: 'block has only one child template with v-slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="block-name">
                        <template v-slot:default>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <template v-slot:default>
                        <ct-block name="block-name">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </ct-block>
                    </template>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<ct-block> with single child should move v-slot attribute to the <ct-block> element',
            }],
        },
        {
            name: 'block has only one child template with scoped slot',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="block-name">
                        <template #body="{prop1, prop2}">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <template #body="{prop1, prop2}">
                        <ct-block name="block-name">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </ct-block>
                    </template>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<ct-block> with single child should move v-slot attribute to the <ct-block> element',
            }],
        },
    ],
});
