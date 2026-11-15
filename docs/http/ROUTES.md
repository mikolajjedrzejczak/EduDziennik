# Trasy Laravel — PLANNED

Wszystkie poniższe rekordy są kontraktem przyszłych tras, nie wynikiem `route:list`. Każda trasa ma middleware `web` (sesja, CSRF dla mutacji, Inertia). `auth` w kolumnie dostępu oznacza dodatkowo `active`; rola student/teacher/admin oznacza `auth + active + role:<rola>`. Wyjątek `/up`: publiczny health frameworka, bez danych sesji. Rejestruj statyczne `/create` przed `{grade}`/`{user}` i ogranicz parametry ID do cyfr.

Dla GET bez Form Request wejście to tylko parametry ścieżki; brak body. Dla pozostałych pełne wejście/walidacja w słowniku Request w [REQUESTS_RESPONSES](REQUESTS_RESPONSES.md). Wynik w tabeli wskazuje kompletny typ props z tego samego dokumentu. Każda trasa dziedziczy zasady błędów poniżej, nie ma domyślnego JSON API.

## Macierz tras

| HTTP | URL | Nazwa | Controller/action | Middleware / zakres | Form Request | Wynik | Feature |
|---|---|---|---|---|---|---|---|
| GET | /login | login | Auth/SessionController@create | guest | — | auth/login; LoginProps | F-004 |
| POST | /login | login.store | Auth/SessionController@store | guest + limiter LoginService | LoginRequest | redirect dashboard | F-004 |
| POST | /logout | logout | Auth/SessionController@destroy | auth | — | redirect login | F-004 |
| GET | /dashboard | dashboard | DashboardController@index | auth | — | redirect wg roli | F-007 |
| GET | /profile | profile.edit | ProfileController@edit | auth | — | settings/profile; ProfileProps | F-401 |
| PATCH | /profile | profile.update | ProfileController@update | auth | UpdateProfileRequest | redirect profile.edit | F-401 |
| PUT | /profile/password | profile.password.update | ProfilePasswordController@update | auth + throttle:password | UpdatePasswordRequest | redirect profile.edit | F-401 |
| GET | /student | student.dashboard | Student/DashboardController@index | student | DashboardFilterRequest | student/dashboard; StudentDashboardProps | F-101 |
| GET | /teacher | teacher.dashboard | Teacher/DashboardController@index | teacher | DashboardFilterRequest | teacher/dashboard; TeacherDashboardProps | F-201 |
| GET | /admin | admin.dashboard | Admin/DashboardController@index | admin | DashboardFilterRequest | admin/dashboard; AdminDashboardProps | F-301 |
| GET | /student/grades | student.grades.index | Student/GradeController@index | student | GradeFilterRequest | student/grades/index; GradeListProps | F-102 |
| GET | /student/grades/{grade} | student.grades.show | Student/GradeController@show | student | — | shared/grades/show; GradeDetailProps | F-103 |
| GET | /student/history | student.history.index | Student/GradeHistoryController@index | student | HistoryFilterRequest | shared/history/index; HistoryProps | F-104 |
| GET | /student/grades/{grade}/history | student.grades.history | Student/GradeHistoryController@show | student | HistoryFilterRequest | shared/history/index; HistoryProps | F-104 |
| GET | /teacher/grades | teacher.grades.index | Teacher/GradeController@index | teacher | GradeFilterRequest | teacher/grades/index; GradeListProps | F-203 |
| GET | /teacher/grades/{grade} | teacher.grades.show | Teacher/GradeController@show | teacher | — | shared/grades/show; GradeDetailProps | F-103 |
| GET | /teacher/history | teacher.history.index | Teacher/GradeHistoryController@index | teacher | HistoryFilterRequest | shared/history/index; HistoryProps | F-104 |
| GET | /teacher/grades/{grade}/history | teacher.grades.history | Teacher/GradeHistoryController@show | teacher | HistoryFilterRequest | shared/history/index; HistoryProps | F-104 |
| GET | /admin/grades | admin.grades.index | Admin/GradeController@index | admin | GradeFilterRequest | admin/grades/index; GradeListProps | F-203 |
| GET | /admin/grades/{grade} | admin.grades.show | Admin/GradeController@show | admin | — | shared/grades/show; GradeDetailProps | F-103 |
| GET | /admin/history | admin.history.index | Admin/GradeHistoryController@index | admin | HistoryFilterRequest | shared/history/index; HistoryProps | F-104 |
| GET | /admin/grades/{grade}/history | admin.grades.history | Admin/GradeHistoryController@show | admin | HistoryFilterRequest | shared/history/index; HistoryProps | F-104 |
| GET | /teacher/classes | teacher.gradebooks.index | Teacher/GradebookController@index | teacher | ClassFilterRequest | teacher/gradebooks/index; ClassListProps | F-202 |
| GET | /teacher/gradebooks/{assignment} | teacher.gradebooks.show | Teacher/GradebookController@show | teacher | GradebookFilterRequest | shared/gradebooks/show; GradebookProps | F-203 |
| GET | /teacher/gradebooks/{assignment}/students/{enrollment} | teacher.students.show | Teacher/StudentController@show | teacher | GradebookFilterRequest | shared/students/show; StudentDetailProps | F-203 |
| POST | /teacher/gradebooks/{assignment}/assessments | teacher.assessments.store | Teacher/AssessmentController@store | teacher | StoreAssessmentRequest | redirect role.gradebooks.show | F-203 |
| DELETE | /teacher/assessments/{assessment} | teacher.assessments.destroy | Teacher/AssessmentController@destroy | teacher | DeleteAssessmentRequest | redirect role.gradebooks.show | F-203 |
| GET | /teacher/grades/create | teacher.grades.create | Teacher/GradeController@create | teacher | GradeFormContextRequest | shared/grades/create; GradeFormProps | F-204 |
| POST | /teacher/grades | teacher.grades.store | Teacher/GradeController@store | teacher | StoreGradeRequest | redirect role.gradebooks.show | F-204 |
| GET | /teacher/grades/{grade}/edit | teacher.grades.edit | Teacher/GradeController@edit | teacher | — | shared/grades/edit; GradeFormProps | F-205 |
| PATCH | /teacher/grades/{grade} | teacher.grades.update | Teacher/GradeController@update | teacher | UpdateGradeRequest | redirect role.grades.show | F-205 |
| DELETE | /teacher/grades/{grade} | teacher.grades.destroy | Teacher/GradeController@destroy | teacher | DeleteGradeRequest | redirect role.gradebooks.show | F-206 |
| GET | /admin/gradebooks | admin.gradebooks.index | Admin/GradebookController@index | admin | ClassFilterRequest | admin/gradebooks/index; ClassListProps | F-203 |
| GET | /admin/gradebooks/{assignment} | admin.gradebooks.show | Admin/GradebookController@show | admin | GradebookFilterRequest | shared/gradebooks/show; GradebookProps | F-203 |
| GET | /admin/gradebooks/{assignment}/students/{enrollment} | admin.students.show | Admin/StudentController@show | admin | GradebookFilterRequest | shared/students/show; StudentDetailProps | F-203 |
| POST | /admin/gradebooks/{assignment}/assessments | admin.assessments.store | Admin/AssessmentController@store | admin | StoreAssessmentRequest | redirect role.gradebooks.show | F-203 |
| DELETE | /admin/assessments/{assessment} | admin.assessments.destroy | Admin/AssessmentController@destroy | admin | DeleteAssessmentRequest | redirect role.gradebooks.show | F-203 |
| GET | /admin/grades/create | admin.grades.create | Admin/GradeController@create | admin | GradeFormContextRequest | shared/grades/create; GradeFormProps | F-204 |
| POST | /admin/grades | admin.grades.store | Admin/GradeController@store | admin | StoreGradeRequest | redirect role.gradebooks.show | F-204 |
| GET | /admin/grades/{grade}/edit | admin.grades.edit | Admin/GradeController@edit | admin | — | shared/grades/edit; GradeFormProps | F-205 |
| PATCH | /admin/grades/{grade} | admin.grades.update | Admin/GradeController@update | admin | UpdateGradeRequest | redirect role.grades.show | F-205 |
| DELETE | /admin/grades/{grade} | admin.grades.destroy | Admin/GradeController@destroy | admin | DeleteGradeRequest | redirect role.gradebooks.show | F-206 |
| GET | /admin/users | admin.users.index | Admin/UserController@index | admin | UserFilterRequest | admin/users/index; UserListProps | F-302 |
| GET | /admin/users/create | admin.users.create | Admin/UserController@create | admin | — | admin/users/create; UserFormProps | F-303 |
| POST | /admin/users | admin.users.store | Admin/UserController@store | admin | StoreUserRequest | redirect admin.users.show | F-303 |
| GET | /admin/users/{user} | admin.users.show | Admin/UserController@show | admin | — | admin/users/show; UserDetailProps | F-302 |
| GET | /admin/users/{user}/edit | admin.users.edit | Admin/UserController@edit | admin | — | admin/users/edit; UserFormProps | F-304 |
| PATCH | /admin/users/{user} | admin.users.update | Admin/UserController@update | admin | UpdateUserRequest | redirect admin.users.show | F-304 |
| DELETE | /admin/users/{user} | admin.users.destroy | Admin/UserController@destroy | admin | DeleteUserRequest | redirect admin.users.index | F-305 |
| GET | /admin/classes | admin.classes.index | Admin/SchoolClassController@index | admin | ClassFilterRequest | admin/classes/index; ClassManagementProps | F-306 |
| POST | /admin/classes | admin.classes.store | Admin/SchoolClassController@store | admin | StoreClassRequest | redirect admin.classes.index | F-306 |
| PATCH | /admin/classes/{schoolClass} | admin.classes.update | Admin/SchoolClassController@update | admin | UpdateClassRequest | redirect admin.classes.index | F-306 |
| DELETE | /admin/classes/{schoolClass} | admin.classes.destroy | Admin/SchoolClassController@destroy | admin | DeleteClassRequest | redirect admin.classes.index | F-306 |
| GET | /admin/subjects | admin.subjects.index | Admin/SubjectController@index | admin | SubjectFilterRequest | admin/subjects/index; SubjectManagementProps | F-307 |
| POST | /admin/subjects | admin.subjects.store | Admin/SubjectController@store | admin | StoreSubjectRequest | redirect admin.subjects.index | F-307 |
| PATCH | /admin/subjects/{subject} | admin.subjects.update | Admin/SubjectController@update | admin | UpdateSubjectRequest | redirect admin.subjects.index | F-307 |
| DELETE | /admin/subjects/{subject} | admin.subjects.destroy | Admin/SubjectController@destroy | admin | DeleteSubjectRequest | redirect admin.subjects.index | F-307 |
| GET | /admin/assignments | admin.assignments.index | Admin/TeacherAssignmentController@index | admin | AssignmentFilterRequest | admin/assignments/index; AssignmentManagementProps | F-308 |
| POST | /admin/assignments | admin.assignments.store | Admin/TeacherAssignmentController@store | admin | StoreAssignmentRequest | redirect admin.assignments.index | F-308 |
| PATCH | /admin/assignments/{assignment} | admin.assignments.update | Admin/TeacherAssignmentController@update | admin | UpdateAssignmentRequest | redirect admin.assignments.index | F-308 |
| DELETE | /admin/assignments/{assignment} | admin.assignments.destroy | Admin/TeacherAssignmentController@destroy | admin | DeleteAssignmentRequest | redirect admin.assignments.index | F-308 |
| POST | /admin/classes/{schoolClass}/enrollments | admin.enrollments.store | Admin/EnrollmentController@store | admin | StoreEnrollmentRequest | redirect admin.classes.index | F-306 |
| PATCH | /admin/classes/{schoolClass}/enrollments/{enrollment} | admin.enrollments.update | Admin/EnrollmentController@update | admin | UpdateEnrollmentRequest | redirect admin.classes.index | F-306 |
| GET | /up | health | HealthController | public | — | HTTP 200/503 + SELECT 1; bez sesji i danych domeny | F-002 / F-503 |

## Uprawnienia i błędy odziedziczone przez każdą trasę

- `student.*`: GradePolicy scope S; dashboard i lista agregują S; historia obejmuje własne soft-deleted grades.
- `teacher.*`: GradePolicy/AssessmentPolicy scope T; gradebook assignment musi należeć do nauczyciela i nie być cofnięty; student enrollment musi być w klasie assignment i przecinać jego term. Mutacje wymagają W.
- `admin.*`: właściwe User/SchoolClass/Subject/Assignment/Enrollment Policies; user show dopuszcza withTrashed z oznaczeniem usunięcia, edit/update/delete usuniętego konta zwracają404; gradebook może używać historycznego/cofniętego assignment jako identyfikatora kontekstu. Admin nie musi być przypisany, ale mutacje nadal wymagają W. Kontekst bez jakiegokolwiek assignment musi dostać assignment przez F-308 przed utworzeniem kolumn/ocen.
- `profile.*`: wyłącznie auth.id; `logout`: każda uwierzytelniona sesja (wylogowanie dostępne również przed sprawdzeniem active).
- Wszystkie chronione GET: 302 do login przy braku sesji, 403 obca rola, 404 cudzy/brakujący parametr. Request filtrów:303 do oczyszczonego URL z errors, bez pętli. Mutacje dodatkowo: 419 CSRF, redirect z błędami pól/domeny/konfliktu, 503 przy niedostępnej bazie. Login/password: 429. `/up`: 503, gdy skonfigurowany health nie przechodzi. [Szczegóły](ERROR_HANDLING.md).

Aktualny kontekst użytkownika jest shared props (`auth.user`, `auth.role`, `can`, `navigation`); GET `/profile` pozwala go odświeżyć standardowym żądaniem Inertia. Nie dodajemy `/api/me` ani osobnej trasy JSON. Laravel wybiera dashboard roli; nie przyjmujemy dowolnego return URL z formularza.

GET grades/edit wyprowadza assignmentId z kontekstu oceny: dla teacher jego niecofnięte przypisanie; dla admin najniższe ID pasującego przypisania (także cofniętego). POST/PATCH/DELETE sprawdza podane assignment_id ponownie i używa go wyłącznie do kanonicznego powrotu oraz zakresu. Współprowadzenie nie tworzy zduplikowanych kolumn dziennika.

Formularze grades/create i grades/edit mają kanoniczny URL; desktop renderuje je jako panel na tle autoryzowanego dziennika, mobile jako arkusz/stronę. Bezpośredni reload URL musi działać. Pola assignment_id w formularzach określają kontekst powrotu, walidowany serwerowo. Redirect nigdy nie ufa surowemu referer/return_to.

## Stan integracji F-308

FACT: `admin.assignments.{index,store,update,destroy}` (`Admin\TeacherAssignmentController`, `role:admin`, logika w `AssignmentService`) są zarejestrowane; nieistniejący przydział daje 404, obca rola 403.

## Stan integracji F-306

FACT: `admin.classes.index/store/update/destroy` i `admin.enrollments.store/update` są zarejestrowane w `routes/web.php` pod `auth` i `role:admin`. Mutacje mają własne Form Requests/Policies i przekierowanie do kanonicznego GET. Zagnieżdżony enrollment innej klasy daje 404; nie istnieje trasa DELETE enrollment. Pozostałe planowane trasy tej tabeli zachowują dotychczasowe statusy.

FACT F-305: `admin.users.destroy` jest zarejestrowana pod `auth`/`role:admin`, z `DeleteUserRequest` i `UserPolicy.delete`. Soft-deleted binding pozostaje wyłącznie dla show; ponowne DELETE daje 404. Kontroler deleguje do `UserService.delete`, sukces przekierowuje do `admin.users.index`.
