# Stack i weryfikacja wersji

VERIFIED 2026-11-15: sprawdzono oficjalne dokumenty i manifesty startera. To weryfikacja deklarowanej kompatybilności, **nie rozwiązania zależności ani uruchomienia aplikacji**. F-001 utrwala dokładne wersje w lockfiles i wykonuje build/testy.

| Obszar | Decyzja | Dowód / warunek |
|---|---|---|
| PHP / Laravel | PHP 8.3.x / Laravel 13.x | [Laravel releases](https://laravel.com/framework/docs/releases): minimum PHP 8.3 |
| Inertia / React | Adapter Laravel 3.x, React adapter 3.x, React i React DOM 19.x | [Starter kits](https://laravel.com/framework/docs/13.x/starter-kits) potwierdza ten zestaw |
| TypeScript | 5.x, strict | F-001: 5.9.3 (`~5.9.3`), zgodne z typescript-eslint `<6.1` |
| Style | Tailwind 4.x, `@tailwindcss/vite` 4.x | [Integracja Vite](https://tailwindcss.com/docs/installation/using-vite) |
| UI | shadcn/ui, Radix UI, Lucide React | Tylko używane komponenty; styl źródłowy z Figmy |
| Build | Standardowy Vite; Node 22.x co najmniej 22.12, npm | [Wymagania Vite](https://vite.dev/guide/); dopasować wersje plugin-react i laravel-vite-plugin podczas F-001 |
| Baza | MariaDB 11.4 LTS, InnoDB, Eloquent | [Laravel database](https://laravel.com/framework/docs/13.x/database): MariaDB 10.3+ |
| PHP QA | PHPUnit 12.5.x, Pint, Composer 2 | [PHPUnit 12](https://docs.phpunit.de/en/12.5/installation.html) wymaga PHP 8.3 |
| JS QA | Vitest, RTL, Playwright, ESLint, Prettier | Dokładne wersje kompatybilne z wybranym Vite/React ustali F-001; brak deklaracji zainstalowania |
| Runtime | Docker Compose v2, Nginx, PHP-FPM | Obrazy z konkretnym patchem/digestem w F-002; Node tylko build/dev |

## Adaptacja oficjalnego startera

FACT: [composer.json](https://raw.githubusercontent.com/laravel/react-starter-kit/main/composer.json) deklaruje PHP ^8.3, Laravel ^13.17, Inertia ^3.0 i PHPUnit ^12.5.23. [package.json](https://raw.githubusercontent.com/laravel/react-starter-kit/main/package.json) ma React ^19.2, Inertia ^3, Tailwind ^4, ale aliasuje Vite do Vite Plus oraz używa `vp check`.

DECISION: nie kopiować ruchomego `main` bez przeglądu. W F-001 wybrać i zapisać commit startera, dostosować do **standardowego Vite + ESLint + Prettier**, usunąć alias/override Vite Plus i nieużywane funkcje. Jest to zachowanie briefu, a nie zmiana stacku; [ADR-010](../adr/ADR-010.md). Zachować małe litery katalogów i model stron startera. Fortify jest dopuszczalnym mechanizmem sesyjnym startera, bez rejestracji publicznej, passkeys, zespołów, 2FA i integracji kont w MVP. Wayfinder opcjonalny tylko jeśli realnie używany przez wybrany starter; nie instalować dodatkowego routera.

F-001 sprawdza `composer validate --strict`, instalację na PHP 8.3, `composer check-platform-reqs`, peer dependencies npm, lint/typecheck/build. Nie używać `--ignore-platform-reqs`, `--force` ani `--legacy-peer-deps`, by maskować problem. Rzeczywista niezgodność → proponowany ADR, bez samowolnego downgrade stacku.

Opcjonalne: Zod dla złożonego formularza; `clsx`, `tailwind-merge`, CVA do wariantów. Backend Form Requests jest zawsze źródłem walidacji. Zabronione domyślnie: CSS Modules jako główny system, Bootstrap, MUI, Ant Design, styled-components, Emotion, Redux, React Router, TanStack Query, Sanctum, Redis, RabbitMQ, Kubernetes, mikroserwisy. Nowa zależność wymaga potrzeby, analizy utrzymania/licencji, zgodności oraz wpisu w dokumentacji.

## Utrwalone wersje (F-001)

VERIFIED 2026-11-15 instalacją z lockfiles w kontenerach PHP 8.3.36 / Node 22.23.5 i pełnym zestawem kontroli ([raport F-001](../features/F-001.md#22-raport-realizacji-2026-11-15)). Dokładne wersje są w `composer.lock` i `package-lock.json`; tabela to stan w chwili utrwalenia.

| Obszar | Wersja w lockfile | Uwagi |
|---|---|---|
| Starter | `laravel/react-starter-kit@012f4248f70e255e32ad93f540aca5fb7e8c96fe` | Commit z 2026-11-10 |
| Laravel / Inertia server | laravel/framework 13.42.0, inertiajs/inertia-laravel 3.7.0 | `config.platform.php = 8.3.0`: resolver nie wybierze pakietu wymagającego nowszego PHP |
| Sesja / trasy TS | laravel/fortify 1.42.0, laravel/wayfinder 0.1.23 | Fortify bez funkcji dodatkowych (`features: []`) |
| PHP QA | phpunit 12.5.44, pint 1.32.3, larastan 3.15.0 (PHPStan level 7) | Larastan pochodzi ze startera; `composer types:check` |
| React | react / react-dom 19.3.1, @inertiajs/react i @inertiajs/vite 3.11.0 | React Compiler: babel-plugin-react-compiler 1.0.0 przez @rolldown/plugin-babel 0.3.1 i @babel/core 7.29 (Babel 7 jawnie, plugin kompilatora zależy od @babel/types 7) |
| Build | vite 8.3.8 (rolldown 1.3.2), laravel-vite-plugin 3.2.1, @vitejs/plugin-react 6.3.0, @laravel/vite-plugin-wayfinder 0.1.11 | `engines.node >=22.12 <23`, `.npmrc engine-strict=true`, `ignore-scripts=true` |
| Style / UI | tailwindcss i @tailwindcss/vite 4.3.5, Radix, lucide-react 0.475.0 | Komponenty `components/ui` bez zmian do F-006 |
| JS QA | eslint 10.15.0, typescript-eslint 8.72.2, @eslint-react/eslint-plugin 5.30.2, eslint-plugin-react-hooks 7.1.1, prettier 3.10.1, prettier-plugin-tailwindcss 0.8.1 | Vitest/RTL/Playwright dodaje F-501 |

DECISION F-402: minimalny harness testów formularzy dodaje już teraz Vitest 5.1, RTL 16.3/DOM 10.4/user-event 14.6/jest-dom 6.9, jsdom 31.0 i Playwright 1.65. Pełny F-501 pozostaje otwarty. jsdom wymaga podniesienia minimalnego developerskiego Node 22 do 22.22.2; `package.json` to egzekwuje, obrazy Docker mają 22.23.5. Uzasadnienie, zgodność i licencje: [wzorzec F-402](../http/FORM_VALIDATION.md).

DECISION F-001: ESLint 9 jest oznaczony przez npm jako niewspierany, a `eslint-plugin-react` 7.37.5 (ostatnie wydanie 2025-04) nie deklaruje zgodności z ESLint 10. Zamiast obniżać ESLint lub wymuszać peer deps użyto utrzymywanego `@eslint-react/eslint-plugin` (preset `recommended-typescript`) oraz `eslint-plugin-react-hooks`. Lint obejmuje typy (`projectService`), zabrania `any` i wymusza `import type`. Wyłączone z lintu i Prettier: wygenerowane typy Wayfinder oraz prymitywy shadcn `components/ui` (jak w starterze).

DECISION F-001: Wayfinder działa jako generator w etapie PHP (`composer wayfinder`, wynik nieśledzony w Git). Plugin Vite uruchamia generator tylko w `vite` dev; `vite build` nie zakłada PHP i kończy się błędem, jeśli typy nie zostały wcześniej wygenerowane.
