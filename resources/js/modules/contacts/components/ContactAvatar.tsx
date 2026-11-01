import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { avatarTone, type AvatarTone } from '@/lib/format/avatarTone';
import { initials } from '@/lib/format/initials';
import { cn } from '@/lib/utils';

const TONE_CLASSES: Record<AvatarTone, string> = {
    indigo: 'bg-indigo-100 text-indigo-800',
    green: 'bg-green-100 text-green-800',
    pink: 'bg-pink-100 text-pink-800',
    amber: 'bg-amber-100 text-amber-800',
    sky: 'bg-sky-100 text-sky-800',
};

const SIZE_CLASSES = {
    md: 'size-[34px] text-xs',
    lg: 'size-14 text-lg',
} as const;

interface ContactAvatarProps {
    id: string;
    name: string;
    size?: keyof typeof SIZE_CLASSES;
}

export function ContactAvatar({ id, name, size = 'md' }: ContactAvatarProps) {
    return (
        <Avatar className={SIZE_CLASSES[size]}>
            <AvatarFallback className={cn('font-semibold', TONE_CLASSES[avatarTone(id)])}>
                {initials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
