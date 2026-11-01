import { plural } from '@/lib/format/plural';

describe('plural', () => {
    it.each([
        [0, 'email', '0 emails'],
        [1, 'contact', '1 contact'],
        [2, 'phone', '2 phones'],
        [40, 'contact', '40 contacts'],
    ])('counts %i %s as "%s"', (count, noun, expected) => {
        expect(plural(count, noun)).toBe(expected);
    });
});
