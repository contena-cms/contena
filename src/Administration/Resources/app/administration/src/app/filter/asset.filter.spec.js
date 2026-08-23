describe('src/app/filter/asset.filter.ts', () => {
    const assetFilter = Contena.Filter.getByName('asset');

    beforeEach(() => {
        Contena.Context.api.assetsPath = '';
    });

    it('should contain a filter', () => {
        expect(assetFilter).toBeDefined();
    });

    it('should return empty string when no value is given', () => {
        const result = assetFilter();

        expect(result).toBe('');
    });

    it('should remove the first slash because double slashes does not work on external storage like s3', () => {
        const result = assetFilter('/test.jpg');

        expect(result).toBe('test.jpg');
    });

    it('should use the assetsPath from the Context API', () => {
        Contena.Context.api.assetsPath = 'https://www.contena.cn/';
        const result = assetFilter('/test.jpg');

        expect(result).toBe('https://www.contena.cn/test.jpg');
    });
});
