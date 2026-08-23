describe('src/app/filter/file-size.filter.js', () => {
    const fileSizeFilter = Contena.Filter.getByName('fileSize');

    Contena.Utils.format.fileSize = jest.fn();

    beforeEach(() => {
        Contena.Utils.format.fileSize.mockClear();
    });

    it('should contain a filter', () => {
        expect(fileSizeFilter).toBeDefined();
    });

    it('should return empty string when no value is given', () => {
        expect(fileSizeFilter()).toBe('');
    });

    it('should call the fileSize format util for formatting', () => {
        fileSizeFilter(1856165, {
            myLocaleOptions: 'foo',
        });

        expect(Contena.Utils.format.fileSize).toHaveBeenCalledWith(1856165, {
            myLocaleOptions: 'foo',
        });
    });
});
