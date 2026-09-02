import createSearchTypeService from './search-type.service';

describe('search-type.service', () => {
    it('registers the user module search type', () => {
        const searchTypeService = createSearchTypeService();

        expect(searchTypeService.getTypeByName('user')).toEqual({
            entityName: 'user',
            placeholderSnippet: 'ct-users.general.placeholderSearchBar',
            listingRoute: 'ct.users.index',
        });
    });

    it('registers the member module search type', () => {
        const searchTypeService = createSearchTypeService();

        expect(searchTypeService.getTypeByName('member')).toEqual({
            entityName: 'member',
            placeholderSnippet: 'ct-member.general.placeholderSearchBar',
            listingRoute: 'ct.member.index',
        });
    });
});
