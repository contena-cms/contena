import createAppFilter from 'src/app/init/filter.init';

describe('src/app/init/filter.init.js', () => {
    beforeAll(() => {
        createAppFilter();
    });

    [
        'asset',
        'date',
        'fileSize',
        'mediaName',
        'striphtml',
        'thumbnailSize',
        'truncate',
        'unicodeUri',
    ].forEach((filterName) => {
        it(`should register filter "${filterName}"`, () => {
            expect(Contena.Filter.getByName(filterName)).toBeInstanceOf(Function);
        });
    });
});
