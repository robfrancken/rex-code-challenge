const DEFAULT_REDIRECT = '/contacts';

/** Only same-origin paths are allowed, so a crafted `?redirect=` cannot send the user off-site. */
export function safeRedirect(value: string | null): string {
    if (value === null || !value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) {
        return DEFAULT_REDIRECT;
    }

    return value;
}
