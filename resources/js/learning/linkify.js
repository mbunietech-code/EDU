/**
 * Splits chat text into plain text and links, so the templates can render
 * links as <a> with x-text / :href (never x-html: the text stays untrusted).
 *
 * Recognised: http(s)://…, www.…, and bare domains such as example.com/page.
 * Only http(s) links are produced — "javascript:" and the like stay text.
 */

const LINK_RE = /\b(?:https?:\/\/[^\s<>"']+|www\.[^\s<>"']+|[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*\.(?:com|net|org|edu|gov|io|co|tz|ke|ug|app|dev|info|biz|me|ai|africa|online|site|xyz|tv|ly|gl|be)(?:\/[^\s<>"']*)?)/gi;
// Punctuation that ends a sentence rather than the link ("see example.com.").
const TRAILING = '.,;:!?\'"»)]}';
const MAX_LINK = 2048;

/** The part of a match that belongs to the link (drops trailing punctuation, keeps "(...)" pairs). */
function trimLink(raw) {
    let url = raw;
    while (url && TRAILING.includes(url[url.length - 1])) {
        // Wikipedia-style "…_(film)": keep a closing bracket that has its opening one.
        if (url.endsWith(')') && url.split('(').length >= url.split(')').length) break;
        url = url.slice(0, -1);
    }

    return url;
}

export function linkHref(text) {
    const href = /^https?:\/\//i.test(text) ? text : 'https://' + text;
    try {
        const u = new URL(href);
        return u.protocol === 'http:' || u.protocol === 'https:' ? u.href : null;
    } catch (e) {
        return null;
    }
}

/** [{ text, href }] — href is null for plain text. */
export function linkify(body) {
    const text = String(body ?? '');
    const parts = [];
    let last = 0;
    LINK_RE.lastIndex = 0;
    let m;
    while ((m = LINK_RE.exec(text)) !== null) {
        // An e-mail address is not a link to its domain.
        if (m.index > 0 && text[m.index - 1] === '@') continue;
        const url = trimLink(m[0]);
        const href = url.length <= MAX_LINK ? linkHref(url) : null;
        if (!href) continue;
        if (m.index > last) parts.push({ text: text.slice(last, m.index), href: null });
        parts.push({ text: url, href });
        last = m.index + url.length;
        LINK_RE.lastIndex = last;
    }
    if (last < text.length) parts.push({ text: text.slice(last), href: null });

    return parts;
}
