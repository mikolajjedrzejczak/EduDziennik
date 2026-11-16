# EduDziennik

Elektroniczny dziennik ocen: uczeń, nauczyciel i administrator. Dokumentacja w języku polskim; kod i identyfikatory po angielsku.

**Stan 2026-11-15: F-001, F-002, fundament bazy F-003 oraz harness testów F-501 do przeglądu.** Jedna aplikacja Laravel 13 + Inertia 3 + React 19. Model czasowy, konfiguracja operatorska i fixture są zaimplementowane; ekrany dziennika pozostają w kolejnych funkcjach. Workflow CI (F-502) jest w `.github/workflows/ci.yml`; [opis](docs/infrastructure/CI_CD.md).

Uruchomienie bez lokalnego PHP, Composer, Node i MariaDB — wymagany działający Docker z Compose v2+:

```sh
cp .env.example .env
# Ustaw prywatne, losowe DB_PASSWORD i MARIADB_ROOT_PASSWORD w .env.
./scripts/bootstrap.sh
```

Bootstrap nie wykonuje migracji ani seedów. Health: `http://localhost:8080/up`, Vite: `http://localhost:5173`. Po bootstrapie uruchom `docker compose exec app php artisan migrate`. Konfiguracja lat i seed demonstracyjny: [procedura F-003](docs/database/SEEDING.md). Procedura, polecenia QA, konflikty portów i test trwałości: [Docker](docs/infrastructure/DOCKER.md). Dowody kontroli: [F-001](docs/features/F-001.md#22-raport-realizacji-2026-11-15), [F-002](docs/features/F-002.md#22-raport-realizacji-2026-11-15).

## Od czego zacząć

1. [Instrukcje agentów](AGENTS.md).
2. [Architektura](docs/architecture/OVERVIEW.md) i [zweryfikowany stack](docs/architecture/TECH_STACK.md).
3. [PRD](docs/product/PRD.md), [RBAC](docs/product/RBAC.md), [reguły domenowe](docs/database/BUSINESS_RULES.md).
4. [Indeks funkcji](docs/features/FEATURE_INDEX.md) i [roadmapa](docs/product/ROADMAP.md).
5. [Mapa ekranów Figmy](docs/design/SCREEN_MAP.md).

Weryfikacja fundamentu: [F-003](docs/features/F-003.md). Testy backendu wymagają dedykowanej bazy `edudziennik_test`; [utworzenie i polecenia](docs/database/SEEDING.md#testy-mariadb).

Test harness F-501: `./scripts/test.sh` po bootstrapie i utworzeniu testowej bazy. PHPUnit/MariaDB, Vitest/RTL, Playwright formularzy oraz smoke zbudowanych assetów; [polecenia, izolacja i raporty](docs/testing/TEST_STRATEGY.md#harness-f-501).

## Mapa dokumentacji

| Katalog | Zawartość |
|---|---|
| `docs/architecture` | Granice MVC, stack, frontend, backend, bezpieczeństwo |
| `docs/product` | Wymagania, uprawnienia, przepływy, kolejność prac |
| `docs/database` | Schemat, ERD, reguły, dane demonstracyjne |
| `docs/http` | Trasy, typy i kontrakty formularzy, błędy |
| `docs/design` | Źródła Figmy, tokeny, komponenty, responsywność |
| `docs/features` | 32 konkretne specyfikacje i indeks zależności |
| `docs/infrastructure` | Projekt Docker i CI/CD |
| `docs/testing` | Strategia testów, kryteria i Definition of Done |
| `docs/adr` | Decyzje architektoniczne |
| `docs/templates` | Szablon kolejnych funkcji |

[Wynik audytu i otwarte kwestie](docs/AUDIT.md).
