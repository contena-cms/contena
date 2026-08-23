<template>
    <ct-block name="sw_settings_search_live_search_explain">
        <section
            v-if="breakdown"
            id="ct-settings-search-live-search-explain"
            class="ct-settings-search-live-search__explain"
            :aria-label="t('ct-settings-search.liveSearchTab.explainTitle')"
        >
            <header class="ct-settings-search-live-search__explain-header">
                <strong class="ct-settings-search-live-search__explain-title">
                    {{ t('ct-settings-search.liveSearchTab.explainTitle')
                    }}<template v-if="explainName"> · {{ explainName }}</template>
                </strong>
                <span class="ct-settings-search-live-search__explain-total">
                    {{ t('ct-settings-search.liveSearchTab.totalScore') }}: {{ formatScore(breakdown.total) }}
                    <mt-icon
                        name="regular-question-circle-s"
                        size="16px"
                        :title="t('ct-settings-search.liveSearchTab.scoreHelpText')"
                    />
                </span>
            </header>

            <p v-if="scoresAreUniform" class="ct-settings-search-live-search__explain-hint">
                {{ t('ct-settings-search.liveSearchTab.uniformScoresHint') }}
            </p>

            <p v-if="breakdown.terms?.missed.length" class="ct-settings-search-live-search__explain-terms">
                <span class="ct-settings-search-live-search__explain-terms-matched">
                    {{ t('ct-settings-search.liveSearchTab.matchedTerms') }}: {{ breakdown.terms.matched.join(', ') || '-' }}
                </span>
                <span class="ct-settings-search-live-search__explain-terms-missed">
                    {{ t('ct-settings-search.liveSearchTab.unmatchedTerms') }}: {{ breakdown.terms.missed.join(', ') }}
                </span>
            </p>

            <div
                v-for="(section, sectionIndex) in breakdown.sections"
                :key="sectionIndex"
                class="ct-settings-search-live-search__explain-section"
            >
                <h5 class="ct-settings-search-live-search__explain-section-title">{{ section.label }}</h5>

                <div
                    v-for="(row, rowIndex) in section.rows"
                    :key="rowIndex"
                    class="ct-settings-search-live-search__explain-row"
                    :class="{ 'ct-settings-search-live-search__explain-row--flat': isFlatRow(row) }"
                >
                    <div v-if="isFlatRow(row)" class="ct-settings-search-live-search__explain-signal">
                        <span class="ct-settings-search-live-search__explain-signal-label">
                            {{ fieldLabel(row.label)
                            }}<template v-if="row.signals[0]?.term"> · “{{ row.signals[0].term }}”</template>
                        </span>
                        <span class="ct-settings-search-live-search__explain-signal-bar">
                            <span
                                class="ct-settings-search-live-search__explain-signal-bar-fill"
                                :style="{ width: row.signals[0]?.barWidth }"
                            />
                        </span>
                        <span class="ct-settings-search-live-search__explain-signal-score">{{ row.signals[0]?.score }}</span>
                    </div>

                    <template v-else>
                        <div class="ct-settings-search-live-search__explain-row-head">
                            <strong class="ct-settings-search-live-search__explain-row-label">{{
                                fieldLabel(row.label)
                            }}</strong>
                            <span v-if="row.ranking !== null" class="ct-settings-search-live-search__explain-row-weight">
                                {{ t('ct-settings-search.liveSearchTab.weight') }} {{ row.ranking }}
                            </span>
                        </div>

                        <div
                            v-for="(signal, signalIndex) in row.signals"
                            :key="signalIndex"
                            class="ct-settings-search-live-search__explain-signal"
                        >
                            <span class="ct-settings-search-live-search__explain-signal-label">
                                <span
                                    v-if="signal.type"
                                    class="ct-settings-search-live-search__explain-row-type"
                                    :title="explainTypeTooltip(signal.type)"
                                >
                                    {{ explainTypeLabel(signal.type) }}
                                </span>
                                <span v-if="signal.term" class="ct-settings-search-live-search__explain-signal-term"
                                    >“{{ signal.term }}”</span
                                >
                                <span v-if="signal.context" class="ct-settings-search-live-search__explain-signal-context">
                                    {{
                                        signal.context.whole
                                            ? t('ct-settings-search.liveSearchTab.partialContextWhole', {
                                                  word: signal.context.word,
                                              })
                                            : t('ct-settings-search.liveSearchTab.partialContext', {
                                                  fragment: signal.context.fragment,
                                                  word: signal.context.word,
                                              })
                                    }}
                                </span>
                            </span>
                            <span class="ct-settings-search-live-search__explain-signal-bar">
                                <span
                                    class="ct-settings-search-live-search__explain-signal-bar-fill"
                                    :style="{ width: signal.barWidth }"
                                />
                            </span>
                            <span class="ct-settings-search-live-search__explain-signal-score">{{ signal.score }}</span>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </ct-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { isFieldClause, parseClauses } from '../../helper/explain.helper';
import { SEARCH_CONFIG_FIELD_SNIPPETS, type SearchConfigField } from '../../constant/search-config-fields.constant';
import './ct-settings-search-live-search-explain.scss';

type SearchResult = {
    name?: string;
    translated?: { name?: string };
    extensions?: { search?: { _score?: string | number; matched_queries?: Record<string, unknown> } };
};
type MatchType = 'phrase' | 'exact' | 'prefix' | 'fuzzy' | 'ngram';
type RawSignal = { type: MatchType | null; term: string | null; score: number };
type RawRow = { label: string; ranking: number | null; signals: RawSignal[] };
type SignalContext = { fragment: string; word: string; whole: boolean };
type Signal = RawSignal & { score: string; barWidth: string; context: SignalContext | null };
type ExplainRow = { label: string; ranking: number | null; signals: Signal[] };
type TermCoverage = { matched: string[]; missed: string[] };
type Breakdown = { total: number; terms: TermCoverage | null; sections: Array<{ label: string; rows: ExplainRow[] }> };

const props = withDefaults(defineProps<{ item: SearchResult; searchTerm?: string; scoresAreUniform?: boolean }>(), {
    searchTerm: '',
    scoresAreUniform: false,
});
const { t } = useI18n();

const getScoreValue = (item: SearchResult) => Number.parseFloat(String(item.extensions?.search?._score ?? 0)) || 0;
const formatScore = (value: string | number) => {
    const score = Number.parseFloat(String(value)) || 0;
    return Number.isInteger(score) ? `${score}` : score.toFixed(1);
};
const matchTypeRank = (type: MatchType | null) => ({ phrase: 0, exact: 1, prefix: 2, fuzzy: 3, ngram: 4 })[type ?? ''] ?? 5;
const isMoreSpecificSignal = (candidate: RawSignal, existing: RawSignal) => {
    const candidateRank = matchTypeRank(candidate.type);
    const existingRank = matchTypeRank(existing.type);
    return candidateRank !== existingRank ? candidateRank < existingRank : candidate.score > existing.score;
};
const humanizeField = (field: unknown) => {
    if (typeof field !== 'string') return '';
    return field
        .split('.')
        .filter((segment) => !/^[0-9a-f]{32}$/i.test(segment))
        .filter(
            (segment) =>
                ![
                    'search',
                    'exact',
                    'ngram',
                ].includes(segment),
        )
        .join('.');
};
const collectFieldRows = (matchedQueries: Record<string, unknown>): RawRow[] => {
    const groups = new Map<string, { label: string; ranking: number | null; signals: Map<string, RawSignal> }>();
    parseClauses(matchedQueries).forEach(({ parsed, score: rawScore }) => {
        if (!isFieldClause(parsed)) return;
        const label = humanizeField(parsed.field);
        const ranking = typeof parsed.ranking === 'number' ? parsed.ranking : 1;
        const score = parsed.weighted ? rawScore : rawScore * ranking;
        const group = groups.get(label) ?? { label, ranking: null, signals: new Map<string, RawSignal>() };
        if (typeof parsed.ranking === 'number') group.ranking = Math.max(group.ranking ?? 0, parsed.ranking);
        const type = typeof parsed.type === 'string' ? parsed.type : null;
        const term = typeof parsed.term === 'string' ? parsed.term : null;
        const key = term ?? type ?? '';
        const candidate = { type, term, score };
        const existing = group.signals.get(key);
        if (!existing || isMoreSpecificSignal(candidate, existing)) group.signals.set(key, candidate);
        groups.set(label, group);
    });
    return Array.from(groups.values()).map((group) => ({
        ...group,
        signals: Array.from(group.signals.values()),
    }));
};
const foldTerm = (value: string) =>
    value
        .toLowerCase()
        .replace(/ß/g, 'ss')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
const longestCommonSubstring = (a: string, b: string) => {
    let best = '';
    for (let i = 0; i < a.length; i += 1) {
        for (let j = i + best.length + 1; j <= a.length; j += 1) {
            const candidate = a.slice(i, j);
            if (b.includes(candidate)) best = candidate;
        }
    }
    return best;
};
const matchedFragment = (term: string | null, text: string, type: MatchType = 'ngram'): SignalContext | null => {
    if (!term || !text) return null;
    const needle = foldTerm(term);
    const words = text.split(/\s+/).filter(Boolean);
    if (type === 'prefix') {
        const word = words.find((candidate) => foldTerm(candidate).startsWith(needle));
        return word ? { fragment: term, word, whole: true } : null;
    }
    let best = { fragment: '', word: '' };
    words.forEach((word) => {
        const fragment = longestCommonSubstring(needle, foldTerm(word));
        if (fragment.length > best.fragment.length) best = { fragment, word };
    });
    return best.fragment.length < 4 ? null : { ...best, whole: best.fragment === needle };
};
const toSignalRows = (rows: RawRow[], fieldText = ''): ExplainRow[] => {
    const max = rows.flatMap((row) => row.signals).reduce((value, signal) => Math.max(value, signal.score), 0) || 1;
    return rows
        .map((row) => ({
            label: row.label,
            ranking: row.ranking,
            top: row.signals.reduce((value, signal) => Math.max(value, signal.score), 0),
            signals: [...row.signals]
                .sort((a, b) => b.score - a.score)
                .map(
                    (signal): Signal => ({
                        ...signal,
                        score: formatScore(signal.score),
                        barWidth: `${Math.max(2, (signal.score / max) * 100)}%`,
                        context:
                            [
                                'ngram',
                                'prefix',
                            ].includes(signal.type ?? '') && row.label === 'name'
                                ? matchedFragment(signal.term, fieldText, signal.type ?? 'ngram')
                                : null,
                    }),
                ),
        }))
        .sort((a, b) => b.top - a.top)
        .map(({ top: _top, ...row }) => row);
};
const termCoverage = (matchedQueries: Record<string, unknown>): TermCoverage | null => {
    const words = props.searchTerm
        .toLowerCase()
        .split(/\s+/)
        .filter((word) => word.length >= 2 && /[\p{L}\p{N}]/u.test(word));
    if (words.length < 2) return null;
    const matchedWords = new Set(
        parseClauses(matchedQueries).flatMap(({ parsed }) =>
            typeof parsed.term === 'string' ? parsed.term.toLowerCase().split(/\s+/).filter(Boolean) : [],
        ),
    );
    return {
        matched: words.filter((word) => matchedWords.has(word)),
        missed: words.filter((word) => !matchedWords.has(word)),
    };
};
const getExplainBreakdown = (item: SearchResult): Breakdown | null => {
    const matchedQueries = item.extensions?.search?.matched_queries;
    if (!matchedQueries) return null;
    const rows = toSignalRows(collectFieldRows(matchedQueries), item.translated?.name ?? item.name ?? '');
    if (!rows.length) return null;
    return {
        total: getScoreValue(item),
        terms: termCoverage(matchedQueries),
        sections: [{ label: t('ct-settings-search.liveSearchTab.relevance'), rows }],
    };
};
const breakdown = computed(() => getExplainBreakdown(props.item));
const explainName = computed(() => props.item.translated?.name ?? props.item.name ?? '');
const isFlatRow = (row: ExplainRow) => row.signals.length === 1 && !row.signals[0]?.type;
const explainTypeLabel = (type: string | null) => {
    if (!type) return '';
    const key = `ct-settings-search.liveSearchTab.matchType.${type}`;
    const label = t(key);
    return label === key ? type : label;
};
const explainTypeTooltip = (type: string | null) => {
    if (!type) return '';
    const key = `ct-settings-search.liveSearchTab.matchTypeTooltip.${type}`;
    const tooltip = t(key);
    return tooltip === key ? type : tooltip;
};
const fieldLabel = (field: string) => {
    const snippet = SEARCH_CONFIG_FIELD_SNIPPETS[field as SearchConfigField];
    return snippet ? t(`ct-settings-search.generalTab.configFields.${snippet}`) : field;
};

const api = {
    breakdown,
    explainName,
    getScoreValue,
    formatScore,
    getExplainBreakdown,
    termCoverage,
    collectFieldRows,
    matchTypeRank,
    isMoreSpecificSignal,
    toSignalRows,
    isFlatRow,
    matchedFragment,
    foldTerm,
    longestCommonSubstring,
    humanizeField,
    explainTypeLabel,
    explainTypeTooltip,
    fieldLabel,
};

swDefinePublic({
    breakdown,
    explainName,
    getScoreValue,
    formatScore,
    getExplainBreakdown,
    termCoverage,
    collectFieldRows,
    matchTypeRank,
    isMoreSpecificSignal,
    toSignalRows,
    isFlatRow,
    matchedFragment,
    foldTerm,
    longestCommonSubstring,
    humanizeField,
    explainTypeLabel,
    explainTypeTooltip,
    fieldLabel,
});

defineExpose(api);
</script>
