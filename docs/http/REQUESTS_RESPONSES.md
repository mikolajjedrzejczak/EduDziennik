# Kontrakty Inertia — PLANNED

Źródło nazw tras: [ROUTES](ROUTES.md), zakres danych: [RBAC](../product/RBAC.md), invariants: [BUSINESS_RULES](../database/BUSINESS_RULES.md). To specyfikacja, nie wygenerowany kod aplikacji.

## Konwencje

Wspólny wzorzec implementacyjny Form Request/useForm, polskie komunikaty, pola i podsumowanie: [FORM_VALIDATION](FORM_VALIDATION.md) (F-402). Nie dodaje samodzielnego kontraktu strony ani produkcyjnej trasy.

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

Jedna definicja każdego typu; docelowo `resources/js/types/domain.ts` i `pages.ts`. FACT F-306: wdrożone typy organizacji są współdzielone w `resources/js/types/school.ts`. Poniższe interfejsy są kompletnym minimum kontraktu. Typy transportu są jawne, a nie duplikowane per rola.

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
interface SubjectDTO { id: Id; name: string; deletedAt: ISODateTime | null; activeAssignmentsCount: number }
interface UserOptions { roles: Role[]; classes: Option[]; subjects: Option[]; terms: Option[] }
interface SubjectSummary { subject: Option; average: Average; count: number }
interface LoginProps extends SharedProps { retryAfter?: number | null }
interface ErrorProps extends SharedProps {
  status: 403 | 404 | 419 | 429 | 500 | 503; message: string;
  correlationId?: string; retryAfter?: number; retryUrl?: string;
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
interface ClassManagementProps extends SharedProps { classes: Page<ClassDTO>; filters: Filters; years: Option[]; selectedClass: ClassDTO | null; enrollments: Page<EnrollmentDTO> | null; students: Option[]; classOptions: Option[] }
interface SubjectManagementProps extends SharedProps { subjects: Page<SubjectDTO>; filters: Filters & { archived: boolean } }
interface AssignmentManagementProps extends SharedProps { assignments: Page<AssignmentDTO>; filters: Filters; teachers: Option[]; classes: Option[]; subjects: Option[]; terms: Option[] }
```

GradeFormProps: opcje ograniczone do jednego autoryzowanego kontekstu; uczniowie max50, assessments max50, przy większej liczbie wybór kontekstu na stronicowanym dzienniku (wymagany enrollment_id/assessment_id), nie pobieranie całej szkoły. Gradebook stronicuje uczniów po 10 i assessments po 10; średnia całego kontekstu, nie tylko 10 kolumn. GradeDTO na rosterze nie duplikuje danych konta (brak emaila). `grades` pomija deleted; `deletedAssessmentIds` oznacza usunięte komórki w aktualnie widocznych kolumnach, żeby UI nie oferował ponownego create. Link historii pozostaje dostępny.

Dashboard recentGrades/recentAudits max5, subjects max100 w ramach jednej szkoły; brak aktywnego termu daje puste tablice. Szczegóły użytkownika relacje max100; jeśli dane przekroczą limit, rozszerzyć kontrakt o Page zamiast obcinać po cichu. Opcje zarządzania admin w MVP max500 rekordów na słownik; większy rozmiar wymaga stronicowanego wyboru Inertia, nie nieudokumentowanego endpointu JSON.

ClassFilterRequest selected_class_id/enrollment_page mapują się na Filters.selectedClassId/enrollmentPage; wybór klasy zasila selectedClass/enrollments. HistoryFilterRequest student_enrollment_id mapuje się na studentEnrollmentId i zawęża historię linkowaną ze szczegółów ucznia. Admin students w ClassManagementProps zawiera tylko aktywne konta student (limit słownika jak wyżej). Żadne pole nie wybiera kontekstu poza uprawnieniami.

SharedProps nie są współdzielonym cache między użytkownikami. Partial reload ma ponownie autoryzować rekord i opcje. Payload historii jest projekcją allowlisty, nie dowolnym JSON. Rozróżniaj brak danych (`[]`, null) od braku uprawnień (403/404), nigdy nie wysyłaj danych i nie ukrywaj ich tylko CSS.

### Stan integracji F-004

FACT: logowanie i wylogowanie używają powyższych tras i walidacji. `errors.form` zawiera neutralny błąd uwierzytelnienia; 429 zachowuje status i nagłówek Retry-After, dodaje `retryAfter` w sekundach. 419 renderuje formularz z instrukcją ponownego logowania, bez automatycznego POST. W obu odpowiedziach `auth.user` i `auth.role` są null. Login zawsze prowadzi do `/dashboard`, ignoruje url.intended i dane klienta określające rolę/cel. Rozdzielenie pulpitów wdraża F-007 (poniżej). Istniejący starter korzysta dodatkowo z allowlistowanego `auth.user.name` (firstName + lastName); id jest stringiem.

DECISION F-004: limiter działa w LoginService po walidacji LoginRequest; zlicza nieudane uwierzytelnienia, a nie wszystkie wywołania HTTP. Nie rejestrujemy dodatkowego `throttle:login`, który podwójnie naliczałby próby.

### Stan integracji F-005

FACT: SharedProps zawierają `can: { createGrade, manageUsers, manageSchool }`, obliczane przez Gate dla bieżącego konta. `createGrade` korzysta z `GradePolicy.createAny` i oznacza wyłącznie aktywną rolę teacher/admin; nie dowodzi istnienia assignment ani spełnienia W. `GradePolicy.create` bez assessment/enrollment odmawia dostępu. Formularz musi dodatkowo wykonać `GradePolicy.create` z assessment, enrollment i datą, a serwis ponawia autoryzację w transakcji. Nie używać `can` z klienta jako wejścia autoryzacji.

FACT: `GradeAccess` dostarcza scoped lookup oceny (jawny tryb archiwalny), historię, autoryzowany assignment i query opcji enrollment/assessment jednego kontekstu. Wynikowe query należy filtrować przed `count`, agregacją i `paginate`; modele pozostają wewnątrz backendu, kontroler nadal buduje DTO z allowlisty. `capabilities(actor, grade)` zwraca flagi rekordowe dopiero po autoryzacji odczytu. Roster nie uprawnia do innych przedmiotów ucznia; oceny i historia wymagają własnego scope.

DECISION: F-005 nie dodaje strony, produkcyjnych tras ani nowego payloadu ocen. Kontrolery funkcji dziennika wdrażają ten mechanizm przy realizacji swoich kontraktów. Domyślne 403/404 Laravel pozostają do prezentacji przez F-404; nie deklarujemy wdrożenia `shared/error`.

### Stan integracji F-404

FACT: `bootstrap/app.php` renderuje `shared/error` (`App\Http\Responses\ErrorPage`) dla 403/404/500/503 żądań nie-JSON; komunikaty w `lang/pl/errors.php`, bez treści wyjątku i stack trace. 500 dodaje `correlationId` (UUID, logowany bez danych formularza); w trybie debug 500 pozostaje stroną diagnostyczną frameworka. DECISION: `retryUrl` (ścieżka i query żądania) jest ustawiane wyłącznie dla 503 na GET; po mutacji klient nie oferuje ponowienia, tylko prosi o sprawdzenie stanu danych. 419/429 pozostają na `auth/login` (F-004). `shared/error` renderuje się bez AppLayout. Offline: `OfflineNotice` (aria-live) i wyłączony `SubmitButton` bez sieci; nic nie jest automatycznie ponawiane.

### Stan integracji F-007

FACT: `/dashboard` zwraca 302 do pulpitu roli (`student.dashboard` → `/student`, `teacher.dashboard` → `/teacher`, `admin.dashboard` → `/admin`); pulpity renderują `student|teacher|admin/dashboard` bez danych domenowych (props tylko shared). Obca rola: 403. SharedProps zawierają `navigation: { primary, secondary } | null`, gdzie element to `{ key, label, shortLabel, href, icon }` (`icon` ∈ `dashboard|profile|classes|subjects`), budowane wyłącznie na serwerze i tylko dla istniejących tras. `sidebarOpen` usunięto.


### Stan integracji F-306

FACT: sześć tras klas/członkostw z ROUTES jest wdrożonych, wyłącznie dla administratora. GET autoryzuje również opcje i wybraną klasę; `selected_class_id` musi należeć do roku i statusu archiwum listy (404 w razie niezgodności). Wyszukiwanie nie zmienia zakresu wybranej klasy. Bez roku/termu lista obejmuje wszystkie lata; `term_id` bez `academic_year_id` wyznacza rok. Mutacje przekierowują do listy z rokiem i wybraną klasą, archiwizacja do listy aktywnych klas danego roku.

DECISION: `ClassManagementProps.classOptions: Option[]` to dodatkowy słownik aktywnych klas (`id`, etykieta `nazwa · rok`) do wyboru celu transferu, niezależny od paginacji listy. `students` zawiera tylko aktywne, nieusunięte konta student i wyłącznie `id/label`. Słowniki uczniów i klas mają limit 500; pobranie 501 wykrywa przekroczenie i zwraca 503, bez cichego ucinania. Rozwinięcie selektorów dla większej szkoły pozostaje poza MVP.

FACT: transfer jest POST do klasy docelowej, z `student_id`, `starts_on` (dzień D), opcjonalnym `ends_on` i `transfer_from_enrollment_id`. Źródło musi należeć do ucznia, obejmować D, zaczynać się przed D i być inną klasą. Daty źródła kończą się D−1. Oceny i audyty zachowują identyfikatory; ochrona zakresu dat uwzględnia także soft-deleted grades. PATCH służy korekcie dat/zakończeniu; nie zmienia ucznia ani klasy, dopuszcza zakończenie członkostwa nieaktywnego ucznia. Zarchiwizowana klasa ma odczyt historii bez mutacji.

FACT: nawigacja administratora dodaje `classes` (`Klasy i przedmioty`, mobilnie `Klasy`) oraz ikonę `classes`. Wspólne props `flash` udostępniają wyłącznie `success/error` z sesji. Pozostałe sekcje organizacji dodają własne funkcje F-307/F-308.

### Stan integracji F-302

FACT: `UserListProps = { users: Page<UserDTO & { relationSummary: string }>, filters, options: { roles, classes, terms } }` — `classes`/`terms` to `Option & { academicYearId }` (max 500, klasy także zarchiwizowane), bez `subjects`. `filters`: `q, role, status, schoolClassId, termId, page, perPage, sort, direction` (domyślnie 10, `last_name asc`, tie-break `id`). Klasa wymaga semestru tego samego roku (`term_id` required_with, exists z kontrolą roku); błąd → 303 na `/admin/users` bez błędnych parametrów. `UserDetailProps` dodaje `relationTotals { enrollments, assignments }`; relacje max 100, a przy przekroczeniu UI pokazuje „Wyświetlono N z M” (nie obcina po cichu). `status=deleted` = konta soft-deleted; domyślnie ukryte.

### Stan integracji F-307

FACT: `admin.subjects.{index,store,update,destroy}` (`Admin\SubjectController`, middleware `role:admin`, Form Requests `SubjectFilterRequest`, `StoreSubjectRequest`, `UpdateSubjectRequest`, `DeleteSubjectRequest`, logika w `SubjectService`). `SubjectDTO` dodaje informacyjne `activeAssignmentsCount` (niecofnięte przydziały); to podpowiedź UI, nie autoryzacja — archiwizację rozstrzyga serwis pod blokadą (`errors.form`, bez zapisu). `filters` dodatkowo niesie `archived` (true = tylko zarchiwizowane; domyślnie aktywne). `per_page` ∈ 10/25/50 (domyślnie 25); `sort` wyłącznie `name`. Błędny filtr GET → 303 do tej samej trasy bez błędnych parametrów + `errors`; strona poza zakresem → przekierowanie na ostatnią stronę. Nazwa: trim, 1–100, unikalna także wśród zarchiwizowanych i bez rozróżniania wielkości liter (collation MariaDB + indeks UNIQUE; wyścig daje błąd pola `name`). Zarchiwizowany przedmiot nie jest edytowalny ani ponownie archiwizowany (404); przywracanie nie należy do zakresu. Sukces: redirect do `admin.subjects.index` z flash `toast` (`type`, `message`) renderowanym przez `Toaster`. `NavigationService` dodaje adminowi wpis „Przedmioty” (`icon: subjects`).

### Stan integracji F-308

FACT: `admin.assignments.{index,store,update,destroy}` (`Admin\TeacherAssignmentController`, `role:admin`, Form Requests `AssignmentFilterRequest`, `StoreAssignmentRequest`, `UpdateAssignmentRequest`, `DeleteAssignmentRequest`, logika w `AssignmentService`). `AssignmentManagementProps.filters` niesie `teacherId`, `schoolClassId`, `subjectId`, `termId` (string|null), `revoked` (true = tylko cofnięte; domyślnie aktywne), `page`, `perPage` (10/25/50, domyślnie 10), `sort` = `id`, `direction`. Opcje `teachers` (tylko aktywni, `id/label` bez e-maili), `classes` (aktywne, `nazwa · rok`), `subjects` (aktywne) i `terms` (otwarte, `Semestr N · rok`) mają limit 500; przekroczenie daje 503, bez cichego ucinania. Błędny filtr GET → 303 do tej samej trasy bez błędnych parametrów + `errors`; strona poza zakresem → ostatnia strona.

FACT: `store` przyjmuje `teacher_id`, `school_class_id`, `subject_id`, `term_id`; spójność (aktywny teacher, aktywna klasa/przedmiot, semestr z roku klasy, otwarty) jest ponownie sprawdzana w `AssignmentService` pod blokadami (błędy pól `teacher_id`/`school_class_id`/`subject_id`/`term_id`). Istniejąca czwórka jest reaktywowana, nie duplikowana. `update` przyjmuje wyłącznie `revoked` (true = cofnij, false = przywróć z tymi samymi regułami co nadanie); obce pola są ignorowane. `destroy` wymaga `confirmed` i ustawia `revoked_at`; cofnięcie działa także w zamkniętym semestrze i jest idempotentne. Sukces: redirect do `admin.assignments.index` z flash `toast`.
