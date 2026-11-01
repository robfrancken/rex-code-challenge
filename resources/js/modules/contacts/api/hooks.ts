import { useCallback, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { isApiProblem } from '@/lib/api/problem';
import {
    callContact,
    createContact,
    deleteContact,
    getContact,
    searchContacts,
    updateContact,
    type CallResult,
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
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => deleteContact(id),
        onSuccess: (_, id) => {
            queryClient.removeQueries({ queryKey: contactKeys.detail(id) });

            return queryClient.invalidateQueries({ queryKey: contactKeys.lists() });
        },
    });
}

/**
 * Places calls for one contact. A network failure is retried once with the same key, so the server
 * places at most one call. `last` is the most recent successful result and survives later failures;
 * `call` never throws, because `error` is exposed for rendering.
 */
export function useCallContact(id: string) {
    const invalidate = useInvalidateContacts();
    const [last, setLast] = useState<CallResult | undefined>(undefined);
    const { mutateAsync, error, isPending } = useMutation({
        mutationFn: ({ phone, idempotencyKey }: { phone: string; idempotencyKey: string }) =>
            callContact(id, phone, idempotencyKey),
        retry: (failureCount, error) => !isApiProblem(error) && failureCount < 1,
        onSuccess: (result) => {
            setLast(result);

            return invalidate();
        },
    });

    const call = useCallback(
        async (phone: string): Promise<void> => {
            try {
                await mutateAsync({ phone, idempotencyKey: crypto.randomUUID() });
            } catch {
                // Surfaced through `error`.
            }
        },
        [mutateAsync],
    );

    return { call, last, error, isPending };
}
