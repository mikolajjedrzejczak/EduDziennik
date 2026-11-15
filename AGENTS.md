# Instrukcje agentów EduDziennik

Obowiązują w całym repozytorium. Stan wyjściowy i status uruchomienia: [README](README.md).

## Architektura i źródła

PHP 8.3, Laravel 13, MariaDB 11.4 LTS; modularny monolit MVC + Services. React 19 + TypeScript strict + Inertia.js 3 + Vite. Tailwind CSS 4 jest głównym systemem stylów; shadcn/ui i Radix dostosowujemy do Figmy, ikony Lucide. Szczegóły wersji: [TECH_STACK](docs/architecture/TECH_STACK.md).

Laravel obsługuje routing, sesje, CSRF, walidację Form Requests i Policies. React jest widokiem; formularze przez `useForm`. Bez osobnych `frontend/` i `backend/`, React Router, domyślnego REST API, Sanctum, tokenów w localStorage, TanStack Query czy globalnego stanu bez potrzeby. Bez mikroserwisów i Repository dla każdego modelu.

Docelowe katalogi `resources/js/pages`, `components`, `layouts`, `hooks`, `lib`, `types` są małymi literami zgodnie ze starterem. PHP: `app/Http`, `Models`, `Policies`, `Services`, `Enums`; [granice warstw](docs/architecture/OVERVIEW.md).

Kolejność rozstrzygania konfliktów: wymagania akademickie → zatwierdzony stack → wymagania funkcjonalne i bezpieczeństwo → zaakceptowane ADR → domena i kontrakty → Figma dla wyglądu → zgodne konwencje kodu → preferencje agenta. Nie zmieniaj stacku bez zaakceptowanego ADR. Nowa zależność wymaga konkretnego uzasadnienia, analizy wersji i aktualizacji lockfile.

## Workflow pojedynczej funkcji

1. Przeczytaj te instrukcje, [OVERVIEW](docs/architecture/OVERVIEW.md), właściwą [specyfikację](docs/features/FEATURE_INDEX.md), [RBAC](docs/product/RBAC.md), [reguły bazy](docs/database/BUSINESS_RULES.md) i powiązane ADR.
2. Sprawdź istniejący kod i status zależności. Zaplanuj mały zakres plików, migracje, kontrakty i testy.
3. Dla UI odczytaj Figma MCP: [mapa](docs/design/SCREEN_MAP.md), design context, Auto Layout, desktop/mobile/tablet, komponenty i stany. Brak dostępu lub ramki oznacz jawnie; nie deklaruj zgodności bez porównania.
4. Implementuj pionowy fragment: Request → Policy → Service/Model → Inertia props → React. Nie kopiuj logiki domenowej do React. Backend autoryzuje także listy, statystyki, historię i opcje formularzy.
5. Uruchom adekwatne testy PHPUnit, Vitest/RTL i Playwright, lint, format check, typecheck oraz build. Testy uprawnień i odmowy dostępu są obowiązkowe dla danych chronionych.
6. Porównaj UI z Figmą. Zaktualizuj specyfikację, kontrakty i ADR przy zmianie zachowania. Raportuj wykonane kontrole i blokady.

## Nienaruszalne reguły

- Nie loguj haseł, sesji, pełnych formularzy ani danych uczniów. Brak sekretów w Git.
- Schemat zmieniaj migracjami. Zmiana oceny i audyt są jedną transakcją. Zakres nauczyciela obejmuje klasę, przedmiot i okres.
- Nie pomijaj błędów TypeScript, nie używaj `any` bez uzasadnienia, nie duplikuj komponentów i typów.
- Nie usuwaj testów w celu ukrycia błędu, nie rób niepowiązanych refaktoryzacji ani pozornych implementacji.
- Nie oznaczaj funkcji jako Done bez acceptance criteria i [Definition of Done](docs/testing/DEFINITION_OF_DONE.md).
- Nie twierdź, że testy, Docker lub CI działają, jeśli ich nie uruchomiono. Informuj o nieweryfikowanych wymaganiach.

Statusy dowodów: FACT — ustalony fakt; VERIFIED — sprawdzone wskazaną metodą; DECISION — decyzja projektowa; ASSUMPTION — założenie; OPEN QUESTION — kwestia otwarta; PLANNED — przyszła realizacja. Status feature nie jest statusem dokumentu.
