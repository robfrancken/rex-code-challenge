const PATTERNS: [RegExp, string][] = [
    [/^\+61(4\d{2})(\d{3})(\d{3})$/, '+61 $1 $2 $3'],
    [/^\+61([2378])(\d{4})(\d{4})$/, '+61 $1 $2 $3'],
    [/^\+64([34679])(\d{3})(\d{4})$/, '+64 $1 $2 $3'],
    [/^\+64(2\d)(\d{3})(\d{3,4})$/, '+64 $1 $2 $3'],
];

/** Spaces an E.164 AU or NZ number for display; anything unrecognised is returned unchanged. */
export function formatPhone(e164: string): string {
    for (const [pattern, replacement] of PATTERNS) {
        if (pattern.test(e164)) {
            return e164.replace(pattern, replacement);
        }
    }

    return e164;
}
