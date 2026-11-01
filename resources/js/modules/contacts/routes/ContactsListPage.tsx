import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Outlet, useLocation, useMatch } from 'react-router';
import { ProblemAlert } from '@/components/ProblemAlert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { fieldErrors, isApiProblem, isValidationProblem } from '@/lib/api/problem';
import { plural } from '@/lib/format/plural';
import { useDocumentTitle } from '@/lib/useDocumentTitle';
import type { Contact, ContactPage } from '@/modules/contacts/api/contacts';
import { useContacts } from '@/modules/contacts/api/hooks';
import { ContactSearch } from '@/modules/contacts/components/ContactSearch';
import { ContactsTable } from '@/modules/contacts/components/ContactsTable';
import { DeleteContactDialog } from '@/modules/contacts/components/DeleteContactDialog';
import {
    FILTER_KEYS,
    useContactSearchParams,
} from '@/modules/contacts/hooks/useContactSearchParams';

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

/**
 * The last page to move to when this page is empty only because it is past the end, such as
 * page 5 after deletes left two pages; undefined otherwise.
 */
function pastTheEnd(page: ContactPage | undefined): number | undefined {
    if (page === undefined || page.data.length > 0) {
        return undefined;
    }

    const { current_page, last_page, total } = page.meta;

    return current_page > 1 && total > 0 ? last_page : undefined;
}

export function ContactsListPage() {
    useDocumentTitle(useMatch('/contacts') ? 'Contacts' : undefined);
    const { search } = useLocation();
    const { params, filters, hasFilters, setFilter, goToPage, clear } = useContactSearchParams();
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
    const lastPageToShow = pastTheEnd(isPlaceholderData ? undefined : latest);
    const isEmpty =
        !isError && lastPageToShow === undefined && data !== undefined && data.data.length === 0;
    const filterErrors = isValidationProblem(error) ? fieldErrors(error) : {};
    const hasFilterError = FILTER_KEYS.some((key) => filterErrors[key] !== undefined);
    const canRetry = !(isApiProblem(error) && error.status < 500);

    useEffect(() => {
        if (lastPageToShow !== undefined) {
            goToPage(lastPageToShow, { replace: true });
        }
    }, [lastPageToShow, goToPage]);

    return (
        <>
            <div className="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-tight">Contacts</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {data ? plural(data.meta.total, 'contact') : ' '}
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
                <ContactSearch
                    filters={filters}
                    errors={filterErrors}
                    hasFilters={hasFilters}
                    onFilterChange={(key, value) => setFilter(key, value, { replace: true })}
                    onClear={clear}
                />

                <ContactsTable
                    contacts={data?.data}
                    isLoading={isPending && !isError}
                    onSelectDomain={(domain) => setFilter('email_domain', domain)}
                    onDelete={setPendingDelete}
                />

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
                        <Button variant="outline" onClick={clear}>
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
