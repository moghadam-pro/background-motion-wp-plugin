<?php
/**
 * Plugin Name: Background Motion
 * Plugin URI:  https://moghadam.pro
 * Description: Interactive pixel displacement canvas effect for your site background with full settings control.
 * Version:     1.3.0
 * Author:      Moghadam.pro
 * Author URI:  https://moghadam.pro
 * License:     GPL-2.0+
 * Text Domain: background-motion
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BGM_VERSION', '1.3.0' );
define( 'BGM_PATH',    plugin_dir_path( __FILE__ ) );
define( 'BGM_URL',     plugin_dir_url( __FILE__ ) );
define( 'BGM_OPTION',  'bgm_settings' );

/* ─────────────────────────────────────────
   SVG icon
───────────────────────────────────────── */
function bgm_menu_icon() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">'
         . '<rect x="1"  y="1"  width="8" height="8" rx="1.5" fill="currentColor" opacity="0.35"/>'
         . '<rect x="11" y="1"  width="8" height="8" rx="1.5" fill="currentColor" opacity="0.6"/>'
         . '<rect x="1"  y="11" width="8" height="8" rx="1.5" fill="currentColor" opacity="0.6"/>'
         . '<rect x="11" y="11" width="8" height="8" rx="1.5" fill="currentColor"/>'
         . '<circle cx="14" cy="14" r="3.5" fill="currentColor" opacity="0.15"/>'
         . '<circle cx="14" cy="14" r="1.5" fill="currentColor"/>'
         . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/* ─────────────────────────────────────────
   Defaults  (no 'enabled' key anymore)
───────────────────────────────────────── */
function bgm_defaults() {
    return [
        'apply_to'        => 'all',   // 'all' | 'specific'
        'specific_pages'  => [],
        'cell_size'       => 22,
        'radius'          => 110,
        'strength'        => 55,
        'lerp'            => 0.09,
        'base_brightness' => 18,
        'color_r_mult'    => 0.72,
        'color_g_mult'    => 0.78,
        'color_b_mult'    => 1.0,
        'alpha_min'       => 0.55,
        'alpha_max'       => 0.90,
        'show_cursor'     => 1,
        'show_ring'       => 1,
        'z_index'         => -1,
        'canvas_opacity'  => 1.0,
    ];
}

function bgm_get( $key = null ) {
    $opts = wp_parse_args( get_option( BGM_OPTION, [] ), bgm_defaults() );
    return $key ? ( $opts[ $key ] ?? null ) : $opts;
}

/* ─────────────────────────────────────────
   Is active?
   Plugin is "active" when:
     apply_to = all  →  always active
     apply_to = specific  →  at least one page is selected
───────────────────────────────────────── */
function bgm_is_active() {
    $opts = bgm_get();
    if ( $opts['apply_to'] === 'all' ) return true;
    return ! empty( $opts['specific_pages'] );
}

/* ─────────────────────────────────────────
   Frontend enqueue
───────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', 'bgm_enqueue' );
function bgm_enqueue() {
    if ( ! bgm_is_active() ) return;

    $opts  = bgm_get();
    $apply = $opts['apply_to'];

    if ( $apply === 'specific' ) {
        $ids = array_map( 'intval', (array) $opts['specific_pages'] );
        if ( ! in_array( get_the_ID(), $ids ) ) return;
    }

    wp_enqueue_script( 'bgm-main', BGM_URL . 'assets/background-motion.js', [], BGM_VERSION, true );

    wp_localize_script( 'bgm-main', 'BGM_CONFIG', [
        'cellSize'       => (int)   $opts['cell_size'],
        'radius'         => (int)   $opts['radius'],
        'strength'       => (int)   $opts['strength'],
        'lerp'           => (float) $opts['lerp'],
        'baseBrightness' => (int)   $opts['base_brightness'],
        'rMult'          => (float) $opts['color_r_mult'],
        'gMult'          => (float) $opts['color_g_mult'],
        'bMult'          => (float) $opts['color_b_mult'],
        'alphaMin'       => (float) $opts['alpha_min'],
        'alphaMax'       => (float) $opts['alpha_max'],
        'showCursor'     => (bool)  $opts['show_cursor'],
        'showRing'       => (bool)  $opts['show_ring'],
        'zIndex'         => (int)   $opts['z_index'],
        'opacity'        => (float) $opts['canvas_opacity'],
    ]);
}

/* ─────────────────────────────────────────
   Admin menu
───────────────────────────────────────── */
add_action( 'admin_menu', 'bgm_admin_menu' );
function bgm_admin_menu() {
    add_menu_page( 'Background Motion', 'Background Motion', 'manage_options',
        'background-motion', 'bgm_settings_page', bgm_menu_icon(), 81 );
}

add_action( 'admin_init', 'bgm_register_settings' );
function bgm_register_settings() {
    register_setting( 'bgm_group', BGM_OPTION, 'bgm_sanitize' );
}

/* ─────────────────────────────────────────
   Sanitize
───────────────────────────────────────── */
function bgm_sanitize( $in ) {
    $d     = bgm_defaults();
    $clean = [];

    $clean['apply_to'] = in_array( $in['apply_to'] ?? '', ['all','specific'] ) ? $in['apply_to'] : 'all';

    $raw = $in['specific_pages'] ?? [];
    if ( is_array( $raw ) ) {
        $clean['specific_pages'] = array_values( array_filter( array_map( 'intval', $raw ) ) );
    } else {
        $ids = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
        $clean['specific_pages'] = array_values( array_filter( array_map( 'intval', $ids ) ) );
    }

    // Numeric helpers
    $int   = fn($v,$mn,$mx,$def) => max($mn, min($mx, isset($in[$v]) ? (int)$in[$v]     : $def));
    $float = fn($v,$mn,$mx,$def) => max($mn, min($mx, isset($in[$v]) ? (float)$in[$v]   : $def));

    $clean['cell_size']       = $int  ('cell_size',       4,    80,  $d['cell_size']);
    $clean['radius']          = $int  ('radius',          20,   400, $d['radius']);
    $clean['strength']        = $int  ('strength',        0,    300, $d['strength']);
    $clean['lerp']            = $float('lerp',            0.01, 1,   $d['lerp']);
    $clean['base_brightness'] = $int  ('base_brightness', 0,    200, $d['base_brightness']);
    $clean['color_r_mult']    = $float('color_r_mult',    0,    2,   $d['color_r_mult']);
    $clean['color_g_mult']    = $float('color_g_mult',    0,    2,   $d['color_g_mult']);
    $clean['color_b_mult']    = $float('color_b_mult',    0,    2,   $d['color_b_mult']);
    $clean['alpha_min']       = $float('alpha_min',       0,    1,   $d['alpha_min']);
    $clean['alpha_max']       = $float('alpha_max',       0,    1,   $d['alpha_max']);
    $clean['z_index']         = (int)  ($in['z_index']         ?? $d['z_index']);
    $clean['canvas_opacity']  = $float('canvas_opacity',  0,    1,   $d['canvas_opacity']);
    $clean['show_cursor']     = ! empty($in['show_cursor']) ? 1 : 0;
    $clean['show_ring']       = ! empty($in['show_ring'])   ? 1 : 0;

    // Ensure alpha_min <= alpha_max
    if ( $clean['alpha_min'] > $clean['alpha_max'] ) {
        $clean['alpha_max'] = $clean['alpha_min'];
    }

    return $clean;
}

/* ─────────────────────────────────────────
   Settings page
───────────────────────────────────────── */
function bgm_settings_page() {
    if ( ! current_user_can('manage_options') ) return;
    $o     = bgm_get();
    $saved = isset($_GET['settings-updated']);
    $active = bgm_is_active();
    ?>
    <div class="wrap" id="bgm-wrap">

    <!–– Header ––>
    <div class="bgm-header">
        <div class="bgm-header-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" width="22" height="22">
                <rect x="1" y="1" width="8" height="8" rx="1.5" fill="#fff" opacity="0.35"/>
                <rect x="11" y="1" width="8" height="8" rx="1.5" fill="#fff" opacity="0.6"/>
                <rect x="1" y="11" width="8" height="8" rx="1.5" fill="#fff" opacity="0.6"/>
                <rect x="11" y="11" width="8" height="8" rx="1.5" fill="#fff"/>
                <circle cx="14" cy="14" r="3.5" fill="#fff" opacity="0.15"/>
                <circle cx="14" cy="14" r="1.5" fill="#fff"/>
            </svg>
        </div>
        <div>
            <h1>Background Motion</h1>
            <p>v1.3.0 &nbsp;·&nbsp; <a href="https://moghadam.pro" target="_blank">moghadam.pro</a>
               &nbsp;·&nbsp;
               <span class="bgm-pill <?= $active ? 'bgm-pill-on' : 'bgm-pill-off' ?>">
                   <?= $active ? '● Active' : '○ Inactive' ?>
               </span>
            </p>
        </div>
    </div>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>
    <?php endif; ?>

    <?php if ( ! $active ) : ?>
    <div class="notice notice-warning"><p><strong>Background Motion is inactive.</strong> Select at least one page in Page Targeting to activate the effect.</p></div>
    <?php endif; ?>

<style>
/* ── Reset & layout ── */
#bgm-wrap * { box-sizing: border-box; }
#bgm-wrap { max-width: 1280px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

/* ── Header ── */
.bgm-header { display:flex; align-items:center; gap:14px; margin:14px 0 20px; }
.bgm-header-icon { width:42px; height:42px; background:#1d2327; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bgm-header h1 { margin:0; font-size:20px; line-height:1.2; }
.bgm-header p  { margin:3px 0 0; color:#8a8a8a; font-size:12px; }
.bgm-header a  { color:#8a8a8a; }
.bgm-pill { display:inline-block; font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px; vertical-align:middle; }
.bgm-pill-on  { background:#edfaef; color:#2a7a3a; }
.bgm-pill-off { background:#fef7ec; color:#9a6700; }

/* ── Two-column master layout ── */
.bgm-master { display:grid; grid-template-columns:1fr 1fr; gap:18px; align-items:start; }
.bgm-left  { display:flex; flex-direction:column; gap:18px; }
.bgm-right { position:sticky; top:32px; }

/* ── Cards ── */
.bgm-card { background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:20px 22px; }
.bgm-card-title {
    margin:0 0 16px; font-size:11px; text-transform:uppercase;
    letter-spacing:.1em; color:#999; font-weight:600;
    border-bottom:1px solid #f0f0f0; padding-bottom:10px;
    display:flex; align-items:center; gap:7px;
}

/* ── Page targeting ── */
.bgm-mode-tabs { display:flex; gap:0; margin-bottom:14px; border-radius:7px; overflow:hidden; border:1px solid #ddd; }
.bgm-mode-tab  {
    flex:1; padding:8px 0; text-align:center; font-size:13px; cursor:pointer;
    background:#f9f9f9; border:none; border-right:1px solid #ddd; transition:background .15s,color .15s;
}
.bgm-mode-tab:last-child { border-right:none; }
.bgm-mode-tab.active { background:#1d2327; color:#fff; font-weight:600; }

.bgm-page-list {
    max-height:200px; overflow-y:auto; border:1px solid #e2e4e7;
    border-radius:7px; background:#fafafa;
}
.bgm-page-list label {
    display:flex; align-items:center; gap:8px; padding:7px 12px;
    font-size:13px; cursor:pointer; border-bottom:1px solid #f0f0f0; transition:background .1s;
}
.bgm-page-list label:last-child { border-bottom:none; }
.bgm-page-list label:hover { background:#f0f4ff; }
.bgm-page-list input[type=checkbox] { width:15px; height:15px; cursor:pointer; margin:0; flex-shrink:0; }
.bgm-page-id { color:#bbb; font-size:11px; margin-left:auto; }
.bgm-no-pages { padding:14px 12px; color:#aaa; font-size:13px; font-style:italic; }

/* ── Rows inside cards ── */
.bgm-row { display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f5f5f5; gap:12px; }
.bgm-row:last-child { border-bottom:none; }
.bgm-row-label { font-size:13px; color:#444; min-width:130px; flex-shrink:0; }
.bgm-row-label small { display:block; font-size:11px; color:#aaa; margin-top:2px; }
.bgm-row-control { display:flex; align-items:center; gap:6px; flex:1; justify-content:flex-end; }

/* ── Slider + number input combo ── */
.bgm-slider-wrap { display:flex; align-items:center; gap:6px; }
.bgm-slider-wrap input[type=range] {
    width:110px; accent-color:#2271b1; cursor:pointer;
}
.bgm-num {
    width:58px; padding:4px 6px; border:1px solid #ddd; border-radius:5px;
    font-size:12px; font-family:monospace; text-align:right;
    transition:border-color .15s;
}
.bgm-num:focus { border-color:#2271b1; outline:none; box-shadow:0 0 0 2px rgba(34,113,177,.15); }
.bgm-num.bgm-invalid { border-color:#dc3232; background:#fff5f5; }
.bgm-spin { display:flex; flex-direction:column; gap:1px; }
.bgm-spin button {
    width:18px; height:14px; border:1px solid #ddd; background:#f9f9f9;
    border-radius:3px; cursor:pointer; font-size:10px; line-height:1;
    display:flex; align-items:center; justify-content:center; padding:0;
    transition:background .1s;
}
.bgm-spin button:hover { background:#e8f0fb; border-color:#2271b1; }

/* ── Toggle checkbox ── */
.bgm-toggle { display:flex; align-items:center; gap:7px; font-size:13px; cursor:pointer; }
.bgm-toggle input { width:15px; height:15px; cursor:pointer; margin:0; }

/* ── Preview panel ── */
.bgm-preview-card { background:#111; border:1px solid #2a2a2a; border-radius:10px; overflow:hidden; }
.bgm-preview-label {
    padding:10px 16px; font-size:11px; text-transform:uppercase; letter-spacing:.1em;
    color:#555; font-weight:600; border-bottom:1px solid #1e1e1e; background:#0d0d0d;
    display:flex; align-items:center; justify-content:space-between;
}
.bgm-preview-label span { color:#333; }
#bgm-preview-canvas { display:block; width:100%; cursor:none; }
.bgm-preview-hint { padding:8px 16px; font-size:11px; color:#444; background:#0d0d0d; text-align:center; }

/* ── Save bar ── */
.bgm-save-bar { margin-top:20px; padding-top:16px; border-top:1px solid #e2e4e7; display:flex; align-items:center; gap:14px; }
.bgm-save-bar .button-primary { height:36px; padding:0 20px; font-size:13px; }
.bgm-footer-note { font-size:12px; color:#ccc; }
</style>

    <form method="post" action="options.php" id="bgm-form">
    <?php settings_fields('bgm_group'); ?>

    <div class="bgm-master">

        <!–– LEFT COLUMN: all setting cards ––>
        <div class="bgm-left">

            <!–– PAGE TARGETING ––>
            <div class="bgm-card">
                <div class="bgm-card-title">
                    <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><rect x="3" y="2" width="14" height="16" rx="2" stroke="#999" stroke-width="2"/><path d="M7 7h6M7 11h4" stroke="#999" stroke-width="1.8" stroke-linecap="round"/></svg>
                    Page Targeting
                </div>

                <!–– Mode toggle ––>
                <div class="bgm-mode-tabs" id="bgm-mode-tabs">
                    <button type="button" class="bgm-mode-tab <?= $o['apply_to']==='all' ? 'active' : '' ?>"
                        onclick="bgmSetMode('all')">All Pages</button>
                    <button type="button" class="bgm-mode-tab <?= $o['apply_to']==='specific' ? 'active' : '' ?>"
                        onclick="bgmSetMode('specific')">Specific Pages</button>
                </div>
                <input type="hidden" name="<?= BGM_OPTION ?>[apply_to]" id="bgm-apply-to" value="<?= esc_attr($o['apply_to']) ?>">

                <!–– Specific pages list ––>
                <div id="bgm-pages-wrap" style="<?= $o['apply_to']==='specific' ? '' : 'display:none' ?>">
                    <div class="bgm-page-list" id="bgm-page-list">
                        <?php bgm_render_page_list( $o['specific_pages'] ); ?>
                    </div>
                    <p style="margin:7px 0 0;font-size:12px;color:#aaa;">
                        Check pages where the effect should appear. Plugin is inactive until at least one page is selected.
                    </p>
                </div>

                <!–– All pages notice ––>
                <div id="bgm-all-notice" style="<?= $o['apply_to']==='all' ? '' : 'display:none' ?>; font-size:13px;color:#555;margin-top:4px;">
                    Effect will run on <strong>every page</strong> of the site.
                </div>
            </div>

            <!–– GRID & PHYSICS ––>
            <div class="bgm-card">
                <div class="bgm-card-title">
                    <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><rect x="2" y="2" width="6" height="6" rx="1" stroke="#999" stroke-width="1.8"/><rect x="12" y="2" width="6" height="6" rx="1" stroke="#999" stroke-width="1.8"/><rect x="2" y="12" width="6" height="6" rx="1" stroke="#999" stroke-width="1.8"/><rect x="12" y="12" width="6" height="6" rx="1" stroke="#999" stroke-width="1.8"/></svg>
                    Grid &amp; Physics
                </div>
                <?php
                bgm_slider_row('Cell size',          'cell_size',  $o['cell_size'],       4,    80,   1,    'px', 'Smaller = denser grid, heavier on CPU');
                bgm_slider_row('Influence radius',   'radius',     $o['radius'],          20,   400,  5,    'px');
                bgm_slider_row('Displacement',       'strength',   $o['strength'],        0,    300,  5,    '');
                bgm_slider_row('Return speed (lerp)','lerp',       $o['lerp'],            0.01, 1,    0.01, '', 'Lower = slower, stretchier');
                ?>
            </div>

            <!–– COLOR ––>
            <div class="bgm-card">
                <div class="bgm-card-title">
                    <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="#999" stroke-width="2"/><path d="M10 3C10 3 5 7 5 11a5 5 0 0010 0c0-4-5-8-5-8z" fill="#999" opacity=".3"/></svg>
                    Color
                </div>
                <?php
                bgm_slider_row('Base brightness',  'base_brightness', $o['base_brightness'], 0, 200, 1,    '');
                bgm_slider_row('Red multiplier',   'color_r_mult',    $o['color_r_mult'],    0, 2,   0.01, '');
                bgm_slider_row('Green multiplier', 'color_g_mult',    $o['color_g_mult'],    0, 2,   0.01, '');
                bgm_slider_row('Blue multiplier',  'color_b_mult',    $o['color_b_mult'],    0, 2,   0.01, '');
                bgm_slider_row('Alpha min',        'alpha_min',       $o['alpha_min'],       0, 1,   0.05, '');
                bgm_slider_row('Alpha max',        'alpha_max',       $o['alpha_max'],       0, 1,   0.05, '');
                ?>
            </div>

            <!–– GENERAL ––>
            <div class="bgm-card">
                <div class="bgm-card-title">
                    <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="3" stroke="#999" stroke-width="2"/><path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.22 4.22l1.42 1.42M14.36 14.36l1.42 1.42M4.22 15.78l1.42-1.42M14.36 5.64l1.42-1.42" stroke="#999" stroke-width="1.8" stroke-linecap="round"/></svg>
                    General
                </div>
                <?php
                bgm_slider_row('Canvas opacity', 'canvas_opacity', $o['canvas_opacity'], 0, 1,    0.05, '');
                bgm_slider_row('Z-index',        'z_index',        $o['z_index'],        -100, 100, 1,   '', 'Negative = behind content');
                ?>
                <div class="bgm-row">
                    <div class="bgm-row-label">Cursor dot</div>
                    <div class="bgm-row-control">
                        <label class="bgm-toggle">
                            <input type="checkbox" name="<?= BGM_OPTION ?>[show_cursor]" value="1" <?php checked($o['show_cursor']); ?> />
                            Show white dot at cursor
                        </label>
                    </div>
                </div>
                <div class="bgm-row">
                    <div class="bgm-row-label">Influence ring</div>
                    <div class="bgm-row-control">
                        <label class="bgm-toggle">
                            <input type="checkbox" name="<?= BGM_OPTION ?>[show_ring]" value="1" <?php checked($o['show_ring']); ?> />
                            Show radius ring
                        </label>
                    </div>
                </div>
            </div>

        </div><!–– /.bgm-left ––>

        <!–– RIGHT COLUMN: live preview ––>
        <div class="bgm-right">
            <div class="bgm-preview-card">
                <div class="bgm-preview-label">
                    Live Preview
                    <span id="bgm-preview-status">— move cursor over canvas</span>
                </div>
                <canvas id="bgm-preview-canvas" height="520"></canvas>
                <div class="bgm-preview-hint">↑ Live preview updates as you adjust settings</div>
            </div>
        </div>

    </div><!–– /.bgm-master ––>

    <div class="bgm-save-bar">
        <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
        <span class="bgm-footer-note">Background Motion v1.3.0 &nbsp;·&nbsp; moghadam.pro</span>
    </div>

    </form>
    </div><!–– /.wrap ––>

<script>
(function(){
'use strict';

/* ── Mode switch ── */
window.bgmSetMode = function(mode) {
    document.getElementById('bgm-apply-to').value = mode;
    document.querySelectorAll('.bgm-mode-tab').forEach(function(t){
        t.classList.toggle('active', t.textContent.trim().toLowerCase().startsWith(mode === 'all' ? 'all' : 'spec'));
    });
    document.getElementById('bgm-pages-wrap').style.display  = mode === 'specific' ? '' : 'none';
    document.getElementById('bgm-all-notice').style.display  = mode === 'all'      ? '' : 'none';
};

/* ── Slider ↔ number sync + spin buttons ── */
function bgmSync(key) {
    var slider = document.getElementById('bgm-slider-' + key);
    var num    = document.getElementById('bgm-num-'    + key);
    if (!slider || !num) return;

    var mn   = parseFloat(slider.min);
    var mx   = parseFloat(slider.max);
    var step = parseFloat(slider.step) || 1;
    var isInt = step >= 1;

    // slider → number
    slider.addEventListener('input', function() {
        num.value = isInt ? parseInt(this.value) : parseFloat(this.value).toFixed(2);
        num.classList.remove('bgm-invalid');
        bgmPreviewUpdate();
    });

    // number → slider (validate on change)
    num.addEventListener('input', function() {
        var v = parseFloat(this.value);
        if (isNaN(v)) { this.classList.add('bgm-invalid'); return; }
        this.classList.remove('bgm-invalid');
        if (v < mn || v > mx) { this.classList.add('bgm-invalid'); return; }
        slider.value = v;
        bgmPreviewUpdate();
    });
    num.addEventListener('blur', function() {
        var v = parseFloat(this.value);
        if (isNaN(v) || v < mn) { this.value = isInt ? mn : mn.toFixed(2); slider.value = mn; }
        else if (v > mx)        { this.value = isInt ? mx : mx.toFixed(2); slider.value = mx; }
        this.classList.remove('bgm-invalid');
        bgmPreviewUpdate();
    });
}

/* ── Spin buttons ── */
window.bgmSpin = function(key, dir) {
    var slider = document.getElementById('bgm-slider-' + key);
    var num    = document.getElementById('bgm-num-'    + key);
    if (!slider || !num) return;
    var step = parseFloat(slider.step) || 1;
    var mn   = parseFloat(slider.min);
    var mx   = parseFloat(slider.max);
    var isInt = step >= 1;
    var v = parseFloat(slider.value) + dir * step;
    v = Math.max(mn, Math.min(mx, v));
    v = Math.round(v / step) * step;
    slider.value = v;
    num.value    = isInt ? Math.round(v) : v.toFixed(2);
    num.classList.remove('bgm-invalid');
    bgmPreviewUpdate();
};

/* Wire up all sliders */
['cell_size','radius','strength','lerp',
 'base_brightness','color_r_mult','color_g_mult','color_b_mult',
 'alpha_min','alpha_max','canvas_opacity','z_index'].forEach(bgmSync);

/* ── Live Preview Canvas ── */
var pc     = document.getElementById('bgm-preview-canvas');
var pctx   = pc.getContext('2d');
var pmouse = { x:-9999, y:-9999 };
var pcells = [];
var praf   = null;
var pW, pH;

function pCfg() {
    function v(id){ return parseFloat(document.getElementById('bgm-slider-'+id).value); }
    return {
        CELL: v('cell_size'), RADIUS: v('radius'), STRENGTH: v('strength'),
        LERP: v('lerp'), BASE_B: v('base_brightness'),
        RM: v('color_r_mult'), GM: v('color_g_mult'), BM: v('color_b_mult'),
        AMIN: v('alpha_min'), AMAX: v('alpha_max'),
    };
}

function pBuild() {
    pW = pc.offsetWidth;
    pH = pc.offsetHeight || 520;
    pc.width  = pW;
    pc.height = pH;
    var cfg  = pCfg();
    var cols = Math.ceil(pW / cfg.CELL) + 1;
    var rows = Math.ceil(pH / cfg.CELL) + 1;
    var gap  = Math.max(1, Math.round(cfg.CELL * 0.12));
    pcells = [];
    for (var r = 0; r < rows; r++) {
        for (var c = 0; c < cols; c++) {
            var ox = c * cfg.CELL, oy = r * cfg.CELL;
            pcells.push({ ox,oy, x:ox,y:oy, tx:ox,ty:oy,
                size: cfg.CELL - gap,
                brightness: cfg.BASE_B + Math.random() * 14,
                alpha: cfg.AMIN + Math.random() * Math.max(0, cfg.AMAX - cfg.AMIN) });
        }
    }
}

function pUpdate() {
    var cfg = pCfg();
    var mx = pmouse.x, my = pmouse.y;
    pcells.forEach(function(cell) {
        var cx = cell.ox + cfg.CELL*.5, cy = cell.oy + cfg.CELL*.5;
        var dx = cx-mx, dy = cy-my;
        var dist = Math.sqrt(dx*dx+dy*dy);
        if (dist < cfg.RADIUS && dist > 0) {
            var f = 1 - dist/cfg.RADIUS;
            var push = f*f*cfg.STRENGTH;
            cell.tx = cell.ox + (dx/dist)*push;
            cell.ty = cell.oy + (dy/dist)*push;
        } else { cell.tx = cell.ox; cell.ty = cell.oy; }
        cell.x += (cell.tx - cell.x) * cfg.LERP;
        cell.y += (cell.ty - cell.y) * cfg.LERP;
    });
}

function pDraw() {
    pctx.clearRect(0,0,pW,pH);
    var cfg = pCfg();
    var mx = pmouse.x, my = pmouse.y;
    pcells.forEach(function(cell) {
        var cx = cell.ox+cfg.CELL*.5, cy = cell.oy+cfg.CELL*.5;
        var dx = cx-mx, dy = cy-my;
        var dist = Math.sqrt(dx*dx+dy*dy);
        var b = cell.brightness + (dist < cfg.RADIUS ? (1-dist/cfg.RADIUS)*60 : 0);
        var r = Math.min(255,Math.round(b*cfg.RM));
        var g = Math.min(255,Math.round(b*cfg.GM));
        var bv= Math.min(255,Math.round(b*cfg.BM));
        pctx.fillStyle = 'rgba('+r+','+g+','+bv+','+cell.alpha+')';
        pctx.fillRect(Math.round(cell.x),Math.round(cell.y),cell.size,cell.size);
    });
    // cursor dot
    if (mx > -9000) {
        pctx.beginPath(); pctx.arc(mx,my,5,0,Math.PI*2);
        pctx.fillStyle='rgba(255,255,255,0.9)'; pctx.fill();
        pctx.beginPath(); pctx.arc(mx,my,cfg.RADIUS,0,Math.PI*2);
        pctx.strokeStyle='rgba(255,255,255,0.07)'; pctx.lineWidth=1; pctx.stroke();
    }
}

function pLoop() { pUpdate(); pDraw(); praf = requestAnimationFrame(pLoop); }

window.bgmPreviewUpdate = function() {
    cancelAnimationFrame(praf);
    pBuild();
    pLoop();
};

pc.addEventListener('mousemove', function(e) {
    var r = pc.getBoundingClientRect();
    pmouse.x = (e.clientX - r.left) * (pW / r.width);
    pmouse.y = (e.clientY - r.top)  * (pH / r.height);
    document.getElementById('bgm-preview-status').textContent = '';
});
pc.addEventListener('mouseleave', function() {
    pmouse.x = pmouse.y = -9999;
    document.getElementById('bgm-preview-status').textContent = '— move cursor over canvas';
});

// Rebuild preview when any input changes
document.getElementById('bgm-form').addEventListener('change', bgmPreviewUpdate);

// Boot preview
pBuild(); pLoop();

})();
</script>
    <?php
}

/* ─────────────────────────────────────────
   Helper: render a slider + number + spin row
───────────────────────────────────────── */
function bgm_slider_row( $label, $key, $value, $min, $max, $step, $unit = '', $desc = '' ) {
    $is_int  = $step >= 1;
    $fmt     = $is_int ? (string)(int)$value : number_format((float)$value, 2, '.', '');
    $step_s  = $is_int ? (string)(int)$step  : rtrim(number_format((float)$step, 2, '.', ''), '0');
    $name    = BGM_OPTION . '[' . $key . ']';
    ?>
    <div class="bgm-row">
        <div class="bgm-row-label">
            <?= esc_html($label) ?>
            <?php if ($unit) echo '<small>' . esc_html($unit) . '</small>'; ?>
            <?php if ($desc) echo '<small>' . esc_html($desc) . '</small>'; ?>
        </div>
        <div class="bgm-row-control">
            <div class="bgm-slider-wrap">
                <input type="range"
                    id="bgm-slider-<?= $key ?>"
                    min="<?= $min ?>" max="<?= $max ?>" step="<?= $step_s ?>"
                    value="<?= esc_attr($value) ?>" />
                <div class="bgm-spin">
                    <button type="button" onclick="bgmSpin('<?= $key ?>',1)"  title="Increase">▲</button>
                    <button type="button" onclick="bgmSpin('<?= $key ?>',-1)" title="Decrease">▼</button>
                </div>
                <input type="number"
                    id="bgm-num-<?= $key ?>"
                    name="<?= $name ?>"
                    class="bgm-num"
                    value="<?= esc_attr($fmt) ?>"
                    min="<?= $min ?>" max="<?= $max ?>" step="<?= $step_s ?>" />
            </div>
        </div>
    </div>
    <?php
}

/* ─────────────────────────────────────────
   Helper: page checkbox list
───────────────────────────────────────── */
function bgm_render_page_list( $selected_ids = [] ) {
    $pages    = get_pages(['post_status' => 'publish', 'number' => 300]);
    $selected = array_map('intval', (array)$selected_ids);

    if ( empty($pages) ) {
        echo '<div class="bgm-no-pages">No published pages found.</div>';
        return;
    }
    foreach ( $pages as $page ) {
        $chk = in_array($page->ID, $selected) ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="' . BGM_OPTION . '[specific_pages][]" value="' . esc_attr($page->ID) . '" ' . $chk . '>';
        echo esc_html($page->post_title);
        echo '<span class="bgm-page-id">ID ' . $page->ID . '</span>';
        echo '</label>';
    }
}

/* ─────────────────────────────────────────
   Activation
───────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
    if ( ! get_option(BGM_OPTION) ) {
        add_option(BGM_OPTION, bgm_defaults());
    }
});
