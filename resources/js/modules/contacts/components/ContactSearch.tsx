import { Search, X } from 'lucide-react';
import { FieldError } from '@/components/FieldError';
import { Button } from '@/components/ui/button';
import { DebouncedInput } from '@/modules/contacts/components/DebouncedInput';

const ERROR_ID = 'q-search-error';

interface ContactSearchProps {
    query: string;
    /** The exact email domain the list is narrowed to; empty for none. */
    emailDomain: string;
    /** The API's validation message for the query or cursor, if it rejected one. */
    error: string | undefined;
    hasSearch: boolean;
    /** Called once typing pauses, with the box's new text. */
    onQueryChange: (query: string) => void;
    onRemoveDomain: () => void;
    onClear: () => void;
}

/** The single search box, the removable email-domain chip, any API error under the box, and Clear. */
export function ContactSearch({
    query,
    emailDomain,
    error,
    hasSearch,
    onQueryChange,
    onRemoveDomain,
    onClear,
}: ContactSearchProps) {
    return (
        <div className="flex flex-col items-stretch gap-2.5 p-3.5 md:flex-row md:items-start">
            <div className="min-w-0 flex-1">
                <div className="relative">
                    <Search
                        className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                        aria-hidden
                    />
                    <DebouncedInput
                        aria-label="Search by name, phone or email domain"
                        aria-invalid={error !== undefined}
                        aria-describedby={error ? ERROR_ID : undefined}
                        placeholder="Search by name, phone or email domain"
                        className="pl-8"
                        value={query}
                        onCommit={onQueryChange}
                    />
                </div>
                <FieldError id={ERROR_ID} message={error} className="mt-1" />
            </div>
            {emailDomain && (
                <span className="bg-secondary inline-flex h-9 items-center justify-between gap-1 rounded-md pr-1 pl-3 text-sm">
                    <span className="truncate">Domain: {emailDomain}</span>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        aria-label="Remove domain filter"
                        onClick={onRemoveDomain}
                    >
                        <X className="size-3.5" aria-hidden />
                    </Button>
                </span>
            )}
            {hasSearch && (
                <Button variant="outline" onClick={onClear}>
                    Clear
                </Button>
            )}
        </div>
    );
}
