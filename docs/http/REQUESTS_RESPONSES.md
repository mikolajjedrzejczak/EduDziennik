# Kontrakty Inertia — PLANNED

Źródło nazw tras: [ROUTES](ROUTES.md), zakres danych: [RBAC](../product/RBAC.md), invariants: [BUSINESS_RULES](../database/BUSINESS_RULES.md). To specyfikacja, nie wygenerowany kod aplikacji.

## Konwencje

ID w URL i DTO to dziesiętne stringi, walidowane jako dodatnie ID istniejących rekordów w autoryzowanym zakresie. HTTP body ma snake_case, DTO camelCase; mapowanie jawne w kontrolerze/serializerze. Daty `YYYY-MM-DD`, timestampy ISO 8601 UTC. Nie wysyłać całych modeli Eloquent, hasha, session payload, IP ani remember token.

Mutacja → redirect do wskazanego GET z `flash.success` lub `errors`. Adapter konwertuje przekierowania po PUT/PATCH/DELETE na 303. Walidacja Inertia nie zwraca ręcznego 422 JSON. [Dokumentacja Inertia](https://inertiajs.com/docs/v3/the-basics/validation).

## Słownik wejścia i walidacji

Wszystkie niezdefiniowane pola ignorowane przy zapisie (allowlista validated), a próby podmiany chronionych ID/roli testowane. Form Request zawsze wykonuje odpowiednią Policy. Istnienie FK nie zastępuje zgodności relacji.

| Request | Pola i walidacja |
|---|---|
| LoginRequest | email required/email/max254, trim+lowercase; password required/string; brak remember w MVP |
| UpdateProfileRequest | first_name, last_name: required Unicode letters/marks/spaces/apostrophe/hyphen, 1–80; tylko własne konto; brak email/role/status |
| UpdatePasswordRequest | current_password required/current_password; password 12–128/confirmed, różne od obecnego; password_confirmation; limit 5/min/user |
| DashboardFilterRequest | term_id opcjonalny, dostępny; brak termu → wybór lub pusty stan, nie losowe dane |
| GradeFilterRequest | term_id, subject_id?, category_id?, q? (temat oceny, max100), date_from?, date_to?, page, per_page, sort, direction; admin dodatkowo student_id?, school_class_id?; teacher tylko uprawnione konteksty, opcjonalnie student_enrollment_id w ich klasie/termie; student nigdy student_id ani student_enrollment_id |
| HistoryFilterRequest | grade_id?, student_enrollment_id? (teacher/admin, scoped), term_id?, subject_id?, action? create/update/delete, date_from?, date_to?, page, per_page; zakres dat filtruje occurred_at (granice dnia Warsaw przeliczone UTC) |
| ClassFilterRequest | term_id?, q?, page, per_page, sort=name, direction; dla admin listy klas także academic_year_id?, archived? boolean, selected_class_id? scoped, enrollment_page? positive default1 |
| GradebookFilterRequest | q? nazwisko ucznia, page, per_page, assessment_page positive default1, sort=last_name, direction; term/class/subject zawsze z assignment URL |
| GradeFormContextRequest | assignment_id required scoped, assessment_id? w tym kontekście, student_enrollment_id? zgodny z klasą i termem |
| StoreAssessmentRequest | category_id aktywne, topic 1–120, default_weight integer1..5, graded_on date w termie/≤today; kontekst z assignment URL |
| DeleteAssessmentRequest | confirmed accepted; assessment bez jakichkolwiek ocen, otwarty term |
| StoreGradeRequest | assignment_id, assessment_id, student_enrollment_id required/scoped; value integer1..6, weight integer1..5, category_id aktywne, topic1..120, comment nullable/max500, graded_on date w termie/enrollment/≤today; unique enrollment+assessment również z trashed |
| UpdateGradeRequest | assignment_id zgodne z oceną, version integer≥1; value,weight,category_id,topic,comment,graded_on jak create; reason required3..500; enrollment_id/assessment_id/issued_by niezmienne |
| DeleteGradeRequest | assignment_id zgodne, version integer≥1, reason required3..500, confirmed accepted |
| UserFilterRequest | q?, role? student/teacher/admin, status? active/inactive/deleted, school_class_id?, term_id?, page/per_page, sort last_name/email/role/created_at, direction |
| StoreUserRequest | first_name,last_name jak profil; email unique także trashed; password12..128/confirmed; role enum, status active/inactive; enrollment? {school_class_id,starts_on,ends_on?} tylko student; assignments? lista {school_class_id,subject_id,term_id} tylko teacher; max20 bez duplikatów |
| UpdateUserRequest | first_name,last_name,email,role,status jak create; password?12..128/confirmed (puste pomijane); brak zagnieżdżonych relacji — zmienia je F-306/F-308; role z historią chronione BR-05 |
| DeleteUserRequest | confirmation_last_name required = bieżące last_name po trim; nie self, nie ostatni aktywny admin |
| StoreClassRequest | academic_year_id istniejący, name required/max20, regex `^[A-Za-z0-9][A-Za-z0-9 -]{0,19}$`, unique(year,name) |
| UpdateClassRequest | name jak create; rok niezmienny; archived nie jest polem tej mutacji |
| DeleteClassRequest | confirmed accepted; brak aktywnych relacji wg BR-05 |
| SubjectFilterRequest | q?, archived? boolean, page/per_page, sort=name, direction |
| StoreSubjectRequest / UpdateSubjectRequest | name required/string1..100, unique także archived; bez sztucznego regexu ograniczającego polskie nazwy |
| DeleteSubjectRequest | confirmed accepted; brak niecofniętych assignments |
| AssignmentFilterRequest | teacher_id?, school_class_id?, subject_id?, term_id?, revoked? boolean, page/per_page; sort=id, direction |
| StoreAssignmentRequest | teacher_id aktywny teacher, class/subject aktywne, term zgodny z rokiem klasy, unikalna czwórka; istniejący cofnięty przydział reaktywowany pod blokadą |
| UpdateAssignmentRequest | revoked boolean; tożsamość czwórki niezmienna; zmiana nauczyciela to revoke + nowe store |
| DeleteAssignmentRequest | confirmed accepted; ustawia revoked_at, bez physical delete |
| StoreEnrollmentRequest | student_id aktywny student, starts_on, ends_on? w roku klasy URL; transfer_from_enrollment_id? należący do tego ucznia; transfer kończy stary dzień wcześniej; atomowo |
| UpdateEnrollmentRequest | starts_on, ends_on?; student i class niezmienne, brak nakładania i ocen poza nowymi datami; zakończenie przez ends_on, brak DELETE |

Błędny filtr GET: 303 do tej samej trasy z odrzuconym błędnym parametrem i session errors, nie back do nieprawidłowego URL. Strona poza zakresem: 303 do ostatniej istniejącej strony z zachowaniem poprawnych filtrów; pusta lista ma stronę1. Przy wielokrotnym paginowaniu dotyczy to każdego parametru oddzielnie.

Reguły wspólne filtrów: q trim/max100; page≥1; per_page IN(10,25,50), domyślnie 10. direction asc/desc, sort wyłącznie z allowlisty danej trasy; domyślnie grades graded_on desc, history occurred_at desc, users last_name asc, zawsze tie-break id w tym samym kierunku. Daty from≤to; nieznany parametr nie staje się warunkiem SQL. W filtrach użytkownika klasa dotyczy enrollmentu przecinającego wybrany term (term wymagany przy school_class_id). Dopuszczalne sorty grades: graded_on,value,weight; history: occurred_at. Date filters grades dotyczą graded_on. Opcjonalne IDs muszą należeć do widocznego zakresu.

Nazwiska Unicode: Laravel regex z `\p{L}`, `\p{M}`, spacją, apostrofem prostym/typograficznym i łącznikiem; minimum jedna litera, nie samo `---`. Nie używać regex dla emaila, haseł ani autoryzacji. Login nie narzuca domeny szkoły. Brak publicznej rejestracji, maila z hasłem i magicznych linków.

## Typy DTO

Jedna definicja każdego typu w `resources/js/types/domain.ts` i `pages.ts`. Poniższe interfejsy są kompletnym minimum kontraktu. Typy transportu są jawne, a nie duplikowane per rola.

```ts
type Id = string;
type ISODate = string;
type ISODateTime = string;
type Role = 'student' | 'teacher' | 'admin';
type AccountStatus = 'active' | 'inactive';
type Average = string | null;
interface Option { id: Id; label: string }
interface NavigationItem { label: string; href: string; active: boolean }
interface AuthUser { id: Id; firstName: string; lastName: string; email: string; role: Role }
interface Capabilities { createGrade: boolean; manageUsers: boolean; manageSchool: boolean }
interface SharedProps {
  auth: { user: AuthUser | null; role: Role | null };
  can: Capabilities;
  navigation: NavigationItem[];
  errors: Record<string, string>;
  flash: { success?: string; error?: string };
}
interface PageLink { label: string; url: string | null; active: boolean }
interface Page<T> {
  data: T[];
  meta: { currentPage: number; perPage: number; total: number; lastPage: number };
  links: PageLink[];
}
interface Filters {
  q?: string; page: number; perPage: 10 | 25 | 50;
  sort: string; direction: 'asc' | 'desc';
  termId?: Id; subjectId?: Id; categoryId?: Id; schoolClassId?: Id;
  studentId?: Id; teacherId?: Id; academicYearId?: Id; gradeId?: Id;
  studentEnrollmentId?: Id; selectedClassId?: Id; enrollmentPage?: number;
  dateFrom?: ISODate; dateTo?: ISODate;
  role?: Role; status?: AccountStatus | 'deleted';
  action?: 'create' | 'update' | 'delete'; archived?: boolean; revoked?: boolean;
  assessmentPage?: number;
}
interface GradeContext {
  assignmentId: Id | null; schoolClass: Option; subject: Option; term: Option;
  closed: boolean;
}
interface StudentSummary { id: Id; enrollmentId: Id; name: string; historical: boolean }
interface AssessmentDTO {
  id: Id; topic: string; category: Option; defaultWeight: number; gradedOn: ISODate;
}
interface GradeDTO {
  id: Id; student: StudentSummary; assessmentId: Id; context: GradeContext;
  value: 1 | 2 | 3 | 4 | 5 | 6; weight: 1 | 2 | 3 | 4 | 5;
  category: Option; topic: string; comment: string | null; gradedOn: ISODate;
  issuedBy: Option; createdAt: ISODateTime; updatedAt: ISODateTime;
  deletedAt: ISODateTime | null; version: number;
  differsFromAssessment: boolean;
  can: { update: boolean; delete: boolean; viewHistory: boolean };
}
interface GradeSnapshot {
  schema_version: 1; id: Id; student_enrollment_id: Id; student_id: Id;
  assessment_id: Id; school_class_id: Id; subject_id: Id; term_id: Id;
  category_id: Id; value: number; weight: number; topic: string;
  comment: string | null; graded_on: ISODate; issued_by: Id;
  version: number; deleted_at: ISODateTime | null;
}
interface AuditDTO {
  id: Id; gradeId: Id; actor: Option; action: 'create' | 'update' | 'delete';
  occurredAt: ISODateTime; oldValues: GradeSnapshot | null;
  newValues: GradeSnapshot; reason: string | null;
}
interface GradeSummary { average: Average; count: number; scopeLabel: string }
interface GradeOptions { terms: Option[]; subjects: Option[]; categories: Option[] }
interface UserDTO extends AuthUser { status: AccountStatus; deletedAt: ISODateTime | null; lastLoginAt: ISODateTime | null }
interface EnrollmentDTO { id: Id; student: Option; schoolClass: Option; startsOn: ISODate; endsOn: ISODate | null }
interface AssignmentDTO { id: Id; teacher: Option; schoolClass: Option; subject: Option; term: Option; revokedAt: ISODateTime | null }
interface ClassDTO { id: Id; name: string; academicYear: Option; deletedAt: ISODateTime | null }
interface SubjectDTO { id: Id; name: string; deletedAt: ISODateTime | null }
interface UserOptions { roles: Role[]; classes: Option[]; subjects: Option[]; terms: Option[] }
interface SubjectSummary { subject: Option; average: Average; count: number }
interface LoginProps extends SharedProps { }
interface ErrorProps extends SharedProps {
  status: 403 | 404 | 419 | 429 | 500 | 503; message: string;
  correlationId?: string; retryAfter?: number;
}
interface ProfileProps extends SharedProps { user: AuthUser }
interface StudentDashboardProps extends SharedProps {
  term: Option | null; terms: Option[]; summary: GradeSummary; subjects: SubjectSummary[]; recentGrades: GradeDTO[];
}
interface TeacherDashboardProps extends SharedProps {
  term: Option | null; terms: Option[]; assignmentCount: number; ungradedCount: number;
  atRiskCount: number; recentGrades: GradeDTO[];
}
interface AdminDashboardProps extends SharedProps {
  term: Option | null; terms: Option[]; userCounts: Record<Role, number>; inactiveCount: number;
  classCount: number; subjectCount: number; recentAudits: AuditDTO[];
}
interface GradeListProps extends SharedProps { grades: Page<GradeDTO>; summary: GradeSummary; filters: Filters; options: GradeOptions }
interface GradeDetailProps extends SharedProps { grade: GradeDTO; summary: GradeSummary; historyUrl: string }
interface HistoryProps extends SharedProps { audits: Page<AuditDTO>; filters: Filters; options: GradeOptions; grade: GradeDTO | null }
interface ClassListProps extends SharedProps { assignments: Page<AssignmentDTO>; filters: Filters; terms: Option[] }
interface GradebookRow { student: StudentSummary; grades: GradeDTO[]; deletedAssessmentIds: Id[]; summary: GradeSummary }
interface GradebookProps extends SharedProps {
  context: GradeContext; assessments: Page<AssessmentDTO>; students: Page<GradebookRow>;
  filters: Filters; categories: Option[];
}
interface StudentDetailProps extends SharedProps { student: StudentSummary; context: GradeContext; grades: Page<GradeDTO>; summary: GradeSummary; historyUrl: string }
interface GradeFormProps extends SharedProps {
  grade: GradeDTO | null; context: GradeContext; assessment: AssessmentDTO | null;
  students: StudentSummary[]; assessments: AssessmentDTO[]; categories: Option[];
  background: GradebookProps; submitUrl: string; cancelUrl: string;
}
interface UserListProps extends SharedProps { users: Page<UserDTO>; filters: Filters; options: UserOptions }
interface UserDetailProps extends SharedProps { user: UserDTO; enrollments: EnrollmentDTO[]; assignments: AssignmentDTO[] }
interface UserFormProps extends SharedProps { user: UserDTO | null; options: UserOptions }
interface ClassManagementProps extends SharedProps { classes: Page<ClassDTO>; filters: Filters; years: Option[]; selectedClass: ClassDTO | null; enrollments: Page<EnrollmentDTO> | null; students: Option[] }
interface SubjectManagementProps extends SharedProps { subjects: Page<SubjectDTO>; filters: Filters }
interface AssignmentManagementProps extends SharedProps { assignments: Page<AssignmentDTO>; filters: Filters; teachers: Option[]; classes: Option[]; subjects: Option[]; terms: Option[] }
```

GradeFormProps: opcje ograniczone do jednego autoryzowanego kontekstu; uczniowie max50, assessments max50, przy większej liczbie wybór kontekstu na stronicowanym dzienniku (wymagany enrollment_id/assessment_id), nie pobieranie całej szkoły. Gradebook stronicuje uczniów po 10 i assessments po 10; średnia całego kontekstu, nie tylko 10 kolumn. GradeDTO na rosterze nie duplikuje danych konta (brak emaila). `grades` pomija deleted; `deletedAssessmentIds` oznacza usunięte komórki w aktualnie widocznych kolumnach, żeby UI nie oferował ponownego create. Link historii pozostaje dostępny.

Dashboard recentGrades/recentAudits max5, subjects max100 w ramach jednej szkoły; brak aktywnego termu daje puste tablice. Szczegóły użytkownika relacje max100; jeśli dane przekroczą limit, rozszerzyć kontrakt o Page zamiast obcinać po cichu. Opcje zarządzania admin w MVP max500 rekordów na słownik; większy rozmiar wymaga stronicowanego wyboru Inertia, nie nieudokumentowanego endpointu JSON.

ClassFilterRequest selected_class_id/enrollment_page mapują się na Filters.selectedClassId/enrollmentPage; wybór klasy zasila selectedClass/enrollments. HistoryFilterRequest student_enrollment_id mapuje się na studentEnrollmentId i zawęża historię linkowaną ze szczegółów ucznia. Admin students w ClassManagementProps zawiera tylko aktywne konta student (limit słownika jak wyżej). Żadne pole nie wybiera kontekstu poza uprawnieniami.

SharedProps nie są współdzielonym cache między użytkownikami. Partial reload ma ponownie autoryzować rekord i opcje. Payload historii jest projekcją allowlisty, nie dowolnym JSON. Rozróżniaj brak danych (`[]`, null) od braku uprawnień (403/404), nigdy nie wysyłaj danych i nie ukrywaj ich tylko CSS.
