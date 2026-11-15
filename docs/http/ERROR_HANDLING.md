# Obsługa błędów

Wdrożony wzorzec błędów formularzy i jego integracja: [FORM_VALIDATION](FORM_VALIDATION.md). Wspólne komponenty nie zastępują obsługi stron HTTP z F-404.

| Sytuacja | Odpowiedź | Zachowanie UI |
|---|---|---|
| Niezalogowany GET chroniony | 302 login | Formularz; po login kanoniczny pulpit roli |
| Nieaktywne/usunięte konto | Unieważnienie sesji + redirect login | Neutralny komunikat |
| Obca rola | 403 Inertia shared/error | Bez danych chronionych, powrót do swojego pulpitu |
| Cudzy/brakujący rekord | 404 Inertia shared/error | Ten sam komunikat dla obu przypadków |
| Walidacja pól mutacji | Redirect back + session errors | useForm.errors, fokus podsumowania, niesekretne pola zachowane |
| Błędny filtr GET | Redirect303 do tej samej trasy z poprawnymi filtrami, bez błędnego parametru + errors | Bez pętli przekierowań także przy wejściu bezpośrednim |
| Zmieniona version | Redirect back + errors.conflict | Pozostawić wpisany tekst; przycisk odświeżenia, brak automatycznego nadpisania |
| Zamknięty term/duplikat/usunięcie ostatniego admina | Redirect back + errors.form lub konkretne pole | Wyjaśnienie, brak commitu |
| Zły CSRF / wygasła sesja mutacji | 419, obsługa sesji | Komunikat odświeżenia/zalogowania; nigdy automatyczne powtórzenie POST |
| Limit logowania/hasła | 429 + Retry-After | Czas ponowienia, bez ujawnienia istnienia konta |
| Niedostępna baza/serwis | 503 Inertia shared/error | Ponów tylko GET; mutacje wymagają sprawdzenia stanu |
| Nieoczekiwany wyjątek | 500 Inertia shared/error | Neutralny komunikat i correlation ID; bez stack trace |
| Offline/timeout | Brak odpowiedzi HTTP | Stan lokalny, sprawdź dane przed ponownym zapisem |

Konflikt domenowy jest errors.conflict po redirect, **nie surowym HTTP 409**: Inertia rezerwuje 409 dla części protokołu, np. zmiany wersji assetów. Nie otwierać modala z HTML błędu walidacji. Error page props: `ErrorProps` z [kontraktu](REQUESTS_RESPONSES.md). 419/429 można pokazać na shared/error, przy czym 419 nie ujawnia starego kontekstu auth.

Formularz nie traktuje przycisku disabled jako zabezpieczenia. Timeout po udanym commicie nie oznacza niepowodzenia: powrót do GET pokaże zapis, unikalność enrollment+assessment powstrzyma duplikat create, version powstrzyma drugi update. Nie dodawać audytu ponownego nieudanego delete. Usunięcie powoduje brak oceny na listach, lecz historia i archiwalne szczegóły nadal mają autoryzowany GET.

Listy rozróżniają „brak danych” od „brak wyników filtrów”; ten drugi oferuje wyczyszczenie filtrów. Skeleton ma aria-busy, toast sukcesu role=status, błędy formularza role=alert. Błędy nie kolorami samymi. Źródła UI: [SCREEN_MAP](../design/SCREEN_MAP.md).
