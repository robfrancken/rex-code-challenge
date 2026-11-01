import { useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router';
import { toast } from 'sonner';
import { ProblemAlert } from '@/components/ProblemAlert';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { newIdempotencyKey } from '@/lib/api/idempotency';
import { isApiProblem, problemMessage } from '@/lib/api/problem';
import { useDocumentTitle } from '@/lib/useDocumentTitle';
import type { ContactInput } from '@/modules/contacts/api/contacts';
import { useContact, useCreateContact, useUpdateContact } from '@/modules/contacts/api/hooks';
import { ContactForm } from '@/modules/contacts/components/ContactForm';
import { ContactNotFound } from '@/modules/contacts/components/ContactNotFound';

interface SheetBodyProps {
    onCancel: () => void;
}

/** Reports a save failure: API problems show in the form, anything else (network) becomes a toast. */
function reportUnexpected(error: unknown) {
    if (!isApiProblem(error)) {
        const { title, detail } = problemMessage(error, 'Could not save contact');
        toast.error(title, { description: detail });
    }
}

function CreateBody({ onCancel }: SheetBodyProps) {
    const navigate = useNavigate();
    const { mutateAsync, isPending, error } = useCreateContact();
    const [idempotencyKey] = useState(newIdempotencyKey);

    async function submit(input: ContactInput) {
        try {
            const contact = await mutateAsync({ input, idempotencyKey });
            toast.success('Contact created');
            await navigate(`/contacts/${contact.id}`);
        } catch (failure) {
            reportUnexpected(failure);
        }
    }

    return (
        <ContactForm
            onSubmit={(input) => void submit(input)}
            onCancel={onCancel}
            submitting={isPending}
            problem={isApiProblem(error) ? error : null}
        />
    );
}

function EditBody({ id, onCancel }: SheetBodyProps & { id: string }) {
    const navigate = useNavigate();
    const { data: contact, error: loadError, isPending: isLoading } = useContact(id);
    const { mutateAsync, isPending, error } = useUpdateContact(id);

    async function submit(input: ContactInput) {
        try {
            await mutateAsync(input);
            toast.success('Contact updated');
            await navigate(`/contacts/${id}`);
        } catch (failure) {
            reportUnexpected(failure);
        }
    }

    if (isLoading) {
        return <Skeleton data-testid="edit-skeleton" aria-busy="true" className="mx-4 h-40" />;
    }

    if (isApiProblem(loadError) && loadError.code === 'contact_not_found') {
        return <ContactNotFound />;
    }

    if (!contact) {
        return <ProblemAlert error={loadError} className="mx-4 w-auto" />;
    }

    return (
        <ContactForm
            key={contact.id}
            defaultValues={contact}
            onSubmit={(input) => void submit(input)}
            onCancel={onCancel}
            submitting={isPending}
            problem={isApiProblem(error) ? error : null}
        />
    );
}

/** Location state for opening the sheet from outside the list, such as the detail page's Edit. */
export interface ContactSheetState {
    /** The in-app path to go back to on close, instead of the list. */
    returnTo: string;
}

function returnPath(state: unknown): string | undefined {
    if (typeof state !== 'object' || state === null || !('returnTo' in state)) {
        return undefined;
    }

    const { returnTo } = state;

    return typeof returnTo === 'string' && returnTo.startsWith('/') && !returnTo.startsWith('//')
        ? returnTo
        : undefined;
}

/**
 * Side sheet over the list for `/contacts/new` and `/contacts/:id/edit`. Closing returns to the
 * `returnTo` in its location state, otherwise to the list with its search kept.
 */
export function ContactSheet() {
    const { id } = useParams();
    const { search, state } = useLocation();
    const navigate = useNavigate();
    const title = id ? 'Edit contact' : 'New contact';
    useDocumentTitle(title);

    function close() {
        void navigate(returnPath(state) ?? { pathname: '/contacts', search });
    }

    return (
        <Sheet open onOpenChange={(open) => !open && close()}>
            <SheetContent className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>
                        Phones must be E.164, Australian or New Zealand.
                    </SheetDescription>
                </SheetHeader>
                {id ? <EditBody id={id} onCancel={close} /> : <CreateBody onCancel={close} />}
            </SheetContent>
        </Sheet>
    );
}
