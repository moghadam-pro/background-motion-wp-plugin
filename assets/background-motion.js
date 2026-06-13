/**
 * Background Motion — Frontend Canvas Script v1.2.1
 * moghadam.pro
 * Config injected by WordPress via wp_localize_script as BGM_CONFIG
 */
(function () {
    'use strict';

    var CFG      = window.BGM_CONFIG || {};
    var CELL     = CFG.cellSize       || 22;
    var RADIUS   = CFG.radius         || 110;
    var STRENGTH = CFG.strength       || 55;
    var LERP     = CFG.lerp           || 0.09;
    var BASE_B   = CFG.baseBrightness || 18;
    var R_MULT   = CFG.rMult          || 0.72;
    var G_MULT   = CFG.gMult          || 0.78;
    var B_MULT   = CFG.bMult          || 1.0;
    var A_MIN    = CFG.alphaMin       || 0.55;
    var A_MAX    = CFG.alphaMax       || 0.90;
    var SHOW_CUR = CFG.showCursor !== undefined ? CFG.showCursor : true;
    var SHOW_RNG = CFG.showRing   !== undefined ? CFG.showRing   : true;
    var Z_IDX    = CFG.zIndex     !== undefined ? CFG.zIndex     : -1;
    var OPACITY  = CFG.opacity    !== undefined ? CFG.opacity    : 1.0;

    // Create and inject canvas
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
                var ox = c * CELL;
                var oy = r * CELL;
                cells.push({
                    ox:         ox,
                    oy:         oy,
                    x:          ox,
                    y:          oy,
                    tx:         ox,
                    ty:         oy,
                    size:       CELL - gap,
                    brightness: BASE_B + Math.random() * 14,
                    alpha:      A_MIN  + Math.random() * (A_MAX - A_MIN),
                });
            }
        }
    }

    function update() {
        var mx  = mouse.x;
        var my  = mouse.y;
        var len = cells.length;

        for (var i = 0; i < len; i++) {
            var cell = cells[i];
            var cx   = cell.ox + CELL * 0.5;
            var cy   = cell.oy + CELL * 0.5;
            var dx   = cx - mx;
            var dy   = cy - my;
            var dist = Math.sqrt(dx * dx + dy * dy);

            if (dist < RADIUS && dist > 0) {
                var force = 1 - dist / RADIUS;
                var push  = force * force * STRENGTH;
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
        ctx.clearRect(0, 0, W, H);

        var mx  = mouse.x;
        var my  = mouse.y;
        var len = cells.length;

        for (var i = 0; i < len; i++) {
            var cell   = cells[i];
            var cx     = cell.ox + CELL * 0.5;
            var cy     = cell.oy + CELL * 0.5;
            var dx     = cx - mx;
            var dy     = cy - my;
            var dist   = Math.sqrt(dx * dx + dy * dy);
            var bright = cell.brightness;

            if (dist < RADIUS) {
                bright += (1 - dist / RADIUS) * 60;
            }

            var r = Math.min(255, Math.round(bright * R_MULT));
            var g = Math.min(255, Math.round(bright * G_MULT));
            var b = Math.min(255, Math.round(bright * B_MULT));

            ctx.fillStyle = 'rgba(' + r + ',' + g + ',' + b + ',' + cell.alpha + ')';
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
            ctx.strokeStyle = 'rgba(255,255,255,0.07)';
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
        mouse.x = -9999;
        mouse.y = -9999;
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
        if (document.hidden) {
            cancelAnimationFrame(rafId);
        } else {
            loop();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { buildGrid(); loop(); });
    } else {
        buildGrid();
        loop();
    }

}());
