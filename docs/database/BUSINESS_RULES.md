# Reguły domenowe — źródło kanoniczne

## BR-01: okresy i członkostwo

ASSUMPTION: jedna szkoła, dwa semestry na rok. Rok i termy konfiguruje operator w F-003; nie wnioskować roku ze stałej daty w UI. Term domyślny obejmuje dzisiejszy dzień Europe/Warsaw; jeśli brak, UI wymaga wyboru dostępnego okresu. `closed_at` oznacza tylko odczyt, także dla admina. Nie ma operacji ponownego otwarcia w MVP.

Enrollment ma domknięte daty starts_on..ends_on; NULL koniec to koniec roku klasy na potrzeby kontroli. Transfer dnia D kończy poprzedni enrollment D−1, tworzy nowy D, pod blokadą User. Nie ma dwóch enrollmentów w tym samym dniu. Istniejące oceny zachowują stary enrollment. Korekta dat enrollmentu nie może pozostawić ocen poza przedziałem. Rekordów z historią nie usuwać fizycznie.

Assignment to teacher+class+subject+term. ASSUMPTION: współprowadzenie dozwolone (dwóch nauczycieli tej samej klasy/przedmiotu/termu), każdy ma ten sam zakres danych i mutacji. Odebranie uprawnienia ustawia revoked_at; ponowne nadanie tej samej czwórki zeruje revoked_at (nie tworzy duplikatu). Cofnięcie natychmiast odbiera również historię. Autorstwo nie daje bezterminowego dostępu. Administrator ma pełny odczyt niezależnie od przypisań.

## BR-02: ocena i kolumna

Ocena całkowita 1–6, waga całkowita 1–5. Plusy/minusy i finalne oceny poza MVP, wymagają ADR. Puste pole nie jest 0. Ocena ma enrollment i assessment; ich klasy muszą być zgodne. Kategoria aktywna, komentarz zwykły tekst max 500, temat 1–120. graded_on nie w przyszłości, w semestrze i enrollment. Timestamp utworzenia generuje serwer.

Tworzenie oceny wymaga aktywnego ucznia i aktywnej klasy/przedmiotu oraz otwartego termu; dotyczy też korekt i usunięcia. Read history pozostaje możliwy dla własnego aktywnego konta nawet gdy kontekst zarchiwizowano. Zmiana roli student/teacher z relacjami historycznymi jest zabroniona, aby nie przepisywać znaczenia tych danych.

Assessment tworzy nauczyciel z przypisaniem lub admin: class, subject, term, category, topic, default_weight, graded_on. Użycie pustej komórki wypełnia formularz domyślnymi wartościami kolumny. Użytkownik może zmienić dane pojedynczej oceny; odstępstwa od nagłówka pokazuje UI. Brak bulk zapisu i edycji kolumn w MVP. [SCHEMA](SCHEMA.md) określa unikalność oceny również po usunięciu.

## BR-03: średnie i statystyki

`SUM(value * weight) / SUM(weight)` dla nieusuniętych ocen w autoryzowanym zakresie. Podmiot: uczeń + term, opcjonalnie subject; transfer nie usuwa wcześniejszych wyników tego ucznia. Średnia ogólna to agregat wszystkich jego ocen w termie, **nie średnia średnich przedmiotowych**. Nauczyciel widzi tylko przydzielone konteksty, więc jego podsumowanie może mieć inny zakres niż uczeń.

Obliczenia SQL DECIMAL, końcowe ROUND(...,2) half-up; DTO string z dwiema cyframi (`"4.43"`), UI `4,43`. Brak ocen → NULL i „—”, licznik 0. Bez średnich utrwalonych w users/grades. Dashboard i lista w tym samym zakresie dają identyczny wynik. Filtry dat/kategorii na liście zawężają jej średnią i muszą być nazwane w UI; paginacja nigdy nie zawęża agregatu. Filtr wyszukiwania ucznia ogranicza roster, a nie jego oceny.

Fixture: 4×2 + 5×1 + 5×3 + 3×1 =31, suma wag=7 →4.43. Dodanie 4×2 →39/9=4.33. Niezależny wariant edycji bazowej 3→4 →32/7=4.57; niezależne usunięcie bazowej 3×1 →28/6=4.67. Nie łączyć tych trzech wariantów jako jednej sekwencji.

Dashboard teacher: assignmentCount liczy niecofnięte przydziały w wybranym termie. ungradedCount liczy puste komórki istniejących, nieusuniętych assessments tylko dla enrollmentów obejmujących datę kolumny; soft-deleted grade jest pustą komórką oznaczoną jako usunięta, której nie można ponownie wypełnić w MVP. atRiskCount liczy pary uczeń+przedmiot w jego kontekstach, nie unikalnych uczniów całej szkoły. Dashboard admin userCounts obejmuje wszystkie nieusunięte konta według roli, inactiveCount ich nieaktywny podzbiór; classCount aktywne klasy roku wybranego termu, subjectCount nieusunięty słownik, recentAudits zakres wybranego termu. Student highest/lowest wybierane z subjects (backend sortuje według niezaokrąglonej średniej, null na końcu, remisy nazwa+ID), UI nie przelicza wyników.

ASSUMPTION: wskaźnik „zagrożenie” to średnia przedmiotowa <2.00, liczona z niezaokrąglonego ilorazu; brak ocen osobno, bez diagnozy/oceny końcowej. Statystyki nie mają skutków administracyjnych.

## BR-04: audyt, wersja, transakcja

Create: version=1, audit old=NULL/new=snapshot. Update: wymagane version i reason 3–500 znaków, atomowe sprawdzenie wersji, old snapshot przed zmianą, new po, version+1. Delete: reason, version i potwierdzenie; soft delete + version+1 + oba snapshoty (new.deleted_at ustawione). No-op PATCH nie tworzy zdarzenia. Powtórny DELETE → 404, zero dodatkowej historii.

Snapshot v1: `schema_version`, `id`, `student_enrollment_id`, `student_id`, `assessment_id`, `school_class_id`, `subject_id`, `term_id`, `category_id`, `value`, `weight`, `topic`, `comment`, `graded_on`, `issued_by`, `version`, `deleted_at`. Wszystkie ID w DTO string; zapis JSON zgodny z tą reprezentacją. `actor_id` w audycie pochodzi z sesji; issued_by jest niezmienny po utworzeniu. Dane old/new są dostępnymi dla studenta danymi oceny, bez tajnych notatek i danych konta.

GradeService obejmuje zmianę i audyt jedną transakcją. Brak audytu = brak commitu. Historia append-only, również dla admina. Właściciel oceny wyprowadzony przez zachowany enrollment, nie z JSON dostarczonego przez klienta. Nie udostępniać surowych dowolnych JSON bez allowlisty przy serializacji.

## BR-05: usuwanie i konta

Usuwanie users to deleted_at + status inactive + unieważnienie sesji. Nie kaskaduje na oceny, enrollments ani audyty. Email pozostaje zarezerwowany także po usunięciu; unika przejęcia tożsamości. Brak samodzielnego usuwania profilu. Nie można usunąć siebie ani doprowadzić przez delete/status/role do 0 aktywnych adminów. Stała blokada domenowa serializuje te operacje.

Klasy/przedmioty archiwizować przez soft delete tylko gdy nie ma aktywnych powiązań organizacyjnych: class bez niezakończonych enrollmentów i niecofniętych assignmentów; subject bez niecofniętych assignmentów. Historia ocen pozostaje czytelna. Nazwy archiwalne nie znikają z historycznych DTO. Nazwy/etykiety prezentowane z aktualnych rekordów; audyt odtwarza wartości oceny, nie pełną historię zmian nazw słowników.

Zmiana hasła unieważnia pozostałe sesje (aktualna odnowiona), zmiana role/status i admin reset hasła unieważniają wszystkie sesje celu. Profil nie zmienia roli/statusu/przydziału. Brak zmiany emaila przez profil w MVP; robi to admin z kontrolą unique.

## BR-06: retencja

DECISION dla demonstracji: tylko fikcyjne dane, brak automatycznego purge, audyt utrzymywany przez cały czas życia bazy demonstracyjnej. Soft delete nie jest usunięciem PII. OPEN QUESTION przed danymi rzeczywistymi: okres retencji kont/ocen/audytów/backupów, prawo dostępu, procedura anonimizacji i właściciel danych. Wymaga odrębnej decyzji oraz testu, nie zgadywać okresu prawnego.
