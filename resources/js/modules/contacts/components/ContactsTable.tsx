import { MoreHorizontal } from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { emailDomain } from '@/lib/format/emailDomain';
import { plural } from '@/lib/format/plural';
import type { Contact } from '@/modules/contacts/api/contacts';
import { ContactAvatar } from '@/modules/contacts/components/ContactAvatar';
import { EmailDomainBadge } from '@/modules/contacts/components/EmailDomainBadge';
import { PhoneNumber } from '@/modules/contacts/components/PhoneNumber';

const SKELETON_ROWS = 5;
const INTERACTIVE = 'a, button, [role=menuitem], [role=menu]';

function MoreCount({ count }: { count: number }) {
    return count > 0 ? <span className="text-muted-foreground ml-1 text-xs">+{count}</span> : null;
}

interface ContactRowProps {
    contact: Contact;
    onSelectDomain: (domain: string) => void;
    onDelete: (contact: Contact) => void;
}

function ContactRow({ contact, onSelectDomain, onDelete }: ContactRowProps) {
    const navigate = useNavigate();
    const { search } = useLocation();
    const [firstEmail] = contact.emails;
    const [firstPhone] = contact.phones;

    return (
        <TableRow
            className="h-14 cursor-pointer md:h-auto"
            onClick={(event) => {
                if (!(event.target as HTMLElement).closest(INTERACTIVE)) {
                    void navigate(`/contacts/${contact.id}`);
                }
            }}
        >
            <TableCell className="w-full max-w-0 md:w-auto md:max-w-none">
                <div className="flex items-center gap-3">
                    <ContactAvatar id={contact.id} name={contact.name} />
                    <div className="min-w-0">
                        <Link
                            to={`/contacts/${contact.id}`}
                            className="block truncate font-semibold hover:underline"
                        >
                            {contact.name}
                        </Link>
                        <div className="text-muted-foreground text-xs">
                            {plural(contact.emails.length, 'email')} ·{' '}
                            {plural(contact.phones.length, 'phone')}
                        </div>
                    </div>
                </div>
            </TableCell>
            <TableCell className="hidden md:table-cell">
                {firstEmail && (
                    <span className="inline-flex items-center gap-1.5">
                        {firstEmail}
                        <EmailDomainBadge
                            domain={emailDomain(firstEmail)}
                            onSelect={onSelectDomain}
                        />
                        <MoreCount count={contact.emails.length - 1} />
                    </span>
                )}
            </TableCell>
            <TableCell className="hidden md:table-cell">
                {firstPhone && <PhoneNumber value={firstPhone} />}
                <MoreCount count={contact.phones.length - 1} />
            </TableCell>
            <TableCell>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Actions for ${contact.name}`}
                        >
                            <MoreHorizontal className="size-4" aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link to={`/contacts/${contact.id}`}>View</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link to={{ pathname: `/contacts/${contact.id}/edit`, search }}>
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem variant="destructive" onSelect={() => onDelete(contact)}>
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </TableCell>
        </TableRow>
    );
}

function SkeletonRows() {
    return Array.from({ length: SKELETON_ROWS }, (_, index) => (
        <TableRow key={index} data-testid="skeleton-row">
            {[0, 1, 2, 3].map((cell) => (
                <TableCell
                    key={cell}
                    className={cell === 1 || cell === 2 ? 'hidden md:table-cell' : undefined}
                >
                    <Skeleton className="h-5 w-full" />
                </TableCell>
            ))}
        </TableRow>
    ));
}

interface ContactsTableProps {
    /** The rows to show; undefined until the first page loads. */
    contacts: Contact[] | undefined;
    isLoading: boolean;
    onSelectDomain: (domain: string) => void;
    onDelete: (contact: Contact) => void;
}

/** The contacts table: skeleton rows while the first page loads, then one row per contact. */
export function ContactsTable({
    contacts,
    isLoading,
    onSelectDomain,
    onDelete,
}: ContactsTableProps) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Contact</TableHead>
                    <TableHead className="hidden md:table-cell">Email</TableHead>
                    <TableHead className="hidden md:table-cell">Phone</TableHead>
                    <TableHead className="w-10">
                        <span className="sr-only">Actions</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody aria-busy={isLoading || undefined}>
                {isLoading && <SkeletonRows />}
                {contacts?.map((contact) => (
                    <ContactRow
                        key={contact.id}
                        contact={contact}
                        onSelectDomain={onSelectDomain}
                        onDelete={onDelete}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
