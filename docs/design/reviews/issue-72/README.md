# Review #72 — mobilne przejście od przedmiotów do ocen

Status: implementacja i porównanie do odbioru; zatwierdzenie adaptacji przez właściciela projektu pozostaje OPEN QUESTION. Nie oznaczono F-102/F-007 jako Done.

VERIFIED (2026-11-17): Figma MCP `get_design_context` wraz z renderami [21:76](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-76) i [21:284](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-284); odczyt Auto Layout, nagłówków Root/Detail, rozmiarów wierszy i tokenów. Zrzuty poniżej pochodzą z Playwright/Chromium, zbudowanych assetów i izolowanej MariaDB 11.4, wyłącznie fikcyjne dane. To referencje do przeglądu, nie automatycznie zatwierdzone goldeny ani test zgodności pikselowej z Figmą.

| Widok | Zrzut |
|---|---|
| Lista 12 przedmiotów, 390×844 | [subjects-390.png](subjects-390.png) |
| Wybrany ostatni przedmiot, 390×844 | [subject-detail-390.png](subject-detail-390.png) |
| Lista, 320×844 | [subjects-320.png](subjects-320.png) |
| Przedmiot, 320×844 | [subject-detail-320.png](subject-detail-320.png) |
| Rozwinięte filtry, CSS zoom 200%, viewport640×844 | [grades-css-zoom-200.png](grades-css-zoom-200.png) |

Porównanie: zachowano osobne list/detail, tytuł i avatar, przycisk powrotu, listę z chevronami, średnią i objaśnienie, tokeny oraz dolną nawigację. Wiersze listy mają min.72px, nagłówek min.56px i przycisk powrotu44px. Przy320px długie tytuły zawijają się zamiast ucinać; wysokości mogą rosnąć. Przycisk filtrów ma48px. Zrzuty nie zawierają fikcyjnej systemowej belki iOS z makiety.

DECISION — adaptacja do odbioru:

- Kontrakt `SubjectSummary` zawiera licznik/średnią, bez wszystkich ocen: lista pokazuje licznik zamiast chipów. Nie pobieramy ani nie wyliczamy dodatkowych ocen w React.
- Pojedynczy semestr wybierany selectem; brak „Cały rok”. Zmiana semestru na telefonie działa od razu. Dodatkowe filtry wymagane kontraktem dostępne w rozwijanym panelu, także w szczególe.
- Avatar prowadzi do istniejącego profilu (z wylogowaniem); nie dodano pustego menu wielokropka z ramki Detail. Dane nauczyciela nie są dodawane do podsumowania przedmiotu; pokazujemy rzeczywisty zakres filtrów.
- Objaśnienie średniej opisuje algorytm, bez przykładowego równania z fikcyjnymi wartościami makiety. Data/waga i linki ocen zachowują istniejący komponent F-102/F-103.
- Tablet/desktop zachowują dwa panele; brak dedykowanej ramki tabletu. Pełnej zgodności wizualnej ani ręcznego odbioru czytnikiem nie deklarujemy.

Testy przejścia używają 12 przedmiotów i ostatniego wiersza: widoczna właściwa ocena bez szukania pod listą, fokus i przewinięcie przy wyborze, Back i Forward przeglądarki, przycisk powrotu, zachowanie semestru/sortowania/wyszukiwania, odświeżenie i bezpośredni URL. Osobny test CSS `zoom:2` sprawdza reflow i dostępność filtrów/powrotu przy efektywnej szerokości320px; nie zastępuje ręcznego testu natywnego zoomu przeglądarki200%.

VERIFIED — wykonane kontrole:

- `npm test`:146/146 (Node22.22.2, pełny Vitest/RTL); nowe testy Root/Detail, zachowania filtrów przy powrocie, fokusu i natychmiastowej zmiany semestru.
- `php artisan test --compact --filter=StudentGradesTest`:14/14,335 asercji (Docker PHP8.3, izolowana MariaDB11.4); własność ocen, odmowa obcych identyfikatorów i ról, autoryzacja opcji/agregatów. Pełnego PHPUnit nie uruchamiano.
- `npx playwright test tests/Browser/student-grades.spec.ts --project=shell`:17/17 (Node22.22.2, Chromium, lokalny PHP8.5.10, izolowana MariaDB11.4). Poza nowym przepływem: regresja filtrów/błędów320/390/1440, layout1024/1440 i odmowa obcego właściciela/roli. To wykonanie E2E użyło lokalnego PHP8.5; PHPUnit sprawdzono na docelowym PHP8.3.
- `npm run lint`, `npm run typecheck`, `npm run format:check`, `npm run build`, `vendor/bin/pint --test tests/Infrastructure/seed-student-grades.php`, `git diff --check`:sukces. Mechaniczny detektor Impeccable dla zmienionych komponentów:brak uwag.

OPEN QUESTION: zatwierdzenie powyższych adaptacji przez właściciela projektu. Niewykonane w tej korekcie: ręczny czytnik ekranu, natywny zoom przeglądarki200%, pełny PHPUnit, pozostałe suite Playwright i CI. Brak nowych zależności, migracji i zmian zakresu MVP.
