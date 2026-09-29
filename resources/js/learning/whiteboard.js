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
        && s.p.every((v) => Number.isInteger(v) && v >= 0 && v <= 10000);
}

/** Draw one stroke on a canvas of width × height device pixels. */
export function drawStroke(ctx, stroke, width, height) {
    const p = stroke.p;
    if (!p || p.length < 2) return;
    const sx = width / 10000;
    const sy = height / 10000;
    const lw = Math.max(1, (stroke.w * width) / 1000);

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

    // Smooth the line through the midpoints of consecutive points.
    ctx.beginPath();
    ctx.moveTo(p[0] * sx, p[1] * sy);
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
