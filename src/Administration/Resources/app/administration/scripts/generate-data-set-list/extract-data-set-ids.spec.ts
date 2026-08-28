import { extractDataSetIds } from './extract-data-set-ids';

describe('extract-data-set-ids', () => {
    it.each([
        [
            'ids from a multi-line and a single-line call',
            `Contena.ExtensionAPI.publishData({ id: 'ct-member-detail__member', path: 'member', scope: this });
             Contena.ExtensionAPI.publishData({ id: 'ct-member-detail__roles', path: 'roles', scope: this });`,
            [
                'ct-member-detail__member',
                'ct-member-detail__roles',
            ],
        ],
        [
            'an id from a vue script setup block',
            `<script setup lang="ts">Contena.ExtensionAPI.publishData({ id: 'ct-native-detail__member', path: 'member' });</script>`,
            ['ct-native-detail__member'],
        ],
        [
            'a double quoted id',
            '.publishData({ id: "ct-quoted__member" })',
            ['ct-quoted__member'],
        ],
    ])('collects %s', (_case, code, expected) => {
        expect(extractDataSetIds(code)).toEqual(expected);
    });

    it.each([
        [
            'a publishData call without an id',
            ".publishData({ path: 'member', scope: this })",
        ],
        [
            'a non literal id',
            '.publishData({ id: dataSetId, path: "member" })',
        ],
        [
            'a spread configuration object',
            '.publishData(dataSetConfig)',
        ],
        [
            'a file without any publishData call',
            "export default { name: 'ct-member-detail' };",
        ],
    ])('ignores %s', (_case, code) => {
        expect(extractDataSetIds(code)).toEqual([]);
    });
});
