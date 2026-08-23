import { SEARCH_CONFIG_FIELD_SNIPPETS } from './search-config-fields.constant';

describe('search-config-fields.constant', () => {
    it('maps every searchable Blog field to its config snippet', () => {
        expect(Object.keys(SEARCH_CONFIG_FIELD_SNIPPETS)).toHaveLength(10);
        expect(SEARCH_CONFIG_FIELD_SNIPPETS.name).toBe('name');
        expect(SEARCH_CONFIG_FIELD_SNIPPETS.descriptionTeaser).toBe('descriptionTeaser');
        expect(SEARCH_CONFIG_FIELD_SNIPPETS['categories.name']).toBe('categoriesName');
    });

    it('is frozen so consumers cannot drift the shared map', () => {
        expect(Object.isFrozen(SEARCH_CONFIG_FIELD_SNIPPETS)).toBe(true);
    });
});
