import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiProblem } from '@/lib/api/problem';
import { ContactForm } from '@/modules/contacts/components/ContactForm';

const noop = () => undefined;

function validationProblem(errors: Record<string, string[]>) {
    return new ApiProblem({
        type: 't',
        title: 'Validation failed',
        status: 422,
        detail: 'The given data was invalid.',
        code: 'validation_failed',
        errors,
    });
}

describe('ContactForm', () => {
    it('adds and removes phone inputs but never the last one', async () => {
        render(<ContactForm onSubmit={noop} submitting={false} problem={null} />);

        expect(screen.getAllByLabelText(/^Phone \d/)).toHaveLength(1);
        expect(screen.getByRole('button', { name: 'Remove phone 1' })).toBeDisabled();

        await userEvent.click(screen.getByRole('button', { name: 'Add phone' }));
        expect(screen.getAllByLabelText(/^Phone \d/)).toHaveLength(2);

        await userEvent.click(screen.getByRole('button', { name: 'Remove phone 2' }));
        expect(screen.getAllByLabelText(/^Phone \d/)).toHaveLength(1);
        expect(screen.getByRole('button', { name: 'Remove phone 1' })).toBeDisabled();
    });

    it('submits the flattened values', async () => {
        const onSubmit = vi.fn();
        render(<ContactForm onSubmit={onSubmit} submitting={false} problem={null} />);

        await userEvent.type(screen.getByLabelText('Name'), 'Dan Brown');
        await userEvent.type(screen.getByLabelText('Email 1'), 'dan@rex.com.au');
        await userEvent.type(screen.getByLabelText('Phone 1'), '+61412345678');
        await userEvent.click(screen.getByRole('button', { name: 'Save contact' }));

        expect(onSubmit).toHaveBeenCalledWith({
            name: 'Dan Brown',
            emails: ['dan@rex.com.au'],
            phones: ['+61412345678'],
        });
    });

    it('puts a field error under its input and an array-level error in the summary alert', () => {
        render(
            <ContactForm
                defaultValues={{
                    name: 'Dan',
                    emails: ['dan@rex.com.au'],
                    phones: ['+61412345678', '0412'],
                }}
                onSubmit={noop}
                submitting={false}
                problem={validationProblem({
                    'phones.1': ['Must be an E.164 number.'],
                    emails: ['The emails field must have at least 1 items.'],
                })}
            />,
        );

        const secondPhone = screen.getByLabelText('Phone 2');
        expect(secondPhone).toHaveAccessibleDescription('Must be an E.164 number.');
        expect(secondPhone).toBeInvalid();
        expect(screen.getByRole('alert')).toHaveTextContent(
            'The emails field must have at least 1 items.',
        );
        expect(screen.getByRole('alert')).not.toHaveTextContent('E.164');
    });

    it('shows an error for a key with no field, such as id, in the summary', () => {
        render(
            <ContactForm
                onSubmit={noop}
                submitting={false}
                problem={validationProblem({ id: ['The id is not a valid UUID.'] })}
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent('The id is not a valid UUID.');
    });

    it('shows the title and detail of a non-validation problem', () => {
        const problem = new ApiProblem({
            type: 't',
            title: 'Key reused',
            status: 422,
            detail: 'That key was used for a different request.',
            code: 'idempotency_key_reused',
        });
        render(<ContactForm onSubmit={noop} submitting={false} problem={problem} />);

        expect(screen.getByRole('alert')).toHaveTextContent(
            'That key was used for a different request.',
        );
    });
});
