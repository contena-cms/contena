import { buildGroups, humanizeLabel, humanizeCommonPrefix } from './mcp-allowlist.utils';

describe('mcp-allowlist.utils', () => {
    describe('buildGroups', () => {
        it('groups items by key returned from getGroupKey', () => {
            const items = [
                { name: 'ct-orders', prefix: 'ct' },
                { name: 'ct-products', prefix: 'ct' },
                { name: 'acme-reports', prefix: 'acme' },
            ];

            const result = buildGroups(items, (item) => item.prefix);

            expect(result).toEqual({
                ct: [
                    { name: 'ct-orders', prefix: 'ct' },
                    { name: 'ct-products', prefix: 'ct' },
                ],
                acme: [{ name: 'acme-reports', prefix: 'acme' }],
            });
        });

        it('uses "other" as fallback key when getGroupKey returns null', () => {
            const items = [{ name: 'unknown' }];

            const result = buildGroups(items, () => null);

            expect(result).toEqual({ other: [{ name: 'unknown' }] });
        });

        it('returns empty object for empty items array', () => {
            expect(buildGroups([], () => 'x')).toEqual({});
        });
    });

    describe('humanizeLabel', () => {
        it('capitalizes each hyphen-separated word', () => {
            expect(humanizeLabel('contena-entity-search')).toBe('Contena Entity Search');
        });

        it('capitalizes each underscore-separated word', () => {
            expect(humanizeLabel('my_tool_name')).toBe('My Tool Name');
        });

        it('handles a single word', () => {
            expect(humanizeLabel('contena')).toBe('Contena');
        });
    });

    describe('humanizeCommonPrefix', () => {
        it('returns empty string for empty names array', () => {
            expect(humanizeCommonPrefix([])).toBe('');
        });

        it('returns full humanized name for a single entry', () => {
            expect(humanizeCommonPrefix(['contena-entity-search'])).toBe('Contena Entity Search');
        });

        it('returns longest common prefix for multiple names sharing a prefix', () => {
            const names = [
                'contena-entity-search',
                'contena-entity-read',
                'contena-entity-aggregate',
            ];

            expect(humanizeCommonPrefix(names)).toBe('Contena Entity');
        });

        it('returns only the shared single segment when names diverge after first segment', () => {
            const names = [
                'ct-orders',
                'ct-products',
            ];

            expect(humanizeCommonPrefix(names)).toBe('Ct');
        });

        it('returns empty string when names share no common prefix', () => {
            const names = [
                'contena-search',
                'acme-orders',
            ];

            expect(humanizeCommonPrefix(names)).toBe('');
        });
    });
});
