export type QueryValue = string | number | boolean | null | undefined;
export type Query = Record<string, string | number>;

/** Drops empty values (null, undefined, '') so URLs stay short and shareable. */
export function compactQuery(query: Record<string, QueryValue>): Query {
    const compact: Query = {};
    for (const [key, value] of Object.entries(query)) {
        if (value === null || value === undefined || value === '') continue;
        compact[key] = typeof value === 'boolean' ? Number(value) : value;
    }

    return compact;
}

/** `path` with the compacted query string; no trailing `?` when nothing is left. */
export function withQuery(path: string, query: Record<string, QueryValue>) {
    const search = new URLSearchParams(
        Object.entries(compactQuery(query)).map(([key, value]) => [
            key,
            String(value),
        ]),
    ).toString();

    return search ? `${path}?${search}` : path;
}
