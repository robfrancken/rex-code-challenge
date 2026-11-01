import { Badge } from '@/components/ui/badge';

interface EmailDomainBadgeProps {
    domain: string;
    onSelect: (domain: string) => void;
}

/** A domain pill that applies that domain as the list's email filter when clicked. */
export function EmailDomainBadge({ domain, onSelect }: EmailDomainBadgeProps) {
    return (
        <Badge variant="outline" asChild>
            <button
                type="button"
                aria-label={`Filter by ${domain}`}
                onClick={() => onSelect(domain)}
            >
                {domain}
            </button>
        </Badge>
    );
}
