# EduDziennik

Elektroniczny dziennik ocen: uczeń, nauczyciel i administrator. Dokumentacja w języku polskim; kod i identyfikatory po angielsku.

**Stan 2026-11-15: szkielet aplikacji (F-001, do przeglądu).** Repozytorium zawiera zaadaptowany starter Laravel 13 + Inertia 3 + React 19 (TypeScript strict, Vite 8, Tailwind 4, ESLint, Prettier) z lockfiles, bez funkcji dziennika i bez migracji domenowych. Brak Git, Docker Compose (F-002) i CI (F-502). Pozostałe funkcje mają status `Planned`; szczegóły i dowody kontroli w [F-001](docs/features/F-001.md#22-raport-realizacji-2026-11-15).

Wymagane runtime: PHP 8.3, Composer 2, Node 22 (≥22.12; `engine-strict`). Do czasu F-002 kontrole uruchamiamy w jednorazowych kontenerach:

```sh
composer install && cp .env.example .env && php artisan key:generate
composer wayfinder                       # typy tras dla TS (przed buildem)
composer lint:check && composer types:check && composer test
npm ci && npm run lint && npm run format:check && npm run typecheck && npm run build
```

## Od czego zacząć

1. [Instrukcje agentów](AGENTS.md).
2. [Architektura](docs/architecture/OVERVIEW.md) i [zweryfikowany stack](docs/architecture/TECH_STACK.md).
3. [PRD](docs/product/PRD.md), [RBAC](docs/product/RBAC.md), [reguły domenowe](docs/database/BUSINESS_RULES.md).
4. [Indeks funkcji](docs/features/FEATURE_INDEX.md) i [roadmapa](docs/product/ROADMAP.md).
5. [Mapa ekranów Figmy](docs/design/SCREEN_MAP.md).

Pierwsza funkcja: [F-001](docs/features/F-001.md) (zrealizowana, do przeglądu). Następnie F-002 i F-003. Polecenia uruchomienia w [Docker](docs/infrastructure/DOCKER.md) są **planowane**, nie działają w obecnym katalogu.

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

[Wynik audytu i otwarte kwestie](docs/AUDIT.md). Nie utworzono pustych katalogów przyszłej aplikacji ani fikcyjnych plików infrastruktury.
