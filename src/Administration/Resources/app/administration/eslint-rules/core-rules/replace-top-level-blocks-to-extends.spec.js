const RulerTester = require('eslint').RuleTester;
const rule = require('./replace-top-level-blocks-to-extends');

const tester = new RulerTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
        sourceType: 'module',
    },
});

tester.run('replace-top-level-blocks-to-extends', rule, {
    valid: [
        {
            name: 'top-level block with extends attribute and inner block',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block extends="block-1">
                        <div>
                            <span>Title</span>
                            <ct-block name="block-2" :data="$dataScope"></ct-block>
                        </div>
                    </ct-block>
                </template>
            `,
        },
        {
            name: 'top-level block defining a non existing block',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="block-1" :data="$dataScope">
                        <div>
                            <span>Title</span>
                            <ct-block name="block-2" :data="$dataScope"></ct-block>
                        </div>
                    </ct-block>
                </template>
            `,
        },
    ],
    invalid: [
        {
            name: 'top-level block without extends attribute',
            filename: 'test.html.twig',
            code: `
                <template>
                    <ct-block name="sw_desktop_content" :data="$dataScope">
                        <div>
                            <span>Title</span>
                            <ct-block name="block-2" :data="$dataScope"></ct-block>
                        </div>
                    </ct-block>
                </template>
            `,
            /* eslint-disable */
            output: `
                <template>
                    <ct-block extends="sw_desktop_content" >
                        <div>
                            <span>Title</span>
                            <ct-block name="block-2" :data="$dataScope"></ct-block>
                        </div>
                    </ct-block>
                </template>
            `,
            /* eslint-enable */
            errors: [{
                message: 'Top-level <ct-block> should use the `extends` prop instead of the `name` prop',
            }],
        },
    ],
});
