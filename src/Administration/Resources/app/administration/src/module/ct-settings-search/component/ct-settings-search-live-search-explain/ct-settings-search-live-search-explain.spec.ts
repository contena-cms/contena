import { mount, type VueWrapper } from '@vue/test-utils';
import component from './ct-settings-search-live-search-explain.vue';

type Clause = Record<string, unknown>;
type ExplainSignal = {
    type: string | null;
    term: string | null;
    score: string;
    barWidth: string;
    context: { fragment: string; word: string; whole: boolean } | null;
};
type ExplainRow = { label: string; ranking: number | null; signals: ExplainSignal[] };
type ExplainBreakdown = {
    total: number;
    terms: { matched: string[]; missed: string[] } | null;
    sections: Array<{ label: string; rows: ExplainRow[] }>;
};
type ExplainVm = {
    getExplainBreakdown: (item: Record<string, unknown>) => ExplainBreakdown | null;
    collectFieldRows: (queries: Record<string, unknown>) => Array<{ signals: Array<Record<string, unknown>> }>;
    isMoreSpecificSignal: (candidate: Record<string, unknown>, existing: Record<string, unknown>) => boolean;
    matchedFragment: (term: string | null, text: string, type?: string) => ExplainSignal['context'];
    humanizeField: (field: unknown) => string;
    isFlatRow: (row: {
        label?: string;
        ranking?: number | null;
        signals: Array<{
            type?: string | null;
            term?: string | null;
            score?: string;
            barWidth?: string;
            context?: ExplainSignal['context'];
        }>;
    }) => boolean;
    explainTypeLabel: (type: string | null) => string;
    explainTypeTooltip: (type: string | null) => string;
    fieldLabel: (field: string) => string;
};
type TestProps = { item: ReturnType<typeof itemWithClauses>; searchTerm: string; scoresAreUniform: boolean };
type TestWrapper = VueWrapper<ExplainVm> & {
    props: {
        (): TestProps;
        (key: 'item'): ReturnType<typeof itemWithClauses>;
        (key: string): unknown;
    };
};

function itemWithClauses(clauses: Array<[Clause, number]>, score = 42, name = 'Article') {
    return {
        name,
        extensions: {
            search: {
                _score: score,
                matched_queries: Object.fromEntries(
                    clauses.map(
                        ([
                            clause,
                            clauseScore,
                        ]) => [
                            JSON.stringify(clause),
                            clauseScore,
                        ],
                    ),
                ),
            },
        },
    };
}

function createWrapper(item = itemWithClauses([]), searchTerm = '', scoresAreUniform = false) {
    return mount(component, {
        props: { item, searchTerm, scoresAreUniform },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-icon': true,
            },
        },
    }) as unknown as TestWrapper;
}

describe('ct-settings-search-live-search-explain', () => {
    it('builds a weighted field breakdown from matched queries', () => {
        const wrapper = createWrapper(
            itemWithClauses([
                [
                    { field: 'name', term: 'article', type: 'exact', ranking: 500 },
                    2,
                ],
                [
                    { field: 'description', term: 'article', type: 'fuzzy', ranking: 80 },
                    3,
                ],
            ]),
        );

        const breakdown = wrapper.vm.getExplainBreakdown(wrapper.props('item'));
        expect(breakdown?.total).toBe(42);
        expect(breakdown?.sections[0]?.rows.map((row) => row.label)).toEqual([
            'name',
            'description',
        ]);
        expect(breakdown?.sections[0]?.rows[0]?.signals[0]?.score).toBe('1000');
    });

    it('does not multiply a score already weighted by the backend', () => {
        const wrapper = createWrapper(
            itemWithClauses([
                [
                    { field: 'name', term: 'article', ranking: 500, weighted: true },
                    12.5,
                ],
            ]),
        );

        expect(wrapper.vm.getExplainBreakdown(wrapper.props('item'))?.sections[0]?.rows[0]?.signals[0]?.score).toBe('12.5');
    });

    it('keeps the most specific match type for one term', () => {
        const wrapper = createWrapper(
            itemWithClauses([
                [
                    { field: 'name', term: 'article', type: 'ngram', ranking: 1 },
                    30,
                ],
                [
                    { field: 'name', term: 'article', type: 'fuzzy', ranking: 1 },
                    20,
                ],
                [
                    { field: 'name', term: 'article', type: 'exact', ranking: 1 },
                    10,
                ],
            ]),
        );

        const item = wrapper.props('item');
        expect(wrapper.vm.collectFieldRows(item.extensions.search.matched_queries)[0]?.signals).toEqual([
            { type: 'exact', term: 'article', score: 10 },
        ]);
    });

    it('keeps the stronger score when match specificity is equal', () => {
        const wrapper = createWrapper();

        expect(
            wrapper.vm.isMoreSpecificSignal(
                { type: 'exact', term: 'article', score: 20 },
                { type: 'exact', term: 'article', score: 10 },
            ),
        ).toBe(true);
    });

    it('excludes boost and cross-entity clauses from the core breakdown', () => {
        const wrapper = createWrapper(
            itemWithClauses([
                [
                    { boost: 5, name: 'rule' },
                    20,
                ],
                [
                    { crossEntity: 'category', term: 'article' },
                    10,
                ],
                [
                    { field: 'name', term: 'article', type: 'exact' },
                    5,
                ],
            ]),
        );

        expect(wrapper.vm.getExplainBreakdown(wrapper.props('item'))?.sections[0]?.rows).toHaveLength(1);
    });

    it('explains prefix matches only at the beginning of a word', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.matchedFragment('box', 'Xbox Boxer', 'prefix')).toEqual({
            fragment: 'box',
            word: 'Boxer',
            whole: true,
        });
    });

    it('explains partial matches across analyzer-style ascii folding', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.matchedFragment('strass', 'Straße', 'ngram')).toEqual({
            fragment: 'strass',
            word: 'Straße',
            whole: true,
        });
    });

    it('does not explain fragments below the default ngram floor', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.matchedFragment('cat', 'Catering', 'ngram')).toBeNull();
    });

    it('reports exact term coverage without substring false positives', () => {
        const item = itemWithClauses([
            [
                { field: 'name', term: 'iron', type: 'exact' },
                10,
            ],
        ]);
        const wrapper = createWrapper(item, 'iron on');

        expect(wrapper.vm.getExplainBreakdown(item)?.terms).toEqual({ matched: ['iron'], missed: ['on'] });
    });

    it('counts every word in a matched phrase', () => {
        const item = itemWithClauses([
            [
                { field: 'name', term: 'content system', type: 'phrase' },
                10,
            ],
        ]);
        const wrapper = createWrapper(item, 'content system');

        expect(wrapper.vm.getExplainBreakdown(item)?.terms).toEqual({
            matched: [
                'content',
                'system',
            ],
            missed: [],
        });
    });

    it('ignores punctuation and words shorter than two characters in term coverage', () => {
        const item = itemWithClauses([
            [
                { field: 'name', term: 'article', type: 'exact' },
                10,
            ],
        ]);
        const wrapper = createWrapper(item, 'article - blue 5');

        expect(wrapper.vm.getExplainBreakdown(item)?.terms).toEqual({ matched: ['article'], missed: ['blue'] });
    });

    it('does not report trivial single-word coverage', () => {
        const item = itemWithClauses([
            [
                { field: 'name', term: 'article', type: 'exact' },
                10,
            ],
        ]);
        const wrapper = createWrapper(item, 'article');

        expect(wrapper.vm.getExplainBreakdown(item)?.terms).toBeNull();
    });

    it('humanizes nested field names and removes technical path segments', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.humanizeField('search.0123456789abcdef0123456789abcdef.categories.name.exact')).toBe(
            'categories.name',
        );
    });

    it('marks one typeless extension signal as a flat row', () => {
        const wrapper = createWrapper();

        expect(
            wrapper.vm.isFlatRow({
                label: 'rule',
                ranking: null,
                signals: [{ type: null, term: null, score: '5', barWidth: '100%', context: null }],
            }),
        ).toBe(true);
    });

    it('renders no panel without explainable field clauses', () => {
        const wrapper = createWrapper(
            itemWithClauses([
                [
                    { boost: 5 },
                    10,
                ],
            ]),
        );

        expect(wrapper.find('#ct-settings-search-live-search-explain').exists()).toBe(false);
    });

    it('renders the panel, result name, score, and tie hint', () => {
        const wrapper = createWrapper(
            itemWithClauses(
                [
                    [
                        { field: 'name', term: 'article', type: 'exact' },
                        10,
                    ],
                ],
                42,
                'First article',
            ),
            'article',
            true,
        );

        expect(wrapper.find('#ct-settings-search-live-search-explain').text()).toContain('First article');
        expect(wrapper.find('#ct-settings-search-live-search-explain').text()).toContain('42');
        expect(wrapper.find('.ct-settings-search-live-search__explain-hint').exists()).toBe(true);
    });

    it('scales raw field scores so they compare with weighted nested scores', () => {
        const wrapper = createWrapper(
            itemWithClauses([
                [
                    { field: 'name', term: 'article', type: 'exact', ranking: 700 },
                    2.8,
                ],
                [
                    { field: 'categories.name', term: 'guide', ranking: 500, weighted: true },
                    2121.7,
                ],
            ]),
        );

        const rows = wrapper.vm.getExplainBreakdown(wrapper.props('item'))?.sections[0]?.rows ?? [];
        const byField = Object.fromEntries(
            rows.map((row) => [
                row.label,
                row,
            ]),
        );

        expect(byField.name?.signals[0]?.score).toBe('1960.0');
        expect(byField['categories.name']?.signals[0]?.score).toBe('2121.7');
        expect(Number.parseFloat(byField.name?.signals[0]?.barWidth ?? '0')).toBeGreaterThan(50);
    });

    it('keeps partial matches and explains their shared fragments', () => {
        const wrapper = createWrapper(
            itemWithClauses(
                [
                    [
                        { field: 'name', term: 'awes', type: 'ngram', ranking: 700 },
                        0.6,
                    ],
                    [
                        { field: 'name', term: 'batter', type: 'ngram', ranking: 700 },
                        0.4,
                    ],
                ],
                745.5,
                'Awesome Article Swatter',
            ),
        );

        const signals = wrapper.vm.getExplainBreakdown(wrapper.props('item'))?.sections[0]?.rows[0]?.signals ?? [];
        expect(signals.map((signal) => signal.term)).toEqual([
            'awes',
            'batter',
        ]);
        expect(signals[0]?.context).toEqual({ fragment: 'awes', word: 'Awesome', whole: true });
        expect(signals[1]?.context).toEqual({ fragment: 'atter', word: 'Swatter', whole: false });
    });

    it('explains analyzer folding for umlauts and eszett', () => {
        const umlautWrapper = createWrapper(
            itemWithClauses(
                [
                    [
                        { field: 'name', term: 'muller', type: 'ngram', ranking: 700 },
                        0.6,
                    ],
                ],
                10,
                'Müller Article',
            ),
        );
        const eszettWrapper = createWrapper(
            itemWithClauses(
                [
                    [
                        { field: 'name', term: 'strasse', type: 'ngram', ranking: 700 },
                        0.6,
                    ],
                ],
                10,
                'Straße Article',
            ),
        );

        expect(
            umlautWrapper.vm.getExplainBreakdown(umlautWrapper.props('item'))?.sections[0]?.rows[0]?.signals[0]?.context,
        ).toEqual({
            fragment: 'muller',
            word: 'Müller',
            whole: true,
        });
        expect(
            eszettWrapper.vm.getExplainBreakdown(eszettWrapper.props('item'))?.sections[0]?.rows[0]?.signals[0]?.context,
        ).toEqual({
            fragment: 'strasse',
            word: 'Straße',
            whole: true,
        });
    });

    it('does not add fragment context to exact or phrase matches', () => {
        const wrapper = createWrapper(
            itemWithClauses(
                [
                    [
                        { field: 'name', term: 'article', type: 'exact', ranking: 700 },
                        2.8,
                    ],
                    [
                        { field: 'name', term: 'content article', type: 'phrase', ranking: 700, weighted: true },
                        2170,
                    ],
                ],
                42,
                'Content Article',
            ),
        );

        const signals = wrapper.vm.getExplainBreakdown(wrapper.props('item'))?.sections[0]?.rows[0]?.signals ?? [];
        expect(signals.find((signal) => signal.type === 'exact')?.context).toBeNull();
        expect(signals.find((signal) => signal.type === 'phrase')?.context).toBeNull();
    });

    it('keeps a weighted phrase score exactly as reported by the backend', () => {
        const wrapper = createWrapper(
            itemWithClauses([
                [
                    { field: 'name', term: 'content article', type: 'phrase', ranking: 700, weighted: true },
                    12180,
                ],
            ]),
        );

        const signal = wrapper.vm.getExplainBreakdown(wrapper.props('item'))?.sections[0]?.rows[0]?.signals[0];
        expect(signal?.type).toBe('phrase');
        expect(signal?.score).toBe('12180');
        expect(signal?.context).toBeNull();
    });

    it('falls back to raw labels for unknown match types and fields', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.explainTypeLabel('semantic')).toBe('semantic');
        expect(wrapper.vm.explainTypeTooltip('semantic')).toBe('semantic');
        expect(wrapper.vm.explainTypeLabel(null)).toBe('');
        expect(wrapper.vm.explainTypeTooltip(null)).toBe('');
        expect(wrapper.vm.fieldLabel('name')).toBe('ct-settings-search.generalTab.configFields.name');
        expect(wrapper.vm.fieldLabel('customFields.material')).toBe('customFields.material');
    });

    it('returns no breakdown for missing, empty, or extension-only clauses', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.getExplainBreakdown({ extensions: { search: { _score: 42 } } })).toBeNull();
        expect(wrapper.vm.getExplainBreakdown({ extensions: { search: { _score: 1, matched_queries: {} } } })).toBeNull();
        expect(
            wrapper.vm.getExplainBreakdown(
                itemWithClauses([
                    [
                        { boost: 5, name: 'Rule' },
                        99,
                    ],
                ]),
            ),
        ).toBeNull();
    });
});
