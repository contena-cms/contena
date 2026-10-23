/**
 * @sw-package framework
 */

import createCustomFieldService from 'src/app/service/custom-field.service';
import CacheService from 'src/app/service/cache.service';

if (!Contena.Service('cacheService')) {
    Contena.Service().register('cacheService', () => new CacheService());
}

describe('src/app/service/custom-field.service.js', () => {
    let customFieldService;
    const expectedTypeConfigs = {
        number: {
            configRenderComponent: 'sw-custom-field-type-number',
            type: 'int',
            config: {
                componentName: 'sw-field',
                type: 'number',
                numberType: 'float',
            },
        },
    };

    beforeEach(() => {
        jest.restoreAllMocks();
        Contena.Service('cacheService').clear();
        customFieldService = createCustomFieldService();
    });

    it('getTypeByName: get number type config', async () => {
        expect(customFieldService.getTypeByName('number')).toEqual(customFieldService.getTypes().number);
    });

    it('getTypeByName: get unknown type config', async () => {
        expect(customFieldService.getTypeByName('unknownType')).toBeUndefined();
    });

    it('getTypeByName: checking expected config', async () => {
        expect(customFieldService.getTypeByName('number')).toEqual(expectedTypeConfigs.number);
    });

    it('upsertType: insert config of new type', async () => {
        expect(customFieldService.getTypeByName('newType')).toBeUndefined();

        const newTypeConfig = {
            configRenderComponent: 'sw-custom-field-type-new-type',
            type: 'newType',
            config: {
                componentName: 'sw-field',
                type: 'newType',
            },
        };
        customFieldService.upsertType('newType', newTypeConfig);

        expect(customFieldService.getTypeByName('newType')).toEqual(newTypeConfig);
    });

    it('upsertType: upsert config', async () => {
        expect(customFieldService.getTypeByName('number')).toEqual(expectedTypeConfigs.number);

        const newConfig = {
            ...expectedTypeConfigs.number,
            type: 'float',
            config: {
                ...expectedTypeConfigs.number.config,
                numberType: 'float',
            },
        };

        customFieldService.upsertType('number', newConfig);

        expect(customFieldService.getTypeByName('number')).toEqual(newConfig);
    });

    it('reuses cached custom field sets per entity name and language', async () => {
        const blogSets = [{ id: 'blog-set', customFields: [{ id: 'field' }] }];
        const translatedProductSets = [{ id: 'blog-set-language-2', customFields: [{ id: 'field' }] }];
        const userSets = [{ id: 'user-set', customFields: [{ id: 'field' }] }];
        const searchMock = jest
            .fn()
            .mockResolvedValueOnce(blogSets)
            .mockResolvedValueOnce(translatedProductSets)
            .mockResolvedValueOnce(userSets);

        jest.spyOn(Contena.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock });

        Contena.Context.api.languageId = 'language-1';
        expect(await customFieldService.getCustomFieldSets('blog')).toEqual(blogSets);
        expect(await customFieldService.getCustomFieldSets('blog')).toEqual(blogSets);

        Contena.Context.api.languageId = 'language-2';
        expect(await customFieldService.getCustomFieldSets('blog')).toEqual(translatedProductSets);

        expect(await customFieldService.getCustomFieldSets('user')).toEqual(userSets);
        expect(searchMock).toHaveBeenCalledTimes(3);
    });

    it('keeps cached custom field sets separate by limit', async () => {
        const searchMock = jest
            .fn()
            .mockResolvedValueOnce([{ id: 'default-limit-set', customFields: [{ id: 'field' }] }])
            .mockResolvedValueOnce([{ id: 'large-limit-set', customFields: [{ id: 'field' }] }]);

        jest.spyOn(Contena.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock });

        await customFieldService.getCustomFieldSets('channel');
        await customFieldService.getCustomFieldSets('channel', false, 100);

        expect(searchMock).toHaveBeenCalledTimes(2);
        expect(searchMock.mock.calls[0][0].limit).toBe(25);
        expect(searchMock.mock.calls[1][0].limit).toBe(100);
    });
});
