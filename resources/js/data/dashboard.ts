/** Mock "Relevant posts" card value; stays mock until the AI filtering spec (AC-105). */
export const mockRelevantPosts = { value: 1936, change: '15.5% relevance' };

const RELEVANT_PATTERN = [
    27, 30, 34, 32, 35, 32, 34, 29, 33, 31, 35, 30, 33, 31,
];

/** Deterministic mock series of relevant posts for the last N days, oldest first. */
export function mockRelevantSeries(days: number): number[] {
    return Array.from(
        { length: days },
        (_, index) => RELEVANT_PATTERN[index % RELEVANT_PATTERN.length],
    );
}
