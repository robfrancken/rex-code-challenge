import { Mail, MoreHorizontal, Phone, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { Link, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router';
import { FieldError } from '@/components/FieldError';
import { ProblemAlert } from '@/components/ProblemAlert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { fieldErrors, isApiProblem, isValidationProblem } from '@/lib/api/problem';
import { emailDomain } from '@/lib/format/emailDomain';
import { useContacts } from '@/modules/contacts/api/hooks';
import type { Contact, SearchParams } from '@/modules/contacts/api/contacts';
import { ContactAvatar } from '@/modules/contacts/components/ContactAvatar';
import { DeleteContactDialog } from '@/modules/contacts/components/DeleteContactDialog';
import { DebouncedInput } from '@/modules/contacts/components/DebouncedInput';
import { EmailDomainBadge } from '@/modules/contacts/components/EmailDomainBadge';
import { PhoneNumber } from '@/modules/contacts/components/PhoneNumber';

const FILTER_KEYS = ['name', 'phone', 'email_domain'] as const;

type FilterKey = (typeof FILTER_KEYS)[number];

const SKELETON_ROWS = 5;
const INTERACTIVE = 'a, button, [role=menuitem], [role=menu]';

function plural(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

function toSearchParams(urlParams: URLSearchParams): SearchParams {
    const params: SearchParams = {};

    for (const key of FILTER_KEYS) {
        const value = urlParams.get(key);
        if (value) {
            params[key] = value;
        }
    }

    const page = Number(urlParams.get('page'));
    if (Number.isInteger(page) && page > 1) {
        params.page = page;
    }

    return params;
}

/**
 * The latest value that was defined. A failed search (such as a 422 on a filter) leaves the
 * query without data, so this keeps the last loaded page on screen beside the error.
 */
function useLastLoaded<T>(value: T | undefined): T | undefined {
    const [lastLoaded, setLastLoaded] = useState(value);

    if (value !== undefined && value !== lastLoaded) {
        setLastLoaded(value);
    }

    return value ?? lastLoaded;
}

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
            className="cursor-pointer"
            onClick={(event) => {
                if (!(event.target as HTMLElement).closest(INTERACTIVE)) {
                    void navigate(`/contacts/${contact.id}`);
                }
            }}
        >
            <TableCell>
                <div className="flex items-center gap-3">
                    <ContactAvatar id={contact.id} name={contact.name} />
                    <div>
                        <Link
                            to={`/contacts/${contact.id}`}
                            className="font-semibold hover:underline"
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
            <TableCell>
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
            <TableCell>
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
                <TableCell key={cell}>
                    <Skeleton className="h-5 w-full" />
                </TableCell>
            ))}
        </TableRow>
    ));
}

export function ContactsListPage() {
    const [urlParams, setUrlParams] = useSearchParams();
    const { search } = useLocation();
    const [pendingDelete, setPendingDelete] = useState<Contact | null>(null);
    const {
        data: latest,
        error,
        isError,
        isPending,
        refetch,
    } = useContacts(toSearchParams(urlParams));
    const data = useLastLoaded(latest);
    const hasFilters = FILTER_KEYS.some((key) => urlParams.has(key));
    const isEmpty = !isError && data !== undefined && data.data.length === 0;
    const filterErrors = isValidationProblem(error) ? fieldErrors(error) : {};
    const hasFilterError = FILTER_KEYS.some((key) => filterErrors[key] !== undefined);
    const canRetry = !(isApiProblem(error) && error.status < 500);

    /** Any filter change returns to page 1, so the user is never stranded past the last page. */
    function setFilter(key: FilterKey, value: string) {
        setUrlParams((previous) => {
            const next = new URLSearchParams(previous);
            next.delete('page');
            if (value) {
                next.set(key, value);
            } else {
                next.delete(key);
            }

            return next;
        });
    }

    function goToPage(page: number) {
        setUrlParams((previous) => {
            const next = new URLSearchParams(previous);
            if (page > 1) {
                next.set('page', String(page));
            } else {
                next.delete('page');
            }

            return next;
        });
    }

    return (
        <>
            <div className="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-tight">Contacts</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {data ? `${data.meta.total} contacts` : ' '}
                    </p>
                </div>
                <Button asChild>
                    <Link to={{ pathname: '/contacts/new', search }}>
                        <Plus className="size-4" aria-hidden />
                        New contact
                    </Link>
                </Button>
            </div>

            <Card className="gap-0 py-0">
                <div className="grid grid-cols-1 items-start gap-2.5 p-3.5 md:grid-cols-[1fr_1fr_1fr_auto]">
                    {(
                        [
                            ['name', 'Name', 'Search by name', Search],
                            ['phone', 'Phone', 'Phone, e.g. 0412 345 678', Phone],
                            ['email_domain', 'Email domain', 'Email domain', Mail],
                        ] as const
                    ).map(([key, label, placeholder, Icon]) => {
                        const message = filterErrors[key];
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
                                        value={urlParams.get(key) ?? ''}
                                        onCommit={(value) => setFilter(key, value)}
                                    />
                                </div>
                                <FieldError id={errorId} message={message} className="mt-1" />
                            </div>
                        );
                    })}
                    {hasFilters && (
                        <Button variant="outline" onClick={() => setUrlParams({})}>
                            Clear
                        </Button>
                    )}
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Contact</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Phone</TableHead>
                            <TableHead className="w-10">
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isPending && !isError && <SkeletonRows />}
                        {data?.data.map((contact) => (
                            <ContactRow
                                key={contact.id}
                                contact={contact}
                                onSelectDomain={(domain) => setFilter('email_domain', domain)}
                                onDelete={setPendingDelete}
                            />
                        ))}
                    </TableBody>
                </Table>

                {isError && !hasFilterError && (
                    <ProblemAlert
                        error={error}
                        className="m-3.5 w-auto"
                        action={
                            canRetry && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-2"
                                    onClick={() => void refetch()}
                                >
                                    Retry
                                </Button>
                            )
                        }
                    />
                )}

                {isEmpty && (
                    <div className="space-y-3 p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            No contacts match your search
                        </p>
                        <Button variant="outline" onClick={() => setUrlParams({})}>
                            Clear
                        </Button>
                    </div>
                )}

                {data && !isEmpty && (
                    <div className="text-muted-foreground flex items-center justify-between p-3.5 text-xs">
                        <span>
                            Showing {data.meta.from}–{data.meta.to} of {data.meta.total}
                        </span>
                        <span className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={data.meta.current_page <= 1}
                                onClick={() => goToPage(data.meta.current_page - 1)}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={data.meta.current_page >= data.meta.last_page}
                                onClick={() => goToPage(data.meta.current_page + 1)}
                            >
                                Next
                            </Button>
                        </span>
                    </div>
                )}
            </Card>
            {pendingDelete && (
                <DeleteContactDialog
                    contact={pendingDelete}
                    onClose={() => setPendingDelete(null)}
                />
            )}
            <Outlet />
        </>
    );
}
