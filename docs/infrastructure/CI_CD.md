# CI/CD — projekt procesu

PLANNED F-502/F-503. Brak repo GitHub, workflow, hostingu i wykonanego runu. Nie dodano pozornego cd.yml. F-001 definiuje skrypty, F-002 obrazy, F-501 test harness; F-502 automatyzację.

## Jednolity interfejs poleceń

| Skrypt npm | Wykonanie | Stan |
|---|---|---|
| dev | vite | F-001 |
| build | vite build (wymaga wcześniejszego `composer wayfinder`) | F-001, VERIFIED |
| lint | eslint . --max-warnings=0 | F-001, VERIFIED |
| format | prettier --write resources/ *.json *.ts *.js (tylko lokalnie, nie w CI) | F-001 |
| format:check | prettier --check resources/ *.json *.ts *.js | F-001, VERIFIED |
| typecheck | tsc --noEmit | F-001, VERIFIED |
| test:unit | vitest run | PLANNED F-501 |
| test:e2e | playwright test | PLANNED F-501 |

Composer (F-001): `composer wayfinder` (generator typów tras), `composer lint` / `composer lint:check` (Pint), `composer types:check` (PHPStan/Larastan), `composer test` (`config:clear` + `php artisan test`). Glob Prettier rozszerzyć o `e2e` i `tests/Frontend`, gdy F-501 je utworzy. Glob Prettier dopasować do istniejących plików F-001; nie tworzyć pustych configów, by zaspokoić glob. PHP: `composer validate --strict`, `composer install --no-interaction --prefer-dist`, `composer check-platform-reqs`, `vendor/bin/pint --test`, `php artisan test`. Testy nie mogą uruchamiać autoformatowania plików. Lockfiles commitowane; cache keyed by lockfile+runtime.

## Jobs

Trigger pull_request i push do domyślnej gałęzi; concurrency per branch, anulowanie starszego runu. permissions contents:read; brak write tokena i sekretów wdrożenia na PR. Użyte GitHub Actions przypięte do zweryfikowanych SHA podczas implementacji, nie zgadywane w dokumentacji.

1. **changes/docs**: zawsze uruchamiany; lokalne linki Markdown, komplet specyfikacji, unikalność tras, graf zależności i spójność odniesień. Docs-only (`*.md` poza zmianą manifestów/configów/testów) uruchamia tylko tę ścieżkę.
2. **frontend** (zmiana kodu): Node 22.x≥22.12, npm ci, lint, format:check, typecheck, test:unit, build; artefakt build + wyniki. PHP generator tras jeśli używany przez starter wykonywany przed Node build.
3. **backend**: PHP 8.3, Composer 2, rozszerzenia jak Docker; MariaDB 11.4 health, baza edudziennik_test; install/validate/platform-check/Pint; losowy APP_KEY; migrate --force na test DB, seed kontrolowanych fixture; PHPUnit z oddzielną konfiguracją. Testy constraints i transakcji na MariaDB, nie SQLite.
4. **E2E** po buildzie: Nginx/PHP-FPM/MariaDB i produkcyjne assety tego samego SHA, bez HMR; osobna resetowalna baza E2E. Playwright z przeglądarką Chromium i zależnościami systemowymi. `/up` gotowe przed testami. Scenariusze login3 role, IDOR, create/edit/delete grade i historia, CRUD users; desktop 1440/mobile 390, tablet smoke1024. Na zmianę wyłącznie docs nie uruchamia się.
5. **required-check** zawsze po pozostałych (`always`): sprawdza, że wymagane dla danej kategorii jobs zakończyły się sukcesem. Skipped E2E dla docs dozwolone, skipped przy zmianie kodu nie. Dzięki temu branch protection nie oczekuje pominiętego workflow.

Nie uzależniać danych testowych od produkcyjnych sekretów. Trace/screenshot tylko fikcyjnych danych; ograniczona retencja artefaktów CI (ASSUMPTION7dni). Zmiana workflow/lockfile uruchamia pełne checks.

## CD i wydanie

OPEN QUESTION: hosting, domena, TLS termination, magazyn backupów i polityka retencji. Nie obiecywać produkcyjnego deploy bez tych danych. Docelowo ręcznie zatwierdzone wydanie z głównej gałęzi po zielonym CI, obraz/artefakt immutable po SHA. Secrets poza repo: APP_KEY, DB credentials, deployment credentials. Brak seed demo.

Procedura: backup i sprawdzenie odtwarzalności → build/promocja artefaktu → migrate --force jako pojedynczy job → podmiana release → cache konfiguracji/tras/widoków → /up + DB health → smoke login/role/grade/history → dopiero ruch użytkowników. Migracje kompatybilne wstecz (expand/contract); w przypadku niekompatybilnych zmian uzgodnione okno maintenance i odcięcie zapisów.

Rollback: poprzedni obraz przy kompatybilnym schemacie; przy destructive migration nie uruchamiać automatycznego migrate:rollback. Osobny plan przywrócenia backupu, ocena utraty zapisów od backupu i zgoda operatora. Ćwiczenie restore na osobnej bazie obejmuje FK i audyt, nie tylko /up. Alert/raport błędu bez danych uczniów.
