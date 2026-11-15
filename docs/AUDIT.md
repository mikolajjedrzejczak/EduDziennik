# Audyt dokumentacji — 2026-11-15

**Stan: dokumentacja utworzona i po korektach spójności. Implementacja wszystkich32 funkcji: Planned.** Zakres audytu obejmuje architekturę, funkcje, zależności, model relacji, RBAC, HTTP, design i operacje. Nie jest to audyt bezpieczeństwa działającego systemu.

## Fakty wejściowe i dowody

- FACT: katalog wejściowy miał jedynie .DS_Store; `git status` potwierdził brak repozytorium Git. Nie było istniejącej aplikacji, testów, manifestów ani infrastruktury do zachowania.
- VERIFIED: Figma MCP odczytało15 stron,67 kanonicznych ramek ekranów na stronach04–11,101 zmiennych i15 stylów tekstowych. Odczytano2 efekty, bindingi i Auto Layout próbek komponentów. Obejrzano dziennik desktop17:620/mobile23:330; nie testowano wszystkich interakcji prototypu.
- VERIFIED: oficjalne źródła potwierdzają podstawowy zestaw PHP8.3/Laravel13/Inertia3/React19/Tailwind4. [TECH_STACK](architecture/TECH_STACK.md) zapisuje źródła i rozbieżności aktualnego startera. Resolver zależności aplikacji nie był uruchamiany.

## Kontrole wykonane na dokumentacji

| Kontrola | Wynik i granice |
|---|---|
| Komplet wymaganych plików |76 plików Markdown:4 wejściowe i72 w docs; wszystkie wymagane działy,32 specyfikacje oraz11 ADR |
| Feature schema |Każda specyfikacja ma21 wypełnionych sekcji, status Planned i P0/P1; szablon osobno |
| Graf zależności |32 węzły,75 krawędzi; bez brakujących ID i cykli; indeks zgodny ze specyfikacjami; roadmapa topologiczna |
| Trasy |64 unikalne nazwy oraz pary method+URL;31 nazw Requests z definicją wejścia;18 typów page props użytych w trasach z definicjami |
| Typy |Fragment TS z REQUESTS_RESPONSES skompilowany TypeScript5.7.3: `tsc --strict --noEmit --skipLibCheck`, exit0; sprawdza deklaracje, nie runtime Laravel/React |
| Linki |Automatycznie sprawdzono 480 względnych linków wszystkich Markdown; brak nieistniejących celów po poprawkach |
| Figma |167 linków node-id porównano z zapisanym wynikiem MCP, bez zmyślonych IDs; luki opisane jawnie |
| Baza/ERD |Porównanie19 FK domenowych diagramu z tabelami; parser Mermaid11.12.0 zaakceptował diagram (diagramType=er); RESTRICT/soft delete i reguły aplikacyjne opisane oddzielnie. Nie renderowano diagramu wizualnie |
| Kolory |Obliczono WCAG luminance/contrast dla20 par (10 Light,10 Dark): wszystkie≥4.5; Light success/success-bg=5.15. Nie jest to pełne potwierdzenie WCAG aplikacji |
| Status wykonania |Docker/CI/CD/komendy/migracje/testy aplikacji konsekwentnie PLANNED; brak fikcyjnego deployu |

## Poprawione niespójności

1. **Stack startera**: oddzielono potwierdzony core od aktualnego Vite Plus i dodatków. ADR-010 wymaga adaptacji do zatwierdzonego Vite/ESLint/Prettier; nie obniża wersji Laravel.
2. **Figma vs domena**: stare wagi1–3, Laravel10, users.class_id, polskie trasy i grade_audit zastąpiono wymaganymi wagami1–5, stackiem i historycznym modelem. Rozstrzygnięcia są w [FIGMA](design/FIGMA.md), nie ukryte w implementacji.
3. **Dziennik**: dodano assessments jako wspólne kolumny; grade zachowuje jednostkowe wartości. Usunięta komórka jest oznaczona deletedAssessmentIds, więc frontend nie oferuje niedozwolonego ponownego create.
4. **Historia**: S/T/admin obowiązują także dla soft-deleted grades i aktorów. Dodano filtr student_enrollment_id oraz jego DTO do linku historii konkretnego ucznia. Usunięte konto ma admin show read-only.
5. **Konta**: pełny CRUD obejmuje osobny show w F-302; ochrona ostatniego admina używa stałego domain lock, nie wyłącznie policzenia wierszy bez serializacji.
6. **Formularze i DTO**: uzupełniono selectedClassId/enrollmentPage, opcje uczniów dla członkostwa, selektory termów i ErrorProps. Kanoniczne przekierowania grade form mają sprawdzony assignment_id.
7. **Błędy**: konflikt wersji przez errors.conflict (bez kolizji z protokołem Inertia409); błędny filtr GET przekierowuje do oczyszczonego URL, żeby nie tworzyć pętli; strona poza zakresem normalizowana.
8. **Średnie**: wspólny zakres/half-up/null, brak średniej średnich; trzy warianty fixture są niezależne. Liczniki dashboardów mają jawne definicje.
9. **Zależności**: walidacja, stany i harness testów są wcześnie; student/shared read przed mutacjami, zaawansowane filtry nie blokują podstawowego MVP. F-502 może wejść wcześniej po harness, ale F-503 po wymaganych funkcjach.
10. **Tokeny**: success odczytano z aktualnej zmiennej #137537, nie starej etykiety planszy. Mapa rozróżnia istniejące ekrany od planowanych adaptacji.

## Otwarte kwestie — nie blokują dokumentacji

| Kwestia | Właściciel / bramka |
|---|---|
| ~~Dokładny commit startera, patche i lockfiles Vite/QA~~ | Rozwiązane w F-001 (2026-11-15): [TECH_STACK](architecture/TECH_STACK.md#utrwalone-wersje-f-001), do przeglądu |
| Dedykowane user show, admin grade CRUD/history, formularze organizacji | Przegląd adaptacji UI w powiązanej funkcji; brak obietnicy zgodności z nieistniejącymi ramkami |
| Hosting, domena, TLS i backup destination | Właściciel wdrożenia przed faktycznym CD w F-503 |
| Produkcyjna retencja, anonimizacja i odpowiedzialność za dane | Właściciel danych przed wprowadzeniem rzeczywistych danych; demo tylko fikcyjne |
| Plusy/minusy, oceny końcowe, seria i Dark UI | Poza obecnym MVP; osobna decyzja i specyfikacje |

ASSUMPTION: jedna szkoła,2 semestry, współprowadzenie dozwolone,1 rola/konto, brak publicznej rejestracji i automatycznej wysyłki haseł, profil nie zmienia emaila. Założenia są jawne w dokumentach domenowych i nie rozszerzają stacku. Przed wdrożeniem mogą zostać zmienione kontrolowanym ADR.

Nie uruchomiono aplikacji, PHPUnit/Vitest/Playwright jej kodu, Docker ani GitHub Actions. Nie zainicjalizowano Git i nie tworzono PR. Narzędzia kontroli dokumentacji działały w katalogu tymczasowym, bez dodania zależności aplikacji do repo. Najbliższy krok: [F-001](features/F-001.md).
