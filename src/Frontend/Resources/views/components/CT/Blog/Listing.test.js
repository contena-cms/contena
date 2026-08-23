import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

import 'contena';

globalThis.activeNavigationId = 'cat-123';

const { default: BlogListing } = await import('./Listing');

function buildEl() {
    const el = document.createElement('div');
    el.setAttribute('data-element-id', 'listing-el');
    el.innerHTML = `
        <div class="ct-blog-listing__grid"></div>
        <div class="ct-blog-listing__pagination"></div>
        <span class="ct-filter-panel__counter">3 results</span>
    `;
    return el;
}

describe('BlogListing', () => {
    let el;
    let listing;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        listing = new BlogListing(el, {});
        listing.init();
        // Prevent actual fetch calls in tests by replacing loadListing.
        listing.loadListing = vi.fn();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('updates the result counter after loading the listing', async () => {
        vi.useFakeTimers();
        vi.spyOn(globalThis, 'fetch').mockResolvedValue({
            text: () => Promise.resolve(`
                <div class="ct-blog-listing__grid"></div>
                <div class="ct-blog-listing__pagination"></div>
                <span class="ct-filter-panel__counter">7 results</span>
            `),
        });

        listing.debouncedLoad();
        await vi.runAllTimersAsync();

        expect(el.querySelector('.ct-filter-panel__counter').textContent).toBe('7 results');
    });

    describe('handleFilterChange', () => {
        it('sets a simple filter param', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });

            expect(listing.activeParams.color).toBe('red');
        });

        it('resets the page to 1 when a filter changes', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });

            expect(listing.activeParams[listing.options.pageParamName]).toBe(1);
        });

        it('removes a param when value is empty string', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });
            listing.handleFilterChange({ paramName: 'color', value: '' });

            expect(listing.activeParams.color).toBeUndefined();
        });

        it('removes a param when value is null', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });
            listing.handleFilterChange({ paramName: 'color', value: null });

            expect(listing.activeParams.color).toBeUndefined();
        });

        it('accumulates activeOptions into a pipe-separated string', () => {
            listing.handleFilterChange({
                paramName: 'tags',
                activeOptions: ['red', 'blue'],
                removedOptions: [],
            });

            expect(listing.activeParams.tags).toBe('red|blue');
        });

        it('removes an option from an existing pipe-separated value', () => {
            listing.handleFilterChange({ paramName: 'tags', activeOptions: ['red', 'blue'], removedOptions: [] });
            listing.handleFilterChange({ paramName: 'tags', activeOptions: [], removedOptions: ['red'] });

            expect(listing.activeParams.tags).toBe('blue');
        });

        it('calls loadListing', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handleFilterRemove', () => {
        it('removes the entire param when no option is specified', () => {
            listing.activeParams.color = 'red';
            listing.handleFilterRemove({ paramName: 'color' });

            expect(listing.activeParams.color).toBeUndefined();
        });

        it('removes a specific option from a pipe-separated param', () => {
            listing.activeParams.tags = 'red|blue|green';
            listing.handleFilterRemove({ paramName: 'tags', option: 'blue' });

            expect(listing.activeParams.tags).toBe('red|green');
        });

        it('removes the param entirely when the last option is removed', () => {
            listing.activeParams.tags = 'red';
            listing.handleFilterRemove({ paramName: 'tags', option: 'red' });

            expect(listing.activeParams.tags).toBeUndefined();
        });

        it('does nothing when the param does not exist', () => {
            listing.handleFilterRemove({ paramName: 'nonexistent' });

            expect(listing.activeParams.nonexistent).toBeUndefined();
        });

        it('calls loadListing', () => {
            listing.activeParams.color = 'red';
            listing.handleFilterRemove({ paramName: 'color' });

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handlePageChange', () => {
        it('sets the page param', () => {
            listing.handlePageChange(3);

            expect(listing.activeParams[listing.options.pageParamName]).toBe(3);
        });

        it('calls loadListing', () => {
            listing.handlePageChange(2);

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handleSortingChange', () => {
        it('sets the sorting param', () => {
            listing.handleSortingChange('createdAt-asc');

            expect(listing.activeParams[listing.options.sortingParamName]).toBe('createdAt-asc');
        });

        it('calls loadListing', () => {
            listing.handleSortingChange('name-desc');

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handleLayoutChange', () => {
        it('sets the layout param', () => {
            listing.handleLayoutChange('layout', 'horizontal');

            expect(listing.activeParams['layout']).toBe('horizontal');
        });
    });

    describe('getStateFromUrl', () => {
        it('parses existing URL search parameters into activeParams', () => {
            // happy-dom allows setting location.search
            Object.defineProperty(window, 'location', {
                value: { ...window.location, search: '?p=2&order=createdAt-asc' },
                configurable: true,
            });

            listing.getStateFromUrl();

            expect(listing.activeParams.p).toBe('2');
            expect(listing.activeParams.order).toBe('createdAt-asc');

            // Restore default
            Object.defineProperty(window, 'location', {
                value: { ...window.location, search: '' },
                configurable: true,
            });
        });

    });

    describe('changeLayout', () => {
        it('adds the layout class to the grid and blog cards', () => {
            vi.useFakeTimers();
            // updateHistory calls new URL(window.location) which fails in happy-dom; stub it.
            listing.updateHistory = vi.fn();

            const grid = el.querySelector('.ct-blog-listing__grid');
            grid.innerHTML = `
                <div class="ct-blog-card is--layout-default"></div>
                <div class="ct-blog-card is--layout-default"></div>
            `;

            listing.changeLayout('horizontal');
            vi.runAllTimers();

            const cards = grid.querySelectorAll('.ct-blog-card');
            cards.forEach(card => {
                expect(card.classList.contains('is--layout-horizontal')).toBe(true);
                expect(card.classList.contains('is--layout-default')).toBe(false);
            });

            vi.useRealTimers();
        });
    });
});
