const VERSION_BYTE = 6;
const VARIANT_BYTE = 8;

function formatUuid(bytes: Uint8Array): string {
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return [
        hex.slice(0, 8),
        hex.slice(8, 12),
        hex.slice(12, 16),
        hex.slice(16, 20),
        hex.slice(20),
    ].join('-');
}

/**
 * A fresh v4 UUID for an `Idempotency-Key` header. Browsers only offer `crypto.randomUUID` in a
 * secure context (HTTPS or localhost), so on plain HTTP it is built from `crypto.getRandomValues`.
 */
export function newIdempotencyKey(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[VERSION_BYTE] = (bytes[VERSION_BYTE] & 0x0f) | 0x40;
    bytes[VARIANT_BYTE] = (bytes[VARIANT_BYTE] & 0x3f) | 0x80;

    return formatUuid(bytes);
}
