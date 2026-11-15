# Docker — projekt środowiska

**PLANNED: nie istnieją jeszcze compose.yaml/Dockerfile (`.env.example` dodano w F-001). Nie uruchomiono Compose. Wszystkie polecenia poniżej są docelowe po F-001/F-002.** Host wymaga Docker Engine/Desktop + Compose v2, nie lokalnego PHP/MariaDB/Node.

## Usługi i pliki

| Usługa | Projekt obrazu/konfiguracji | Dostęp i wolumeny | Gotowość |
|---|---|---|---|
| app | docker/php/Dockerfile: PHP 8.3-FPM, Composer 2, pdo_mysql, mbstring, intl, zip, bcmath, opcache, xml/dom dla testów | /var/www/html bind repo; vendor named volume;9000 tylko sieć | php-fpm ping; DB gotowa przed aplikacją |
| nginx | docker/nginx/default.conf, stabilny Nginx przypięty digestem | localhost:8080→80, repo read-only, tylko public jako root; fastcgi app:9000 | /up przez app; start po app |
| node | docker/node/Dockerfile: Node 22.x≥22.12 + npm | repo bind, node_modules named volume; localhost:5173 dla HMR | Vite HTTP; dev profile |
| db | mariadb:11.4 z przypiętym patchem/digestem w implementacji | db_data volume;3306 bez portu hosta | healthcheck.sh --connect --innodb_initialized |

Sieć Compose prywatna; nazwy DNS app/db. PHP i Nginx widzą ten sam public/build. Nginx `try_files` kieruje do public/index.php, blokuje dotfiles oraz wykonanie dowolnych uploadów/PHP poza front controllerem. Nie udostępniać root repo przez HTTP. storage i bootstrap/cache zapisywalne przez app, nie chmod777.

depends_on service_healthy chroni cold start, lecz nie zastępuje obsługi utraty DB w runtime. FPM ping wymaga jawnej konfiguracji pool i klienta FastCGI w obrazie. Health /up ma listener sprawdzający DB krótkim SELECT1, bez ujawniania połączenia. Migracje wykonywane osobno; /up może być503 przed bootstrapem.

## Zmienne

`.env.example` (F-001) bez sekretów: APP_NAME=EduDziennik, APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8080, DB_CONNECTION=mariadb, DB_HOST=db, DB_PORT=3306, DB_DATABASE=edudziennik, DB_USERNAME=edudziennik, DB_PASSWORD=<lokalne>, SESSION_DRIVER=database, CACHE_STORE=database, QUEUE_CONNECTION=sync, SESSION_SECURE_COOKIE=false. APP_KEY generowany w bootstrapie. VITE_APP_NAME publiczne; nigdy DB_* ani sekrety z prefixem VITE_. Strefa serwera UTC, prezentacja Europe/Warsaw. SESSION_LIFETIME=120 minut (ASSUMPTION). Dodatkowo `INERTIA_DEVTOOLS_ENABLED=false`, bo DevTools Inertia zapisuje props stron na dysk; APP_LOCALE=pl.

MARIADB_DATABASE/USER/PASSWORD przekazane do db z lokalnego env; osobne losowe hasło root tylko dla bootstrapu serwera. Nie drukować wartości w logach CI. Production: APP_DEBUG=false, HTTPS URL, secure cookie=true, oddzielny DB user runtime i migrator. .env ignorowany przez Git.

## Bootstrap i polecenia — wszystkie PLANNED

F-001 zakłada wygenerowanie szkieletu w jednorazowym kontenerze PHP/Composer i Node. F-002 dodaje poniższy Compose, nie wymaga wcześniejszego lokalnego PHP. Po utworzeniu .env przez kopię .env.example i ustawieniu lokalnych sekretów:

```sh
docker compose build
docker compose up -d db
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan db:seed --class=DemoSeeder
docker compose run --rm node npm ci
docker compose --profile dev up -d app nginx node
```

DemoSeeder tylko local/testing, z DEMO_PASSWORD ustawionym prywatnie. Nie używać migrate:fresh poza dedykowaną bazą testową. Node dev nasłuchuje0.0.0.0:5173 wewnątrz kontenera, HMR host=localhost, allowedHosts jawne; port hosta związany127.0.0.1.

| Cel | Planowane polecenie |
|---|---|
| Start | `docker compose --profile dev up -d` |
| Stop bez utraty danych | `docker compose --profile dev down` |
| Rebuild | `docker compose --profile dev up -d --build` |
| Migracje | `docker compose exec app php artisan migrate` |
| Seed demo | `docker compose exec app php artisan db:seed --class=DemoSeeder` |
| PHPUnit | `docker compose exec app php artisan test` (jawny .env.testing z edudziennik_test) |
| Frontend test | `docker compose run --rm node npm run test:unit` |
| Lint PHP | `docker compose exec app vendor/bin/pint --test` |
| Node QA | `docker compose run --rm node npm run lint` / `npm run format:check` / `npm run typecheck` |
| Composer | `docker compose run --rm app composer <command>` |
| npm | `docker compose run --rm node npm <command>` |
| Logi | `docker compose logs --tail=100 app nginx node db` |
| Build assetów | `docker compose run --rm node npm run build` |

W wierszu Node QA każdą komendę uruchomić z tym samym prefiksem docker compose, nie na hoście. Skrypty npm zdefiniowane w [CI_CD](CI_CD.md). Kontener Playwright w CI ma przeglądarki i biblioteki systemowe; zwykły node dev nie gwarantuje uruchomienia przeglądarki.

## Produkcja

Wielostopniowy root Dockerfile (PLANNED): Composer dependency stage, Node asset build stage, PHP-FPM runtime bez dev dependencies, Nginx z identycznym public/build. Build nie potrzebuje produkcyjnej DB ani sekretów runtime. Jeśli starter zachowa Wayfinder, generowanie typów tras wykonuje etap PHP przed build JS; Node nie zakłada dostępnego PHP. MVP bez SSR, Vite dev server i node poza runtime.

Release ma lockfiles i identyfikator SHA; używa wolumenu storage i zewnętrznego sekretu APP_KEY. Nie nadpisywać APP_KEY podczas wdrożeń. Nie bind-mountować całego repo w produkcji. Usługi mogą działać na jednym hoście; konkretny hosting pozostaje OPEN QUESTION. Test gotowości i rollback: [CI_CD](CI_CD.md).
