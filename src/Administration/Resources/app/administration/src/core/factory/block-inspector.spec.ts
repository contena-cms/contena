import {
    BLOCK_INSPECTOR_STORAGE_KEY,
    getInspectedBlock,
    getInspectedBlocks,
    isBlockInspectorEnabled,
    mergeBlockMarker,
    parseBlockMarker,
    registerInspectedBlock,
    resetBlockInspector,
    setBlockInspectorEnabled,
} from './block-inspector';

describe('core/factory/block-inspector', () => {
    beforeEach(() => {
        localStorage.removeItem(BLOCK_INSPECTOR_STORAGE_KEY);
        resetBlockInspector();
    });

    describe('enabled flag', () => {
        it('is off by default', () => {
            expect(isBlockInspectorEnabled()).toBe(false);
        });

        it('reads the stored flag once at first use', () => {
            localStorage.setItem(BLOCK_INSPECTOR_STORAGE_KEY, 'true');

            expect(isBlockInspectorEnabled()).toBe(true);

            localStorage.removeItem(BLOCK_INSPECTOR_STORAGE_KEY);

            // A block rendered with markers must not lose them mid-session.
            expect(isBlockInspectorEnabled()).toBe(true);
        });

        it('persists the flag for the next boot and applies it right away', () => {
            setBlockInspectorEnabled(true);

            expect(isBlockInspectorEnabled()).toBe(true);
            expect(localStorage.getItem(BLOCK_INSPECTOR_STORAGE_KEY)).toBe('true');

            setBlockInspectorEnabled(false);

            expect(isBlockInspectorEnabled()).toBe(false);
            expect(localStorage.getItem(BLOCK_INSPECTOR_STORAGE_KEY)).toBeNull();
        });
    });

    describe('registry', () => {
        it('keeps the first registration of a block', () => {
            registerInspectedBlock({ name: 'a', component: 'ct-first', kind: 'native' });
            registerInspectedBlock({ name: 'a', component: 'ct-second', kind: 'native' });

            expect(getInspectedBlock('a')).toEqual({ name: 'a', component: 'ct-first', kind: 'native' });
            expect(getInspectedBlocks().size).toBe(1);
        });

        it('returns undefined for unknown blocks', () => {
            expect(getInspectedBlock('missing')).toBeUndefined();
        });
    });

    describe('marker values', () => {
        it('parses and merges names without duplicates', () => {
            expect(parseBlockMarker(' inner  outer ')).toEqual([
                'inner',
                'outer',
            ]);
            expect(parseBlockMarker(null)).toEqual([]);
            expect(mergeBlockMarker('inner', 'outer')).toBe('inner outer');
            expect(mergeBlockMarker('inner outer', 'inner')).toBe('inner outer');
            expect(mergeBlockMarker(undefined, 'only')).toBe('only');
        });
    });
});
