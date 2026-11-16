import type { Page } from './pagination';
import type { Option } from './school';

export type GradeValue = 1 | 2 | 3 | 4 | 5 | 6;

/** Contract: docs/http/REQUESTS_RESPONSES.md (GradeDTO, GradeSummary, GradeListProps). */

export type Average = string | null;

export type GradeContext = {
    assignmentId: string | null;
    schoolClass: Option;
    subject: Option;
    term: Option;
    closed: boolean;
};

export type GradeDTO = {
    id: string;
    student: {
        id: string;
        enrollmentId: string;
        name: string;
        historical: boolean;
    };
    assessmentId: string;
    context: GradeContext;
    value: GradeValue;
    weight: 1 | 2 | 3 | 4 | 5;
    category: Option;
    topic: string;
    comment: string | null;
    gradedOn: string;
    issuedBy: Option;
    createdAt: string;
    updatedAt: string;
    deletedAt: string | null;
    version: number;
    differsFromAssessment: boolean;
    can: { update: boolean; delete: boolean; viewHistory: boolean };
};

/** `scopeLabel` names the active term/subject/category/date/topic filters the average covers. */
export type GradeSummary = {
    average: Average;
    count: number;
    scopeLabel: string;
};

export type SubjectSummary = {
    subject: Option;
    average: Average;
    count: number;
};

export type GradeSort = 'graded_on' | 'value' | 'weight';

export type GradeFilters = {
    termId: string | null;
    subjectId: string | null;
    categoryId: string | null;
    q: string;
    dateFrom: string | null;
    dateTo: string | null;
    page: number;
    perPage: 10 | 25 | 50;
    sort: GradeSort;
    direction: 'asc' | 'desc';
};

export type GradeOptions = {
    terms: Option[];
    subjects: Option[];
    categories: Option[];
};

export type GradeListProps = {
    grades: Page<GradeDTO>;
    summary: GradeSummary;
    subjects: SubjectSummary[];
    filters: GradeFilters;
    options: GradeOptions;
};

export type GradeHistoryEntry = {
    id: string;
    action: 'create' | 'update' | 'delete';
    actor: Option;
    occurredAt: string;
    oldValue: GradeValue | null;
    value: GradeValue;
    weight: number;
    reason: string | null;
};

export type GradeDetailProps = {
    grade: GradeDTO;
    summary: GradeSummary;
    averageWithoutGrade: Average;
    historyUrl: string;
    backUrl: string;
    history: Page<GradeHistoryEntry>;
};
