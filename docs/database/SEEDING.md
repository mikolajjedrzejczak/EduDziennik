# Dane demonstracyjne

PLANNED F-003. Wyłącznie fikcyjne dane i domena `example.test`. Seeder demonstracyjny odmawia wykonania w production. Hasło deweloperskie pochodzi z lokalnego DEMO_PASSWORD (≥12 znaków); brak stałego produkcyjnego hasła. Hashe przez Laravel Hash, bez logowania hasła. Accounts admin1/admin2 pozwalają testować ostatniego administratora.

Kolejność: 2 adminów → 3 nauczycieli → 18 uczniów → rok 2026/2027 (2026-09-01..2027-08-31) → term 1 (do 2027-01-31), term 2 (2027-02-01..2027-08-31) → klasy 1A/2A/2B → 4 przedmioty → enrollments → assignments → kategorie → assessments → grades i audyty przez GradeService. Osobny historyczny rok 2025/2026 sprawdza odczyt archiwalny: jego oceny i audyty utworzyć przez GradeService przed ustawieniem closed_at, następnie zamknąć termy. Nie omijać reguły W przez bezpośredni insert ocen do zamkniętego termu.

Kategorie: Quiz waga 2, Test 3, Homework 1, Activity 1 (etykiety UI: Kartkówka, Sprawdzian, Praca domowa, Aktywność). Dopuszczalne wagi 4 i 5 również w fixture walidacji. Sześciu uczniów w klasie. Jeden nauczyciel prowadzi dwa przedmioty, drugi tylko jeden, trzeci bez przypisań. Przedmiot i klasa bez ocen, uczeń bez ocen, konto inactive, konto soft-deleted, cofnięty assignment.

W bazowej 2A/Matematyka utworzyć cztery assessments i fixture 31/7 opisany w [BR-03](BUSINESS_RULES.md). Tworzenie/edycja/usunięcie w oddzielnych scenariuszach, żeby wyniki 4.33, 4.57 i 4.67 nie były sprzeczną sekwencją. Oceny z create/update/delete audit, usunięta ocena widoczna wyłącznie w historii. Factory nie tworzy grade z losowym, niezgodnym enrollment/assessment.

Dane datować względem jawnego zegara testowego 2026-11-15; testy zamrażają czas. Seeder nie zakłada, że dzisiejsza data na maszynie mieści się w danym termie. W środowisku demo zestaw dat musi być jawnie dobrany przy uruchomieniu. Powtórne seedowanie przez reset wyłącznie w dedykowanej bazie testowej; nie upsertować historycznych ocen na żywym środowisku.

Kontrole: FK bez sierot, wszystkie role zgodne, term/class/year spójne, brak nakładających enrollmentów, każda ocena ma create audit, liczba aktywnych adminów ≥1, przykładowe średnie zgodne. Seedery nie są migracjami produkcyjnych danych.
