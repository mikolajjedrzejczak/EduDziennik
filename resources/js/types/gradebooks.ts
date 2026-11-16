import type { AssignmentDTO } from './assignments';
import type {
    GradeContext,
    GradeDTO,
    GradeHistoryEntry,
    GradeSummary,
} from './grades';
import type { Filters, Page } from './pagination';
import type { Option } from './school';

export type ClassListProps = {
    assignments: Page<AssignmentDTO>;
    filters: Filters & { q: string; termId: string | null; sort: 'name' };
    terms: Option[];
};

export type GradebookProps = {
    context: GradeContext;
    canCreateAssessment: boolean;
    assessments: Page<{
        id: string;
        topic: string;
        category: Option;
        defaultWeight: number;
        gradedOn: string;
        canDelete: boolean;
    }>;
    students: Page<{
        student: GradeDTO['student'];
        grades: GradeDTO[];
        deletedAssessmentIds: string[];
        creatableAssessmentIds: string[];
        summary: GradeSummary;
    }>;
    filters: Filters & { q: string; assessmentPage: number };
    categories: Option[];
};

export type StudentDetailProps = {
    student: GradeDTO['student'];
    context: GradeContext;
    grades: Page<GradeDTO>;
    summary: GradeSummary;
    historyUrl: string;
    recentHistory: (GradeHistoryEntry & { topic: string })[];
};

export type GradeFormProps = {
    grade: null;
    context: GradeContext;
    assessment: GradebookProps['assessments']['data'][number] | null;
    selectedEnrollmentId: string | null;
    students: GradeDTO['student'][];
    assessments: GradebookProps['assessments']['data'];
    categories: Option[];
    background: GradebookProps;
    submitUrl: string;
    cancelUrl: string;
};
