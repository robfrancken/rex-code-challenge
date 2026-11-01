import { useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router';
import { toast } from 'sonner';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { isApiProblem, problemMessage } from '@/lib/api/problem';
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
    const [idempotencyKey] = useState(() => crypto.randomUUID());

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
        return <Skeleton className="mx-4 h-40" />;
    }

    if (loadError) {
        return isApiProblem(loadError) && loadError.code === 'contact_not_found' ? (
            <ContactNotFound />
        ) : (
            <p className="text-destructive px-4 text-sm">{problemMessage(loadError).detail}</p>
        );
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

/** Side sheet over the list for `/contacts/new` and `/contacts/:id/edit`. */
export function ContactSheet() {
    const { id } = useParams();
    const { search } = useLocation();
    const navigate = useNavigate();

    function close() {
        void navigate({ pathname: '/contacts', search });
    }

    return (
        <Sheet open onOpenChange={(open) => !open && close()}>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{id ? 'Edit contact' : 'New contact'}</SheetTitle>
                    <SheetDescription>
                        Phones must be E.164, Australian or New Zealand.
                    </SheetDescription>
                </SheetHeader>
                {id ? <EditBody id={id} onCancel={close} /> : <CreateBody onCancel={close} />}
            </SheetContent>
        </Sheet>
    );
}
