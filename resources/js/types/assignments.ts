import type { Filters, Id, Page } from './pagination';
import type { Option } from './school';

/** AssignmentDTO (docs/http/REQUESTS_RESPONSES.md): no account data beyond a display label. */
export type AssignmentDTO = {
    id: Id;
    teacher: Option;
    schoolClass: Option;
    subject: Option;
    term: Option;
    revokedAt: string | null;
};

export type AssignmentFilters = Omit<Filters, 'q' | 'sort'> & {
    teacherId: Id | null;
    schoolClassId: Id | null;
    subjectId: Id | null;
    termId: Id | null;
    revoked: boolean;
    sort: 'id';
};

export type AssignmentOptions = {
    teachers: Option[];
    classes: Option[];
    subjects: Option[];
    terms: Option[];
};

export type AssignmentManagementProps = {
    assignments: Page<AssignmentDTO>;
    filters: AssignmentFilters;
    createOptions: AssignmentOptions;
    filterOptions: AssignmentOptions;
};
