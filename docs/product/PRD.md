# Wymagania produktu

EduDziennik pomaga nauczycielowi prowadzić oceny, uczniowi rozumieć wyniki i ich zmiany, a administratorowi zarządzać kontami oraz organizacją nauczania. Projekt akademicki jednej szkoły, bez multi-tenancy. Wszystkie funkcje są PLANNED.

## Wymagania akademickie

| ID | Wymaganie | Realizacja |
|---|---|---|
| A1 | Dziennik ocen | F-203–206, F-102–104 |
| A2 | Co najmniej trzy poziomy uprawnień | F-005, student/teacher/admin |
| A3 | Widoki zależne od roli | F-007, F-101, F-201, F-301 |
| A4 | Pełny CRUD użytkowników, w tym szczegóły | F-302–305; szczegóły w F-302 |
| A5 | Walidacja formularzy | F-402 i testy każdego zapisu |
| A6 | MVC | Laravel Controllers + Eloquent + widoki Inertia/React |
| A7 | PHP 8.x, HTML5, CSS3, MariaDB | PHP 8.3, semantyczny HTML, Tailwind 4, MariaDB 11.4 |
| A8 | Laravel ≥10 | Laravel 13 |

## MVP

Logowanie/wylogowanie, własne hasło i dozwolone dane profilu; trzy pulpity. Administrator tworzy, czyta (lista i szczegóły), edytuje i logicznie usuwa konta, zarządza klasami, członkostwem uczniów, przedmiotami i przydziałami nauczycieli. Nauczyciel widzi dziennik przypisanego kontekstu i zapisuje oceny 1–6, wagi 1–5. Uczeń widzi własne oceny i historię. Administrator zarządza wszystkimi ocenami przez wspólne mechanizmy domenowe. Każda mutacja oceny ma trwały audyt. Średnie są ważone i wspólne dla ekranów.

Lata i semestry istnieją od początku, ale ich konfiguracja odbywa się kontrolowanym seederem/komendą operatorską w F-003; nie projektujemy osobnego panelu kalendarza. Kategorie są słownikiem seedowanym. Kolumny dziennika (`assessments`) są częścią F-203 i F-204, nie osobnym modułem egzaminacyjnym.

Bazowa paginacja i filtry kontekstu są częścią MVP. Rozszerzone wyszukiwanie, sortowanie i kombinacje filtrów: F-403 (P1). Dodatkowe statystyki dashboardów P1; podstawowy routing i pusty pulpit ról istnieją już w F-007. CI i gotowość wdrożenia P1, lecz wymagane przed przekazaniem projektu. P2: tryb ciemny i seryjne wpisywanie ocen — odroczone bez obietnicy realizacji w obecnych 32 funkcjach.

## Wymagania niefunkcjonalne

WCAG AA, klawiatura i 390/1024/1440 px. Polski interfejs, strefa Europe/Warsaw, zapis timestampów UTC. Listy serwerowo stronicowane; brak nieograniczonych tablic ocen w props. ASSUMPTION do pomiaru: przy 500 uczniach i 50 tys. ocen typowy GET p95 <800 ms po rozgrzaniu w uzgodnionym środowisku; to cel, nie zmierzony wynik. Brak utraty audytu przy błędzie, brak odczytu cudzych danych, odtwarzalny build i backup.

Poza zakresem: frekwencja, plan lekcji, płatności, komunikatory, rodzice, zewnętrzne systemy szkolne, publiczna rejestracja, powiadomienia email, oceny końcowe i plusy/minusy.

Koniec MVP: A1–A8, wszystkie P0 i ich kryteria, brak błędów autoryzacji, poprawne historyczne relacje, działające desktop/mobile. Przekazanie: dodatkowo [kryteria odbioru](../testing/ACCEPTANCE_CRITERIA.md). Figma nie definiuje walidacji ani zabezpieczeń wbrew tym wymaganiom.
