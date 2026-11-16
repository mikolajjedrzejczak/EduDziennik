import type { GradeDTO, GradeOptions, GradeValue } from './grades';
import type { Page } from './pagination';
import type { Option } from './school';

export type GradeSnapshot = {
    schema_version: 1;
    id: string;
    student_enrollment_id: string;
    student_id: string;
    assessment_id: string;
    school_class_id: string;
    subject_id: string;
    term_id: string;
    category_id: string;
    value: GradeValue;
    weight: number;
    topic: string;
    comment: string | null;
    graded_on: string;
    issued_by: string;
    version: number;
    deleted_at: string | null;
};
export type AuditAction = 'create' | 'update' | 'delete';
export type AuditDTO = {
    id: string;
    gradeId: string;
    actor: Option;
    action: AuditAction;
    occurredAt: string;
    oldValues: GradeSnapshot | null;
    newValues: GradeSnapshot;
    reason: string | null;
    subject: Option;
    student: Option;
    gradeUrl: string;
};
export type HistoryFilters = {
    gradeId: string | null;
    studentEnrollmentId: string | null;
    termId: string | null;
    subjectId: string | null;
    action: AuditAction | null;
    dateFrom: string | null;
    dateTo: string | null;
    page: number;
    perPage: 10 | 25 | 50;
};
export type HistoryProps = {
    audits: Page<AuditDTO>;
    filters: HistoryFilters;
    options: GradeOptions;
    grade: GradeDTO | null;
    indexUrl: string;
    historyUrl: string;
};
