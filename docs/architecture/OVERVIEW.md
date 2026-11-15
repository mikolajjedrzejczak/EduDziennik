# Architektura

DECISION: jedna aplikacja Laravel, jeden proces wdrożenia i jedna MariaDB. Moduły domenowe: konta, organizacja nauczania, oceny i audyt, odczyt/statystyki. To granice odpowiedzialności, nie osobne usługi ani pakiety.

## Przepływ i MVC

Przeglądarka → Nginx → PHP-FPM/Laravel → `web` (sesja, CSRF) → `auth`, aktywność konta i rola → scoped lookup / Policy → Form Request → Controller → Service → Eloquent → MariaDB. W praktyce Form Request wywołuje Policy w `authorize`; odczyt kontroler autoryzuje przed budowaniem props. Transakcja ponownie sprawdza zmienne warunki.

Model: Eloquent i reguły domenowe usług. View: React renderowany przez Inertia, uruchamiany z powłoki Blade. Controller: orkiestracja bez obliczeń domenowych. Pierwszy GET zwraca HTML z danymi strony, kolejne wizyty Inertia używają jej protokołu; to nadal routing Laravel, nie publiczne REST API.

GET daje `Inertia::render(page, props)`. Mutacja daje przekierowanie do kanonicznego GET i flash; walidacja wraca w session error bag. Nie serializujemy całego modelu. [Kontrakty](../http/REQUESTS_RESPONSES.md) definiują allowlisty.

## Docelowa struktura (PLANNED)

```text
app/Enums
app/Http/Controllers/{Auth,Student,Teacher,Admin}
app/Http/Requests
app/Http/Middleware
app/Models
app/Policies
app/Services/{Grades,Users,Statistics,School}
bootstrap/app.php
config
database/{migrations,factories,seeders}
resources/css/{app.css,tokens.css}
resources/js/{pages,components,layouts,hooks,lib,types}
resources/js/pages/{auth,student,teacher,admin,shared,settings}
resources/js/components/{ui,grades,forms}
resources/views/app.blade.php
routes/{web.php,auth.php}
tests/{Feature,Unit,Frontend}
e2e
docker/{nginx,php,node}
.github/workflows
```

Resolver stron używa dokładnie nazw w [ROUTES](../http/ROUTES.md). `resources/js/pages/student/dashboard.tsx` odpowiada `student/dashboard`. Nie przenosić bez potrzeby katalogów startera do PascalCase. Nie dodawać `app/Http/Kernel.php` ze starych poradników; middleware rejestrować według Laravel 13 w `bootstrap/app.php`.

## Spójność

Wszystkie zapisy ocen przechodzą przez GradeService: blokada kontekstu, Policy, wersja rekordu, zmiana, audyt, commit. AuditService nie jest osobnym procesem. Błąd insertu historii cofa ocenę. Średnie liczy serwis odczytu w SQL, nigdy klient. FK i CHECK uzupełniają reguły przekrojowe w usługach.

Uprawnienia są wspólne dla list, eksportu props, szczegółów, historii i statystyk. Błędy domenowe mają komunikaty po polsku bez informacji o cudzych rekordach; [błędy HTTP](../http/ERROR_HANDLING.md). Przetwarzanie synchroniczne; brak kolejki, SSR i zewnętrznych usług w MVP.
