import { emailDomain } from '@/lib/format/emailDomain';

describe('emailDomain', () => {
    it('returns the lower-cased part after the last @', () => {
        expect(emailDomain('Chloe@Rex.com.au')).toBe('rex.com.au');
        expect(emailDomain('"a@b"@example.co.nz')).toBe('example.co.nz');
    });

    it('returns an empty string without an @', () => {
        expect(emailDomain('nope')).toBe('');
    });
});
