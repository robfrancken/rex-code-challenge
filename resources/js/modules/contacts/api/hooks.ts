import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { isApiProblem } from '@/lib/api/problem';
import {
    callContact,
    createContact,
    deleteContact,
    getContact,
    searchContacts,
    updateContact,
    type ContactInput,
    type SearchParams,
} from '@/modules/contacts/api/contacts';
import { contactKeys } from '@/modules/contacts/api/keys';

export function useContacts(params: SearchParams) {
    return useQuery({
        queryKey: contactKeys.list(params),
        queryFn: () => searchContacts(params),
        placeholderData: keepPreviousData,
    });
}

export function useContact(id: string) {
    return useQuery({ queryKey: contactKeys.detail(id), queryFn: () => getContact(id) });
}

function useInvalidateContacts() {
    const queryClient = useQueryClient();

    return () => queryClient.invalidateQueries({ queryKey: contactKeys.all });
}

export function useCreateContact() {
    const invalidate = useInvalidateContacts();

    return useMutation({
        mutationFn: ({ input, idempotencyKey }: { input: ContactInput; idempotencyKey: string }) =>
            createContact(input, idempotencyKey),
        onSuccess: invalidate,
    });
}

export function useUpdateContact(id: string) {
    const invalidate = useInvalidateContacts();

    return useMutation({
        mutationFn: (input: ContactInput) => updateContact(id, input),
        onSuccess: invalidate,
    });
}

export function useDeleteContact() {
    const invalidate = useInvalidateContacts();

    return useMutation({
        mutationFn: (id: string) => deleteContact(id),
        onSuccess: invalidate,
    });
}

/** A network failure is retried once with the same key, so the server places at most one call. */
export function useCallContact(id: string) {
    const invalidate = useInvalidateContacts();
    const mutation = useMutation({
        mutationFn: ({ phone, idempotencyKey }: { phone: string; idempotencyKey: string }) =>
            callContact(id, phone, idempotencyKey),
        retry: (failureCount, error) => !isApiProblem(error) && failureCount < 1,
        onSuccess: invalidate,
    });

    return {
        call: (phone: string) => mutation.mutateAsync({ phone, idempotencyKey: crypto.randomUUID() }),
        last: mutation.data,
        error: mutation.error,
        isPending: mutation.isPending,
    };
}
