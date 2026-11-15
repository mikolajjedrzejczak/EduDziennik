# Kryteria odbioru projektu

Każdy scenariusz Given/When/Then w32 [specyfikacjach](../features/FEATURE_INDEX.md) jest wiążący. Poniżej przekrojowy odbiór akademicki, nie druga definicja reguł domeny.

| Wymaganie | Dowód odbioru |
|---|---|
| A1 | Teacher wystawia1–6 z wagą1–5 w przypisanym kontekście; student widzi własną ocenę, admin zarządza każdą; historia zachowana po delete |
| A2 | Testy sesji i backendowych scopes wszystkich3 ról, negatywne IDOR/class/subject/term |
| A3 | Trzy pulpity i odpowiednie nawigacje po login, mobile/desktop |
| A4 | Admin: lista, show, create, edit, delete user; blokada ostatniego admina również równolegle |
| A5 | Formularze odrzucają błędy po stronie Laravel; pokazują polskie komunikaty i focus |
| A6 | Przegląd kodu: Controllers/Models/React View + Services, brak logiki biznesowej skopiowanej do UI |
| A7/A8 | Manifesty/lockfiles i działające środowisko PHP 8.3/Laravel 13/MariaDB 11.4, semantyczny HTML i Tailwind 4 |

Odbiór MVP wymaga wszystkich P0, w tym historii dla teacher/student i admin grade CRUD objętych wspólnymi specyfikacjami. Przekazanie projektu wymaga dodatkowo P1 dashboardów, profilu, rozszerzonych filtrów, CI i gotowości wydania. Tryb ciemny/seryjny P2 nie są warunkiem odbioru.

Dane testowe: fixture średnich z BR-03, użytkownik bez enrollment, teacher bez assignment, archived context, soft-deleted grade/user, dwa adminy. Żaden ekran ani props nie ujawnia cudzych ocen lub haseł. Wszystkie mutacje ocen są audytowane atomowo. Przy brakujących makietach zapisany przegląd adaptacji; brak deklaracji nieistniejących ramek.

Przekazanie zawiera rzeczywiste logi checks/build/test, zweryfikowany Docker cold start, wynik restore drill i listę faktycznych ograniczeń. Brak hostingu nie jest błędem dokumentacji, ale deployment nie może być wtedy oznaczony jako wykonany.
