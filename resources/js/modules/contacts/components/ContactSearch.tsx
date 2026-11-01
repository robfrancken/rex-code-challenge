import { Mail, Phone, Search, type LucideIcon } from 'lucide-react';
import { FieldError } from '@/components/FieldError';
import { Button } from '@/components/ui/button';
import { DebouncedInput } from '@/modules/contacts/components/DebouncedInput';
import type { FilterKey, Filters } from '@/modules/contacts/hooks/useContactSearchParams';

interface SearchField {
    key: FilterKey;
    label: string;
    placeholder: string;
    Icon: LucideIcon;
}

const FIELDS: readonly SearchField[] = [
    { key: 'name', label: 'Name', placeholder: 'Search by name', Icon: Search },
    { key: 'phone', label: 'Phone', placeholder: 'Phone, e.g. 0412 345 678', Icon: Phone },
    { key: 'email_domain', label: 'Email domain', placeholder: 'Email domain', Icon: Mail },
];

interface ContactSearchProps {
    filters: Filters;
    /** The API's validation message for each filter it rejected. */
    errors: Partial<Record<FilterKey, string>>;
    hasFilters: boolean;
    /** Called once typing pauses, with the filter's new value. */
    onFilterChange: (key: FilterKey, value: string) => void;
    onClear: () => void;
}

/** The name, phone and email-domain filters, each with any API error under it, and Clear. */
export function ContactSearch({
    filters,
    errors,
    hasFilters,
    onFilterChange,
    onClear,
}: ContactSearchProps) {
    return (
        <div className="grid grid-cols-1 items-start gap-2.5 p-3.5 md:grid-cols-[1fr_1fr_1fr_auto]">
            {FIELDS.map(({ key, label, placeholder, Icon }) => {
                const message = errors[key];
                const errorId = `${key}-filter-error`;

                return (
                    <div key={key}>
                        <div className="relative">
                            <Icon
                                className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden
                            />
                            <DebouncedInput
                                aria-label={label}
                                aria-invalid={message !== undefined}
                                aria-describedby={message ? errorId : undefined}
                                placeholder={placeholder}
                                className="pl-8"
                                value={filters[key]}
                                onCommit={(value) => onFilterChange(key, value)}
                            />
                        </div>
                        <FieldError id={errorId} message={message} className="mt-1" />
                    </div>
                );
            })}
            {hasFilters && (
                <Button variant="outline" onClick={onClear}>
                    Clear
                </Button>
            )}
        </div>
    );
}
