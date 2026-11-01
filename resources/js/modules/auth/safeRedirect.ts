const DEFAULT_REDIRECT = '/contacts';

/** C0 controls and DEL. URL parsing strips tabs and newlines, so `/\t/evil` would become `//evil`. */
function hasControlCharacter(value: string): boolean {
    return Array.from(value).some((character) => {
        const code = character.charCodeAt(0);

        return code < 0x20 || code === 0x7f;
    });
}

/**
 * Only same-origin paths are allowed, so a crafted `?redirect=` cannot send the user off-site. The
 * value is resolved the way the browser would resolve it, and must stay on this origin.
 */
export function safeRedirect(value: string | null): string {
    if (value === null || !value.startsWith('/') || hasControlCharacter(value)) {
        return DEFAULT_REDIRECT;
    }

    let url: URL;

    try {
        url = new URL(value, window.location.origin);
    } catch {
        return DEFAULT_REDIRECT;
    }

    return url.origin === window.location.origin
        ? url.pathname + url.search + url.hash
        : DEFAULT_REDIRECT;
}
