import { relativeTime } from '@/lib/format/relativeTime';

const now = new Date('2026-06-15T12:00:00Z');

describe('relativeTime', () => {
    it.each([
        ['2026-06-15T11:59:40Z', 'just now'],
        ['2026-06-15T11:58:00Z', '2 minutes ago'],
        ['2026-06-15T09:00:00Z', '3 hours ago'],
        ['2026-06-14T12:00:00Z', 'yesterday'],
        ['2026-05-01T12:00:00Z', 'last month'],
        ['2024-06-15T12:00:00Z', '2 years ago'],
    ])('describes %s as %s', (iso, expected) => {
        expect(relativeTime(iso, now)).toBe(expected);
    });
});
