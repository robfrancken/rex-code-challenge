import '@testing-library/jest-dom/vitest';

if (typeof globalThis.crypto?.randomUUID !== 'function') {
    Object.defineProperty(globalThis.crypto, 'randomUUID', {
        configurable: true,
        value: (): `${string}-${string}-${string}-${string}-${string}` =>
            'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
                const random = Math.floor(Math.random() * 16);
                return (char === 'x' ? random : (random & 0x3) | 0x8).toString(16);
            }) as `${string}-${string}-${string}-${string}-${string}`,
    });
}
