---
name: Technical Intelligence System
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#45464d'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#4648d4'
  on-secondary: '#ffffff'
  secondary-container: '#6063ee'
  on-secondary-container: '#fffbff'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#00201d'
  on-tertiary-container: '#0c9488'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#e1e0ff'
  secondary-fixed-dim: '#c0c1ff'
  on-secondary-fixed: '#07006c'
  on-secondary-fixed-variant: '#2f2ebe'
  tertiary-fixed: '#89f5e7'
  tertiary-fixed-dim: '#6bd8cb'
  on-tertiary-fixed: '#00201d'
  on-tertiary-fixed-variant: '#005049'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
  status-amber: '#D97706'
  status-violet: '#7C3AED'
  status-blue: '#2563EB'
  risk-red: '#DC2626'
  market-teal: '#0D9488'
  border-subtle: '#E2E8F0'
  text-muted: '#64748B'
typography:
  headline-xl:
    fontFamily: IBM Plex Sans Thai
    fontSize: 40px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: IBM Plex Sans Thai
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.25'
  headline-lg-mobile:
    fontFamily: IBM Plex Sans Thai
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  price-display:
    fontFamily: IBM Plex Sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: '1'
  body-md:
    fontFamily: IBM Plex Sans Thai
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1'
    letterSpacing: 0.05em
  data-table:
    fontFamily: IBM Plex Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.4'
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  section-gap: 64px
  stack-sm: 8px
  stack-md: 16px
---

## Brand & Style

The design system is built on the pillars of **Professionalism, Transparency, and Technical Credible**. It positions the product as a sophisticated financial intelligence tool for hardware, rather than a consumer marketplace. The aesthetic is "Data-First," meaning the UI serves as a clean vessel for information, prioritizing clarity over decoration.

The chosen style is **Corporate / Modern** with a **Minimalist** foundation. It utilizes a structured, grid-based layout that feels engineered and precise. By avoiding "gaming" tropes like neon glows and aggressive angles, the system establishes trust. It feels like a high-end SaaS platform or a modern fintech dashboard—reliable, unbiased, and authoritative.

**Key Visual Principles:**
- **Neutrality-as-a-Feature:** The UI does not push the user to act; it presents data for the user to make informed decisions.
- **Technical Precision:** Use of subtle borders and mathematical spacing to create a sense of order.
- **Content over Chrome:** UI elements (buttons, inputs) are understated to allow the price data and charts to take center stage.

## Colors

The color palette is designed to be informative rather than emotional. The **Primary** color is a deep Charcoal/Navy, used to provide grounding and high contrast for typography. The **Secondary** (Indigo) and **Tertiary** (Teal) serve as technical accents for interactive states and brand flourishes.

**Functional Color Logic:**
- **Primary Text:** Use `#0F172A` for all primary headlines and body copy to ensure maximum legibility.
- **Price Zones:** 
    - **Market Range:** Use `market-teal` or `status-blue`. This represents the stable baseline.
    - **Premium/Caution:** Use `status-amber` for prices trending high or low-confidence data. Use `status-violet` for high-end "Premium" tiers.
- **Risk Management:** `risk-red` is strictly reserved for data integrity errors or critical hardware risks. It is never used simply to denote "high price."
- **Backgrounds:** The interface primarily uses `neutral_color_hex` (`#F8FAFC`) to maintain a clean, breathable "paper" feel, differentiating it from dark-mode gaming sites.

## Typography

This design system uses a **Thai-first** approach, selecting **IBM Plex Sans Thai** for its professional, loopless, and modern structure that bridges the gap between Thai and Latin characters seamlessly. 

**Typography Strategy:**
- **Tabular Figures:** For all prices, specifications, and data tables, `fontFeatureSettings: 'tnum'` (tabular numbers) must be enabled to ensure vertical alignment of digits, which is critical for price comparison.
- **Technical Labels:** **JetBrains Mono** is introduced for small labels and metadata (e.g., "GPU GEN," "CONFIDENCE SCORE") to evoke a technical, "under-the-hood" feeling.
- **Hierarchy:** High-contrast weights (700) are used for prices, while body copy stays at a lighter weight (400) to maintain the clean aesthetic.

## Layout & Spacing

The layout follows a **Fixed Grid** model on desktop (12 columns) and a fluid single-column model on mobile. The philosophy is "Generous Clarity"—using whitespace to prevent the data from feeling overwhelming.

**Layout Rules:**
- **Desktop:** 1280px max-width container. 12 columns with 24px gutters. Use wide side-margins to keep content centered and readable.
- **Mobile:** 16px side margins. Cards should span the full width of the viewport minus margins.
- **Spacing Rhythm:** Based on a 4px scale. Components should primarily use `stack-md` (16px) for internal padding to ensure touch-targets are accessible and data points are distinct.
- **Data Density:** Use "Independent Margins" (extra whitespace) around the Price Distribution Bar to signify its importance as the primary tool of the page.

## Elevation & Depth

Visual hierarchy is conveyed through **Tonal Layers** and **Low-contrast Outlines** rather than heavy shadows. This maintains the "Modern/Financial" aesthetic.

**Elevation Levels:**
- **Level 0 (Surface):** The main background (`neutral_color_hex`).
- **Level 1 (Cards):** White background (`#FFFFFF`) with a 1px solid border in `border-subtle`. On hover, apply an "Ambient Shadow": a very soft, high-diffusion blur (Y: 4px, Blur: 12px, Opacity: 4% Black).
- **Level 2 (Modals/Drawers):** A slightly more pronounced shadow (Y: 8px, Blur: 24px, Opacity: 8% Black) to indicate temporary interaction layers.
- **Separation:** Use `border-subtle` horizontal lines for table rows instead of alternating row colors, keeping the UI looking crisp and architectural.

## Shapes

The shape language is **Soft** and controlled. A moderate border-radius of `0.25rem` (4px) is applied to all standard components to strike a balance between technical precision (sharp) and modern approachability (rounded).

**Component Shapes:**
- **Cards & Inputs:** `rounded` (4px).
- **Status Badges & Chips:** `rounded-lg` (8px) to make them stand out as distinct interactive or status elements.
- **Distribution Bars:** Rounded terminals on the outermost ends of the bar, but internal segments (zones) should have square joins to emphasize the continuous spectrum of data.

## Components

**Buttons:**
Primary buttons use the Secondary Indigo color with white text. They should be "Solid" but with no gradient. Secondary buttons use a ghost style (border and text only).

**Cards:**
The "Result Card" is the core of the UI. It features a clean white surface, a 1px border, and a layout that prioritizes the price in the top right. Use the `label-sm` (JetBrains Mono) for specs like "RTX 3080" or "10GB VRAM."

**Distribution Bars:**
The central data component. It is a horizontal bar split into four distinct color zones (`risk-red` for outlier-low, `market-teal` for market, `status-amber` for slightly high, `status-violet` for premium). A vertical indicator (the "Radar") points to the current item's position on this scale.

**Confidence & Freshness Badges:**
Small, pill-shaped chips using `label-sm`. They use a subtle background tint of their status color with a darker text version of the same hue (e.g., Light Amber background with Dark Amber text) to remain readable without being loud.

**Input Fields:**
Minimalist design. 1px border that thickens slightly on focus and changes to the Secondary color. Use Thai placeholder text that is clearly muted.

**Data Tables:**
High-density tables for the "Market History" view. Use `data-table` typography with 12px vertical padding. The header row should be in a light gray background with all-caps monospaced labels.