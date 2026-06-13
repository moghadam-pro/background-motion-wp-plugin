<?php
/**
 * Plugin Name: Background Motion
 * Plugin URI:  https://moghadam.pro
 * Description: Interactive pixel displacement canvas effect for your site background with full settings control.
 * Version:     1.2.1
 * Author:      Moghadam.pro
 * Author URI:  https://moghadam.pro
 * License:     GPL-2.0+
 * Text Domain: background-motion
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BGM_VERSION', '1.2.1' );
define( 'BGM_PATH',    plugin_dir_path( __FILE__ ) );
define( 'BGM_URL',     plugin_dir_url( __FILE__ ) );
define( 'BGM_OPTION',  'bgm_settings' );

/* ──────────────────────────────────────────
   SVG icon (inline, base64 for menu)
   A simple 4-square pixel grid with a
   motion/cursor accent — fits 20x20 admin icon
────────────────────────────────────────── */
function bgm_menu_icon() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">'
         . '<rect x="1"  y="1"  width="8"  height="8"  rx="1.5" fill="currentColor" opacity="0.4"/>'
         . '<rect x="11" y="1"  width="8"  height="8"  rx="1.5" fill="currentColor" opacity="0.7"/>'
         . '<rect x="1"  y="11" width="8"  height="8"  rx="1.5" fill="currentColor" opacity="0.7"/>'
         . '<rect x="11" y="11" width="8"  height="8"  rx="1.5" fill="currentColor" opacity="1"/>'
         . '<circle cx="14" cy="14" r="3.5" fill="currentColor" opacity="0.15"/>'
         . '<circle cx="14" cy="14" r="1.5" fill="currentColor"/>'
         . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/* ──────────────────────────────────────────
   Default settings
────────────────────────────────────────── */
function bgm_defaults() {
    return [
        'enabled'         => 1,
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
        'apply_to'        => 'all',
        'specific_pages'  => [],
        'z_index'         => -1,
        'canvas_opacity'  => 1.0,
    ];
}

function bgm_get( $key = null ) {
    $opts = wp_parse_args( get_option( BGM_OPTION, [] ), bgm_defaults() );
    return $key ? ( $opts[ $key ] ?? null ) : $opts;
}

/* ──────────────────────────────────────────
   Frontend: enqueue
────────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', 'bgm_enqueue' );
function bgm_enqueue() {
    $opts = bgm_get();

    if ( ! $opts['enabled'] ) return;

    $apply = $opts['apply_to'];
    if ( $apply === 'front' && ! is_front_page() ) return;
    if ( $apply === 'specific' ) {
        $ids = array_map( 'intval', (array) $opts['specific_pages'] );
        if ( ! in_array( get_the_ID(), $ids ) ) return;
    }

    wp_enqueue_script(
        'bgm-main',
        BGM_URL . 'assets/background-motion.js',
        [],
        BGM_VERSION,
        true
    );

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

/* ──────────────────────────────────────────
   Admin: top-level menu (with icon)
────────────────────────────────────────── */
add_action( 'admin_menu', 'bgm_admin_menu' );
function bgm_admin_menu() {
    add_menu_page(
        'Background Motion',          // page title
        'Background Motion',          // menu label
        'manage_options',             // capability
        'background-motion',          // slug
        'bgm_settings_page',          // callback
        bgm_menu_icon(),              // icon
        81                            // position (after Settings = 80)
    );
}

add_action( 'admin_init', 'bgm_register_settings' );
function bgm_register_settings() {
    register_setting( 'bgm_group', BGM_OPTION, 'bgm_sanitize' );
}

/* ──────────────────────────────────────────
   Sanitize
────────────────────────────────────────── */
function bgm_sanitize( $input ) {
    $d     = bgm_defaults();
    $clean = [];

    $clean['enabled']         = ! empty( $input['enabled'] ) ? 1 : 0;
    $clean['cell_size']       = max( 4,    min( 80,  (int)   ( $input['cell_size']       ?? $d['cell_size'] ) ) );
    $clean['radius']          = max( 20,   min( 400, (int)   ( $input['radius']          ?? $d['radius'] ) ) );
    $clean['strength']        = max( 0,    min( 300, (int)   ( $input['strength']        ?? $d['strength'] ) ) );
    $clean['lerp']            = max( 0.01, min( 1,   (float) ( $input['lerp']            ?? $d['lerp'] ) ) );
    $clean['base_brightness'] = max( 0,    min( 200, (int)   ( $input['base_brightness'] ?? $d['base_brightness'] ) ) );
    $clean['color_r_mult']    = max( 0,    min( 2,   (float) ( $input['color_r_mult']    ?? $d['color_r_mult'] ) ) );
    $clean['color_g_mult']    = max( 0,    min( 2,   (float) ( $input['color_g_mult']    ?? $d['color_g_mult'] ) ) );
    $clean['color_b_mult']    = max( 0,    min( 2,   (float) ( $input['color_b_mult']    ?? $d['color_b_mult'] ) ) );
    $clean['alpha_min']       = max( 0,    min( 1,   (float) ( $input['alpha_min']       ?? $d['alpha_min'] ) ) );
    $clean['alpha_max']       = max( 0,    min( 1,   (float) ( $input['alpha_max']       ?? $d['alpha_max'] ) ) );
    $clean['show_cursor']     = ! empty( $input['show_cursor'] ) ? 1 : 0;
    $clean['show_ring']       = ! empty( $input['show_ring'] )   ? 1 : 0;
    $clean['apply_to']        = in_array( $input['apply_to'] ?? '', ['all','front','specific'] )
                                    ? $input['apply_to'] : 'all';
    $clean['z_index']         = (int) ( $input['z_index'] ?? $d['z_index'] );
    $clean['canvas_opacity']  = max( 0, min( 1, (float) ( $input['canvas_opacity'] ?? $d['canvas_opacity'] ) ) );

    $raw = $input['specific_pages'] ?? '';
    if ( is_array( $raw ) ) {
        $clean['specific_pages'] = array_values( array_filter( array_map( 'intval', $raw ) ) );
    } else {
        $ids = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
        $clean['specific_pages'] = array_values( array_filter( array_map( 'intval', $ids ) ) );
    }

    return $clean;
}

/* ──────────────────────────────────────────
   Admin: settings page
────────────────────────────────────────── */
function bgm_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $o     = bgm_get();
    $saved = isset( $_GET['settings-updated'] );
    ?>
    <div class="wrap" id="bgm-wrap">

    <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px;margin-top:12px;">
        <div style="width:40px;height:40px;background:#1d2327;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" width="22" height="22">
                <rect x="1"  y="1"  width="8"  height="8"  rx="1.5" fill="#fff" opacity="0.35"/>
                <rect x="11" y="1"  width="8"  height="8"  rx="1.5" fill="#fff" opacity="0.6"/>
                <rect x="1"  y="11" width="8"  height="8"  rx="1.5" fill="#fff" opacity="0.6"/>
                <rect x="11" y="11" width="8"  height="8"  rx="1.5" fill="#fff" opacity="1"/>
                <circle cx="14" cy="14" r="3.5" fill="#fff" opacity="0.15"/>
                <circle cx="14" cy="14" r="1.5" fill="#fff"/>
            </svg>
        </div>
        <div>
            <h1 style="margin:0;font-size:20px;line-height:1.2;">Background Motion</h1>
            <p style="margin:2px 0 0;color:#8a8a8a;font-size:12px;">v1.2.1 &nbsp;·&nbsp; <a href="https://moghadam.pro" target="_blank" style="color:#8a8a8a;">moghadam.pro</a></p>
        </div>
    </div>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible" style="margin-top:16px;"><p>Settings saved successfully.</p></div>
    <?php endif; ?>

    <style>
    #bgm-wrap { max-width:940px; }
    #bgm-wrap .bgm-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:20px; }
    #bgm-wrap .bgm-card { background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:20px 24px; }
    #bgm-wrap .bgm-card h2 { margin:0 0 14px; font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:#999; font-weight:600; border-bottom:1px solid #f0f0f0; padding-bottom:10px; display:flex; align-items:center; gap:7px; }
    #bgm-wrap .bgm-card h2 svg { opacity:.5; }
    #bgm-wrap table.form-table { margin:0; }
    #bgm-wrap table.form-table th { width:160px; font-weight:400; font-size:13px; padding:7px 10px 7px 0; vertical-align:middle; color:#444; }
    #bgm-wrap table.form-table td { padding:5px 0; vertical-align:middle; }
    #bgm-wrap input[type=number] { width:68px; }
    #bgm-wrap input[type=range]  { width:148px; vertical-align:middle; margin-right:4px; accent-color:#2271b1; }
    #bgm-wrap .bgm-val { display:inline-block; min-width:34px; text-align:right; font-size:12px; color:#555; font-family:monospace; }
    #bgm-wrap .bgm-full { grid-column:1/-1; }
    #bgm-wrap p.description { margin:3px 0 0; font-size:12px; color:#aaa; }
    #bgm-wrap .bgm-badge { display:inline-block; background:#f0f4ff; color:#3b6fd4; font-size:10px; font-weight:600; padding:1px 6px; border-radius:3px; margin-left:5px; vertical-align:middle; letter-spacing:.03em; }
    #bgm-wrap .bgm-toggle { display:flex; align-items:center; gap:8px; }
    #bgm-wrap .bgm-toggle input[type=checkbox] { width:16px; height:16px; cursor:pointer; }
    #bgm-wrap .bgm-page-list { max-height:150px; overflow-y:auto; border:1px solid #ddd; border-radius:6px; padding:6px 10px; background:#fafafa; }
    #bgm-wrap .bgm-page-list label { display:block; margin:4px 0; font-size:13px; cursor:pointer; }
    #bgm-wrap .bgm-swatch { width:46px; height:46px; border-radius:7px; border:1px solid #ddd; transition:background .15s; }
    #bgm-wrap .bgm-swatch-group { display:flex; align-items:flex-start; gap:10px; padding-top:4px; }
    #bgm-wrap .bgm-swatch-label { font-size:11px; color:#aaa; margin:4px 0 0; text-align:center; }
    #bgm-wrap .bgm-status-on  { color:#46b450; font-weight:600; font-size:13px; }
    #bgm-wrap .bgm-status-off { color:#dc3232; font-weight:600; font-size:13px; }
    </style>

    <form method="post" action="options.php">
    <?php settings_fields( 'bgm_group' ); ?>

    <div class="bgm-grid">

        <!-- ── General ── -->
        <div class="bgm-card">
            <h2>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8" stroke="#444" stroke-width="2"/><path d="M10 6v4l3 3" stroke="#444" stroke-width="2" stroke-linecap="round"/></svg>
                General
            </h2>
            <table class="form-table">
                <tr>
                    <th>Effect</th>
                    <td>
                        <label class="bgm-toggle">
                            <input type="checkbox" name="<?= BGM_OPTION ?>[enabled]" value="1" <?php checked( $o['enabled'] ); ?> />
                            <span class="<?= $o['enabled'] ? 'bgm-status-on' : 'bgm-status-off' ?>" id="bgm-status-label">
                                <?= $o['enabled'] ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Z-index</th>
                    <td>
                        <input type="number" name="<?= BGM_OPTION ?>[z_index]" value="<?= esc_attr( $o['z_index'] ) ?>" step="1" />
                        <p class="description">-1 = behind all content</p>
                    </td>
                </tr>
                <tr>
                    <th>Canvas opacity</th>
                    <td>
                        <input type="range" min="0" max="1" step="0.05" value="<?= $o['canvas_opacity'] ?>"
                            oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2)"
                            name="<?= BGM_OPTION ?>[canvas_opacity]" />
                        <span class="bgm-val"><?= number_format($o['canvas_opacity'],2) ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Cursor dot</th>
                    <td>
                        <label class="bgm-toggle">
                            <input type="checkbox" name="<?= BGM_OPTION ?>[show_cursor]" value="1" <?php checked( $o['show_cursor'] ); ?> />
                            Show white dot at cursor
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Influence ring</th>
                    <td>
                        <label class="bgm-toggle">
                            <input type="checkbox" name="<?= BGM_OPTION ?>[show_ring]" value="1" <?php checked( $o['show_ring'] ); ?> />
                            Show radius ring around cursor
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Page Targeting ── -->
        <div class="bgm-card">
            <h2>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="2" width="14" height="16" rx="2" stroke="#444" stroke-width="2"/><path d="M7 7h6M7 11h4" stroke="#444" stroke-width="1.8" stroke-linecap="round"/></svg>
                Page Targeting
            </h2>
            <table class="form-table">
                <tr>
                    <th>Apply to</th>
                    <td>
                        <select name="<?= BGM_OPTION ?>[apply_to]" id="bgm-apply-to" onchange="bgmTogglePages()">
                            <option value="all"      <?php selected( $o['apply_to'], 'all' ); ?>>All pages</option>
                            <option value="front"    <?php selected( $o['apply_to'], 'front' ); ?>>Front page only</option>
                            <option value="specific" <?php selected( $o['apply_to'], 'specific' ); ?>>Specific pages</option>
                        </select>
                    </td>
                </tr>
                <tr id="bgm-specific-row" style="<?= $o['apply_to'] !== 'specific' ? 'display:none' : '' ?>">
                    <th>Select pages</th>
                    <td>
                        <div class="bgm-page-list">
                            <?php bgm_render_page_picker( $o['specific_pages'] ); ?>
                        </div>
                        <p class="description" style="margin-top:7px;">Or enter IDs manually (comma-separated):</p>
                        <input type="text"
                            name="<?= BGM_OPTION ?>[specific_pages]"
                            value="<?= esc_attr( implode( ', ', (array) $o['specific_pages'] ) ) ?>"
                            placeholder="e.g. 12, 45, 78"
                            style="width:100%;margin-top:5px;" />
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Grid & Physics ── -->
        <div class="bgm-card">
            <h2>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="6" height="6" rx="1" stroke="#444" stroke-width="1.8"/><rect x="12" y="2" width="6" height="6" rx="1" stroke="#444" stroke-width="1.8"/><rect x="2" y="12" width="6" height="6" rx="1" stroke="#444" stroke-width="1.8"/><rect x="12" y="12" width="6" height="6" rx="1" stroke="#444" stroke-width="1.8"/></svg>
                Grid &amp; Physics
            </h2>
            <table class="form-table">
                <tr>
                    <th>Cell size <span class="bgm-badge">px</span></th>
                    <td>
                        <input type="range" min="4" max="80" step="1" value="<?= $o['cell_size'] ?>"
                            oninput="this.nextElementSibling.textContent=this.value"
                            name="<?= BGM_OPTION ?>[cell_size]" />
                        <span class="bgm-val"><?= $o['cell_size'] ?></span>
                        <p class="description">Smaller = more pixels, heavier on CPU</p>
                    </td>
                </tr>
                <tr>
                    <th>Influence radius <span class="bgm-badge">px</span></th>
                    <td>
                        <input type="range" min="20" max="400" step="5" value="<?= $o['radius'] ?>"
                            oninput="this.nextElementSibling.textContent=this.value"
                            name="<?= BGM_OPTION ?>[radius]" />
                        <span class="bgm-val"><?= $o['radius'] ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Displacement strength</th>
                    <td>
                        <input type="range" min="0" max="300" step="5" value="<?= $o['strength'] ?>"
                            oninput="this.nextElementSibling.textContent=this.value"
                            name="<?= BGM_OPTION ?>[strength]" />
                        <span class="bgm-val"><?= $o['strength'] ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Return speed <span class="bgm-badge">lerp</span></th>
                    <td>
                        <input type="range" min="0.01" max="1" step="0.01" value="<?= $o['lerp'] ?>"
                            oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2)"
                            name="<?= BGM_OPTION ?>[lerp]" />
                        <span class="bgm-val"><?= number_format($o['lerp'],2) ?></span>
                        <p class="description">Lower = slower, stretchier return</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Color ── -->
        <div class="bgm-card">
            <h2>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="7" stroke="#444" stroke-width="2"/><path d="M10 3C10 3 5 7 5 11a5 5 0 0010 0c0-4-5-8-5-8z" fill="#444" opacity=".3"/></svg>
                Color
            </h2>
            <table class="form-table">
                <tr>
                    <th>Base brightness</th>
                    <td>
                        <input type="range" min="0" max="200" step="1" value="<?= $o['base_brightness'] ?>"
                            oninput="this.nextElementSibling.textContent=this.value;bgmUpdatePreview()"
                            name="<?= BGM_OPTION ?>[base_brightness]" id="bgm-bb" />
                        <span class="bgm-val"><?= $o['base_brightness'] ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Red multiplier</th>
                    <td>
                        <input type="range" min="0" max="2" step="0.01" value="<?= $o['color_r_mult'] ?>"
                            oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2);bgmUpdatePreview()"
                            name="<?= BGM_OPTION ?>[color_r_mult]" id="bgm-rm" />
                        <span class="bgm-val"><?= number_format($o['color_r_mult'],2) ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Green multiplier</th>
                    <td>
                        <input type="range" min="0" max="2" step="0.01" value="<?= $o['color_g_mult'] ?>"
                            oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2);bgmUpdatePreview()"
                            name="<?= BGM_OPTION ?>[color_g_mult]" id="bgm-gm" />
                        <span class="bgm-val"><?= number_format($o['color_g_mult'],2) ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Blue multiplier</th>
                    <td>
                        <input type="range" min="0" max="2" step="0.01" value="<?= $o['color_b_mult'] ?>"
                            oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2);bgmUpdatePreview()"
                            name="<?= BGM_OPTION ?>[color_b_mult]" id="bgm-bm" />
                        <span class="bgm-val"><?= number_format($o['color_b_mult'],2) ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Alpha min</th>
                    <td>
                        <input type="range" min="0" max="1" step="0.05" value="<?= $o['alpha_min'] ?>"
                            oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2)"
                            name="<?= BGM_OPTION ?>[alpha_min]" />
                        <span class="bgm-val"><?= number_format($o['alpha_min'],2) ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Alpha max</th>
                    <td>
                        <input type="range" min="0" max="1" step="0.05" value="<?= $o['alpha_max'] ?>"
                            oninput="this.nextElementSibling.textContent=parseFloat(this.value).toFixed(2)"
                            name="<?= BGM_OPTION ?>[alpha_max]" />
                        <span class="bgm-val"><?= number_format($o['alpha_max'],2) ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Color preview</th>
                    <td>
                        <div class="bgm-swatch-group">
                            <div>
                                <div class="bgm-swatch" id="bgm-swatch-base"></div>
                                <p class="bgm-swatch-label">Base</p>
                            </div>
                            <div>
                                <div class="bgm-swatch" id="bgm-swatch-lit"></div>
                                <p class="bgm-swatch-label">Lit</p>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div><!-- /.bgm-grid -->

    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e4e7;display:flex;align-items:center;gap:14px;">
        <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
        <span style="font-size:12px;color:#bbb;">Background Motion v1.2.1 &nbsp;·&nbsp; moghadam.pro</span>
    </div>

    </form>
    </div><!-- /.wrap -->

    <script>
    function bgmTogglePages() {
        var v = document.getElementById('bgm-apply-to').value;
        document.getElementById('bgm-specific-row').style.display = v === 'specific' ? '' : 'none';
    }
    function bgmClamp(v, mn, mx) { return Math.min(mx, Math.max(mn, Math.round(v))); }
    function bgmUpdatePreview() {
        var bb = parseFloat(document.getElementById('bgm-bb').value);
        var rm = parseFloat(document.getElementById('bgm-rm').value);
        var gm = parseFloat(document.getElementById('bgm-gm').value);
        var bm = parseFloat(document.getElementById('bgm-bm').value);
        var r  = bgmClamp(bb * rm, 0, 255);
        var g  = bgmClamp(bb * gm, 0, 255);
        var b  = bgmClamp(bb * bm, 0, 255);
        var rl = bgmClamp((bb + 60) * rm, 0, 255);
        var gl = bgmClamp((bb + 60) * gm, 0, 255);
        var bl = bgmClamp((bb + 60) * bm, 0, 255);
        document.getElementById('bgm-swatch-base').style.background = 'rgb('+r+','+g+','+b+')';
        document.getElementById('bgm-swatch-lit').style.background  = 'rgb('+rl+','+gl+','+bl+')';
    }
    bgmUpdatePreview();

    // Live enable label toggle
    var cb = document.querySelector('input[name="bgm_settings[enabled]"]');
    var lbl = document.getElementById('bgm-status-label');
    if (cb && lbl) {
        cb.addEventListener('change', function() {
            lbl.textContent = this.checked ? 'Enabled' : 'Disabled';
            lbl.className   = this.checked ? 'bgm-status-on' : 'bgm-status-off';
        });
    }
    </script>
    <?php
}

/* ──────────────────────────────────────────
   Helper: page checkbox list
────────────────────────────────────────── */
function bgm_render_page_picker( $selected_ids = [] ) {
    $pages    = get_pages( ['post_status' => 'publish', 'number' => 200] );
    $selected = array_map( 'intval', (array) $selected_ids );
    if ( empty( $pages ) ) { echo '<p style="color:#aaa;font-size:13px;margin:4px 0;">No published pages found.</p>'; return; }
    foreach ( $pages as $page ) {
        $chk = in_array( $page->ID, $selected ) ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="' . BGM_OPTION . '[specific_pages][]" value="' . esc_attr( $page->ID ) . '" ' . $chk . ' style="margin-right:6px;"> ';
        echo esc_html( $page->post_title );
        echo ' <span style="color:#bbb;font-size:11px;">(ID: ' . $page->ID . ')</span>';
        echo '</label>';
    }
}

/* ──────────────────────────────────────────
   Activation
────────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
    if ( ! get_option( BGM_OPTION ) ) {
        add_option( BGM_OPTION, bgm_defaults() );
    }
} );
