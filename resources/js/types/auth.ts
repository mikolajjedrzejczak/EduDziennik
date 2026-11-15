export type User = {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    firstName: string;
    lastName: string;
    role: 'student' | 'teacher' | 'admin';
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Capabilities = {
    createGrade: boolean;
    manageUsers: boolean;
    manageSchool: boolean;
};
