# Strategia testowania

PLANNED: pełne pokrycie domeny. Testy każdej funkcji powstają razem z nią; F-501 rozszerza harness, F-502 automatyzuje kontrole. [Polecenia](../infrastructure/CI_CD.md) i [Docker](../infrastructure/DOCKER.md) opisują własny stan realizacji.

FACT F-402: istnieją już testy startera i technicznego wzorca formularzy oraz minimalny harness PHPUnit/Vitest/RTL/Playwright. Poniższa strategia domenowa nadal jest planem. Uruchomienie i granice dowodów: [FORM_VALIDATION](../http/FORM_VALIDATION.md), [raport F-402](../features/F-402.md#22-raport-realizacji--2026-11-15).

| Warstwa | Narzędzie | Co dowodzi |
|---|---|---|
| Czysta domena | PHPUnit Unit | Reguły wartości/wagi, half-up, snapshot v1 |
| HTTP i baza | PHPUnit Feature + MariaDB 11.4 | Sesje, Policy, props, walidacja, FK/CHECK, transakcje |
| Prezentacja | Vitest | Format polski, null average, daty bez przesunięcia dnia |
| Komponenty | RTL + Vitest | Form errors, pending, focus, dialog, empty/error |
| Cały system | Playwright | Scenariusze ról i odmowy, responsywność, reload URL |

FACT F-003: PHPUnit korzysta teraz z MariaDB i stałej izolowanej bazy `edudziennik_test` (wymuszenie w phpunit.xml, kontrola rozwiązanego połączenia w createApplication **przed** RefreshDatabase). Jednorazowy provisioner i instrukcja: [SEEDING](../database/SEEDING.md#testy-mariadb). Dodano testy migracji/rollback, FK/CHECK, JSON audytu, spójnych factories, fixture i SQL średnich, temporalnych scopes, odmów zapisu i atomowości audytu. Dwa osobne procesy z barierą NOWAIT testują konkurencyjne enrollmenty i zamknięcie termu przed oczekującym zapisem. Wynik lokalny: 115 testów / 379 asercji; [raport F-003](../features/F-003.md#22-raport-realizacji-2026-11-15). Pełna macierz HTTP i scenariusze administratorów/wersji w dwóch procesach pozostają w kolejnych funkcjach.

## Harness F-501

FACT: wspólny zegar PHP (`Tests\Support\TestClock`), Vitest setup i Playwright fixture wskazują `2026-11-15T12:00:00Z`. Laravel przywraca zegar po teście; Vitest przywraca prawdziwe timery, czyści DOM i mocki. Playwright daje nowy context na każdy test, strefę Europe/Warsaw, locale pl-PL i automatycznie sprawdza błędy JavaScript. Nie włączamy globalnie fałszywych timerów — user-event i oczekiwanie na HTTP działają normalnie.

`TestDatabase` sprawdza środowisko testing, domyślne/rozwiązane połączenie MariaDB, nazwę bazy i nadpisania read/write **przed** RefreshDatabase. Ten sam bezpiecznik działa w workerze współbieżności. PHPUnit wymusza środowisko/nazwę/driver i wyłącza DB_URL; osobny test potwierdza rzeczywistą bazę i serwer MariaDB 11.4. Testy błędnej konfiguracji nie łączą się z bazą. Schemat zmieniają wyłącznie migracje; migracje/konkurencja mają istniejące testy integracyjne. Nie uruchamiaj suite równolegle na jednej bazie i nie używaj `--parallel`.

Po bootstrapie zależności F-002 i jednorazowym utworzeniu bazy według [SEEDING](../database/SEEDING.md#testy-mariadb):

```sh
./scripts/test.sh
```

Skrypt przerywa przy pierwszym błędzie, wykonuje kolejno PHPUnit → Vitest/RTL → Wayfinder → build → Playwright. Nie instaluje ani nie resetuje bazy deweloperskiej. Opcjonalny obraz `docker/testing/Dockerfile` łączy te same przypięte PHP 8.3/Node 22 co obrazy dev oraz Chromium instalowane przez Playwright z lockfile. Manifest nie publikuje portów runnera. Budowa obrazu wymaga sieci; błąd instalacji kończy etap. `.dockerignore` dopuszcza tylko katalog docker i manifesty npm, bez `.env` i danych aplikacji. Wykonanie pojedynczych runnerów:

```sh
docker compose exec -T app composer test
docker compose run --rm node npm run test:unit
docker compose exec -T app composer wayfinder
docker compose run --rm node npm run build
docker compose -f compose.yaml -f compose.testing.yaml run --build --rm browser
```

FACT: projekt Playwright `forms` weryfikuje prawdziwe Laravel Form Request/Inertia/CSRF i klawiaturę w technicznym fixture (390/1024/1440/320) z Vite dev. Projekt `build` sprawdza zbudowane assety rzeczywistego `/login`, przekierowanie gościa z `/dashboard`, załadowanie React, CSS i obsługę klawiatury (390/1024/1440), ignorując lokalny `public/hot`. Serwery fixture są jawne, CLI-only, nie są ładowane przez public/index.php i odmawiają poza testing. Nie dodano UI ani tras produkcyjnych.

Oba obecne projekty E2E nie korzystają z bazy: formularz ma efekt wyłącznie w sesji plikowej, smoke nie loguje użytkownika. Używają nazwy `edudziennik_e2e` i nieistniejącego użytkownika, więc przypadkowa próba dostępu do danych kończy się błędem. Nie tworzą i nie resetują tego schematu. PLANNED: E2E domenowe otrzymają osobny provisioner/fixture dla bazy E2E oraz macierz ról z wdrażanymi funkcjami; obecny smoke jej nie zastępuje.

Raporty (nieśledzone w Git): `test-results/phpunit.xml`, `test-results/vitest.xml`, `test-results/playwright.xml`, `playwright-report/index.html`; trace i screenshot Playwright przy błędzie. Nie są dowodem pokrycia całej domeny. PHPUnit odrzuca pusty suite, warning i risky; Vitest/Playwright domyślnie odrzucają brak testów, Playwright również `test.only`, bez retries ukrywających flaky. W CI (F-502, `.github/workflows/ci.yml`) raporty są zachowywane jako artefakty również przy błędzie. `scripts/test.sh` usuwa stare trzy raporty JUnit przed startem; przy osobnych wywołaniach usuń stare raporty albo oceniaj ich timestamp i exit code odpowiedniego runnera; sam plik nie oznacza sukcesu.

Nowe funkcje dodają scenariusze sukcesu, odmowy i rollbacku do właściwej warstwy; wykorzystują istniejące zgodne factories/seeder F-003. Backend musi pozostać sprawdzany na MariaDB, także w CI. Wyniki F-501: [raport](../features/F-501.md#22-raport-realizacji-2026-11-15).

## Krytyczne scenariusze backendu

1. Login/logout/inactive/deleted, regenerate session, CSRF,429, password change unieważniający inne sesje.
2. Każda chroniona trasa: guest, student własny/cudzy rekord, teacher poprawny/inny class/subject/term, admin. Test również response props, counts, agregatów i opcji — samo status403 nie wystarcza.
3. CRUD users: show bez hasha, unique email także soft-deleted, relacje atomowo, status/role session revocation, self-delete denied. Dwóch adminów równocześnie: co najmniej jeden active pozostaje.
4. Oceny: value1/6 i weight1/5, odrzucenie0/7/wagi6 i plusów, future date, enrollment/term boundary. Każda mutacja tworzy dokładnie jeden audit.
5. Awaria insertu grade_audits cofa grade; konkurencyjne PATCH starej version nie nadpisuje; delete twice nie dodaje historii; no-op bez audytu.
6. Cofnięcie assignment równolegle ze zmianą oceny: blokady dają spójny porządek; zapis sprzed revoke może zakończyć się wcześniej, zapis po revoke musi zostać odrzucony.
7. Transfer enrollmentu, zakresy bez nakładania, semestr innego roku, historyczny odczyt po soft delete konta/oceny, RESTRICT physical delete.
8. Średnie31/7=4.43; niezależne warianty39/9=4.33,32/7=4.57,28/6=4.67; null przy braku; średnia ogólna z wag, nie średnich; filtr/paginacja zgodne z BR-03.
9. Assessment context immutable, duplicate grade cell, usunięcie niepustej kolumny denied; override wagi liczy grade.weight, nie default_weight.

Współbieżność testować dwoma niezależnymi połączeniami/procesami do MariaDB z kontrolowaną barierą, nie dwoma wywołaniami w tej samej transakcji testowej. Freeze clock; `RefreshDatabase` dla zwykłych Feature, dedykowane migracje/cleanup dla testów równoległych. Nie ujawniać sekretów przy fault injection.

## E2E odbiorowe

- Student → login → grades → subject → details → history; następnie cudzy URL →404.
- Teacher → class/subject → student → grade create → student widzi zmianę → edit z reason → audit → delete → audit nadal istnieje. Osobny przypadek braku assignment.
- Admin → create user → show → edit → delete z nazwiskiem; osobno anulowanie i ostatni admin.
- Class/enrollment transfer i assignment revoke sprawdzają rzeczywistą zmianę dostępu.

Viewport 390/1024/1440, dodatkowy reflow320px/zoom200%, klawiatura (tab, enter, escape, focus return), nazwy dostępne i kontrasty. Nie deklarować pełnego WCAG AA na podstawie automatycznego narzędzia; manualny przegląd kluczowych interakcji obowiązkowy. Porównanie Figma dotyczy wartości i układu, a tekst fixture nie musi kopiować nazwisk makiety.

Fixture tylko fikcyjne. CI izoluje test i E2E DB; schema reset tylko tam. Nie używać produkcyjnego dumpa. Nie wyłączać testów, żeby przejść pipeline. N1 queries, duże props i czas GET mierzyć na kontrolowanym zestawie50tys. ocen przed F-503.
