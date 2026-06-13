/**
 * Background Motion — Frontend Canvas Script v1.3.1
 * moghadam.pro
 * Config injected by WordPress via wp_localize_script as BGM_CONFIG
 * NOTE: wp_localize_script serialises all values as strings.
 *   booleans come as "1" (true) or "" (false) — compare with == 1
 *   numbers come as numeric strings — parseFloat/parseInt as needed
 */
(function () {
    'use strict';

    var CFG      = window.BGM_CONFIG || {};
    var CELL     = parseInt(CFG.cellSize, 10)       || 22;
    var RADIUS   = parseInt(CFG.radius,   10)       || 110;
    var STRENGTH = parseInt(CFG.strength, 10)       || 55;
    var LERP     = parseFloat(CFG.lerp)             || 0.09;
    var BASE_B   = parseInt(CFG.baseBrightness, 10) || 18;
    var R_MULT   = parseFloat(CFG.rMult)            || 0.72;
    var G_MULT   = parseFloat(CFG.gMult)            || 0.78;
    var B_MULT   = parseFloat(CFG.bMult)            || 1.0;
    var A_MIN    = parseFloat(CFG.alphaMin)         || 0.55;
    var A_MAX    = parseFloat(CFG.alphaMax)         || 0.90;
    var OPACITY  = parseFloat(CFG.opacity)          || 1.0;
    var Z_IDX    = parseInt(CFG.zIndex, 10);
    if (isNaN(Z_IDX)) Z_IDX = -1;

    // FIX: wp_localize_script sends booleans as "1" or "" — use == 1
    var SHOW_CUR = (CFG.showCursor == 1);
    var SHOW_RNG = (CFG.showRing   == 1);

    // Color helpers
    var BG_COLOR    = CFG.bgColor    || '#000000';
    var PIXEL_COLOR = CFG.pixelColor || '#ffffff';

    function hexToRgb(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        return {
            r: parseInt(hex.substring(0, 2), 16),
            g: parseInt(hex.substring(2, 4), 16),
            b: parseInt(hex.substring(4, 6), 16)
        };
    }

    var BG  = hexToRgb(BG_COLOR);
    var PXC = hexToRgb(PIXEL_COLOR);

    // Create canvas
    var canvas = document.createElement('canvas');
    canvas.id  = 'bgm-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = [
        'position:fixed',
        'top:0',
        'left:0',
        'width:100vw',
        'height:100vh',
        'pointer-events:none',
        'z-index:' + Z_IDX,
        'opacity:' + OPACITY,
    ].join(';');
    document.body.appendChild(canvas);

    var ctx = canvas.getContext('2d');
    var W, H, cells = [];
    var mouse = { x: -9999, y: -9999 };
    var rafId = null;

    function buildGrid() {
        W = window.innerWidth;
        H = window.innerHeight;
        canvas.width  = W;
        canvas.height = H;

        var cols = Math.ceil(W / CELL) + 2;
        var rows = Math.ceil(H / CELL) + 2;
        var gap  = Math.max(1, Math.round(CELL * 0.12));

        cells = [];
        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                var ox = c * CELL, oy = r * CELL;
                cells.push({
                    ox: ox, oy: oy, x: ox, y: oy, tx: ox, ty: oy,
                    size:       CELL - gap,
                    brightness: BASE_B + Math.random() * 14,
                    alpha:      A_MIN  + Math.random() * (A_MAX - A_MIN),
                });
            }
        }
    }

    function update() {
        var mx = mouse.x, my = mouse.y, len = cells.length;
        for (var i = 0; i < len; i++) {
            var cell = cells[i];
            var cx = cell.ox + CELL * 0.5, cy = cell.oy + CELL * 0.5;
            var dx = cx - mx, dy = cy - my;
            var dist = Math.sqrt(dx * dx + dy * dy);

            if (dist < RADIUS && dist > 0) {
                var f    = 1 - dist / RADIUS;
                var push = f * f * STRENGTH;
                cell.tx = cell.ox + (dx / dist) * push;
                cell.ty = cell.oy + (dy / dist) * push;
            } else {
                cell.tx = cell.ox;
                cell.ty = cell.oy;
            }
            cell.x += (cell.tx - cell.x) * LERP;
            cell.y += (cell.ty - cell.y) * LERP;
        }
    }

    function draw() {
        // Background fill
        ctx.fillStyle = 'rgb(' + BG.r + ',' + BG.g + ',' + BG.b + ')';
        ctx.fillRect(0, 0, W, H);

        var mx = mouse.x, my = mouse.y, len = cells.length;

        for (var i = 0; i < len; i++) {
            var cell   = cells[i];
            var cx     = cell.ox + CELL * 0.5, cy = cell.oy + CELL * 0.5;
            var dx     = cx - mx, dy = cy - my;
            var dist   = Math.sqrt(dx * dx + dy * dy);
            var litFactor = dist < RADIUS ? (1 - dist / RADIUS) : 0;

            var b    = cell.brightness + litFactor * 60;
            var norm = b / 200;
            var r    = Math.min(255, Math.round(PXC.r * norm * R_MULT / 0.72));
            var g    = Math.min(255, Math.round(PXC.g * norm * G_MULT / 0.78));
            var bv   = Math.min(255, Math.round(PXC.b * norm * B_MULT / 1.0));

            ctx.fillStyle = 'rgba(' + r + ',' + g + ',' + bv + ',' + cell.alpha + ')';
            ctx.fillRect(Math.round(cell.x), Math.round(cell.y), cell.size, cell.size);
        }

        if (SHOW_CUR && mx > -9000) {
            ctx.beginPath();
            ctx.arc(mx, my, 6, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255,255,255,0.9)';
            ctx.fill();
        }

        if (SHOW_RNG && mx > -9000) {
            ctx.beginPath();
            ctx.arc(mx, my, RADIUS, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(255,255,255,0.12)';
            ctx.lineWidth   = 1;
            ctx.stroke();
        }
    }

    function loop() {
        update();
        draw();
        rafId = requestAnimationFrame(loop);
    }

    document.addEventListener('mousemove', function (e) {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
    });
    document.addEventListener('mouseleave', function () {
        mouse.x = mouse.y = -9999;
    });

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            cancelAnimationFrame(rafId);
            buildGrid();
            loop();
        }, 200);
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { cancelAnimationFrame(rafId); } else { loop(); }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { buildGrid(); loop(); });
    } else {
        buildGrid();
        loop();
    }

}());
