# PoisaPay — Phase 3: Design System

**Principle:** UX first; visual design supports usability. This system *extends* the existing token layer in `resources/css/app.css` and the 39-component `x-ui` kit — it does not restart them. Everything is token-driven so light/dark and future rebrands are a variable swap, never a rewrite.

**Design language:** *Calm, precise, trustworthy.* Generous whitespace, one accent, restrained depth, motion that confirms rather than decorates. The anti-goal is a loud crypto-exchange look; the target is the quiet confidence of Wise / Linear / Apple Wallet — expressed originally, not cloned.

---

## 1. Brand identity

- **Accent:** a single confident **PoisaPay Blue** (evolves the current `theme-minimal` #2563EB). One accent, used sparingly for primary actions and focus — money screens stay neutral so *value* is the hero, not chrome.
- **Neutrals:** a true slate scale for surfaces/text (calm, cool, premium).
- **Money semantics:** green up / rose down, reserved *only* for value change — never decorative.
- **Voice in UI:** short, literal labels ("Send", "Withdraw", "Escrow locked"). No jargon. Numbers are first-class citizens (tabular, aligned).

---

## 2. Color tokens

Defined as CSS custom properties. Light in `:root`, dark in `.dark`. Components reference **semantic** tokens (`--surface`, `--text`, `--accent`) — never raw scale values — so theming is centralized.

### 2.1 Brand scale (PoisaPay Blue)
```
--blue-50:#eff5ff --blue-100:#dbe8fe --blue-200:#bfd6fe --blue-300:#93b8fd
--blue-400:#609afa --blue-500:#2563eb --blue-600:#1d4ed8 --blue-700:#1e40af
--blue-800:#1e3a8a --blue-900:#172554
```

### 2.2 Neutral (slate)
```
--slate-50:#f8fafc --slate-100:#f1f5f9 --slate-200:#e2e8f0 --slate-300:#cbd5e1
--slate-400:#94a3b8 --slate-500:#64748b --slate-600:#475569 --slate-700:#334155
--slate-800:#1e293b --slate-900:#0f172a --slate-950:#0b1120
```

### 2.3 Semantic + money
```
--green-500:#10b981  --amber-500:#f59e0b  --red-500:#ef4444  --sky-500:#3b82f6
--money-up:#10b981   --money-down:#f43f5e
```

### 2.4 Semantic surface/text tokens — LIGHT (`:root`)
```
--bg:            #f6f8fb   /* app canvas */
--surface:       #ffffff   /* cards */
--surface-2:     #f8fafc   /* insets, table headers */
--surface-sunken:#eef2f7
--border:        #e2e8f0
--border-strong: #cbd5e1
--text:          #0f172a   /* primary */
--text-2:        #475569   /* secondary */
--text-3:        #94a3b8   /* tertiary/placeholder — AA-checked ≥4.5 on surface */
--accent:        var(--blue-500)
--accent-hover:  var(--blue-600)
--accent-weak:   var(--blue-50)
--focus-ring:    var(--blue-500)
```

### 2.5 Semantic surface/text tokens — DARK (`.dark`)
```
--bg:            #0b1120   /* deep slate canvas */
--surface:       #111a2e   /* cards — one step up from bg */
--surface-2:     #16213a   /* elevated insets */
--surface-sunken:#0d1526
--border:        rgba(148,163,184,0.16)
--border-strong: rgba(148,163,184,0.28)
--text:          #e6edf7
--text-2:        #9fb0c8
--text-3:        #6b7d97
--accent:        var(--blue-400)   /* lighter accent for contrast on dark */
--accent-hover:  var(--blue-300)
--accent-weak:   rgba(37,99,235,0.16)
--focus-ring:    var(--blue-400)
```

**Dark-mode rules:** never invert with pure black; elevation is expressed by *lighter* surfaces + hairline borders, not heavy shadows. Money green/rose keep the same hues (they read on both). All text/accent pairings validated to WCAG AA (≥4.5 body, ≥3 large). Fixes audit **DS1, A3**.

---

## 3. Typography

**Family:** **Inter** (primary), system-sans fallback; **mono** for addresses/refs. (Current build ships Nunito — this is a single `--font-sans` token swap; keeping Inter aligns with the premium `theme-minimal` intent.) Numbers use `font-variant-numeric: tabular-nums` everywhere money appears.

| Token | Size / line | Weight | Use |
|---|---|---|---|
| `display` | 32 / 40 | 700 | Balance hero, page hero |
| `h1` | 24 / 32 | 700 | Screen title |
| `h2` | 20 / 28 | 600 | Section title |
| `h3` | 18 / 26 | 600 | Card title |
| `body-lg` | 16 / 24 | 400–500 | Primary reading |
| `body` | 14 / 22 | 400–500 | Default UI text |
| `caption` | 13 / 18 | 500 | Metadata, labels |
| `micro` | 12 / 16 | 500 | Badges, timestamps |
| `amount-xl` | 34 / 40 | 600, tabular, -0.01em | Money hero fields |
| `mono` | 13 / 20 | 500 | Addresses, refs, codes |

Max line length 66ch for reading blocks. Letter-spacing tightens slightly on large/numeric, loosens on `micro`/uppercase badges.

---

## 4. Spacing, grid, radius, elevation, z-index, motion

### 4.1 Spacing — 4px base
`--space-0:0 · 1:4 · 2:8 · 3:12 · 4:16 · 5:20 · 6:24 · 8:32 · 10:40 · 12:48 · 16:64`
Screen gutters: 16 (mobile) / 24 (tablet) / 32 (desktop). Card padding: 20 mobile / 24 desktop.

### 4.2 Grid & breakpoints
`sm 640 · md 768 · lg 1024 · xl 1280`. Content max-width 1200. Mobile single-column; dashboard uses a 12-col grid ≥lg (8/4 split for hero+aside). Bottom-tab shell reserves 64px safe-area-aware footer on mobile.

### 4.3 Radius scale (fix DS4)
`--r-xs:6 · sm:8 · md:10 · lg:12(card) · xl:16 · 2xl:20 · pill:999`
Cards `lg`, sheets/modals `xl`, inputs `md`, chips/badges `pill`, card-visual `xl`.

### 4.4 Elevation scale (fix DS3)
| Token | Light | Dark |
|---|---|---|
| `--e0` | none | none |
| `--e1` card | `0 1px 2px rgb(16 24 40/.04), 0 1px 3px rgb(16 24 40/.06)` | border + `0 1px 2px rgb(0 0 0/.4)` |
| `--e2` raised | `0 4px 12px -2px rgb(16 24 40/.08)` | `0 2px 8px rgb(0 0 0/.5)` |
| `--e3` popover | `0 8px 24px -6px rgb(16 24 40/.12)` | `0 8px 24px rgb(0 0 0/.55)` |
| `--e4` modal | `0 20px 48px -12px rgb(16 24 40/.20)` | `0 24px 56px rgb(0 0 0/.6)` |
Depth in dark leans on surface lightness + `--border`, not shadow weight.

### 4.5 Z-index scale (fix DS7)
`base 0 · dropdown 1000 · sticky 1010 · header 1020 · tabbar 1025 · backdrop 1030 · sheet/drawer 1040 · modal 1050 · toast 1060 · tooltip 1070`

### 4.6 Motion tokens (fix DS5) — 120–220ms, purposeful
```
--dur-fast:120ms  --dur-base:160ms  --dur-slow:220ms  --dur-slower:300ms
--ease-standard: cubic-bezier(.2,0,0,1)     /* enter/most */
--ease-emphasis: cubic-bezier(.2,0,0,1.2)   /* raised Pay, success pop */
--ease-exit:     cubic-bezier(.4,0,1,1)      /* leave */
```
Named transitions: **page/partial swap** 160ms fade+2px rise · **sheet** 220ms up · **modal** 160ms scale .98→1 · **toast** 200ms up · **tab switch** 120ms · **optimistic toggle** 120ms. Respect `prefers-reduced-motion` (already honored — keep). No motion longer than 300ms; nothing bounces except the signature Pay press and success check.

---

## 5. Iconography & illustration

- **Icons:** Heroicons v2 (already integrated). Formalize sizes: `16 · 20(default) · 24 · 28`. Stroke 1.5. One icon set only.
- **Illustration system (fix DS6):** a small, consistent set (line + single-accent-fill, matching brand) for: empty states (per surface), success moments (deposit credited, order complete), KYC steps, error/offline, referral hero. Replaces "icon-in-a-grey-circle." Kept minimal and flat — warmth without noise. Delivered as reusable `x-ui.illustration name="…"` slots so empty-states/success screens compose consistently.

---

## 6. Depth, glass & gradient (used sparingly)

- **Gradient:** reserved for two places — the **card visual** and the **balance hero accent** (subtle blue→indigo). Never on buttons or text.
- **Glass/blur:** only on the mobile tab bar and sticky headers (backdrop-blur over translucent surface) — a hint of depth, not a theme.
- **Shadows:** from the elevation scale only. No arbitrary inline shadows (retire the ad-hoc `shadow-2xl` usages).

---

## 7. Components

### 7.1 Keep & extend (existing 39 `x-ui`)
Button, Input, Select, Combobox, Checkbox, Radio, Textarea, Badge, Alert, Skeleton, Avatar, Empty-state, Tooltip, Toast, Modal, Drawer, Table, Pagination, Stat-card, Balance-card, Progress-steps, Copy-text, File/Avatar-upload, Detail-list, Card, Asset-icon, Card-network-mark. **Re-skin to the new tokens; add missing states (loading, empty, error, disabled) uniformly.**

### 7.2 New components required by the IA
| Component | Purpose | Notes |
|---|---|---|
| `bottom-nav` + `tab-item` | Mobile primary nav | Raised center Pay; safe-area aware; active indicator |
| `command-search` | Global search / `⌘K` | Grouped results, recents, keyboard nav (audit N1) |
| `segmented-control` | Buy/Sell, All/Crypto/Fiat, tabs | Replaces ad-hoc inline toggles |
| `bottom-sheet` | Mobile confirms, pickers, filters | Sheet variant of modal; drag-to-dismiss |
| `amount-field` | Canonical money input | Hero size, asset pill, MAX chip, live summary — formalizes today's good pattern |
| `asset-pill-selector` | Choose asset within amount-field | Search + recent |
| `quick-action` | Home/Wallet action pills | Consistent 44px targets |
| `merchant-card` | P2P/marketplace | Rating, completion, avg release, online dot, methods, verified |
| `order-timeline` | P2P/order status | Escrow states, countdown |
| `countdown` | Payment/quote timers | Accessible, color-escalating |
| `card-visual` | Animated card | Gradient, sheen, freeze/closed overlays, reveal |
| `connection-indicator` | Realtime status | Subtle online/offline (fix F1) |
| `skeleton/*` per screen | Loading (fix DS2) | List, card, detail, table, dashboard, chart |

### 7.3 Universal component contract (consistency — rule 9)
Every interactive component ships the same state set and API shape:
```
states: default · hover · focus-visible(ring=--focus-ring) · active · loading · disabled · error
sizes:  sm · md · lg   (locked min-heights — already done for form controls)
motion: token-driven (no hardcoded ms)
a11y:   role + label + aria-* ; error via aria-describedby ; live regions via aria-live (fix A1/A2)
target: ≥44px touch (fix A4) — icon buttons get a 44px hit area even if visually 24px
```

---

## 8. Theming (light/dark) mechanics

- Dark mode via a **`.dark` class** on `<html>` toggled from **Settings → Appearance** (new — fix S1) with `System / Light / Dark`, persisted (cookie/localStorage) and respecting `prefers-color-scheme` on first visit. Server reads the preference to avoid a flash (SSR-set class).
- Because components use only semantic tokens, dark mode is "free" for every screen once tokens land.
- Admin stays light-only (per platform convention) — dark mode is a consumer-frontend feature.

---

## 9. Accessibility as tokens (rule 10)

- Focus ring: 2px `--focus-ring` + 2px offset, on every interactive element (already present — standardize).
- Contrast: all semantic text/surface pairs AA-validated in both themes (fix A3).
- Touch: 44px minimum hit area token applied to icon buttons (fix A4).
- Announcements: `aria-live="polite"` on toasts, `assertive` on errors; errors linked via `aria-describedby` (fix A1/A2).
- Motion: reduced-motion path already respected — keep as a first-class variant.

---

## 10. Deliverable of this phase

A single `tokens` source (extend `app.css` `@theme` + `.dark` block) + the component contract above. This is the substrate for Phase 4 wireframes and Phase 5 hi-fi. No screen is designed with a value that isn't a token.
