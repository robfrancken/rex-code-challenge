import { useEffect } from 'react';

const APP_NAME = 'Rex CRM';

/**
 * Sets `document.title` to "<page> · Rex CRM". Pass undefined to leave it to a nested route, such
 * as the list while its sheet is open; the next defined name takes over again.
 */
export function useDocumentTitle(page: string | undefined): void {
    useEffect(() => {
        if (page !== undefined) {
            document.title = `${page} · ${APP_NAME}`;
        }
    }, [page]);
}
