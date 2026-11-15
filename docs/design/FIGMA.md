# Figma — źródło UI

VERIFIED 2026-11-15 przez Figma MCP: inwentaryzacja wszystkich15 stron, ramek głównych i komponentów; odczyt zmiennych, stylów tekstu, efektów oraz notatek produktu i audytu. Obejrzano zrzuty dziennika desktop17:620 i mobile23:330. Nie klikano całego prototypu ani nie porównywano z implementacją — aplikacja jeszcze nie istnieje.

[EduDziennik — Elektroniczny Dziennik Lekcyjny](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp/EduDziennik?node-id=1-6). File key: `QmOrJFZWMpJTHC5Q8s7Ltp`. URL wskazuje stronę ucznia1:6, nie całość wymagań.

## Strony

| Page ID | Nazwa | Zastosowanie |
|---|---|---|
| [0:1](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=0-1) | 00 — Product Strategy & User Flows | Kontekst produktu; notatki technologiczne częściowo nieaktualne |
| [1:2](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-2) | 01 — Design References | Referencje, nie ekran implementacyjny |
| [1:3](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-3) | 02 — Visual Concepts A & B | Wybrana koncepcja A i odrzucone/niezatwierdzone alternatywy nawigacji |
| [1:4](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-4) | 03 — Design System | Komponenty, zmienne Light/Dark, typografia |
| [1:5](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-5) | 04 — Desktop / Authentication | Logowanie i403/404 desktop |
| [1:6](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-6) | 05 — Desktop / Student | Oceny, historia, profil ucznia i tablet |
| [1:7](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-7) | 06 — Desktop / Teacher | Klasy, dziennik, operacje ocen i tablet |
| [1:8](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-8) | 07 — Desktop / Administrator | Konta, organizacja i tablet |
| [1:9](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-9) | 08 — Mobile / Authentication | Mobilne logowanie i błędy |
| [1:10](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-10) | 09 — Mobile / Student | Mobilne oceny/historia/profil |
| [1:11](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-11) | 10 — Mobile / Teacher | Mobilny dziennik i arkusze ocen |
| [1:12](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-12) | 11 — Mobile / Administrator | Mobilne konta i organizacja |
| [1:13](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-13) | 12 — Interactive Prototypes | Kopie ekranów przepływów A–E desktop/mobile |
| [1:14](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-14) | 13 — States & Edge Cases | Ładowanie, pusty wynik, offline, konflikt, błędy |
| [1:15](https://www.figma.com/design/QmOrJFZWMpJTHC5Q8s7Ltp?node-id=1-15) | 14 — UX/UI Audit | Adnotacje audytu projektanta; nie dowód testów aplikacji |

Wybrana **Structured Academic Workspace**, koncepcja A: etykieta32:229. Alternatywna B i cztery późniejsze propozycje nawigacji ze strony02 nie zastępują ekranów właściwych04–11. Bazowy desktop ma sidebar248, mobile dolną nawigację.

## Workflow implementacji

Przeczytaj feature → SCREEN_MAP → Figma MCP → design context konkretnej ramki → Auto Layout, spacing, komponenty i bindingi → odpowiednik mobile/tablet → states/prototype → dobór shadcn/Radix → implementacja → porównanie zrzutów390/1024/1440 i obsługi klawiatury. Pobranie design context wymaga skillu figma-design-to-code w środowisku agenta. Ten etap dokumentacji korzystał z read-only Plugin API, nie wygenerował kodu z design context.

Jeżeli brak MCP, node zmienił ID lub brak wariantu, oznacz OPEN QUESTION i nie deklaruj wizualnej zgodności. Node IDs w tym repo są potwierdzonym snapshotem, nie gwarancją przyszłej niezmienności pliku. Nie tworzyć generycznego dashboardu w miejsce projektu.

## Rozstrzygnięte konflikty

| Notatka Figma | Obowiązująca dokumentacja | Powód |
|---|---|---|
|30:275/30:343: wagi1–3|Wagi1–5 we wszystkich Requests/DB/UI|Brief nadrzędny; rozwinąć Select do5|
|30:221: Laravel 10,30:203 ręczne password_verify|Laravel 13 + sesyjne Auth/Hash|Zatwierdzony stack|
|30:340: users.class_id|student_enrollments z datami|Historia członkostwa|
|30:355: jeden nauczyciel na class+subject|Czwórka teacher/class/subject/term; współprowadzenie|Jawne ASSUMPTION domenowe|
|30:308: teacher musi mieć przydział|Konto bez przydziału dozwolone, empty state|Brak konieczności tworzenia niepełnych relacji|
|30:310: mail z danymi|Brak wysyłki hasła/automatycznego maila|Zakres i bezpieczeństwo|
|Polskie URL i grade_audit|Angielskie ROUTES, grade_audits|Spójne nazewnictwo projektu|
|9:408: success #15803D|Aktualna zmienna #137537|Korekta kontrastu w35:93; odczyt bindingu|
|23:2099: tryb serii|P2 poza MVP|Najpierw pojedyncze poprawne mutacje|
|Dark previews37:*|Tokeny odczytane; przełącznik Dark P2|Nie rozszerzać podstawowego zakresu|

Nie zmieniano pliku Figma. Konflikty funkcjonalne rozstrzygnięto w dokumentacji; wygląd zachowuje potwierdzone ramki z jawnymi rozszerzeniami wymaganymi przez domenę. Brak osobnych ramek admin grade CRUD / user show nie upoważnia do twierdzenia, że je zweryfikowano; [mapa i luki](SCREEN_MAP.md).
