# Schemat relacyjny — F-003, do przeglądu

MariaDB 11.4, InnoDB, utf8mb4_unicode_ci. `id` = BIGINT UNSIGNED PK auto increment; wszystkie FK ten sam typ. Każda tabela domenowa poza audytem ma created_at/updated_at (UTC, mikrosekundy). `?` oznacza NULL. Daty kalendarzowe DATE, pieniężnych/float pól brak. Unikalności obejmują rekordy usunięte, o ile nie zaznaczono inaczej. Brak fizycznych cascade delete danych domenowych.

## Tabele

| Tabela | Kolumny poza id i timestamps | Ograniczenia / indeksy |
|---|---|---|
| users | first_name varchar(80), last_name varchar(80), email varchar(254), password varchar(255), role varchar(16), status varchar(16), last_login_at datetime(6)?, deleted_at datetime(6)? | UQ email (lowercase); CHECK role student/teacher/admin; CHECK status active/inactive; IDX(role,status,deleted_at), IDX(last_name,first_name,id) |
| academic_years | name varchar(9), starts_on date, ends_on date | UQ name; CHECK starts_on ≤ ends_on; brak nakładania lat kontrolowany w usłudze |
| terms | academic_year_id FK, number tinyint unsigned, starts_on date, ends_on date, closed_at datetime(6)? | UQ(year,number); CHECK number IN(1,2); CHECK starts_on ≤ ends_on |
| school_classes | academic_year_id FK, name varchar(20), deleted_at datetime(6)? | UQ(year,name); IDX(year,deleted_at) |
| subjects | name varchar(100), deleted_at datetime(6)? | UQ name |
| student_enrollments | student_id FK users, school_class_id FK, starts_on date, ends_on date? | UQ(student_id,starts_on); CHECK ends_on IS NULL OR ends_on ≥ starts_on; IDX(class,starts_on,ends_on); IDX(student,starts_on,ends_on) |
| teacher_assignments | teacher_id FK users, school_class_id FK, subject_id FK, term_id FK, revoked_at datetime(6)? | UQ(teacher,class,subject,term); IDX(teacher,revoked_at,term), IDX(class,subject,term,revoked_at) |
| grade_categories | name varchar(60), default_weight tinyint unsigned, is_active boolean | UQ name; CHECK default_weight BETWEEN 1 AND 5 |
| assessments | school_class_id FK, subject_id FK, term_id FK, category_id FK grade_categories, topic varchar(120), default_weight tinyint unsigned, graded_on date, created_by FK users, deleted_at datetime(6)? | CHECK default_weight 1..5; IDX(class,subject,term,graded_on,id) |
| grades | student_enrollment_id FK, assessment_id FK, category_id FK grade_categories, value tinyint unsigned, weight tinyint unsigned, topic varchar(120), comment varchar(500)?, graded_on date, issued_by FK users, version int unsigned default 1, deleted_at datetime(6)? | UQ(enrollment,assessment); CHECK value 1..6; CHECK weight 1..5; CHECK version ≥1; IDX(assessment,deleted_at,graded_on,id), IDX(enrollment,deleted_at,graded_on,id) |
| grade_audits | grade_id FK, actor_id FK users, action varchar(8), occurred_at datetime(6), old_values JSON?, new_values JSON?, reason varchar(500)?, grade_version int unsigned | UQ(grade_id,grade_version); IDX(grade_id,occurred_at,id); IDX(occurred_at,id); CHECK action create/update/delete; brak updated_at |
| domain_locks | key varchar(64) zamiast liczbowego id | PK key; stałe wiersze admin_membership i calendar_configuration; nie zawiera danych produktu |

`grade_audits` nie ma zwykłych created_at/updated_at — czas zdarzenia to occurred_at. CHECK audytu: create → old NULL/new NOT NULL; update → obie NOT NULL; delete → obie NOT NULL, new.deleted_at ustawione. reason wymagane dla update/delete, NULL dla create. JSON ma schema_version=1 i pola z [BUSINESS_RULES](BUSINESS_RULES.md).

Każda wymieniona FK to jawne `ON DELETE RESTRICT`, `ON UPDATE RESTRICT`, NOT NULL. Usunięcie logiczne zachowuje wszystkie FK. Models: User, AcademicYear, Term, SchoolClass, Subject, StudentEnrollment, TeacherAssignment, GradeCategory, Assessment, Grade, GradeAudit. `User::enrollments/assignments/issuedGrades`, `Assessment::grades`, `Grade::audits`, pozostałe belongsTo zgodnie z tabelą.

## Co zapewnia aplikacja, a nie zwykły FK

- enrollment.student_id ma rolę student; assignment.teacher_id ma rolę teacher; issued_by jest uprawnionym teacher/admin w chwili zapisu.
- Daty termu zawarte w roku; dwa semestry nie nakładają się; rok klasy = rok termu assignment/assessment.
- Brak nakładania enrollmentów jednego ucznia (również pomiędzy klasami), z przedziałami domkniętymi.
- Klasa enrollmentu oceny = klasa assessmentu; graded_on mieści się w enrollment i termie. Przedmiot, klasa, okres oceny są wyprowadzane przez assessment, uczeń przez enrollment; **brak users.class_id**.
- Nie zmieniać kontekstu assessmentu po utworzeniu. Zmiana grade.category/weight/topic/date dotyczy pojedynczej oceny, nie nagłówka kolumny.

Te invariants wymagają blokad i transakcji oraz testów MariaDB; nie należy twierdzić, że pojedyncze FK sprawdzają zależności między wierszami. Tworzenie enrollmentu blokuje User; assignment i assessment blokują Term oraz kontekst. [Kolejność blokad](../architecture/BACKEND.md).

## Kolumny dziennika

Assessment jest wspólną kolumną (np. Kartkówka 23.10), której puste komórki nie są oceną 0. Nagłówek ma domyślną kategorię/wagę/datę; Grade zapisuje ich snapshot i temat. Indywidualna korekta wagi/kategorii/daty/tematu nie zmienia innych uczniów; UI oznacza odstępstwo w komórce i szczegółach. Nie oblicza średniej z default_weight.

Jeden grade na enrollment+assessment, także po soft delete. Ponowne wystawienie w usuniętej komórce jest niedozwolone; utworzyć nową kolumnę. Nie projektujemy przywracania historii w MVP. Assessment można logicznie usunąć tylko jeśli nie miał żadnych ocen, także usuniętych; w przeciwnym razie odmowa. Assessment metadata po utworzeniu nieedytowalne w MVP; korekty realizuje się na ocenach.

## Tabele techniczne

`sessions`: id varchar PK, user_id nullable FK users RESTRICT, ip_address?, user_agent?, payload, last_activity (indeks); migracja standardowa z jawną decyzją FK. `cache`: key PK, value, expiration; `cache_locks`: key PK, owner, expiration — limiter i blokady frameworka. `migrations`: tabela Laravel. CACHE_STORE=database, SESSION_DRIVER=database, QUEUE_CONNECTION=sync. Nie dodawać jobs ani password_reset_tokens, gdy dana funkcja nie jest używana.

## Kolejność migracji

users + domain_locks → academic_years → terms i school_classes → subjects → enrollments i assignments → categories → assessments → grades → audits → tabele techniczne. Indeksy FK nadać jawne krótkie nazwy. Down w odwrotnej kolejności tylko dla pustego/testowego środowiska; produkcyjny rollback danych nie polega na kasowaniu historii.

## Realizacja F-003 (2026-11-15)

FACT: migracja `2026_11_15_000003_create_temporal_grade_foundation` aktualizuje istniejący schemat startera, zachowując historię migracji. Usuwa nieużywane jobs/job_batches/failed_jobs/password_reset_tokens; preflight odmawia przed DDL, jeśli zawierają dane lub nazwy/email użytkowników nie mieszczą się w nowych kolumnach. MariaDB DDL nie jest transakcyjne; błąd migracji wymaga diagnostyki przed ponowieniem, bez komunikatu gotowości.

DECISION: zachowane techniczne `users.remember_token` i `users.email_verified_at` utrzymują zgodność istniejącego Fortify i testów profilu. Nie dodają weryfikacji emaila ani resetu hasła do MVP. `name` jest akcesorem/mutatorem zgodności startera, nie kolumną; zapisuje first_name/last_name. Formularz startera kontroluje limit 80 znaków każdej części i 254 znaków emaila. Docelowe formularze F-401 użyją osobnych pól.

FACT: snapshoty audytu mają CHECK obecności 17 pól v1, typów JSON, zakresów wartości/wag, stringowych ID i kształtu create/update/delete. Audyt powstaje w tej samej transakcji co ocena. Model blokuje aktualizację/usunięcie historii; uprawnienia konta DB do produkcyjnego append-only pozostają elementem wdrożenia. Bezpośredni SQL operatora nie jest interfejsem domenowym i omija aplikacyjne reguły przekrojowe.

FACT: CalendarService serializuje konfigurację przez `domain_locks.calendar_configuration`, dodaje lata i semestry, aktualizuje kategorie, zamyka termy. Nie zmienia dat istniejących lat/termów ani nie otwiera termów ponownie. Zmiana kalendarza wymaga odrębnej migracji i analizy istniejących relacji. EnrollmentService serializuje nowe członkostwa pod User; AssignmentService/AssessmentService/GradeService blokują Term i kontekst. Brak publicznych mutacji konfiguracji. Kompletne interfejsy organizacji i ocen pozostają w ich funkcjach HTTP.

VERIFIED: migracje, rollback pustej bazy, seed, 20 FK RESTRICT, CHECK, zakresy i testy dwóch procesów na MariaDB — [raport F-003](../features/F-003.md#22-raport-realizacji-2026-11-15).
