# RBAC — autoryzacja i zakres danych

Jedna rola na konto: `student`, `teacher`, `admin`. Każda operacja wymaga aktywnego, nieusuniętego konta; rola odczytywana z bazy przy żądaniu. Niezalogowany → login; nieaktywne konto → unieważnienie sesji.

**S**: `grade.student_enrollment.student_id == auth.id`, również po zakończeniu członkostwa.
**T**: istnieje `teacher_assignments` z teacher_id=auth.id i dokładnie school_class_id + subject_id + term_id zgodnymi z assessment; `revoked_at IS NULL`. Samo bycie autorem oceny nie daje dostępu. Obecny nauczyciel kontekstu widzi także wcześniejsze oceny tego kontekstu i może je zmieniać w otwartym okresie.
**W**: okres niezamknięty; aktywny kontekst, uczeń aktywny, assessment nieusunięty; graded_on wewnątrz termu i członkostwa, nie w przyszłości. W obowiązuje także administratora. Zakończony enrollment dopuszcza korektę historycznej oceny w jego datach, bez przepisywania ucznia.

| Operacja | Student | Teacher | Admin | Mechanizm |
|---|---|---|---|---|
| Własny pulpit/profil/hasło | Tak | Tak | Tak | Role middleware / ProfilePolicy |
| Lista i szczegóły ocen | S | T | Wszystkie | GradePolicy.view + scoped query |
| Historia, w tym usunięte oceny | S | T | Wszystkie | GradePolicy.viewHistory; ten sam scope |
| Tworzenie oceny | Nie | T + W | W | GradePolicy.create |
| Edycja/usunięcie oceny | Nie | T + W | W | GradePolicy.update/delete + version |
| Kolumny assessments | Nie | T + W | W | AssessmentPolicy |
| Lista klas | Własna etykieta | T | Wszystkie | SchoolClassPolicy |
| Uczniowie klasy | Tylko własne dane | T, wyłącznie roster i dane dydaktyczne | Wszystkie | EnrollmentPolicy; bez emaila uczniów dla teacher |
| Statystyki | S | T | Wszystkie | Te same scopes przed agregacją |
| Użytkownicy: lista/szczegóły/create/update/delete | Nie | Nie | Tak | UserPolicy + UserService |
| Role/status kont | Nie | Nie | Tak, ochrona ostatniego admina | UserPolicy + domain lock |
| Klasy/przedmioty/enrollments/assignments | Nie | Nie | Tak | Odpowiednie Policies + usługi |
| Lata/termy/kategorie | Odczyt dostępnych opcji | Odczyt dostępnych opcji | Odczyt | Konfiguracja operatorska F-003, brak publicznych mutacji |
| Edycja/usunięcie audytu | Nie | Nie | Nie | Brak tras i uprawnień DB |
| Fizyczny purge historii | Nie | Nie | Nie | Poza aplikacją, osobna decyzja retencji |

Student nie może wybierać student_id w filtrze. Teacher widzi listę uczniów enrollmentów przecinających daty termu danej klasy, w tym zakończone oznaczone jako historyczne; brak dostępu do innych przedmiotów ucznia. Historia obejmuje tylko audyty ocen spełniających scope, nie filtrowanie po autorze audytu.

Cudze rekordy i zagnieżdżenia niezgodne z URL → 404; wejście w sekcję innej roli → 403. Usunięte oceny mają osobny odczyt archiwalny szczegółów i historii (jawne withTrashed + Policy), bez edycji. `can` w props to wskazówka UI, nigdy ochrona.

Admin nie może usunąć własnego konta; zmiana własnej roli/statusu dozwolona tylko jeśli pozostaje inny aktywny admin, po czym sesja jest unieważniana. Konta z historycznymi enrollmentami/assignmentami nie zmieniają roli domenowej student↔teacher/admin:  błąd walidacji; należy dezaktywować konto i utworzyć inne. Admin↔teacher dopuszczalne tylko bez assignmentów. [Reguły](../database/BUSINESS_RULES.md).

## Integracja F-005

FACT: alias middleware `role` rejestrowany jest w `bootstrap/app.php`; użycie po `auth`, np. `role:teacher,admin`. Lista ról jest jawna i nie zawiera domyślnego obejścia dla administratora. Aktywność konta obsługuje istniejący middleware `EnsureActiveAccount`.

FACT: Policies są odkrywane przez Laravel; `StudentEnrollment` ma jawne mapowanie do `EnrollmentPolicy`. Profil używa osobnych abilities `viewProfile`/`updateProfile`, ponieważ `UserPolicy` chroni administrację kontami. Żaden globalny `Gate::before` nie przyznaje dostępu adminowi. Polityka audytu odmawia create/update/delete wszystkim rolom; audyt tworzy wewnętrznie wyłącznie GradeService przy zmianie oceny.

FACT: `visibleTo(actor)` istnieje dla Grade, GradeAudit, Assessment, StudentEnrollment, TeacherAssignment, SchoolClass i Subject. Odczyt historii jawnie obejmuje soft-deleted grade. Zakres T ignoruje autorstwo, uwzględnia dokładny aktywny assignment i zachowuje odczyt zarchiwizowanego kontekstu. Roster nauczyciela uwzględnia tylko enrollmenty przecinające term assignmentu. `GradeAccess.enrollments/assessments` ponownie autoryzują przekazany assignment i zawężają opcje do jego kontekstu.

DECISION: nie używać globalnego scope zależnego od sesji (operator, seeder i wewnętrzne transakcje mają inne potrzeby). Każdy nowy kontroler odczytu musi wywołać Policy i jawny scope/GradeAccess przed pobraniem danych. Każdy Form Request mutacji wykonuje Policy z rekordem/kontekstem; usługi ocen i kolumn ponawiają ją wraz z kontrolą W pod blokadami. Reguły ostatniego admina, transferów i archiwizacji pozostają obowiązkiem usług odpowiednich funkcji, a nie ogólnego middleware ról.
