import { render, screen } from '@testing-library/react';
import { ProblemAlert } from '@/components/ProblemAlert';
import { ApiProblem } from '@/lib/api/problem';

const problem = new ApiProblem({
    type: 't',
    title: 'Server error',
    status: 500,
    detail: 'The server fell over.',
    code: 'server_error',
});

describe('ProblemAlert', () => {
    it("shows the problem's title and detail", () => {
        render(<ProblemAlert error={problem} />);

        expect(screen.getByRole('alert')).toHaveTextContent('Server error');
        expect(screen.getByRole('alert')).toHaveTextContent('The server fell over.');
    });

    it('shows the fallback copy, never the raw message, for any other error', () => {
        render(
            <ProblemAlert
                error={new Error('Network Error')}
                fallbackTitle="Could not load"
                fallbackDetail="Check your connection."
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent('Could not load');
        expect(screen.getByRole('alert')).toHaveTextContent('Check your connection.');
        expect(screen.getByRole('alert')).not.toHaveTextContent('Network Error');
    });

    it('renders the action inside the alert', () => {
        render(<ProblemAlert error={problem} action={<button type="button">Retry</button>} />);

        expect(screen.getByRole('alert')).toContainElement(
            screen.getByRole('button', { name: 'Retry' }),
        );
    });
});
