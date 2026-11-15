import type { Filters, Id, Page } from './pagination';

/** SubjectDTO; activeAssignmentsCount is an informational hint, the server decides archiving. */
export type SubjectDTO = {
    id: Id;
    name: string;
    deletedAt: string | null;
    activeAssignmentsCount: number;
};

export type SubjectFilters = Filters & { archived: boolean };

export type SubjectManagementProps = {
    subjects: Page<SubjectDTO>;
    filters: SubjectFilters;
};
