import { isProduction } from '@/lib/env';

function setEnv(value: string | null) {
    document.head.innerHTML = value === null ? '' : `<meta name="app-env" content="${value}">`;
}

describe('isProduction', () => {
    afterEach(() => setEnv(null));

    it('is true for production', () => {
        setEnv('production');
        expect(isProduction()).toBe(true);
    });

    it('is false for local', () => {
        setEnv('local');
        expect(isProduction()).toBe(false);
    });

    it('treats a missing meta tag as production', () => {
        setEnv(null);
        expect(isProduction()).toBe(true);
    });
});
