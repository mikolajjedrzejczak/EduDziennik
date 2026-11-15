# Strategia testowania

PLANNED. Nie istnieją jeszcze testy aplikacji. F-501 buduje harness wcześnie; testy każdej funkcji powstają razem z nią. F-502 automatyzuje je. [Polecenia](../infrastructure/CI_CD.md) i [Docker](../infrastructure/DOCKER.md) są planem.

| Warstwa | Narzędzie | Co dowodzi |
|---|---|---|
| Czysta domena | PHPUnit Unit | Reguły wartości/wagi, half-up, snapshot v1 |
| HTTP i baza | PHPUnit Feature + MariaDB 11.4 | Sesje, Policy, props, walidacja, FK/CHECK, transakcje |
| Prezentacja | Vitest | Format polski, null average, daty bez przesunięcia dnia |
| Komponenty | RTL + Vitest | Form errors, pending, focus, dialog, empty/error |
| Cały system | Playwright | Scenariusze ról i odmowy, responsywność, reload URL |

## Krytyczne scenariusze backendu

1. Login/logout/inactive/deleted, regenerate session, CSRF,429, password change unieważniający inne sesje.
2. Każda chroniona trasa: guest, student własny/cudzy rekord, teacher poprawny/inny class/subject/term, admin. Test również response props, counts, agregatów i opcji — samo status403 nie wystarcza.
3. CRUD users: show bez hasha, unique email także soft-deleted, relacje atomowo, status/role session revocation, self-delete denied. Dwóch adminów równocześnie: co najmniej jeden active pozostaje.
4. Oceny: value1/6 i weight1/5, odrzucenie0/7/wagi6 i plusów, future date, enrollment/term boundary. Każda mutacja tworzy dokładnie jeden audit.
5. Awaria insertu grade_audits cofa grade; konkurencyjne PATCH starej version nie nadpisuje; delete twice nie dodaje historii; no-op bez audytu.
6. Cofnięcie assignment równolegle ze zmianą oceny: blokady dają spójny porządek; zapis sprzed revoke może zakończyć się wcześniej, zapis po revoke musi zostać odrzucony.
7. Transfer enrollmentu, zakresy bez nakładania, semestr innego roku, historyczny odczyt po soft delete konta/oceny, RESTRICT physical delete.
8. Średnie31/7=4.43; niezależne warianty39/9=4.33,32/7=4.57,28/6=4.67; null przy braku; średnia ogólna z wag, nie średnich; filtr/paginacja zgodne z BR-03.
9. Assessment context immutable, duplicate grade cell, usunięcie niepustej kolumny denied; override wagi liczy grade.weight, nie default_weight.

Współbieżność testować dwoma niezależnymi połączeniami/procesami do MariaDB z kontrolowaną barierą, nie dwoma wywołaniami w tej samej transakcji testowej. Freeze clock; `RefreshDatabase` dla zwykłych Feature, dedykowane migracje/cleanup dla testów równoległych. Nie ujawniać sekretów przy fault injection.

## E2E odbiorowe

- Student → login → grades → subject → details → history; następnie cudzy URL →404.
- Teacher → class/subject → student → grade create → student widzi zmianę → edit z reason → audit → delete → audit nadal istnieje. Osobny przypadek braku assignment.
- Admin → create user → show → edit → delete z nazwiskiem; osobno anulowanie i ostatni admin.
- Class/enrollment transfer i assignment revoke sprawdzają rzeczywistą zmianę dostępu.

Viewport 390/1024/1440, dodatkowy reflow320px/zoom200%, klawiatura (tab, enter, escape, focus return), nazwy dostępne i kontrasty. Nie deklarować pełnego WCAG AA na podstawie automatycznego narzędzia; manualny przegląd kluczowych interakcji obowiązkowy. Porównanie Figma dotyczy wartości i układu, a tekst fixture nie musi kopiować nazwisk makiety.

Fixture tylko fikcyjne. CI izoluje test i E2E DB; schema reset tylko tam. Nie używać produkcyjnego dumpa. Nie wyłączać testów, żeby przejść pipeline. N1 queries, duże props i czas GET mierzyć na kontrolowanym zestawie50tys. ocen przed F-503.
