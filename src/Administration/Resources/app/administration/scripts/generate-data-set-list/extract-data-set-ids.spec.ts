import { extractDataSetIds } from './extract-data-set-ids';

describe('extract-data-set-ids', () => {
    it.each([
        [
            'ids from a multi-line and a single-line call',
            `Contena.ExtensionAPI.publishData({
                id: 'sw-blog-detail__product',
                path: 'blog',
                scope: this,
            });
            Contena.ExtensionAPI.publishData({ id: 'sw-blog-detail__variants', path: 'v', scope: this });`,
            [
                'sw-blog-detail__product',
                'sw-blog-detail__variants',
            ],
        ],
        [
            'an id from a vue script setup block',
            `<script setup lang="ts">
            Contena.ExtensionAPI.publishData({ id: 'sw-native-detail__product', path: 'blog' });
            </script>
            <template><div /></template>`,
            ['sw-native-detail__product'],
        ],
        [
            'a double quoted id',
            '.publishData({ id: "sw-quoted__product" })',
            ['sw-quoted__product'],
        ],
    ])('collects %s', (_case, code, expected) => {
        expect(extractDataSetIds(code)).toEqual(expected);
    });

    it.each([
        [
            'a publishData call without an id',
            ".publishData({ path: 'blog', scope: this })",
        ],
        [
            'a non literal id',
            '.publishData({ id: dataSetId, path: "blog" })',
        ],
        [
            'a spread configuration object',
            '.publishData(dataSetConfig)',
        ],
        [
            'a file without any publishData call',
            "export default { name: 'sw-blog-detail' };",
        ],
    ])('ignores %s', (_case, code) => {
        expect(extractDataSetIds(code)).toEqual([]);
    });
});
