const RulerTester = require('eslint').RuleTester;
const rule = require('./move-v-if-conditions-to-blocks');

const tester = new RulerTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
        sourceType: 'module',
    },
});

tester.run('move-v-if-conditions-to-blocks', rule, {
    valid: [
        {
            name: 'block has multiple children',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="ct_block-name">
                        <template v-if="condition">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                        <template v-else-if="condition2">
                            <h1>Title 2</h1>
                            <p>{{ message2 }}</p>
                        </template>
                        <template v-else>
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
            name: 'block has only one child template with v-if',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="ct_block-name">
                        <template v-if="condition">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <ct-block name="ct_block-name" v-if="condition" >
                        <template >
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<ct-block> with single child should move v-if, v-else or v-else-if attributes to the <ct-block> element',
            }],
        },
        {
            name: 'block has only one child template with v-else-if',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="ct_block-name">
                        <template v-else-if="condition">
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <ct-block name="ct_block-name" v-else-if="condition" >
                        <template >
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<ct-block> with single child should move v-if, v-else or v-else-if attributes to the <ct-block> element',
            }],
        },
        {
            name: 'block has only one child template with v-else',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="ct_block-name">
                        <template v-else>
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <ct-block name="ct_block-name" v-else >
                        <template >
                            <h1>Title</h1>
                            <p>{{ message }}</p>
                        </template>
                    </ct-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: '<ct-block> with single child should move v-if, v-else or v-else-if attributes to the <ct-block> element',
            }],
        },
    ],
});
