import { toast } from 'sonner';
import { useNavigate } from 'react-router';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { problemMessage } from '@/lib/api/problem';
import type { Contact } from '@/modules/contacts/api/contacts';
import { useDeleteContact } from '@/modules/contacts/api/hooks';

interface DeleteContactDialogProps {
    contact: Pick<Contact, 'id' | 'name'>;
    onClose: () => void;
}

/** Mount it to ask for confirmation; `onClose` fires when the user cancels. */
export function DeleteContactDialog({ contact, onClose }: DeleteContactDialogProps) {
    const navigate = useNavigate();
    const { mutateAsync, isPending } = useDeleteContact();

    async function confirm() {
        try {
            await mutateAsync(contact.id);
        } catch (error) {
            const { title, detail } = problemMessage(error, 'Could not delete contact');
            toast.error(title, { description: detail });
            onClose();

            return;
        }

        toast.success('Contact deleted');
        await navigate('/contacts');
    }

    return (
        <AlertDialog open onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Delete {contact.name}? This can&apos;t be undone.
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        The contact and its emails and phones will be removed.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isPending}>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        disabled={isPending}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
