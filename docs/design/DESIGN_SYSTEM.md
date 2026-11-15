# Design system — odczyt Figma MCP

VERIFIED 2026-11-15: kolekcje Primitives, Semantic (Light/Dark), Dimensions;15 stylów Public Sans i2 style efektów. Źródło: strona1:4, foundations9:351; obowiązują wartości zmiennych, nie nieaktualne opisy planszy.

## Kolory semantyczne

| Figma variable | CSS suffix | Light | Dark (odroczony UI) |
|---|---|---|---|
| `color/primary` | `primary` | #2350D8 | #7B9BF2 |
| `color/primary-hover` | `primary-hover` | #1A3FB0 | #A3BBF7 |
| `color/primary-muted` | `primary-muted` | #DCE5FB | #1E3270 |
| `color/primary-subtle` | `primary-subtle` | #F0F4FE | #16234A |
| `color/bg` | `bg` | #F6F7F9 | #0E1A33 |
| `color/surface` | `surface` | #FFFFFF | #14213D |
| `color/surface-secondary` | `surface-secondary` | #EDF0F4 | #1F2E52 |
| `color/text-primary` | `text-primary` | #141B2D | #F6F7F9 |
| `color/text-secondary` | `text-secondary` | #5B6577 | #AEB8CF |
| `color/text-disabled` | `text-disabled` | #98A2B3 | #5B6577 |
| `color/text-on-primary` | `text-on-primary` | #FFFFFF | #0E1A33 |
| `color/border` | `border` | #DCE1E8 | #33436A |
| `color/border-strong` | `border-strong` | #C5CCD7 | #4A5A82 |
| `color/success` | `success` | #137537 | #4ADE80 |
| `color/success-bg` | `success-bg` | #E7F5EC | #12351F |
| `color/warning` | `warning` | #A65308 | #F5A524 |
| `color/warning-bg` | `warning-bg` | #FDF1E1 | #3A2610 |
| `color/error` | `error` | #B91C1C | #F87171 |
| `color/error-bg` | `error-bg` | #FCE8E8 | #3B1418 |
| `color/neutral` | `neutral` | #98A2B3 | #98A2B3 |
| `color/nav-bg` | `nav-bg` | #14213D | #0A1328 |
| `color/nav-active` | `nav-active` | #33436A | #1F2E52 |
| `color/nav-text` | `nav-text` | #FFFFFF | #FFFFFF |
| `color/nav-text-muted` | `nav-text-muted` | #AEB8CF | #AEB8CF |
| `color/focus` | `focus` | #2350D8 | #7B9BF2 |
| `grade/1-bg` | `grade-1-bg` | #FCE8E8 | #3B1418 |
| `grade/1-fg` | `grade-1-fg` | #B91C1C | #F87171 |
| `grade/2-bg` | `grade-2-bg` | #FDF1E1 | #3A2610 |
| `grade/2-fg` | `grade-2-fg` | #A65308 | #F5A524 |
| `grade/3-bg` | `grade-3-bg` | #EDF0F4 | #1F2E52 |
| `grade/3-fg` | `grade-3-fg` | #141B2D | #F6F7F9 |
| `grade/4-bg` | `grade-4-bg` | #F0F4FE | #16234A |
| `grade/4-fg` | `grade-4-fg` | #1A3FB0 | #A3BBF7 |
| `grade/5-bg` | `grade-5-bg` | #E7F5EC | #12351F |
| `grade/5-fg` | `grade-5-fg` | #137537 | #4ADE80 |
| `grade/6-bg` | `grade-6-bg` | #137537 | #4ADE80 |
| `grade/6-fg` | `grade-6-fg` | #FFFFFF | #0E1A33 |
| `grade/1-border` | `grade-1-border` | #E5A1A1 | #87393C |
| `grade/2-border` | `grade-2-border` | #DFBA95 | #855918 |
| `grade/3-border` | `grade-3-border` | #C6CAD0 | #4A5673 |
| `grade/4-border` | `grade-4-border` | #B4C1E8 | #475887 |
| `grade/5-border` | `grade-5-border` | #A7CFB6 | #267041 |

## Typografia

Public Sans lokalnie hostowany z polskimi znakami; wagi400/500/600/700, font-display:swap. Liczby tabular-nums.

| Styl | Rozmiar / line-height px | Waga |
|---|---|---|
| Display | 32 / 40 | 600 |
| H1 | 24 / 32 | 600 |
| H2 | 18 / 26 | 600 |
| H3 | 15 / 22 | 600 |
| Body/Large | 16 / 24 | 400 |
| Body/Regular | 14 / 20 | 400 |
| Body/Strong | 14 / 20 | 500 |
| Body/Small | 13 / 18 | 400 |
| Label | 13 / 16 | 500 |
| Caption | 12 / 16 | 400 |
| Numeric/XL | 40 / 44 | 600 |
| Numeric/L | 24 / 28 | 600 |
| Numeric/M | 15 / 20 | 600 |
| Grade | 14 / 16 | 700 |
| Badge | 12 / 16 | 500 |

## Wymiary

| Zmienna | px |
|---|---|
| space/1 | 4 |
| space/2 | 8 |
| space/3 | 12 |
| space/4 | 16 |
| space/5 | 20 |
| space/6 | 24 |
| space/8 | 32 |
| space/12 | 48 |
| radius/sm | 4 |
| radius/md | 6 |
| radius/lg | 8 |
| radius/xl | 12 |
| size/touch | 44 |
| size/sidebar | 248 |

FACT komponent Button4:3: wysokość40, padding poziomy16, gap8, radius6, Auto Layout horizontal. GradeChip M4:185:32×32, radius6, obrys1px powiązany z grade/5-border. StudentCard7:611: padding16, gap10, radius0 (nie ujednolicać mechanicznie do radius-lg). Sidebar248px; collapsed72px z38:8368. Mobile Input48px; minimalny cel dotyku44px. Te wartości są wyjątkiem uzasadnionym konkretnym komponentem, nie nowym globalnym spacingiem.

Obramowanie pól/paneli zwykle1px border; fokus outline2px focus. Efekt Focus/Ring: cień0 0 0 3px rgba(34.935,80.07,215.985,.35). Elevation/Overlay:0 12px 32px rgba(20.4,28.05,51,.16),0 2px 6px rgba(20.4,28.05,51,.08). Ułamkowe RGB wynikają z wartości odczytanych w API; można zapisać je w CSS rgba z tymi wartościami, bez arbitralnej zamiany na cień shadcn.

Lucide: sekcja3:2,41 ikon o rozmiarze bazowym24px. Ikony dekoracyjne aria-hidden; samodzielne przyciski muszą mieć nazwę dostępną. GradeChip ma cyfrę niezależnie od koloru. Button: Primary/Secondary/Ghost/Destructive × Default/Hover/Focus/Disabled/Loading. Input: Text/Select/Search/Password i warianty stanów; dokładne warianty ponownie odczytać przy implementacji.

## Mapowanie Tailwind 4 (PLANNED)

```css
@import "tailwindcss";
:root {
  --primary: #2350D8;
  --surface: #FFFFFF;
  --text-primary: #141B2D;
  --success: #137537;
  --radius-md: 6px;
}
@theme inline {
  --color-primary: var(--primary);
  --color-surface: var(--surface);
  --color-foreground: var(--text-primary);
  --color-success: var(--success);
  --font-sans: "Public Sans", sans-serif;
  --radius-control: var(--radius-md);
}
```

To fragment instrukcyjny, nie pełny wygenerowany theme. F-006 mapuje wszystkie używane w tabeli tokeny (w tym grade-N-bg/fg/border) do `tokens.css`; shadcn background→bg, card→surface, foreground→text-primary, muted→surface-secondary, destructive→error, ring→focus. Tailwind standard spacing4px odpowiada odczytanej bazie; zachować istniejące wyjątki komponentów. Nie nadpisywać semantycznego primary generycznym blue-600.

Breakpointy opisane w [RESPONSIVENESS](RESPONSIVENESS.md), nie znaleziono ich jako zmiennych kolekcji. Kolory i fonty są zweryfikowane z API; pełna zgodność WCAG implementacji pozostaje PLANNED, ponieważ wymaga rzeczywistego DOM, stanów i pomiaru.
