import type { QueryClient } from '@tanstack/react-query';
import { createBrowserRouter, redirect } from 'react-router';
import { AppShell } from '@/components/AppShell';
import { NotFound } from '@/components/NotFound';
import { RouteError } from '@/components/RouteError';
import { LoginPage } from '@/modules/auth/LoginPage';
import { requireAuth } from '@/modules/auth/requireAuth';
import { ContactDetailPage } from '@/modules/contacts/routes/ContactDetailPage';
import { ContactSheet } from '@/modules/contacts/routes/ContactSheet';
import { ContactsListPage } from '@/modules/contacts/routes/ContactsListPage';

export function createAppRouter(queryClient: QueryClient) {
    const errorElement = <RouteError />;

    return createBrowserRouter([
        { path: '/login', element: <LoginPage />, errorElement },
        {
            path: '/',
            element: <AppShell />,
            loader: requireAuth(queryClient),
            errorElement,
            children: [
                { index: true, loader: () => redirect('/contacts') },
                {
                    path: 'contacts',
                    element: <ContactsListPage />,
                    errorElement,
                    children: [
                        { path: 'new', element: <ContactSheet />, errorElement },
                        { path: ':id/edit', element: <ContactSheet />, errorElement },
                    ],
                },
                { path: 'contacts/:id', element: <ContactDetailPage />, errorElement },
                { path: '*', element: <NotFound />, errorElement },
            ],
        },
    ]);
}
