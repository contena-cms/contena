/** @private */
export const SEARCH_CONFIG_FIELD_SNIPPETS = Object.freeze({
    name: 'name',
    description: 'description',
    descriptionTeaser: 'descriptionTeaser',
    keywords: 'keywords',
    customSearchKeywords: 'customSearchKeywords',
    'categories.name': 'categoriesName',
    'categories.customFields': 'categoriesCustomFields',
    'tags.name': 'tagsName',
    metaTitle: 'metaTitle',
    metaDescription: 'metaDescription',
} as const);

/** @private */
export type SearchConfigField = keyof typeof SEARCH_CONFIG_FIELD_SNIPPETS;
