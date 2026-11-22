# Home hero RGB lighting (MODINSPECT-HERO-RGB-001 / 002)

The approved picture is `design/hero-rgb/source.webp` (1774x887). It is never modified.

## How it works
- `original.webp` is the `<img>` in the page: the LCP element, the no-JS / failure fallback.
- After `window.load`, `public/assets/mi-hero-rgb.js` loads a neutral base (`base.webp`, lime pulled out, luminance kept) and seven transparent lighting overlays: `fan-1..3`, `floor`, `scene-l/c/r` (original colours, alpha = how lit a pixel is, cropped to their own box, placed in % of the 1774x887 picture so registration holds at every size), plus `dim.webp` (black, same alpha, used to lower intensity without fading hardware).
- Overlays are recoloured with `filter: hue-rotate()` (lime is hue 0). One selected mode drives everything; a mode change tweens from the colour shown now, so there are no jumps.
- Mode switch: 8 small icon-only buttons floating over the bottom-right of the picture, no panel and no wheel (original line icons, `aria-pressed`, 44px targets, name in tooltip and aria-label). No colour input exists.

## Modes
Static (brand lime #D4F139) · Breathing (3.6 s) · Strobing (slow pulse, 0.59 Hz, max 50% dim) · Rainbow (default, 12 s, fans staggered) · Color Cycle (12 s, synced) · Starry Night (slow irregular twinkle, blue-violet) · Smart (fixed palette lime→cyan→violet→magenta→orange, 26 s) · Dark (80% dim, hardware stays visible). Choice is kept per tab (`sessionStorage` key `miRgbMode`).

## Motion rules
Reduced motion: no loops; every mode is a still state, the HUD stays operable. Animations pause when the hero is off screen or the tab is hidden.

## Regenerating the assets
`php scripts/build_hero_rgb.php` (PHP 8.1+ with GD + WebP; `--preview` writes a recoloured check image to the temp folder). Output: `public/assets/brand/hero-rgb/` including `layers.json`, which the home view reads for overlay positions.

## Edge
The picture and the light stage share one soft mask (left 9%, right 8%, top 8%, bottom 16%) so no rectangle shows; the case fans stay fully inside.

## Known limits
- Lime is found by colour (hue 52-112, saturation, value). Gold connectors and the teal board are left as they are. Soft glow edges keep a little of the neutral base under the colour.
- `hue-rotate` is a matrix approximation, so blue/violet states are slightly less saturated than a true re-render.
- Measured here: all 8 specs (keyframes, durations, hues) and the pause/resume wiring. Not measurable here: real FPS, Lighthouse, memory (the test browser throttled rAF to ~1.5-2.5 fps even on a plain text page). Transfer: original 166 KB first; base + overlays + dim about 715 KB loaded after the page is idle.
