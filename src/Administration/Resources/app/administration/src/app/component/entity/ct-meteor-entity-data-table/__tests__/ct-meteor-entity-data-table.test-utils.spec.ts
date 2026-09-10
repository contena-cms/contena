/**
 * @ct-package framework
 */

import { createDeferred, createSearchResult } from './ct-meteor-entity-data-table.test-utils';

describe('ct-meteor-entity-data-table test utilities', () => {
    it('creates administration search results for wrapper specs', () => {
        const records = [{ id: 'tag-1', name: 'Contena' }];
        const result = createSearchResult(records, 12);

        expect(Array.from(result)).toEqual(records);
        expect(result.total).toBe(12);
        expect(result.criteria).toBeDefined();
    });

    it('creates deferred promises for loading state assertions', async () => {
        const deferred = createDeferred<string>();

        deferred.resolve('loaded');

        await expect(deferred.promise).resolves.toBe('loaded');
    });
});
