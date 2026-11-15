/** Contract: docs/http/REQUESTS_RESPONSES.md (Id, PageLink, Page, Filters). */
export type Id = string;

export type PageLink = { label: string; url: string | null; active: boolean };

export type Page<T> = {
    data: T[];
    meta: {
        currentPage: number;
        perPage: number;
        total: number;
        lastPage: number;
    };
    links: PageLink[];
};

export type Filters = {
    q?: string;
    page: number;
    perPage: 10 | 25 | 50;
    sort: string;
    direction: 'asc' | 'desc';
};
