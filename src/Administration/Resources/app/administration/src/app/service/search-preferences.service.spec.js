import SearchPreferencesService from 'src/app/service/search-preferences.service';
import mediaDefaultSearchConfiguration from 'src/module/ct-media/default-search-configuration';

describe('searchPreferencesService', () => {
    it('is registered correctly', () => {
        let searchPreferencesService = new SearchPreferencesService({
            userConfigRepository: Contena.Service('repositoryFactory').create('user_config'),
        });
        searchPreferencesService = {
            createUserSearchPreferences: jest.fn(),
            getDefaultSearchPreferences: jest.fn(),
            getUserSearchPreferences: jest.fn(),
            processSearchPreferences: jest.fn(),
            processSearchPreferencesFields: jest.fn(),
        };

        expect(searchPreferencesService).toEqual(
            expect.objectContaining({
                createUserSearchPreferences: searchPreferencesService.createUserSearchPreferences,
                getDefaultSearchPreferences: searchPreferencesService.getDefaultSearchPreferences,
                getUserSearchPreferences: searchPreferencesService.getUserSearchPreferences,
                processSearchPreferences: searchPreferencesService.processSearchPreferences,
                processSearchPreferencesFields: searchPreferencesService.processSearchPreferencesFields,
            }),
        );
    });

    describe('processSearchPreferences', () => {
        it('returns data correctly', async () => {
            const searchPreferencesService = new SearchPreferencesService({
                userConfigRepository: Contena.Service('repositoryFactory').create('user_config'),
            });
            const searchPreferences = await searchPreferencesService.processSearchPreferences([
                { media: mediaDefaultSearchConfiguration },
            ]);

            expect(searchPreferences).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        fields: [
                            expect.objectContaining({ fieldName: 'fileName' }),
                            expect.objectContaining({ fieldName: 'path' }),
                            expect.objectContaining({ fieldName: 'alt' }),
                            expect.objectContaining({ fieldName: 'title' }),
                            expect.objectContaining({ fieldName: 'tagsName' }),
                            expect.objectContaining({ fieldName: 'mediaFolderName' }),
                        ],
                    }),
                ]),
            );
        });
    });
});
