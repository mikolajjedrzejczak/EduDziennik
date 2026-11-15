# Frontend

React 19 to warstwa View Laravel MVC. `resources/js/pages` składa strony, `layouts` powłoki ról, `components/ui` dostosowane prymitywy shadcn, `components/grades` domenowe prezentacje. Wspólne DTO w `types`, funkcje prezentacyjne w `lib`, hooki tylko dla powtarzalnego zachowania.

Nazwy stron i props są w [kontrakcie](../http/REQUESTS_RESPONSES.md). TypeScript strict, funkcje i hooki, brak nieuzasadnionego `any` i rzutowań omijających kontrolę. Identyfikatory przesyłamy jako string (BIGINT); średnia jako string dziesiętny albo null. Format polski tylko w prezentacji. Daty dnia bez konwersji strefy; timestampy UTC formatowane w Europe/Warsaw.

Formularze: `useForm`, błędy z serwera, `processing` blokuje podwójne kliknięcie, zachowane niesekretne pola po błędzie. Hasła czyścić; nie zapisywać ich przez remember/history. Zod tylko gdy upraszcza istotne reguły UX. Walidacja kliencka nie zastępuje backendu. Sort/filter/page w URL przez GET Inertia z resetem page po zmianie filtra. Lokalny stan wystarczy do otwarcia arkusza i podglądu hasła.

GradeForm współdzielony przez nauczyciela i administratora, kontekst oraz dozwolone URL dostarcza backend. Nie wylicza uprawnień ani średnich. `can` steruje widocznością; każde POST/PATCH/DELETE wymaga Policy.

Tailwind 4 + CSS variables i `@theme inline`; [tokeny](../design/DESIGN_SYSTEM.md). shadcn to baza zachowań Radix, nie wzorzec wizualny. Bez instalowania całej biblioteki. Kolorów nie kopiować do komponentów; GradeChip mapuje 1–6 na statyczne klasy, nie interpoluje nieznanych nazw utilities.

Semantyczne main/nav/table/form, label i aria-describedby, widoczny fokus, skip link, komunikaty aria-live, dialog z powrotem fokusu do wyzwalacza. Mobile: karty dziennika, dolna nawigacja i Sheet. Nie renderować dwóch aktywnych kopii formularza dla desktop/mobile. [Responsywność](../design/RESPONSIVENESS.md).

Błędy: [ERROR_HANDLING](../http/ERROR_HANDLING.md); pusty wynik nie jest błędem. Skeleton przy oczekiwaniu na nawigację, offline bez automatycznego ponowienia mutacji. RTL sprawdza formularze i fokus, Vitest formatowanie, Playwright przepływy i rozmiary; patrz [strategia](../testing/TEST_STRATEGY.md).
