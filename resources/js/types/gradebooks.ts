import type { AssignmentDTO } from './assignments';
import type { GradeContext, GradeDTO, GradeSummary } from './grades';
import type { Filters, Page } from './pagination';
import type { Option } from './school';

export type ClassListProps = {
    assignments: Page<AssignmentDTO>;
    filters: Filters & { q: string; termId: string | null; sort: 'name' };
    terms: Option[];
};

export type GradebookProps = {
    context: GradeContext;
    assessments: Page<{
        id: string;
        topic: string;
        category: Option;
        defaultWeight: number;
        gradedOn: string;
    }>;
    students: Page<{
        student: GradeDTO['student'];
        grades: GradeDTO[];
        deletedAssessmentIds: string[];
        summary: GradeSummary;
    }>;
    filters: Filters & { q: string; assessmentPage: number };
    categories: Option[];
};
