export type Option = { id: string; label: string };
export type { Page } from './pagination';
import type { Page } from './pagination';
export type ClassDTO = {
    id: string;
    name: string;
    academicYear: Option;
    deletedAt: string | null;
};
export type EnrollmentDTO = {
    id: string;
    student: Option;
    schoolClass: Option;
    startsOn: string;
    endsOn: string | null;
};
export type ClassManagementProps = {
    classes: Page<ClassDTO>;
    filters: {
        q: string;
        academicYearId: string | null;
        termId: string | null;
        archived: boolean;
        page: number;
        perPage: 10 | 25 | 50;
        sort: 'name';
        direction: 'asc' | 'desc';
        selectedClassId: string | null;
        enrollmentPage: number;
    };
    years: Option[];
    selectedClass: ClassDTO | null;
    enrollments: Page<EnrollmentDTO> | null;
    students: Option[];
    classOptions: Option[];
};
