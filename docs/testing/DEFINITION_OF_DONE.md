# Definition of Done

Status Done dotyczy działającej funkcji, nie napisanej specyfikacji. Warunki łączne:

- Działają jej zależności i acceptance criteria; brak placeholderów.
- Backend wymusza RBAC i walidację; testy sukcesu i odmowy dostępu przechodzą.
- Reguły domenowe, relacje, transakcje i historia są spójne; migracje sprawdzone na MariaDB.
- UI obsługuje loading/empty/error/success, klawiaturę, desktop/mobile i dotyczący ekranu tablet.
- Porównano właściwe ramki Figmy. Dla luk udokumentowano przegląd adaptacji i nie nazwano go zgodnością z nieistniejącą makietą.
- Adekwatne PHPUnit/RTL/Vitest/Playwright oraz lint/format/typecheck/build przeszły. Nie muszą wszystkie dotyczyć funkcji bez UI, ale pominięcie jest jawne i uzasadnione.
- Wymagane checks CI przechodzą od chwili wdrożenia F-502. Wcześniej te same kontrole uruchomione lokalnie; brak CI oznaczony, nie fałszywe zielone checks.
- Brak regresji, sekretów i niepowiązanych refaktoryzacji. Kontrakty, feature status i ADR zaktualizowane.

Raport końcowy feature: co działa, pliki, wykonane polecenia i wyniki, zakres porównania Figma, ograniczenia. Nie deklarować sukcesu testu bez uruchomienia. Dla obecnego etapu dokumentacji stosujemy osobny [AUDIT](../AUDIT.md), wszystkie funkcje pozostają Planned.
