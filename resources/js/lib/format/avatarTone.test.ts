import { avatarTone } from '@/lib/format/avatarTone';

const tones = ['indigo', 'green', 'pink', 'amber', 'sky'];

describe('avatarTone', () => {
    it('is deterministic', () => {
        const id = 'c0ffee00-0000-4000-8000-000000000001';

        expect(avatarTone(id)).toBe(avatarTone(id));
    });

    it('always returns a known tone and uses more than one', () => {
        const seen = new Set(
            Array.from({ length: 50 }, (_, i) => avatarTone(`c0ffee00-0000-4000-8000-${String(i).padStart(12, '0')}`)),
        );

        expect([...seen].every((tone) => tones.includes(tone))).toBe(true);
        expect(seen.size).toBeGreaterThan(1);
    });
});
