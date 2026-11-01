import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Outlet, useLocation, useMatch } from 'react-router';
import { ProblemAlert } from '@/components/ProblemAlert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { firstFieldError, isApiProblem, isValidationProblem } from '@/lib/api/problem';
import { useDocumentTitle } from '@/lib/useDocumentTitle';
import type { Contact } from '@/modules/contacts/api/contacts';
import { useContacts } from '@/modules/contacts/api/hooks';
import { ContactSearch } from '@/modules/contacts/components/ContactSearch';
import { ContactsTable } from '@/modules/contacts/components/ContactsTable';
import { DeleteContactDialog } from '@/modules/contacts/components/DeleteContactDialog';
import { useContactSearchParams } from '@/modules/contacts/hooks/useContactSearchParams';

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

/** What a screen reader hears once another page has loaded: the names the page runs from and to. */
function pageAnnouncement(contacts: Contact[]): string {
    if (contacts.length === 0) {
        return '';
    }
    const first = contacts[0].name;
    const last = contacts[contacts.length - 1].name;

    return contacts.length === 1
        ? `Showing results: ${first}`
        : `Showing results: ${first} to ${last}`;
}

export function ContactsListPage() {
    useDocumentTitle(useMatch('/contacts') ? 'Contacts' : undefined);
    const { search } = useLocation();
    const {
        params,
        query,
        emailDomain,
        hasSearch,
        setQuery,
        setEmailDomain,
        goToCursor,
        dropCursor,
        clear,
    } = useContactSearchParams();
    const [pendingDelete, setPendingDelete] = useState<Contact | null>(null);
    const {
        data: latest,
        error,
        isError,
        isPending,
        isPlaceholderData,
        refetch,
    } = useContacts(params);
    const data = useLastLoaded(latest);
    const isEmpty = !isError && data !== undefined && data.data.length === 0;
    const searchError = isValidationProblem(error)
        ? (firstFieldError(error, 'q') ?? firstFieldError(error, 'cursor'))
        : undefined;
    const canRetry = !(isApiProblem(error) && error.status < 500);
    const rejectsOnlyCursor =
        isValidationProblem(error) &&
        Object.keys(error.errors).length > 0 &&
        Object.keys(error.errors).every((key) => key === 'cursor');
    const announcement =
        params.cursor !== undefined && data !== undefined && !isPlaceholderData
            ? pageAnnouncement(data.data)
            : '';

    // A stale or edited cursor in the URL: show the first page of the same search instead.
    useEffect(() => {
        if (rejectsOnlyCursor) {
            dropCursor();
        }
    }, [rejectsOnlyCursor, dropCursor]);

    return (
        <>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-tight">Contacts</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Search by name, phone or email domain
                    </p>
                </div>
                <Button asChild className="w-full sm:w-auto">
                    <Link to={{ pathname: '/contacts/new', search }}>
                        <Plus className="size-4" aria-hidden />
                        New contact
                    </Link>
                </Button>
            </div>

            <Card className="gap-0 py-0">
                <ContactSearch
                    query={query}
                    emailDomain={emailDomain}
                    error={searchError}
                    hasSearch={hasSearch}
                    onQueryChange={setQuery}
                    onRemoveDomain={() => setEmailDomain(null)}
                    onClear={clear}
                />

                <ContactsTable
                    contacts={data?.data}
                    isLoading={isPending && !isError}
                    onSelectDomain={setEmailDomain}
                    onDelete={setPendingDelete}
                />

                {isError && searchError === undefined && (
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
                        <Button variant="outline" onClick={clear}>
                            Clear
                        </Button>
                    </div>
                )}

                {data && !isEmpty && (
                    <nav
                        aria-label="Pagination"
                        className="flex items-center justify-end gap-2 p-3.5"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-11 md:h-8"
                            disabled={isPlaceholderData || data.meta.prev_cursor === null}
                            onClick={() =>
                                data.meta.prev_cursor && goToCursor(data.meta.prev_cursor)
                            }
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-11 md:h-8"
                            disabled={isPlaceholderData || data.meta.next_cursor === null}
                            onClick={() =>
                                data.meta.next_cursor && goToCursor(data.meta.next_cursor)
                            }
                        >
                            Next
                        </Button>
                    </nav>
                )}
                <p role="status" aria-live="polite" className="sr-only">
                    {announcement}
                </p>
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
