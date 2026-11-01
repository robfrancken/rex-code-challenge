import { useEffect, useRef, useState, type ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

const DEBOUNCE_MS = 300;

interface DebouncedInputProps extends Omit<ComponentProps<typeof Input>, 'value' | 'onChange'> {
    value: string;
    onCommit: (value: string) => void;
    /** Whether a settled draft is worth reporting; one it rejects stays in the box uncommitted. */
    canCommit?: (value: string) => boolean;
}

/**
 * Shows what the user types at once, but reports it via `onCommit` only after 300 ms of quiet,
 * and only when `canCommit` accepts it. A `value` change from outside (Clear, a badge) drops
 * any pending draft and replaces the text.
 */
export function DebouncedInput({
    value,
    onCommit,
    canCommit = () => true,
    ...props
}: DebouncedInputProps) {
    const [draft, setDraft] = useState(value);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const lastCommitted = useRef(value);

    useEffect(() => {
        if (value !== lastCommitted.current) {
            clearTimeout(timer.current);
            lastCommitted.current = value;
            setDraft(value);
        }
    }, [value]);
    useEffect(() => () => clearTimeout(timer.current), []);

    return (
        <Input
            {...props}
            value={draft}
            onChange={(event) => {
                const next = event.target.value;
                setDraft(next);
                clearTimeout(timer.current);
                timer.current = setTimeout(() => {
                    if (!canCommit(next)) {
                        return;
                    }
                    lastCommitted.current = next;
                    onCommit(next);
                }, DEBOUNCE_MS);
            }}
        />
    );
}
