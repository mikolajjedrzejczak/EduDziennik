# Bezpieczeństwo

DECISION: standardowa sesja Laravel; brak tokenów SPA. Login normalizuje email, używa Laravel Hash/Auth, sprawdza aktywność i deleted_at, regeneruje ID sesji i aktualizuje last_login_at. Logout to POST z CSRF, invalidate session i regenerate token. Na produkcji HTTPS, Secure + HttpOnly cookie, SameSite=Lax, host-only cookie, APP_DEBUG=false. Lokalnie HTTP na localhost dopuszcza Secure=false. Sesje w bazie; APP_KEY poza Git.

Login: limit 5 nieudanych prób / 15 minut dla znormalizowanego email+IP oraz osobny limit 50/IP/15 minut (ASSUMPTION operacyjna). Komunikat jednakowy dla nieistniejącego konta, błędnego hasła, konta nieaktywnego/usuniętego. Rate limiter używa database cache, bez tabeli login_attempts i bez Redis. Publiczna rejestracja i reset mailowy poza MVP; administrator ustawia nowe hasło poza kanałem logów. Hasła: 12–128 znaków, potwierdzenie, bez arbitralnego regexu złożoności. Lokalna demonstracja ma wyłącznie fikcyjne konta.

[RBAC](../product/RBAC.md) egzekwowany w Policy oraz scope zapytania przed COUNT, AVG, listą i opcjami formularza. Cudzy rekord daje 404, obca rola na sekcji 403. Klient nie przekazuje zaufanego actor_id, issuer_id, role ani student_id bez weryfikacji. Mass assignment tylko z jawnej allowlisty. Nie używać globalnego `Gate::before(admin => true)` do omijania ochrony ostatniego administratora lub zamkniętego termu.

SQL: parametry Eloquent, allowlista sortowania, żadnego `orderByRaw` z wejścia. Wyszukiwanie literalne: escape znaków `%` i `_`. React domyślnie escapuje tekst; bez dangerouslySetInnerHTML dla komentarzy. Komentarz to zwykły tekst. CSP dobrać do assetów aplikacji, brak inline skryptów z danych użytkownika. Żadnych danych ucznia w query-string poza technicznymi filtrami i koniecznym wyszukiwaniem; logi dostępu redagują query.

Logi: identyfikator korelacji, kod błędu i techniczny kontekst; bez payloadów, cookies, haseł, komentarzy, nazwisk i pełnych zapytań SQL z wartościami. Historia ocen dostępna wyłącznie w autoryzowanym UI, nie w ogólnym logu. Role bazy bez DDL w runtime; migrator osobny. Uprawnienia historii: SELECT/INSERT, bez UPDATE/DELETE dla runtime.

Usunięcie konta jest logiczne, natychmiast odbiera sesje i dostęp. PII nie znika fizycznie: to nie jest obietnica anonimizacji. Produkcyjna retencja i podstawa przetwarzania wymagają decyzji właściciela; [reguły retencji](../database/BUSINESS_RULES.md). Backupy szyfrowane i ograniczone dostępem; sekrety tylko środowisko wdrożenia.

## Obowiązkowe testy

- Student: cudzy grade_id/audit_id, filtr innego studenta, dane w props/history/paginacji → brak ujawnienia.
- Teacher: inny przedmiot tej samej klasy, inna klasa, inny term, cofnięty assignment, manipulacja enrollment/assessment → odmowa i zero audytów.
- Admin: blokada ostatniego aktywnego admina także równolegle; zmiana roli/statusu odbiera sesje.
- Wszystkie role: CSRF, utrwalony XSS w komentarzu, SQL w sort/q, wylogowanie, nieaktywność, 429, brak hasła w props i logach, rollback audytu.
