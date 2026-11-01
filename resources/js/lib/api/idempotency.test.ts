import { newIdempotencyKey } from '@/lib/api/idempotency';
import { hideRandomUUID } from '@/test/insecureContext';

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

describe('newIdempotencyKey', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('uses crypto.randomUUID when the browser provides it', () => {
        const randomUUID = vi
            .spyOn(crypto, 'randomUUID')
            .mockReturnValue('11111111-1111-4111-8111-111111111111');

        expect(newIdempotencyKey()).toBe('11111111-1111-4111-8111-111111111111');
        expect(randomUUID).toHaveBeenCalledOnce();

        randomUUID.mockRestore();
    });

    it('builds a v4 uuid from getRandomValues outside a secure context', () => {
        hideRandomUUID();

        const first = newIdempotencyKey();
        const second = newIdempotencyKey();

        expect(first).toMatch(UUID_V4);
        expect(second).toMatch(UUID_V4);
        expect(second).not.toBe(first);
    });

    it('sets the version and variant bits on the random bytes', () => {
        vi.stubGlobal('crypto', {
            getRandomValues: (array: Uint8Array) => array.fill(0xff),
        });

        expect(newIdempotencyKey()).toBe('ffffffff-ffff-4fff-bfff-ffffffffffff');
    });
});
