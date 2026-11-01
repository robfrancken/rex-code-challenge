import { safeRedirect } from '@/modules/auth/safeRedirect';

describe('safeRedirect', () => {
    it('returns a same-origin path unchanged', () => {
        expect(safeRedirect('/contacts/1')).toBe('/contacts/1');
        expect(safeRedirect('/contacts?page=2')).toBe('/contacts?page=2');
    });

    it.each([['//evil.example'], ['https://evil.example'], [null], ['javascript:alert(1)'], [''], ['/\\evil.example']])(
        'falls back to /contacts for %s',
        (value) => {
            expect(safeRedirect(value)).toBe('/contacts');
        },
    );
});
