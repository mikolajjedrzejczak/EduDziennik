# Przepływy użytkowników

## Wspólny start

GET login → POST credentials → walidacja/limit → regenerate session → dashboard roli. Nieprawidłowe dane i inactive/deleted dają ten sam komunikat; obca sekcja403. POST logout invaliduje sesję. Profile: własne nazwisko/imię lub hasło z current_password, inne sesje odwołane po zmianie hasła.

## Student — F-101–104

Pulpit (wybrany term) → lista ocen → filtr przedmiotu → szczegóły → historia. Cofnięcie filtrów zachowuje query URL. Brak ocen daje— i wyjaśnienie. Historia pokazuje też delete; link do archiwalnych szczegółów działa bez przycisków edycji. Zmiana cudzego ID daje404 bez metadanych. Transfer klasy nie ukrywa wcześniejszych własnych ocen.

## Teacher — F-201–206

Pulpit → moje klasy (konkretna klasa/przedmiot/term) → dziennik → uczeń/komórka. Brak kolumny: utwórz assessment → puste komórki. Dodaj ocenę → formularz z kontekstem i domyślnymi polami kolumny → walidacja → commit grade+audit → dziennik z nową średnią. Błąd zachowuje niesekretne pola i fokus.

Klik oceny → szczegóły/edycja → reason + version → commit → szczegóły i historia. Przy stale version komunikat conflict; użytkownik odświeża i świadomie porównuje wpisy, aplikacja nic nie nadpisuje. Delete → dialog identyfikujący ucznia/ocenę/przedmiot + reason → potwierdzenie → średnia bez oceny, historia zachowana. Anulowanie bez mutacji. Teacher bez przypisania widzi empty; revoke odbiera dostęp także już otwartemu formularzowi.

Mobile używa kart uczniów i arkusza, desktop siatki i panelu. Tryb serii poza MVP. Historia ogólna dostępna w zakresie T, student detail pokazuje tylko prowadzony przedmiot.

## Administrator — F-301–308 i wspólne oceny

Pulpit → users → lista/filtry → show → edit lub delete. Create → rola/status/hasło + opcjonalna klasa ucznia/przydziały nauczyciela → walidacja → jedna transakcja → show nowego konta. Konto bez relacji ma odpowiedni pusty ekran. Zmiana organizacji przez klasy/enrollments/assignments, nie podmiana users.class_id.

Delete user → potwierdź nazwisko → soft delete i odwołanie sesji → lista. Anuluj bez zmian; self-delete i ostatni admin odrzucone. Grade history nie znika z usunięciem aktora.

Organizacja → klasa roku → enrollment/transfer lub subject → teacher assignment z termem. Revoke assignment nie kasuje ocen. Admin gradebooks → wybierz kontekst → te same formularze i historia co teacher, lecz pełny zakres; zamknięty term nadal read-only.

## Niepewne zakończenie zapisu

Timeout/offline po POST → bez auto-retry → odśwież bezpieczny GET i sprawdź stan. Unikalna komórka oraz version chronią ponowienia, lecz UI nie zapewnia fikcyjnej kolejki offline. Przy419 ponowne logowanie i ręczne podjęcie operacji po sprawdzeniu danych.

[Mapowanie prototypów](../design/SCREEN_MAP.md) wskazuje istniejące referencje A–E; [ROUTES](../http/ROUTES.md) stanowi obowiązujący kontrakt adresów.
