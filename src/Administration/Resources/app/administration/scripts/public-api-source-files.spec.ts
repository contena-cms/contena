import { isTemplateSourceFile, isDataSetSourceFile } from './public-api-source-files';

describe('isTemplateSourceFile', () => {
    it.each([
        'src/module/ct-member/page/ct-member-detail/ct-member-detail.html.twig',
        'src/module/ct-member/page/ct-member-detail/ct-member-detail.vue',
    ])('accepts %s', (filePath) => {
        expect(isTemplateSourceFile(filePath)).toBe(true);
    });

    it.each([
        'src/module/ct-member/page/ct-member-detail/index.ts',
        'src/module/ct-member/page/ct-member-detail/ct-member-detail.scss',
        'src/module/ct-member/page/ct-member-detail/snippet/en.json',
        'src/module/ct-member/page/ct-member-detail/ct-member.spec/fixture.vue',
    ])('rejects %s', (filePath) => {
        expect(isTemplateSourceFile(filePath)).toBe(false);
    });
});

describe('isDataSetSourceFile', () => {
    it.each([
        'src/module/ct-member/page/ct-member-detail/index.ts',
        'src/module/ct-member/page/ct-member-detail/index.js',
        'src/module/ct-member/page/ct-member-detail/ct-member-detail.vue',
    ])('accepts %s', (filePath) => {
        expect(isDataSetSourceFile(filePath)).toBe(true);
    });

    it.each([
        'src/module/ct-member/page/ct-member-detail/index.spec.ts',
        'src/module/ct-member/page/ct-member-detail/ct-member-detail.spec.vue',
        'src/module/ct-member/acl/index.ts',
        'src/global.types.d.ts',
        'src/module/ct-member/ct-member.html.twig',
    ])('rejects %s', (filePath) => {
        expect(isDataSetSourceFile(filePath)).toBe(false);
    });
});
