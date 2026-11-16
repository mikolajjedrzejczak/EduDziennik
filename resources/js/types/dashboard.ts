import type { AssignmentDTO } from './assignments';
import type { GradeDTO, GradeSummary, SubjectSummary } from './grades';
import type { Option } from './school';

export type StudentDashboardProps = {
    term: Option | null;
    terms: Option[];
    summary: GradeSummary;
    /** Backend orders by the unrounded average descending, with name/id tie breaks. */
    subjects: SubjectSummary[];
    recentGrades: GradeDTO[];
};

export type TeacherDashboardProps = {
    term: Option | null;
    terms: Option[];
    assignmentCount: number;
    ungradedCount: number;
    atRiskCount: number;
    /** First five assignments in class name/id order; full list is F-202. */
    assignments: AssignmentDTO[];
    recentGrades: GradeDTO[];
};
