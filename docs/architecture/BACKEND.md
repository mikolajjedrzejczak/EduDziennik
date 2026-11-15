# Backend

| Warstwa | Odpowiedzialność |
|---|---|
| Controllers | Pobranie zatwierdzonego wejścia, autoryzacja GET, serwis, DTO, Inertia/redirect |
| Form Requests | `authorize` deleguje do Policy; normalizacja, reguły pól, błędy po polsku |
| Policies / Gates | Uprawnienia rekordów; brak globalnego admin bypass omijającego reguły domenowe |
| Services | GradeService, UserService, EnrollmentService, AssignmentService, AssessmentService, StatisticsService |
| Models | Relacje Eloquent, casts, scopes, SoftDeletes; bez zależności od React |
| Middleware | Sesja, auth, active account, role; rejestracja w bootstrap/app.php |
| Enums | UserRole(student/teacher/admin), AccountStatus(active/inactive), AuditAction(create/update/delete) |

Relacje i nazwy kolumn wyłącznie w [SCHEMA](../database/SCHEMA.md). `Grade belongsTo StudentEnrollment, Assessment, GradeCategory, issuer(User)`; student przez enrollment, subject/class/term przez assessment. `Grade hasMany GradeAudit`. Eager loading z allowlistą pól zapobiega N+1; scope Policy musi poprzedzać paginate i agregację.

## Zapis oceny

GradeService przyjmuje aktora i zwalidowane dane, a nie model użytkownika z formularza. Transakcja blokuje term → użytkowników uporządkowanych ID → assignment → enrollment → assessment → grade (rosnące ID w grupie). Sprawdza bieżącą rolę/status, dostęp nauczyciela, okres, członkostwo i wersję. Wykonuje zapis i insert audytu w jednej transakcji. Konflikt wersji daje błąd `conflict` bez nadpisania. Powtórne DELETE nie dodaje drugiego audytu. Identyczny PATCH jest no-op bez zwiększenia wersji.

Zmiany assignment/enrollment i blokowanie kont muszą brać te same blokady rekordów jak operacje ocen. Przeniesienie ucznia nie przepisuje istniejących ocen. Zamknięcie okresu blokuje wiersz term; oczekujące zapisy zobaczą nowy stan. Retry deadlock ograniczony (np. 3 próby transakcji); brak częściowego commitu.

UserService blokuje stały rekord `domain_locks.admin_membership` przed każdą zmianą roli/statusu/usunięciem konta administratora. Dopiero pod tą blokadą liczy aktywnych administratorów. Bez tego dwa równoległe żądania mogłyby usunąć obu. Aktualizacja hasła/roli/statusu usuwa właściwe sesje w tej samej transakcji.

Obsługa zdarzeń Eloquent nie jest jedyną ochroną audytu: bulk update omija eventy, więc mutacje ocen przez jedną usługę. Brak endpointów aktualizacji historii. Audit JSON ma stały schemat snapshotu; nie zawiera hasła, emaila ani całych modeli. [Reguły](../database/BUSINESS_RULES.md).

Testy PHPUnit na MariaDB: poprawne scenariusze, scoped lookup, FK/CHECK, rollback audytu, współbieżność administratorów i wersji. Nie zastępować integracyjnej MariaDB SQLite.
