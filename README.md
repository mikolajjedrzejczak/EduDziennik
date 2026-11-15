# EduDziennik

Elektroniczny dziennik ocen: uczeń, nauczyciel i administrator. Dokumentacja w języku polskim; kod i identyfikatory po angielsku.

**Stan 2026-11-15: szkielet F-001 i środowisko Docker F-002.** Jedna aplikacja Laravel 13 + Inertia 3 + React 19, bez funkcji dziennika. Docelowy schemat i migracje techniczne należą do F-003, CI do F-502.

Uruchomienie bez lokalnego PHP, Composer, Node i MariaDB — wymagany działający Docker z Compose v2+:

```sh
cp .env.example .env
# Ustaw prywatne, losowe DB_PASSWORD i MARIADB_ROOT_PASSWORD w .env.
./scripts/bootstrap.sh
```

Bootstrap nie wykonuje migracji ani seedów. Health: `http://localhost:8080/up`, Vite: `http://localhost:5173`. Strony korzystające z sesji/cache DB wymagają migracji przygotowanych w F-003. Procedura, polecenia QA, konflikty portów i test trwałości: [Docker](docs/infrastructure/DOCKER.md). Dowody kontroli: [F-001](docs/features/F-001.md#22-raport-realizacji-2026-11-15), [F-002](docs/features/F-002.md#22-raport-realizacji-2026-11-15).

## Od czego zacząć

1. [Instrukcje agentów](AGENTS.md).
2. [Architektura](docs/architecture/OVERVIEW.md) i [zweryfikowany stack](docs/architecture/TECH_STACK.md).
3. [PRD](docs/product/PRD.md), [RBAC](docs/product/RBAC.md), [reguły domenowe](docs/database/BUSINESS_RULES.md).
4. [Indeks funkcji](docs/features/FEATURE_INDEX.md) i [roadmapa](docs/product/ROADMAP.md).
5. [Mapa ekranów Figmy](docs/design/SCREEN_MAP.md).

Kolejny etap: [F-003](docs/features/F-003.md), docelowa baza danych.

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
