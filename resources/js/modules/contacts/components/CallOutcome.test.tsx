import { render, screen } from '@testing-library/react';
import { ApiProblem } from '@/lib/api/problem';
import type { CallResult } from '@/modules/contacts/api/contacts';
import { CallOutcome } from '@/modules/contacts/components/CallOutcome';

function resultWith(outcome: CallResult['outcome']): CallResult {
    return {
        contact_id: 'c1',
        phone: '+61298765432',
        outcome,
        placed_at: new Date().toISOString(),
    };
}

function problem(code: ApiProblem['code'], status: number, title: string, detail: string) {
    return new ApiProblem({ type: 't', title, status, detail, code });
}

describe('CallOutcome', () => {
    it.each([
        ['answered', 'Call answered'],
        ['no_answer', 'No answer'],
        ['busy', 'Line busy'],
        ['voicemail', 'Went to voicemail'],
        ['rejected', 'Call rejected'],
    ] as const)('labels the %s outcome as "%s"', (outcome, label) => {
        render(<CallOutcome result={resultWith(outcome)} error={null} isPending={false} />);

        expect(screen.getByText(label)).toBeInTheDocument();
        expect(screen.getByText(/Dialled \+61 2 9876 5432/)).toBeInTheDocument();
    });

    it('shows the detail of a telephony_failed problem in an alert', () => {
        render(
            <CallOutcome
                result={undefined}
                error={problem(
                    'telephony_failed',
                    502,
                    'Call could not be placed',
                    'The provider is down.',
                )}
                isPending={false}
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent('The provider is down.');
    });

    it('shows "Call already in progress" for an in-flight idempotent request', () => {
        render(
            <CallOutcome
                result={undefined}
                error={problem('idempotency_request_in_progress', 409, 'Busy', 'Still working.')}
                isPending={false}
            />,
        );

        expect(screen.getByText('Call already in progress')).toBeInTheDocument();
    });

    it('keeps showing the last result next to a later error', () => {
        render(
            <CallOutcome
                result={resultWith('busy')}
                error={problem('telephony_failed', 502, 'Failed', 'Nope.')}
                isPending={false}
            />,
        );

        expect(screen.getByText('Line busy')).toBeInTheDocument();
        expect(screen.getByRole('alert')).toHaveTextContent('Nope.');
    });

    it('shows the empty state with no result yet', () => {
        render(<CallOutcome result={undefined} error={null} isPending={false} />);

        expect(screen.getByText('No calls yet. Use Call next to a number.')).toBeInTheDocument();
    });

    it('announces outcomes politely to screen readers', () => {
        render(<CallOutcome result={undefined} error={null} isPending={false} />);

        expect(
            screen.getByText('No calls yet. Use Call next to a number.').closest('[aria-live]'),
        ).toHaveAttribute('aria-live', 'polite');
    });

    it('shows a placing message while pending', () => {
        render(<CallOutcome result={undefined} error={null} isPending />);

        expect(screen.getByText('Placing call…')).toBeInTheDocument();
    });
});
