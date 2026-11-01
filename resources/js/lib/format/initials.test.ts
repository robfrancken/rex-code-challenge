import { initials } from '@/lib/format/initials';

describe('initials', () => {
    it('uses the first letters of the first and last words', () => {
        expect(initials('Chloe Smith')).toBe('CS');
        expect(initials('  anna  maria  jones ')).toBe('AJ');
    });

    it('handles a single name', () => {
        expect(initials('Madonna')).toBe('M');
    });

    it('returns a question mark for blank input', () => {
        expect(initials('  ')).toBe('?');
        expect(initials('')).toBe('?');
    });
});
