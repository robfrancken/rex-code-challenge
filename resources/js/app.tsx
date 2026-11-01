import { QueryClientProvider } from '@tanstack/react-query';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';
import { Toaster } from '@/components/ui/sonner';
import { onUnauthenticated } from '@/lib/api/client';
import { createQueryClient } from '@/lib/query-client';
import { createUnauthenticatedHandler } from '@/modules/auth/handleUnauthenticated';
import { createAppRouter } from '@/router';

const container = document.getElementById('app');

if (container) {
    const queryClient = createQueryClient();
    const router = createAppRouter(queryClient);

    onUnauthenticated(
        createUnauthenticatedHandler(
            queryClient,
            (to) => router.navigate(to),
            () => window.location.pathname + window.location.search,
        ),
    );

    createRoot(container).render(
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
            <Toaster />
        </QueryClientProvider>,
    );
}
