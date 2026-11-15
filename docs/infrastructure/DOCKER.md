# Docker — środowisko lokalne F-002

Host wymaga tylko Docker Engine/Desktop z działającym daemonem i Compose v2+ oraz powłoki POSIX. PHP, Composer, Node i MariaDB działają w kontenerach. To konfiguracja deweloperska; produkcyjne obrazy i wdrożenie pozostają zakresem F-503.

## Pierwsze uruchomienie

```sh
cp .env.example .env
# Ustaw w .env niezależne, losowe DB_PASSWORD i MARIADB_ROOT_PASSWORD.
./scripts/bootstrap.sh
```

Nie publikuj `.env`; nie używaj `set -x` ani `docker compose config` bez `--quiet`, bo rozwinięta konfiguracja zawiera hasła. Bootstrap odmawia działania bez `.env` lub haseł. Build, instalacja albo healthcheck zakończone błędem przerywają skrypt bez komunikatu gotowości. Błąd nie usuwa danych; po usunięciu przyczyny można ponowić skrypt. Wymagany dostęp do rejestrów obrazów, repozytoriów pakietów oraz fontów używanych przez obecny build Vite.

Bootstrap buduje obrazy, czeka na DB, instaluje zależności z lockfiles w named volumes, czyści cache konfiguracji, generuje APP_KEY tylko gdy go brak, generuje typy tras przez PHP, wykonuje `npm ci` i build, uruchamia cztery usługi i czeka na ich healthchecks. **Nie wykonuje migracji ani seedów.** `/up` sprawdza połączenie `SELECT 1`, nie wymaga tabel. Sesje/cache korzystają z bazy, więc strony aplikacji wymagające tych tabel mogą nie działać do jawnego uruchomienia migracji. F-002 nie tworzy migracji produktu; docelowy schemat i tabele techniczne należą do F-003. Istniejące migracje startera F-001 nie są schematem docelowym. DemoSeeder jeszcze nie istnieje.

Po bootstrapie: aplikacja `http://localhost:8080`, Vite `http://localhost:5173`. Przy konflikcie portu ustaw `APP_PORT` oraz zgodny `APP_URL` w `.env`; dla Vite ustaw `VITE_PORT`. Następnie ponów bootstrap. Porty związane są z `127.0.0.1`; środowisko nie służy do udostępniania w LAN.

## Usługi

| Usługa | Konfiguracja | Wolumeny / dostęp | Healthcheck |
|---|---|---|---|
| app | `docker/php/Dockerfile`, PHP 8.3 FPM + Composer 2; pdo_mysql, mbstring, intl, zip, bcmath, opcache, dom/xml | repo bind, vendor named volume; 9000 wyłącznie w sieci Compose | jawny FPM ping przez cgi-fcgi |
| nginx | stabilny obraz przypięty digestem; `docker/nginx/default.conf` | repo read-only; root tylko public; localhost:8080 | HTTP `/up` przez Laravel i DB |
| node | `docker/node/Dockerfile`, Node 22 ≥22.22.2 (harness F-402); profil dev | repo bind, node_modules named volume; localhost:5173 | klient HTTP Vite |
| db | MariaDB 11.4, obraz przypięty digestem | db_data; bez portu hosta | `healthcheck.sh --connect --innodb_initialized` |

Wszystkie obrazy bazowe mają digest indeksu wieloplatformowego. Aktualizacje digestów wymagają ponownego builda i kontroli F-002. Kontekst builda zawiera wyłącznie `docker/`, bez `.env`, źródeł aplikacji i sekretów. DB ma oddzielne hasło root, runtime korzysta z DB_USERNAME. UTC w kontenerach; prezentacja Europe/Warsaw. Lokalny `.env` pozostaje źródłem APP_KEY i ustawień Laravel. Po zmianie hasła w istniejącym DB volume trzeba jawnie zmienić konto w DB — zmienne inicjalizacyjne obrazu nie rotują haseł istniejącej bazy.

App startuje po zdrowej DB, nginx po zdrowym FPM. `/up` zwraca 503 przy awarii DB z ogólnym komunikatem, bez surowego wyjątku połączenia. Kontroler health działa poza middleware web/sesją. Własna odpowiedź jest potrzebna, ponieważ health frameworka Laravel 13 zwraca 500 przy błędzie diagnostyki, a kontrakt projektu wymaga 503 także przy APP_DEBUG=true. Timeout połączenia MariaDB wynosi 3 sekundy. Runtime nie uruchamia migracji. Entrypoint zapewnia zapisywalność wyłącznie storage i bootstrap/cache przez www-data, bez chmod 777. Nginx blokuje dotfiles i wykonywanie PHP poza index.php; access log jest wyłączony, aby nie zapisywać parametrów URL.

## Codzienna praca

```sh
docker compose --profile dev up -d --wait
docker compose --profile dev down       # zachowuje named volumes
# Nie używaj down -v, jeśli chcesz zachować dane!
docker compose --profile dev up -d --build --wait
```

| Cel | Polecenie |
|---|---|
| Migracje (jawnie, po przygotowaniu F-003) | `docker compose exec app php artisan migrate` |
| Typy tras po zmianie routes/controller | `docker compose exec app composer wayfinder` |
| Composer | `docker compose run --rm app composer <command>` |
| npm | `docker compose run --rm node npm <command>` |
| Build assetów | `docker compose run --rm app composer wayfinder` następnie `docker compose run --rm node npm run build` |
| QA PHP | `docker compose exec app composer validate --strict`, `composer check-platform-reqs`, `composer lint:check`, `composer types:check`, `composer test` (każde z tym samym prefiksem `docker compose exec app`) |
| QA Node | `docker compose run --rm node npm run lint`, `npm run format:check`, `npm run typecheck` (każde z tym samym prefiksem `docker compose run --rm node`) |
| Test infrastruktury | `./scripts/test-infrastructure.sh` |
| Stan | `docker compose --profile dev ps` |
| Logi lokalne | `docker compose logs --tail=100 app nginx node db` — nie publikuj niesprawdzonych logów |

Kontener Node nie ma PHP. W Compose `WAYFINDER_EXTERNAL=true` wyłącza automatyczny generator pluginu Vite; po zmianie tras wykonaj powyższy generator przez app. HMR komponentów i CSS oraz odświeżanie widoków nadal działa. Vite nasłuchuje na 0.0.0.0 wewnątrz kontenera, ma strictPort, jawne allowedHosts i HMR localhost. Zatrzymanie samego Node może pozostawić public/hot: przed pracą ze statycznym buildem usuń go przez `docker compose run --rm app rm -f public/hot`.

Test WebSocket HMR: `docker compose exec -T node node tests/Infrastructure/hmr.mjs` (na chwilę zmienia i przywraca welcome.tsx; nie uruchamiaj równocześnie z restartem kontenera ani edycją tego pliku).

PHPUnit od F-003 używa dedykowanej MariaDB `edudziennik_test`, bez SQLite. Jednorazowe utworzenie: `docker compose exec app php tests/Infrastructure/create-test-database.php`, następnie `docker compose exec app php artisan test`. Migracje, seed i konfiguracja operatorska: [SEEDING](../database/SEEDING.md). Vitest/RTL i Playwright wzorca formularzy pochodzą z F-402; F-003 nie dodaje UI i nie wymaga Figmy.

## Test infrastruktury i błędy

Test zakłada zakończony bootstrap i APP_ENV=local. Tworzy **oddzielną** bazę `edudziennik_f002_probe`, tabelę fixture wyłącznie przez migrację w tests/Infrastructure/migrations, zapisuje marker, wykonuje down/up bez usuwania wolumenów, odczytuje marker i usuwa bazę fixture, następnie sprawdza HTTP i WebSocket HMR (chwilowa zmiana i przywrócenie welcome.tsx). Nie zmienia schematu aplikacji. Używa root tylko do operatorskiego utworzenia/usunięcia izolowanej bazy testowej. Odmawia nadpisania istniejącej bazy fixture. Po przerwanym teście sprawdź ją i jawnie usuń przez `docker compose exec app php tests/Infrastructure/persistence.php cleanup`, zanim ponowisz test. Skrypt restartuje lokalny zestaw usług.

Brak zależności: uruchom ponownie bootstrap (install jest jawnym etapem). Konflikt portu: Compose zgłasza błąd bindowania, zmień port i APP_URL; brak komunikatu sukcesu. Awaria bazy: `/up` 503; depends_on nie zastępuje obsługi awarii w runtime. Nie resetuj db_data w celu obejścia błędu. Logi i manifesty sprawdzaj bez drukowania haseł ani APP_KEY.

## Weryfikacja

Dowody faktycznie wykonanych kontroli i ograniczenia: [raport F-002](../features/F-002.md#22-raport-realizacji-2026-11-15). Docelowy proces wydania: [CI/CD](CI_CD.md).
