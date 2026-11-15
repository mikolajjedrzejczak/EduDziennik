# Roadmapa implementacji

PLANNED, bez dat udających estymację. Kolejność poniżej jest topologiczna względem FEATURE_INDEX. Wspólny odczyt ocen jest wcześniej niż mutacje, żeby create/edit/delete mogły od razu przekierować do istniejących szczegółów i historii. To doprecyzowanie ogólnych faz briefu, nie przesunięcie bezpieczeństwa na koniec.

| Etap | Kolejność Feature IDs | Zakres | Bramka ukończenia |
|---|---|---|---|
| 1. Fundament i środowisko | F-001, F-002, F-402 | Szkielet, Docker i konwencja walidacji. | Instalacja/build i Compose cold start; wersje utrwalone. |
| 2. Model i harness testów | F-003, F-501 | Migracje, fixture i uruchamialne testy od początku. | MariaDB constraints, poprawne fixture31/7 i izolacja testowej bazy. |
| 3. Logowanie i RBAC | F-004, F-005 | Sesje, aktywność konta, role i scopes. | Login/logout3 role i próby IDOR bez ujawnienia danych. |
| 4. Design system i powłoki | F-006, F-404, F-007 | Tokeny, stany i nawigacja. | Porównanie390/1024/1440, fokus i odrębne podstawowe pulpity. |
| 5. Administracja i organizacja | F-306, F-307, F-308, F-302, F-303, F-304, F-305 | Klasy, przedmioty, przypisania i pełny CRUD kont. | Transfer bez utraty ocen; atomowe konto/relacje; ochrona ostatniego admina. |
| 6. Wspólny odczyt ocen | F-102, F-103, F-104 | Student grades, wspólne szczegóły i historia na fixture. | Odczyt scoped, średnie i usunięta ocena z historią. |
| 7. Praca nauczyciela i admina z ocenami | F-202, F-203, F-204, F-205, F-206 | Dziennik, assessments i mutacje współdzielone przez role. | Pełny przepływ grade→audit; rollback i konflikt wersji. |
| 8. Podsumowania i rozszerzenia | F-101, F-201, F-301, F-401, F-403 | Pulpity, profil, wyszukiwanie i zaawansowane filtry. | Wszystkie widoki pokazują zgodne agregaty; filtr URL i revoke sesji. |
| 9. CI i gotowość wydania | F-502, F-503 | Automatyzacja, finalny odbiór, backup/restore, plan hostingu. | Zielone checks, security/a11y/Figma review, restore drill; deployment tylko z rzeczywistym celem. |

## Zależności etapów

- **1. Fundament i środowisko**: wcześniej brak funkcji; repo i brief.
- **2. Model i harness testów**: wcześniej F-001, F-002.
- **3. Logowanie i RBAC**: wcześniej F-003, F-402.
- **4. Design system i powłoki**: wcześniej F-001, F-004, F-005.
- **5. Administracja i organizacja**: wcześniej F-003, F-005, F-007, F-402.
- **6. Wspólny odczyt ocen**: wcześniej F-003, F-007.
- **7. Praca nauczyciela i admina z ocenami**: wcześniej F-007, F-103, F-104, F-306, F-308, F-402.
- **8. Podsumowania i rozszerzenia**: wcześniej F-007, F-102, F-104, F-202, F-203, F-302, F-306, F-307, F-308, F-402.
- **9. CI i gotowość wydania**: wcześniej F-101, F-201, F-206, F-301, F-305, F-401, F-403, F-501.

F-501 jest szkieletem testów, nie odłożeniem testowania do ostatniej fazy. F-502 można wdrożyć wcześniej po F-501, gdy powstanie repo GitHub; F-503 zawsze po wszystkich wskazanych zależnościach. Bazowe sprawdzanie/lint/test lokalne obowiązuje przed CI. P1 nie znaczy opcjonalne przed ostatecznym przekazaniem. Nie zaczynać funkcji bez działających zależności; seedery służą testom wcześniejszego odczytu, nie zastępują CRUD.

Najbliższy krok: F-001, adaptacja startera do zatwierdzonego stacku i zapis dokładnego SHA/lockfiles. Po nim F-002. Nie implementować wszystkich modułów naraz.
