# Responsywność

FACT: główne ekrany desktop 1440×900, mobile 390×844; formularze użytkownika desktop mają również wysokości1180 i1000 (przewijana treść, nie wymagany viewport). Tablet1024×768: student38:8813, teacher38:11292, admin38:13515.

FACT notatka35:52: tablet768–1279px i sidebar collapsed72px. DECISION implementacyjna: <768px układ mobile; 768–1279 tablet; ≥1280 desktop z sidebar248. Odpowiada Tailwind md=48rem, xl=80rem przy standardowej bazie16px; breakpointy nie są odczytanymi zmiennymi Figmy. Bazowa siatka płynna, nie width:1440px dla aplikacji.

Desktop: tabela dziennika i metadane kolumn; drawer oceny480px. Tablet: zwężone kolumny, collapsed sidebar, lista users ukrywa ostatnie logowanie (dostępne w szczegółach). Mobile: karty uczniów, dolna nawigacja zależna od roli, filtry w Sheet, formularz oceny w arkuszu. W bazowym MVP dziennik ma10 kolumn/stronę; mobile ten sam zakres z przewijaniem wewnątrz karty lub wyborem strony kolumn, bez poziomego przewijania całego dokumentu.

Nie odtwarzać systemowego paska telefonu jako elementu web UI. Dolna nawigacja i sticky footer uwzględniają env(safe-area-inset-bottom), a formularz scroll/klawiaturę. Minimalny cel dotyku44px; chip24/32px może mieć większy niewidoczny hit target. Dialog ma tytuł, focus trap, Escape i powrót fokusu; przy destrukcji bez domyślnego fokusu na Usuń.

Testy:390,1024,1440 oraz320px i zoom200%; długa nazwa użytkownika/przedmiotu,30 błędów, pusty stan i max treści komentarza. Sprawdzić reflow, widoczność akcji, kontrast, kolejność fokusu, reduced motion. Brak makiety320px nie uzasadnia przepełnienia treści. Nowe warianty oznaczyć jako adaptację, nie odczytaną ramkę.
