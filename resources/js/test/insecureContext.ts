/**
 * Removes `crypto.randomUUID`, as browsers do outside a secure context (plain HTTP on a host other
 * than localhost). `crypto.getRandomValues` stays, since it exists everywhere. Undo it with
 * `vi.unstubAllGlobals()`.
 */
export function hideRandomUUID(): void {
    const realCrypto = globalThis.crypto;

    vi.stubGlobal('crypto', {
        getRandomValues: <T extends ArrayBufferView>(array: T): T =>
            realCrypto.getRandomValues(array),
    });
}
