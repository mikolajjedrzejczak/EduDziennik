# Dane demonstracyjne

FACT F-003: seeder i testy zaimplementowane, do przeglądu. Wyłącznie fikcyjne dane i domena `example.test`. Seeder demonstracyjny odmawia wykonania w production. Hasło deweloperskie pochodzi z lokalnego DEMO_PASSWORD (≥12 znaków); brak stałego produkcyjnego hasła. Hashe przez Laravel Hash, bez logowania hasła. Accounts admin1/admin2 pozwalają testować ostatniego administratora.

Kolejność: 2 adminów → 3 nauczycieli → 18 uczniów → rok 2026/2027 (2026-09-01..2027-08-31) → term 1 (do 2027-01-31), term 2 (2027-02-01..2027-08-31) → klasy 1A/2A/2B → 4 przedmioty → enrollments → assignments → kategorie → assessments → grades i audyty przez GradeService. Osobny historyczny rok 2025/2026 sprawdza odczyt archiwalny: jego oceny i audyty utworzyć przez GradeService przed ustawieniem closed_at, następnie zamknąć termy. Nie omijać reguły W przez bezpośredni insert ocen do zamkniętego termu.

Kategorie: Quiz waga 2, Test 3, Homework 1, Activity 1 (etykiety UI: Kartkówka, Sprawdzian, Praca domowa, Aktywność). Dopuszczalne wagi 4 i 5 również w fixture walidacji. Sześciu uczniów w klasie. Jeden nauczyciel prowadzi dwa przedmioty, drugi tylko jeden, trzeci bez przypisań. Przedmiot i klasa bez ocen, uczeń bez ocen, konto inactive, konto soft-deleted, cofnięty assignment.

W bazowej 2A/Matematyka utworzyć cztery assessments i fixture 31/7 opisany w [BR-03](BUSINESS_RULES.md). Tworzenie/edycja/usunięcie w oddzielnych scenariuszach, żeby wyniki 4.33, 4.57 i 4.67 nie były sprzeczną sekwencją. Oceny z create/update/delete audit, usunięta ocena widoczna wyłącznie w historii. Factory nie tworzy grade z losowym, niezgodnym enrollment/assessment.

Dane datować względem jawnego zegara testowego 2026-11-15; testy zamrażają czas. Seeder nie zakłada, że dzisiejsza data na maszynie mieści się w danym termie. W środowisku demo zestaw dat musi być jawnie dobrany przy uruchomieniu. Powtórne seedowanie przez reset wyłącznie w dedykowanej bazie testowej; nie upsertować historycznych ocen na żywym środowisku.

Kontrole: FK bez sierot, wszystkie role zgodne, term/class/year spójne, brak nakładających enrollmentów, każda ocena ma create audit, liczba aktywnych adminów ≥1, przykładowe średnie zgodne. Seedery nie są migracjami produkcyjnych danych.

## Uruchomienie i konfiguracja operatorska

Po bootstrapie (F-002) wykonaj migracje, następnie wybierz **konfigurację środowiska** albo **seed pustej bazy demo**. Seeder sam konfiguruje lata/kategorie; nie uruchamiaj wcześniej przykładowej konfiguracji na tej samej bazie demo.

```sh
docker compose exec app php artisan migrate --no-interaction
# Konfiguracja operatorska: skopiuj i dostosuj jawny JSON, bez sekretów.
docker compose exec app php artisan school:configure database/configuration/school.example.json
```

Kontrakt JSON: `years` i `categories` wymagane (mogą być pustymi tablicami), opcjonalne `close_term_ids` z istniejącymi ID. Rok: name, starts_on, ends_on, dokładnie dwa terms (number 1/2, starts_on, ends_on). Kategoria: name, default_weight (liczba całkowita 1..5), is_active (boolean). Daty YYYY-MM-DD. Nieznane klucze, błędne typy, nakładanie lat/semestrów, term poza rokiem i nadpisywanie roku kończą komendę kodem 1, bez częściowego zapisu. Błąd DB przerywa komendę. Kategorie aktualizowane po nazwie, lata tworzone jednokrotnie. Zamknięcie: np. `{"years":[],"categories":[],"close_term_ids":[1]}`; powtórne zamknięcie nie zmienia closed_at. Brak klucza/operacji reopening.

Demo wymaga pustej dedykowanej bazy, `APP_ENV=local` lub `testing`, lokalnego `DEMO_PASSWORD` minimum 12 znaków i `DEMO_DATE=2026-11-15` w `.env`. Datę wybrano jawnie dla tego fixture; seeder ustawia zegar wyłącznie na czas transakcji i przywraca poprzedni zegar. Inny zestaw dat wymaga nowej, spójnej definicji fixture, a nie przesuwania pojedynczego termu.

```sh
docker compose exec app php artisan db:seed --no-interaction
```

Nie uruchamiaj resetu na bazie z danymi do zachowania. Seeder odmawia powtórnego wykonania, nie upsertuje ocen ani kont. `student7@example.test`, 2A/Matematyka, term 1 roku 2026/2027 ma 31/7=4.43. Student8 ma niezależny usunięty wpis z create/update/delete audit. Student1 i 2B są bez ocen, Biologia bez ocen/przydziału; teacher3 bez przydziałów; student17 inactive i student18 soft-deleted. Historia student7 z 2025/2026 powstaje przed zamknięciem obu semestrów. Login używa sekretu lokalnego operatora, którego komenda nie wypisuje.

## Testy MariaDB

Po bootstrapie utwórz testową bazę **jednorazowo**, bez nadpisywania istniejącej:

```sh
docker compose exec app php tests/Infrastructure/create-test-database.php
docker compose exec app php artisan test
```

Provisioner wymaga lokalnego środowiska, bierze root password z prywatnego `.env`, nadaje użytkownikowi aplikacji uprawnienia tylko do stałej bazy `edudziennik_test`. Jeśli baza już istnieje, kończy się błędem; sprawdź jej przeznaczenie, nie kasuj jej automatycznie. PHPUnit wymusza MariaDB i nazwę bazy; TestCase sprawdza je przed resetowaniem schematu. Nie uruchamiaj jednocześnie dwóch suite na tej samej bazie. Testy migracji i współbieżności resetują tę bazę. Worker concurrency ma taki sam bezpiecznik.

Factories tworzą zgodne konteksty; domyślne lata są dobierane wstecz od 2026, omijając istniejące nazwy. Term/daty kolumn wywodzą się z utworzonego roku. GradeFactory zapisuje przez GradeService wraz z audytem i transakcją. Jawne overrides testu muszą wskazywać zgodny kontekst; błędne wartości przy zapisie oceny odrzuca ta sama usługa co dla aplikacji.

Rollback fundamentu odmawia działania przy danych domenowych; test pustej bazy wykonuje pełne migrate → rollback → migrate. Produkcyjny rollback historii nie jest usuwaniem tabel.
