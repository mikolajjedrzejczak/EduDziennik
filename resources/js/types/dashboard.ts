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
