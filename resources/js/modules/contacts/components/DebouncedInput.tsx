import { useEffect, useRef, useState, type ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

const DEBOUNCE_MS = 300;

interface DebouncedInputProps extends Omit<ComponentProps<typeof Input>, 'value' | 'onChange'> {
    value: string;
    onCommit: (value: string) => void;
}

/** Shows what the user types at once, but reports it via `onCommit` only after 300 ms of quiet. */
export function DebouncedInput({ value, onCommit, ...props }: DebouncedInputProps) {
    const [draft, setDraft] = useState(value);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => setDraft(value), [value]);
    useEffect(() => () => clearTimeout(timer.current), []);

    return (
        <Input
            {...props}
            value={draft}
            onChange={(event) => {
                const next = event.target.value;
                setDraft(next);
                clearTimeout(timer.current);
                timer.current = setTimeout(() => onCommit(next), DEBOUNCE_MS);
            }}
        />
    );
}
