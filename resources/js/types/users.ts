import type { Page } from '@/types/pagination';
import type { EnrollmentDTO, Option } from '@/types/school';

/** Contract: docs/http/REQUESTS_RESPONSES.md (UserDTO, AssignmentDTO, UserListProps, UserDetailProps). */
export type Role = 'student' | 'teacher' | 'admin';
export type AccountStatus = 'active' | 'inactive';

export type UserDTO = {
    id: string;
    firstName: string;
    lastName: string;
    email: string;
    role: Role;
    status: AccountStatus;
    deletedAt: string | null;
    lastLoginAt: string | null;
};

/** List rows add a server-built "class / assignment" summary (Figma 19:379). */
export type UserRowDTO = UserDTO & { relationSummary: string };

export type AssignmentDTO = {
    id: string;
    teacher: Option;
    schoolClass: Option;
    subject: Option;
    term: Option;
    revokedAt: string | null;
};

export type UserSort = 'last_name' | 'email' | 'role' | 'created_at';

export type UserFilters = {
    q: string;
    role: Role | null;
    status: AccountStatus | 'deleted' | null;
    schoolClassId: string | null;
    termId: string | null;
    page: number;
    perPage: 10 | 25 | 50;
    sort: UserSort;
    direction: 'asc' | 'desc';
};

export type YearOption = Option & { academicYearId: string };

export type UserListOptions = {
    roles: Role[];
    classes: YearOption[];
    terms: YearOption[];
};

export type UserListProps = {
    users: Page<UserRowDTO>;
    filters: UserFilters;
    options: UserListOptions;
};

export type UserDetailProps = {
    user: UserDTO;
    enrollments: EnrollmentDTO[];
    assignments: AssignmentDTO[];
    relationTotals: { enrollments: number; assignments: number };
};

/** UserFormProps: user is null on create; edit exposes only role options and empty relation dictionaries. */
export type UserFormOptions = UserListOptions & { subjects: Option[] };

export type UserFormProps = {
    user: UserDTO | null;
    options: UserFormOptions;
};
