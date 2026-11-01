const TONES = ['indigo', 'green', 'pink', 'amber', 'sky'] as const;

export type AvatarTone = (typeof TONES)[number];

/** Picks a stable tone from an id so a contact keeps its colour across renders. */
export function avatarTone(id: string): AvatarTone {
    let hash = 0;
    for (const char of id) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return TONES[hash % TONES.length];
}
