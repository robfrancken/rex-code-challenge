import { render, screen } from '@testing-library/react';
import { RouterProvider, createMemoryRouter } from 'react-router';
import { RouteError } from '@/components/RouteError';
import { ApiProblem } from '@/lib/api/problem';

function renderFailingRoute(error: unknown) {
    const router = createMemoryRouter([
        {
            path: '/',
            loader: () => {
                throw error;
            },
            element: <p>Never shown</p>,
            errorElement: <RouteError />,
        },
    ]);

    render(<RouterProvider router={router} />);
}

describe('RouteError', () => {
    it("shows an API problem's title and detail", async () => {
        renderFailingRoute(
            new ApiProblem({
                type: 't',
                title: 'Server error',
                status: 500,
                detail: 'The server fell over.',
                code: 'server_error',
            }),
        );

        const alert = await screen.findByRole('alert');
        expect(alert).toHaveTextContent('Server error');
        expect(alert).toHaveTextContent('The server fell over.');
    });

    it('shows generic copy, never the raw message, for any other error', async () => {
        renderFailingRoute(new Error('TypeError: x is undefined'));

        const alert = await screen.findByRole('alert');
        expect(alert).toHaveTextContent('Something went wrong');
        expect(alert).toHaveTextContent('An unexpected error occurred. Please try again.');
        expect(alert).not.toHaveTextContent('x is undefined');
    });
});
