/**
 * Live classroom whiteboard — drawing helpers shared by classroom.js.
 *
 * Board coordinates are integers 0..10000 on both axes of a 16:9 board, so a
 * stroke looks the same on every screen size. A stroke is
 * { uid, c: colour, w: pen width in 1/1000 of the board width, p: [x0, y0, x1, y1, …] }.
 * Laravel (RoomBoardService) validates the same limits when a stroke is saved.
 */

export const BOARD_BG = '#ffffff';
/** Pen colours; the last one is the eraser (paints with the board colour). */
export const BOARD_COLOURS = ['#111827', '#dc2626', '#2563eb', '#16a34a', '#f59e0b', BOARD_BG];
export const PEN_COLOURS = BOARD_COLOURS.slice(0, -1);
export const PEN_SIZES = { thin: 3, medium: 6, thick: 14 };
export const ERASER_SIZE = 40;
export const MAX_POINTS = 1500;
export const TEXT_MAX = 200;
export const BOARD_RATIO = 16 / 9;
/** Skip points closer than this to the last one (board units): smaller strokes, same look. */
const MIN_STEP = 12;

export function newStrokeId() {
    return 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
}

/** The largest 16:9 rectangle centred in a box. */
export function fitBoard(width, height) {
    let w = width;
    let h = width / BOARD_RATIO;
    if (h > height) {
        h = height;
        w = height * BOARD_RATIO;
    }

    return { w: Math.max(1, Math.floor(w)), h: Math.max(1, Math.floor(h)), x: Math.floor((width - w) / 2), y: Math.floor((height - h) / 2) };
}

/** Pointer position → board coordinates (clamped to the board). */
export function toBoard(clientX, clientY, rect) {
    const clamp = (v) => Math.max(0, Math.min(10000, Math.round(v)));

    return [clamp(((clientX - rect.left) / rect.width) * 10000), clamp(((clientY - rect.top) / rect.height) * 10000)];
}

/** Append a point unless it is (nearly) where the stroke already is. Returns true when added. */
export function addPoint(stroke, x, y) {
    const p = stroke.p;
    if (p.length >= MAX_POINTS * 2) return false;
    if (p.length >= 2) {
        const dx = x - p[p.length - 2];
        const dy = y - p[p.length - 1];
        if (dx * dx + dy * dy < MIN_STEP * MIN_STEP) return false;
    }
    p.push(x, y);

    return true;
}

/** Same checks as the server, for strokes that arrive from other people over the SFU. */
export function isValidStroke(s) {
    return !!s
        && BOARD_COLOURS.includes(s.c)
        && Number.isInteger(s.w) && s.w >= 1 && s.w <= 80
        && Array.isArray(s.p) && s.p.length % 2 === 0 && s.p.length <= MAX_POINTS * 2
        && s.p.every((v) => Number.isInteger(v) && v >= 0 && v <= 10000)
        && (s.t === undefined || (typeof s.t === 'string' && s.t.length > 0 && s.t.length <= TEXT_MAX && s.p.length === 2));
}

/** Draw one stroke on a canvas of width × height device pixels. */
export function drawStroke(ctx, stroke, width, height) {
    const p = stroke.p;
    if (!p || p.length < 2) return;
    const sx = width / 10000;
    const sy = height / 10000;
    const lw = Math.max(1, (stroke.w * width) / 1000);

    // A text label at one point; w is the letter size.
    if (typeof stroke.t === 'string' && stroke.t) {
        ctx.fillStyle = stroke.c;
        ctx.font = '600 ' + Math.max(8, lw) + 'px system-ui, -apple-system, "Segoe UI", sans-serif';
        ctx.textBaseline = 'top';
        ctx.fillText(stroke.t, p[0] * sx, p[1] * sy);
        return;
    }

    ctx.save();
    // Highlighter: see-through, so what is underneath stays readable.
    if (stroke.h) ctx.globalAlpha = 0.35;
    try {
        drawPath(ctx, stroke, p, sx, sy, lw);
    } finally {
        ctx.restore();
    }
}

function drawPath(ctx, stroke, p, sx, sy, lw) {
    ctx.strokeStyle = stroke.c;
    ctx.fillStyle = stroke.c;
    ctx.lineWidth = lw;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (p.length === 2) {
        ctx.beginPath();
        ctx.arc(p[0] * sx, p[1] * sy, lw / 2, 0, Math.PI * 2);
        ctx.fill();
        return;
    }

    ctx.beginPath();
    ctx.moveTo(p[0] * sx, p[1] * sy);

    // Shapes keep sharp corners: straight segments point to point.
    if (stroke.s) {
        for (let i = 2; i < p.length; i += 2) ctx.lineTo(p[i] * sx, p[i + 1] * sy);
        // A filled shape: a light fill inside, the outline on top.
        if (stroke.f) {
            ctx.save();
            ctx.globalAlpha *= 0.3;
            ctx.fill();
            ctx.restore();
        }
        ctx.stroke();
        return;
    }

    // A pen line is smoothed through the midpoints of consecutive points.
    for (let i = 2; i < p.length - 2; i += 2) {
        const mx = ((p[i] + p[i + 2]) / 2) * sx;
        const my = ((p[i + 1] + p[i + 3]) / 2) * sy;
        ctx.quadraticCurveTo(p[i] * sx, p[i + 1] * sy, mx, my);
    }
    ctx.lineTo(p[p.length - 2] * sx, p[p.length - 1] * sy);
    ctx.stroke();
}

/** Paint the board background and every stroke (oldest first). */
export function paintBoard(ctx, strokes, width, height) {
    ctx.fillStyle = BOARD_BG;
    ctx.fillRect(0, 0, width, height);
    strokes.forEach((s) => drawStroke(ctx, s, width, height));
}

// --- Shapes -------------------------------------------------------------------
// Board units are not square (16:9): 1 unit of y is 9/16 of a unit of x on screen.
const Y_SCALE = 9 / 16;
const clampUnit = (v) => Math.max(0, Math.min(10000, Math.round(v)));

export const SHAPES = ['line', 'arrow', 'double', 'rect', 'ellipse', 'triangle', 'diamond', 'star', 'hexagon'];
/** Shapes with an inside (they can be filled). */
export const CLOSED_SHAPES = ['rect', 'ellipse', 'triangle', 'diamond', 'star', 'hexagon'];

/** Points around the box (x0, y0)–(x1, y1): angles in turns from the top, radius 0..1 of the box. */
function polygon(x0, y0, x1, y1, corners) {
    const cx = (x0 + x1) / 2;
    const cy = (y0 + y1) / 2;
    const rx = Math.abs(x1 - x0) / 2;
    const ry = Math.abs(y1 - y0) / 2;
    const out = [];
    corners.forEach(([turn, r]) => {
        const a = turn * Math.PI * 2 - Math.PI / 2;
        out.push(clampUnit(cx + rx * r * Math.cos(a)), clampUnit(cy + ry * r * Math.sin(a)));
    });
    out.push(out[0], out[1]);
    return out;
}

/** Points of a shape dragged from (x0, y0) to (x1, y1), in board units. */
export function shapePoints(kind, x0, y0, x1, y1) {
    if (kind === 'line') return [x0, y0, x1, y1];
    if (kind === 'rect') return [x0, y0, x1, y0, x1, y1, x0, y1, x0, y0];
    if (kind === 'ellipse') {
        const cx = (x0 + x1) / 2;
        const cy = (y0 + y1) / 2;
        const rx = Math.abs(x1 - x0) / 2;
        const ry = Math.abs(y1 - y0) / 2;
        const out = [];
        for (let i = 0; i <= 48; i++) {
            const a = (i / 48) * Math.PI * 2;
            out.push(clampUnit(cx + rx * Math.cos(a)), clampUnit(cy + ry * Math.sin(a)));
        }
        return out;
    }
    if (kind === 'triangle') return [clampUnit((x0 + x1) / 2), Math.min(y0, y1), Math.max(x0, x1), Math.max(y0, y1), Math.min(x0, x1), Math.max(y0, y1), clampUnit((x0 + x1) / 2), Math.min(y0, y1)];
    if (kind === 'diamond') return polygon(x0, y0, x1, y1, [[0, 1], [0.25, 1], [0.5, 1], [0.75, 1]]);
    if (kind === 'hexagon') return polygon(x0, y0, x1, y1, [0, 1, 2, 3, 4, 5].map((i) => [i / 6 + 1 / 12, 1]));
    if (kind === 'star') return polygon(x0, y0, x1, y1, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9].map((i) => [i / 10, i % 2 ? 0.45 : 1]));
    if (kind === 'double') {
        const one = shapePoints('arrow', x0, y0, x1, y1);
        const back = shapePoints('arrow', x1, y1, x0, y0);
        // Arrow one way, then the second head at the start.
        return [...one, x0, y0, back[4], back[5], x0, y0, back[8], back[9]];
    }
    if (kind === 'arrow') {
        // Head drawn in screen proportions so it is not squashed.
        const dx = x1 - x0;
        const dy = (y1 - y0) * Y_SCALE;
        const len = Math.hypot(dx, dy) || 1;
        const head = Math.min(400, len * 0.35);
        const ang = Math.atan2(dy, dx);
        const wing = (side) => [
            clampUnit(x1 - head * Math.cos(ang + side * 0.5)),
            clampUnit(y1 - (head * Math.sin(ang + side * 0.5)) / Y_SCALE),
        ];
        const [ax, ay] = wing(1);
        const [bx, by] = wing(-1);
        return [x0, y0, x1, y1, ax, ay, x1, y1, bx, by];
    }
    return [x0, y0, x1, y1];
}

/**
 * Smart pen: when a hand-drawn line is close to a straight line, a box or an
 * ellipse, return that clean shape ({ p, s: 1 }); otherwise null (keep the
 * hand-drawn line).
 */
export function recogniseShape(points) {
    const n = points.length / 2;
    if (n < 4) return null;
    const xs = [];
    const ys = [];
    for (let i = 0; i < points.length; i += 2) {
        xs.push(points[i]);
        ys.push(points[i + 1] * Y_SCALE); // screen proportions
    }
    let length = 0;
    for (let i = 1; i < n; i++) length += Math.hypot(xs[i] - xs[i - 1], ys[i] - ys[i - 1]);
    if (length < 300) return null; // a dot or a tiny scribble

    const minX = Math.min(...xs);
    const maxX = Math.max(...xs);
    const minY = Math.min(...ys);
    const maxY = Math.max(...ys);
    const w = maxX - minX;
    const h = maxY - minY;
    const ends = Math.hypot(xs[n - 1] - xs[0], ys[n - 1] - ys[0]);
    const toBoard = (arr) => arr.map((v, i) => (i % 2 ? clampUnit(v / Y_SCALE) : clampUnit(v)));

    // Straight line: every point stays close to the line between the two ends.
    if (ends > length * 0.85) {
        const ax = xs[0];
        const ay = ys[0];
        const bx = xs[n - 1];
        const by = ys[n - 1];
        let worst = 0;
        for (let i = 0; i < n; i++) {
            worst = Math.max(worst, Math.abs((by - ay) * xs[i] - (bx - ax) * ys[i] + bx * ay - by * ax) / (ends || 1));
        }
        if (worst < ends * 0.06) return { p: toBoard([ax, ay, bx, by]), s: 1 };
        return null;
    }

    // Closed shapes: the pen came back near where it started.
    if (ends > Math.max(w, h) * 0.3 || w < 150 || h < 150) return null;

    // Ellipse: points sit on the ellipse that fits the bounding box.
    const cx = (minX + maxX) / 2;
    const cy = (minY + maxY) / 2;
    const rx = w / 2;
    const ry = h / 2;
    let ellipseErr = 0;
    for (let i = 0; i < n; i++) {
        ellipseErr += Math.abs(Math.hypot((xs[i] - cx) / rx, (ys[i] - cy) / ry) - 1);
    }
    ellipseErr /= n;

    // Box: points sit on the edges of the bounding box.
    let boxErr = 0;
    for (let i = 0; i < n; i++) {
        const edge = Math.min(Math.abs(xs[i] - minX), Math.abs(xs[i] - maxX), Math.abs(ys[i] - minY), Math.abs(ys[i] - maxY));
        boxErr += edge / Math.min(w, h);
    }
    boxErr /= n;

    if (boxErr < 0.08 && boxErr < ellipseErr) {
        return { p: toBoard([minX, minY, maxX, minY, maxX, maxY, minX, maxY, minX, minY]), s: 1 };
    }
    if (ellipseErr < 0.15) {
        const out = [];
        for (let i = 0; i <= 48; i++) {
            const a = (i / 48) * Math.PI * 2;
            out.push(cx + rx * Math.cos(a), cy + ry * Math.sin(a));
        }
        return { p: toBoard(out), s: 1 };
    }
    return null;
}
