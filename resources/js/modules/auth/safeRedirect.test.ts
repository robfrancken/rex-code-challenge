import { safeRedirect } from '@/modules/auth/safeRedirect';

describe('safeRedirect', () => {
    it('returns a same-origin path unchanged', () => {
        expect(safeRedirect('/contacts/1')).toBe('/contacts/1');
        expect(safeRedirect('/contacts?page=2')).toBe('/contacts?page=2');
    });

    it('keeps the search and hash of a same-origin path', () => {
        expect(safeRedirect('/contacts?name=smi&page=2#top')).toBe('/contacts?name=smi&page=2#top');
    });

    it.each([
        ['//evil.example'],
        ['https://evil.example'],
        [null],
        ['javascript:alert(1)'],
        [''],
        ['/\\evil.example'],
        ['/\t/evil.example'],
        ['/\n/evil.example'],
        ['/\r\n/evil.example'],
        ['/contacts\u0000'],
        ['//['],
    ])('falls back to /contacts for %j', (value) => {
        expect(safeRedirect(value)).toBe('/contacts');
    });
});
