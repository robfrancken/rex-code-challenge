import { cn } from '@/lib/utils';

interface FieldErrorProps {
    /** Point the input's `aria-describedby` at this id. */
    id: string;
    message?: string;
    className?: string;
}

/** The API's message for one input, rendered under it; nothing when there is no message. */
export function FieldError({ id, message, className }: FieldErrorProps) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className={cn('text-destructive text-xs', className)}>
            {message}
        </p>
    );
}
