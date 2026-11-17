# CI/CD — projekt procesu

FACT F-502: workflow [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) jest zaimplementowany (jobs: changes, docs, frontend, backend, e2e, required-check), skrypty pomocnicze w `scripts/ci/`. Jego jedyny przebieg na GitHub (próbny PR) jest opisany w [raporcie F-502](../features/F-502.md#22-raport-realizacji); do czasu zapisu URL runu nie deklarować działania na GitHub. PLANNED F-503: CD; hosting jest nieznany, nie dodano pozornego cd.yml. F-001 definiuje skrypty, F-002 obrazy, F-501 test harness; F-502 automatyzację.

## Jednolity interfejs poleceń

| Skrypt npm | Wykonanie | Stan |
|---|---|---|
| dev | vite | F-001 |
| build | vite build (wymaga wcześniejszego `composer wayfinder`) | F-001, VERIFIED |
| lint | eslint . --max-warnings=0 | F-001, VERIFIED |
| format | prettier --write resources/ *.json *.ts *.js (tylko lokalnie, nie w CI) | F-001 |
| format:check | prettier --check resources/ *.json *.ts *.js | F-001, VERIFIED |
| typecheck | tsc --noEmit | F-001, VERIFIED |
| test:unit | vitest run | F-501, lokalny harness |
| test:e2e | playwright test | F-501, forms + build smoke |

Composer (F-001): `composer wayfinder` (generator typów tras), `composer lint` / `composer lint:check` (Pint), `composer types:check` (PHPStan/Larastan), `composer test` (`config:clear` + `php artisan test`). FACT F-501: Prettier obejmuje tests/Frontend, tests/Browser i TSX fixture; typecheck obejmuje również te katalogi. Glob Prettier dopasować do istniejących plików F-001; nie tworzyć pustych configów, by zaspokoić glob. PHP: `composer validate --strict`, `composer install --no-interaction --prefer-dist`, `composer check-platform-reqs`, `vendor/bin/pint --test`, `php artisan test`. Testy nie mogą uruchamiać autoformatowania plików. Lockfiles commitowane; cache keyed by lockfile+runtime.

## Jobs

Trigger pull_request i push do domyślnej gałęzi; concurrency per branch, anulowanie starszego runu. permissions contents:read; brak write tokena i sekretów wdrożenia na PR. Użyte GitHub Actions przypięte do zweryfikowanych SHA podczas implementacji, nie zgadywane w dokumentacji.

1. **changes/docs**: zawsze uruchamiany; lokalne linki Markdown, komplet specyfikacji, unikalność tras, graf zależności i spójność odniesień. Docs-only (`*.md` poza zmianą manifestów/configów/testów) uruchamia tylko tę ścieżkę.
2. **frontend** (zmiana kodu): Node 22.x≥22.12, npm ci, lint, format:check, typecheck, test:unit, build; artefakt build + wyniki. PHP generator tras jeśli używany przez starter wykonywany przed Node build.
3. **backend**: PHP 8.3, Composer 2, rozszerzenia jak Docker; MariaDB 11.4 health, baza edudziennik_test; install/validate/platform-check/Pint; losowy APP_KEY; migrate --force na test DB, seed kontrolowanych fixture; PHPUnit z konfiguracją phpunit.xml wymuszającą testową bazę. Testy constraints i transakcji na MariaDB, nie SQLite.
4. **E2E** po buildzie: Nginx/PHP-FPM/MariaDB i produkcyjne assety tego samego SHA, bez HMR; osobna resetowalna baza E2E. Playwright z przeglądarką Chromium i zależnościami systemowymi. `/up` gotowe przed testami. Scenariusze login3 role, IDOR, create/edit/delete grade i historia, CRUD users; desktop 1440/mobile 390, tablet smoke1024. Na zmianę wyłącznie docs nie uruchamia się.
5. **required-check** zawsze po pozostałych (`always`): sprawdza, że wymagane dla danej kategorii jobs zakończyły się sukcesem. Skipped E2E dla docs dozwolone, skipped przy zmianie kodu nie. Dzięki temu branch protection nie oczekuje pominiętego workflow.

FACT F-502 — odchylenia i decyzje implementacyjne:

- Akcje przypięte do SHA (komentarz z tagiem): checkout v7.0.1, setup-node v7.1.0, cache v6.0.0, upload-artifact v7.0.2, download-artifact v8.0.2, setup-php 2.40.0. Aktualizacja SHA jest zmianą workflow i uruchamia pełne checks.
- Kategoria zmiany (`scripts/ci/classify-changes.sh`): docs-only to wyłącznie pliki `*.md` poza `tests/`, `.github/` i `scripts/`. Brak bazy diffu (nowa gałąź) lub błąd diffu = `code`.
- Job `docs` = `scripts/ci/check-docs.mjs` (bez zależności): lokalne linki i kotwice Markdown, komplet specyfikacji względem FEATURE_INDEX, unikalność metod/URL i nazw tras w ROUTES.md, brak cykli zależności.
- DECISION: E2E w CI są tymczasowo wyłączone domyślnie. Zmienna repozytorium GitHub Actions `ENABLE_E2E=true` wznawia job `e2e`; lokalne `npm run test:e2e` pozostaje dostępne.
- `required-check` (`scripts/ci/require-jobs.sh`) wymaga success dla changes i docs; dla `code` także frontend, backend oraz e2e przy `ENABLE_E2E=true` (w przeciwnym razie e2e musi być `skipped`); dla `docs` frontend/backend/e2e muszą być `skipped`. Cancelled/failure/missing zawsze kończą się błędem. Jako required status w branch protection wskazać tylko `required-check`.
- MariaDB 11.4 (ten sam digest co `compose.yaml`) to service w jobach backend i e2e (osobne instancje, więc baza E2E jest resetowana na każdy run). Efemeryczny root bez hasła na runnerze (`MARIADB_ALLOW_EMPTY_ROOT_PASSWORD`), brak sekretów; baza `edudziennik_test` wymuszana przez phpunit.xml.
- Backend: `.env` kopiowany z `.env.example` (bez sekretów; wymagany przez testy), losowy `APP_KEY` na run, `migrate --force` na pustej bazie jako osobny krok, potem `composer test`. Seed demo nie jest uruchamiany: testy tworzą własne fixture.
- E2E używa istniejącego harnessu F-501 (`php -S` + `tests/Infrastructure/browser-server.php`, zbudowane assety z artefaktu `frontend-build` tego samego SHA, bez HMR dla projektów shell/classes/build). PLANNED: wariant Nginx/PHP-FPM z pełnym obrazem produkcyjnym.
- Fork PR: trigger `pull_request` bez sekretów; workflow nie używa żadnych. Cache: `composer.lock`/`package-lock.json` w kluczu, `composer validate --strict` i `npm ci` kończą job przy rozjechanym lockfile.
- Artefakty raportów (`test-results/`, `playwright-report/`) są wysyłane także po błędzie, retencja 7 dni.

Nie uzależniać danych testowych od produkcyjnych sekretów. Trace/screenshot tylko fikcyjnych danych; ograniczona retencja artefaktów CI (ASSUMPTION7dni). Zmiana workflow/lockfile uruchamia pełne checks.

## CD i wydanie

OPEN QUESTION: hosting, domena, TLS termination, magazyn backupów i polityka retencji. Nie obiecywać produkcyjnego deploy bez tych danych. Docelowo ręcznie zatwierdzone wydanie z głównej gałęzi po zielonym CI, obraz/artefakt immutable po SHA. Secrets poza repo: APP_KEY, DB credentials, deployment credentials. Brak seed demo.

Procedura: backup i sprawdzenie odtwarzalności → build/promocja artefaktu → migrate --force jako pojedynczy job → podmiana release → cache konfiguracji/tras/widoków → /up + DB health → smoke login/role/grade/history → dopiero ruch użytkowników. Migracje kompatybilne wstecz (expand/contract); w przypadku niekompatybilnych zmian uzgodnione okno maintenance i odcięcie zapisów.

Rollback: poprzedni obraz przy kompatybilnym schemacie; przy destructive migration nie uruchamiać automatycznego migrate:rollback. Osobny plan przywrócenia backupu, ocena utraty zapisów od backupu i zgoda operatora. Ćwiczenie restore na osobnej bazie obejmuje FK i audyt, nie tylko /up. Alert/raport błędu bez danych uczniów.

FACT F-501: lokalne polecenia i raporty runnerów opisano w [TEST_STRATEGY](../testing/TEST_STRATEGY.md#harness-f-501); automatyzacja workflow oraz domenowe E2E nadal PLANNED.
