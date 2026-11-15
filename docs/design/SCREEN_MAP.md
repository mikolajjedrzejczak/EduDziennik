# Mapa ekranów — potwierdzone ramki i planowana implementacja

Źródło [Figma](FIGMA.md), odczyt 2026-11-15. Frame IDs są VERIFIED; route/page i Feature IDs to DECISION/PLANNED. Nazwa strony Inertia odpowiada plikowi `resources/js/pages/<name>.tsx`. URL trasy rozwiń według [ROUTES](../http/ROUTES.md). Ramki stanów nie są osobnymi endpointami.

| Figma frame (dokładna nazwa i ID) | Laravel route | React page | Rola | Feature ID |
|---|---|---|---|---|
| [01 Logowanie / Domyślny — 13:2 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=13-2) | `login` | `auth/login` | guest | F-004 |
| [01 Logowanie / Błędy walidacji — 13:86 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=13-86) | `login` | `auth/login` | guest | F-004 |
| [01 Logowanie / Nieprawidłowe dane — 13:185 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=13-185) | `login` | `auth/login` | guest | F-004 |
| [01 Logowanie / Ładowanie — 13:274 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=13-274) | `login` | `auth/login` | guest | F-004 |
| [18 Brak dostępu (403) — 13:363 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=13-363) | `brak osobnej trasy` | `shared/error` | wszystkie | F-404 |
| [19 Nie znaleziono (404) — 13:403 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=13-403) | `brak osobnej trasy` | `shared/error` | wszystkie | F-404 |
| [02 Pulpit ucznia — 14:2 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=14-2) | `student.dashboard` | `student/dashboard` | student | F-101 |
| [03 Moje oceny — Matematyka — 14:154 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=14-154) | `student.grades.index` | `student/grades/index` | student | F-102 |
| [04 Szczegóły oceny — 14:434 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=14-434) | `student.grades.show` | `shared/grades/show` | student | F-103 |
| [05 Historia zmian — 14:604 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=14-604) | `student.history.index` | `shared/history/index` | student | F-104 |
| [17 Profil i hasło — 14:996 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=14-996) | `profile.edit` | `settings/profile` | auth | F-401 |
| [Tablet — 02 Pulpit ucznia (1024) — 38:8813 (1024×768)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=38-8813) | `student.dashboard` | `student/dashboard` | student | F-101 |
| [06 Pulpit nauczyciela — 17:2 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=17-2) | `teacher.dashboard` | `teacher/dashboard` | teacher | F-201 |
| [07 Moje klasy — 17:352 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=17-352) | `teacher.gradebooks.index` | `teacher/gradebooks/index` | teacher | F-202 |
| [08 Dziennik ocen — 2A Matematyka — 17:620 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=17-620) | `teacher.gradebooks.show` | `shared/gradebooks/show` | teacher | F-203 |
| [09 Uczeń w klasie — Jan Kowalski — 17:1204 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=17-1204) | `teacher.students.show` | `shared/students/show` | teacher | F-203 |
| [10 Wystaw ocenę — formularz — 18:542 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-542) | `teacher.grades.create` | `shared/grades/create` | teacher | F-204 |
| [10 Wystaw ocenę — błędy walidacji — 18:927 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-927) | `teacher.grades.create` | `shared/grades/create` | teacher | F-204 |
| [10 Wystaw ocenę — zapisano — 18:1320 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-1320) | `teacher.grades.create` | `shared/grades/create` | teacher | F-204 |
| [11 Edytuj ocenę — formularz — 18:1741 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-1741) | `teacher.grades.edit` | `shared/grades/edit` | teacher | F-205 |
| [11 Usuń ocenę — potwierdzenie — 18:2282 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-2282) | `teacher.grades.edit` | `shared/grades/edit` | teacher | F-206 |
| [11 Usunięto ocenę — dziennik — 18:2846 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-2846) | `teacher.gradebooks.show` | `shared/gradebooks/show` | teacher | F-203 |
| [11 Usunięcie — błąd serwera — 18:3278 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-3278) | `teacher.grades.edit` | `shared/grades/edit` | teacher | F-206 |
| [11 Zapisano zmianę — historia ucznia — 18:3707 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=18-3707) | `teacher.grades.history` | `shared/history/index` | teacher | F-104 |
| [Tablet — 08 Dziennik ocen (1024) — 38:11292 (1024×768)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=38-11292) | `teacher.gradebooks.show` | `shared/gradebooks/show` | teacher | F-203 |
| [12 Pulpit administratora — 19:2 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=19-2) | `admin.dashboard` | `admin/dashboard` | admin | F-301 |
| [13 Użytkownicy — lista (filtr 2A) — 19:379 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=19-379) | `admin.users.index` | `admin/users/index` | admin | F-302 |
| [15 Usuń użytkownika — potwierdzenie — 19:1067 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=19-1067) | `admin.users.index` | `admin/users/index` | admin | F-305 |
| [15 Usunięto użytkownika — lista — 19:1798 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=19-1798) | `admin.users.index` | `admin/users/index` | admin | F-302 |
| [14 Utworzono użytkownika — lista — 19:2490 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=19-2490) | `admin.users.index` | `admin/users/index` | admin | F-302 |
| [14 Dodaj użytkownika — formularz — 20:1261 (1440×1180)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=20-1261) | `admin.users.create` | `admin/users/create` | admin | F-303 |
| [14 Dodaj użytkownika — błędy walidacji — 20:1600 (1440×1180)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=20-1600) | `admin.users.create` | `admin/users/create` | admin | F-303 |
| [15 Edytuj użytkownika — Anna Nowak — 20:1891 (1440×1000)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=20-1891) | `admin.users.edit` | `admin/users/edit` | admin | F-304 |
| [16 Klasy i przedmioty — 20:2220 (1440×900)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=20-2220) | `admin.classes.index` | `admin/classes/index` | admin | F-306/F-307/F-308 |
| [Tablet — 13 Użytkownicy (1024) — 38:13515 (1024×768)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=38-13515) | `admin.users.index` | `admin/users/index` | admin | F-302 |
| [01 Logowanie — mobile — 25:2 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=25-2) | `login` | `auth/login` | guest | F-004 |
| [01 Logowanie — błędy walidacji (mobile) — 25:65 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=25-65) | `login` | `auth/login` | guest | F-004 |
| [01 Logowanie — nieprawidłowe dane (mobile) — 25:143 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=25-143) | `login` | `auth/login` | guest | F-004 |
| [01 Logowanie — ładowanie (mobile) — 25:211 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=25-211) | `login` | `auth/login` | guest | F-004 |
| [18 Brak dostępu (403) — mobile — 25:284 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=25-284) | `brak osobnej trasy` | `shared/error` | wszystkie | F-404 |
| [19 Nie znaleziono (404) — mobile — 25:327 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=25-327) | `brak osobnej trasy` | `shared/error` | wszystkie | F-404 |
| [02 Pulpit ucznia — mobile — 21:2 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-2) | `student.dashboard` | `student/dashboard` | student | F-101 |
| [03 Moje oceny — mobile — 21:76 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-76) | `student.grades.index` | `student/grades/index` | student | F-102 |
| [03 Przedmiot: Matematyka — mobile — 21:284 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-284) | `student.grades.index` | `student/grades/index` | student | F-102 |
| [04 Szczegóły oceny — mobile — 21:409 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-409) | `student.grades.show` | `shared/grades/show` | student | F-103 |
| [05 Historia zmian — mobile — 21:473 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-473) | `student.history.index` | `shared/history/index` | student | F-104 |
| [05 Historia — filtry (bottom sheet) — 21:616 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-616) | `student.history.index` | `shared/history/index` | student | F-104 |
| [17 Profil — mobile — 21:856 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=21-856) | `profile.edit` | `settings/profile` | auth | F-401 |
| [06 Pulpit nauczyciela — mobile — 23:14 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-14) | `teacher.dashboard` | `teacher/dashboard` | teacher | F-201 |
| [07 Moje klasy — mobile — 23:199 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-199) | `teacher.gradebooks.index` | `teacher/gradebooks/index` | teacher | F-202 |
| [08 Dziennik ocen — mobile (karty uczniów) — 23:330 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-330) | `teacher.gradebooks.show` | `shared/gradebooks/show` | teacher | F-203 |
| [09 Uczeń w klasie — mobile — 23:705 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-705) | `teacher.students.show` | `shared/students/show` | teacher | F-203 |
| [10 Wystaw ocenę — arkusz — 23:796 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-796) | `teacher.grades.create` | `shared/grades/create` | teacher | F-204 |
| [10 Wystaw ocenę — błędy walidacji — 23:1254 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-1254) | `teacher.grades.create` | `shared/grades/create` | teacher | F-204 |
| [10 Zapisano ocenę — mobile — 23:1718 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-1718) | `teacher.gradebooks.show` | `shared/gradebooks/show` | teacher | F-203 |
| [10 Wpisywanie serii — Kartkówka 23.10 — 23:2099 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-2099) | `brak osobnej trasy` | `brak — odroczone` | teacher | P2 poza MVP |
| [11 Edytuj ocenę — mobile — 23:2215 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-2215) | `teacher.grades.edit` | `shared/grades/edit` | teacher | F-205 |
| [11 Usuń ocenę — potwierdzenie — 23:2312 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=23-2312) | `teacher.grades.edit` | `shared/grades/edit` | teacher | F-206 |
| [12 Pulpit administratora — mobile — 24:2 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-2) | `admin.dashboard` | `admin/dashboard` | admin | F-301 |
| [13 Użytkownicy — mobile — 24:197 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-197) | `admin.users.index` | `admin/users/index` | admin | F-302 |
| [13 Użytkownicy — filtry (bottom sheet) — 24:404 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-404) | `admin.users.index` | `admin/users/index` | admin | F-302 |
| [14 Dodaj użytkownika — mobile — 24:685 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-685) | `admin.users.create` | `admin/users/create` | admin | F-303 |
| [14 Dodaj użytkownika — błędy (mobile) — 24:807 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-807) | `admin.users.create` | `admin/users/create` | admin | F-303 |
| [15 Edytuj użytkownika — mobile — 24:948 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-948) | `admin.users.edit` | `admin/users/edit` | admin | F-304 |
| [15 Usuń konto — potwierdzenie (mobile) — 24:1044 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-1044) | `admin.users.index` | `admin/users/index` | admin | F-305 |
| [16 Klasy i przedmioty — mobile — 24:1180 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-1180) | `admin.classes.index` | `admin/classes/index` | admin | F-306/F-307/F-308 |
| [16 Klasa 2A — przydziały (mobile) — 24:1328 (390×844)](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=24-1328) | `admin.assignments.index` | `admin/assignments/index` | admin | F-308 |

## Wspólne stany i prototypy

| Node | Zastosowanie |
|---|---|
|29:2 /29:59|Loading /empty, F-404|
|29:89 /29:405|Server/session/conflict i mobile offline, F-404|
|29:135 /29:219 /29:337|Sukces, walidacja, potwierdzenie destrukcji|
|26:3→26:383 /26:4947→26:5219|Przepływ A: login, oceny, historia desktop/mobile|
|26:627→26:1799 /26:5293→26:5477|Przepływ B: wystawianie oceny|
|26:2064→26:3334 /38:6156→38:6640|Przepływ C: edycja/usunięcie|
|26:3599→26:4057 /38:6924→38:7232|Przepływ D: nowe konto|
|26:4272→26:4727 /38:7375→38:7593|Przepływ E: usunięcie konta|

Powyższe ID są odczytane; działanie wszystkich interakcji prototypowych nie było testowane. Kanoniczne UI pochodzi ze stron04–11, a nie kopii w prototypach.

## Jawne luki i sposób realizacji

- **F-302 user show**: nie znaleziono dedykowanej ramki szczegółów użytkownika w stronach04–11. PLANNED `admin/users/show`: kompozycja read-only z danych formularza20:1891/24:948 i PageHeader, wymaga przeglądu wizualnego. Nie utożsamiać show z edit ani pomijać wymaganego szczegółu CRUD.
- **Admin grade CRUD/history (F-203–206/F-103–104)**: brak dedykowanych ekranów admina. Wspólne pages `shared/gradebooks/show`, `shared/grades/*`, `shared/history/index`, shell admina i selector kontekstu. Referencje nauczyciela17:620/23:330,18:542/23:796,18:1741/23:2215 oraz historii14:604/21:473 są referencjami adaptacji, nie potwierdzeniem istnienia admin frames.
- **Teacher history**: student detail17:1204/23:705 i zapis18:3707 pokazują kontekst; oddzielna lista korzysta z14:604/21:473 z zakresem T.
- **AssessmentForm, transfer, pełny CRUD klas/przedmiotów**:20:2220/24:1180/24:1328 pokazują organizację, nie każdą mutację. Formularze z istniejących Input/Dialog; przed Done potrzebny przegląd adaptacji.
- **Klasy i przedmioty**: główna ramka mapuje `admin.classes.index`; zakładka przedmiotów `admin.subjects.index`→`admin/subjects/index`, przydziały `admin.assignments.index`→`admin/assignments/index`. To współdzielony ekran organizacji, nie trzy odkryte osobne ramki. F-307 (adaptacja do przeglądu): osobna strona `admin/subjects/index` bez zakładek Klasy/Przydziały (te trasy jeszcze nie istnieją), stylem tabeli i listy mobilnej z 20:2220/24:1180; dialogi dodaj/zmień nazwę/archiwizuj i filtr aktywne/zarchiwizowane nie mają ramek.
- **Tryb serii23:2099**, Dark37:379/37:530/37:793 i alternatywy nawigacji40:*/41:* nie są P0/P1.

Luki nie blokują modeli i backendu. Nie wolno oznaczyć pełnej zgodności z Figmą dla adaptacji bez jej odrębnego przeglądu. Figma nie została automatycznie rozszerzona w ramach zadania dokumentacyjnego.
