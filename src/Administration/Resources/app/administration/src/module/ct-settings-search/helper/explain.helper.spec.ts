import { isFieldClause, parseClauses } from './explain.helper';

describe('src/module/ct-settings-search/helper/explain.helper', () => {
    it('returns no clauses for a missing map', () => {
        expect(parseClauses(undefined)).toEqual([]);
        expect(parseClauses(null)).toEqual([]);
    });

    it('parses valid clause names and numeric scores', () => {
        const clause = JSON.stringify({ field: 'name', term: 'article' });

        expect(parseClauses({ [clause]: 12.5 })).toEqual([{ parsed: { field: 'name', term: 'article' }, score: 12.5 }]);
    });

    it('skips invalid JSON and defaults invalid scores to zero', () => {
        const valid = JSON.stringify({ field: 'name' });

        expect(parseClauses({ 'not json': 1, [valid]: 'x' })).toEqual([{ parsed: { field: 'name' }, score: 0 }]);
    });

    it('excludes boost and cross-entity clauses by key presence', () => {
        expect(isFieldClause({ field: 'name', term: 'article' })).toBe(true);
        expect(isFieldClause({ boost: 0, name: 'rule' })).toBe(false);
        expect(isFieldClause({ crossEntity: 'category', term: 'article' })).toBe(false);
        expect(isFieldClause(null)).toBe(false);
    });
});
