/** @private */
export type ParsedClause = {
    parsed: Record<string, unknown>;
    score: number;
};

/** @private */
export function parseClauses(matchedQueries?: Record<string, unknown> | null): ParsedClause[] {
    if (!matchedQueries) {
        return [];
    }

    return Object.keys(matchedQueries).flatMap((clause) => {
        try {
            const parsed: unknown = JSON.parse(clause);
            if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
                return [];
            }

            return [
                { parsed: parsed as Record<string, unknown>, score: Number.parseFloat(String(matchedQueries[clause])) || 0 },
            ];
        } catch {
            return [];
        }
    });
}

/** @private */
export function isFieldClause(parsed: unknown): parsed is Record<string, unknown> {
    return (
        parsed !== null &&
        typeof parsed === 'object' &&
        !Array.isArray(parsed) &&
        !('boost' in parsed) &&
        !('crossEntity' in parsed)
    );
}
