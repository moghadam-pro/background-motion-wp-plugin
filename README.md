# Background Motion

> Interactive pixel displacement canvas effect for WordPress — move your cursor and watch the background come alive.

![Version](https://img.shields.io/badge/version-1.2.1-black?style=flat-square)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-3858e9?style=flat-square&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-GPL--2.0-green?style=flat-square)

---

## Overview

**Background Motion** adds a full-screen canvas layer behind your site's content. As the visitor moves their mouse, nearby pixel blocks are displaced outward — stretching and snapping back with a smooth spring motion. The effect is fully GPU-accelerated via `requestAnimationFrame` and pauses automatically when the browser tab is hidden.

Every aspect of the effect — grid density, displacement physics, color, opacity, and which pages it appears on — is configurable from a dedicated admin panel without touching any code.

---

## Preview

```
✦ Pixel grid covers the full viewport (position: fixed, z-index: -1)
   Each cell tracks the mouse and is pushed outward on approach
   Cells lerp back to their origin when the cursor moves away
   Color brightens near the cursor for a subtle glow effect
```

---

## Features

- **Zero dependencies** — pure vanilla JS, no jQuery or external libraries
- **Full settings panel** — accessible from the WordPress root sidebar menu
- **Live color preview** — see your color changes before saving
- **Page targeting** — apply to all pages, front page only, or a custom selection
- **Physics controls** — cell size, influence radius, displacement strength, return speed
- **Performance-aware** — pauses on hidden tabs via Page Visibility API, debounces on resize
- **Accessible** — canvas is `aria-hidden` and `pointer-events: none`, never interferes with content

---

## Installation

### From ZIP (manual)

1. Download `background-motion.zip` from the [Releases](../../releases) page
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### From Source

```bash
cd wp-content/plugins/
git clone https://github.com/moghadam-pro/background-motion.git
```

Then activate from **Plugins → Installed Plugins**.

---

## Configuration

After activation, find **Background Motion** in the WordPress sidebar menu (below Settings).

### General

| Setting | Default | Description |
|---|---|---|
| Effect | Enabled | Master on/off switch |
| Z-index | `-1` | Stack position of the canvas. `-1` places it behind all content |
| Canvas opacity | `1.0` | Overall transparency of the canvas layer |
| Cursor dot | On | Shows a small white dot at the exact cursor position |
| Influence ring | On | Shows a subtle circle marking the displacement radius |

### Page Targeting

| Option | Description |
|---|---|
| All pages | Effect runs on every page of the site |
| Front page only | Effect runs only on `is_front_page()` |
| Specific pages | Pick pages from a checkbox list or enter IDs manually |

### Grid & Physics

| Setting | Range | Default | Description |
|---|---|---|---|
| Cell size | 4 – 80 px | `22` | Size of each pixel block. Smaller = denser grid, higher CPU cost |
| Influence radius | 20 – 400 px | `110` | How far from the cursor cells are affected |
| Displacement strength | 0 – 300 | `55` | How far cells are pushed from their origin |
| Return speed (lerp) | 0.01 – 1.0 | `0.09` | Interpolation factor. Lower = slower, stretchier return |

### Color

| Setting | Range | Default | Description |
|---|---|---|---|
| Base brightness | 0 – 200 | `18` | Luminance of cells at rest |
| Red multiplier | 0 – 2 | `0.72` | Scales the red channel of each cell's color |
| Green multiplier | 0 – 2 | `0.78` | Scales the green channel |
| Blue multiplier | 0 – 2 | `1.0` | Scales the blue channel |
| Alpha min | 0 – 1 | `0.55` | Minimum per-cell transparency (randomized per cell) |
| Alpha max | 0 – 1 | `0.90` | Maximum per-cell transparency |

> **Color formula:** `R = clamp(brightness × rMult, 0, 255)` — same for G and B. Cells near the cursor receive a `+60` brightness boost.

#### Preset examples

| Look | Brightness | R | G | B |
|---|---|---|---|---|
| Cool blue (default) | 18 | 0.72 | 0.78 | 1.0 |
| Warm amber | 22 | 1.0 | 0.75 | 0.3 |
| Monochrome grey | 20 | 0.9 | 0.9 | 0.9 |
| Deep green | 16 | 0.3 | 1.0 | 0.5 |
| Magenta | 20 | 1.0 | 0.3 | 0.9 |

---

## File Structure

```
background-motion/
├── background-motion.php       # Main plugin file — registration, admin UI, settings
└── assets/
    └── background-motion.js    # Frontend canvas effect (vanilla JS, no dependencies)
```

---

## How It Works

### Canvas layer

On page load, the script appends a `<canvas>` element to `<body>` with:

```css
position: fixed;
top: 0; left: 0;
width: 100vw; height: 100vh;
pointer-events: none;
z-index: -1;
aria-hidden: true;
```

### Pixel grid

The viewport is divided into a grid of cells sized `CELL × CELL` pixels. Each cell stores:

- `ox, oy` — original (resting) position
- `x, y` — current rendered position
- `tx, ty` — target position (updated each frame based on mouse proximity)
- `brightness` and `alpha` — randomized slightly per cell for visual texture

### Physics loop (`requestAnimationFrame`)

Each frame:

1. For every cell, compute distance from the mouse
2. If within `RADIUS`, calculate a quadratic push force: `force² × STRENGTH`
3. Set `tx/ty` to origin displaced by that force
4. Lerp `x/y` toward `tx/ty` by factor `LERP` — this gives the spring-like return

```js
var force = 1 - dist / RADIUS;
var push  = force * force * STRENGTH;
cell.tx   = cell.ox + (dx / dist) * push;

cell.x   += (cell.tx - cell.x) * LERP;
```

### Color

```js
var bright = cell.brightness + (nearCursor ? (1 - dist/RADIUS) * 60 : 0);
var r = Math.min(255, Math.round(bright * R_MULT));
var g = Math.min(255, Math.round(bright * G_MULT));
var b = Math.min(255, Math.round(bright * B_MULT));
ctx.fillStyle = `rgba(${r},${g},${b},${cell.alpha})`;
```

### Config injection

Settings are passed from PHP to JS via `wp_localize_script`:

```php
wp_localize_script( 'bgm-main', 'BGM_CONFIG', [
    'cellSize' => 22,
    'radius'   => 110,
    // ...
]);
```

---

## Performance Notes

- A `22px` cell size on a 1920×1080 viewport creates ~4,700 cells — runs comfortably at 60fps on modern hardware
- Dropping to `10px` cells creates ~20,000 cells — noticeable on lower-end devices
- The canvas pauses via `document.visibilitychange` when the tab is not visible
- Resize rebuilds the grid with a 200ms debounce to avoid thrashing

---

## Requirements

| | Minimum |
|---|---|
| WordPress | 5.8 |
| PHP | 7.4 |
| Browser | Any modern browser with Canvas 2D and `requestAnimationFrame` support |

---

## Changelog

### 1.2.1
- Initial public release
- Full settings panel with live color preview swatches
- Page targeting with checkbox picker
- Root sidebar menu entry with custom SVG icon
- Page Visibility API pause/resume
- Debounced resize handler

---

## Author

**Moghadam.pro** — [moghadam.pro](https://moghadam.pro)

---

## License

Licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).
