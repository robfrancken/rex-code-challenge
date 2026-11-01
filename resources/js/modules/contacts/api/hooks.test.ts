import { createElement, type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import { callContact, createContact } from '@/modules/contacts/api/contacts';
import { useCallContact, useCreateContact } from '@/modules/contacts/api/hooks';
import { contactKeys } from '@/modules/contacts/api/keys';

vi.mock('@/modules/contacts/api/contacts');

const input = { name: 'Ada', emails: ['ada@example.com'], phones: ['+61412345678'] };
const contact = { id: 'c1', ...input, created_at: null, updated_at: null };

function setup() {
    const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
    const wrapper = ({ children }: { children: ReactNode }) => createElement(QueryClientProvider, { client: queryClient }, children);

    return { queryClient, wrapper };
}

describe('contact hooks', () => {
    beforeEach(() => vi.resetAllMocks());

    it('useCreateContact passes the idempotency key through', async () => {
        vi.mocked(createContact).mockResolvedValue(contact);
        const { wrapper } = setup();
        const { result } = renderHook(() => useCreateContact(), { wrapper });

        await act(() => result.current.mutateAsync({ input, idempotencyKey: 'key-1' }));

        expect(createContact).toHaveBeenCalledWith(input, 'key-1');
    });

    it('a successful create invalidates contactKeys.all', async () => {
        vi.mocked(createContact).mockResolvedValue(contact);
        const { queryClient, wrapper } = setup();
        const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
        const { result } = renderHook(() => useCreateContact(), { wrapper });

        await act(() => result.current.mutateAsync({ input, idempotencyKey: 'key-1' }));

        expect(invalidate).toHaveBeenCalledWith({ queryKey: contactKeys.all });
    });

    it('useCallContact keeps the last result and sends a fresh key per call', async () => {
        const callResult = { contact_id: 'c1', phone: '+61412345678', outcome: 'answered' as const, placed_at: '2026-01-01T00:00:00Z' };
        vi.mocked(callContact).mockResolvedValue(callResult);
        const { wrapper } = setup();
        const { result } = renderHook(() => useCallContact('c1'), { wrapper });

        await act(() => result.current.call('+61412345678'));
        await waitFor(() => expect(result.current.last).toEqual(callResult));
        await act(() => result.current.call('+61412345678'));

        const keys = vi.mocked(callContact).mock.calls.map((callArgs) => callArgs[2]);
        expect(keys).toHaveLength(2);
        expect(keys[0]).not.toBe(keys[1]);
    });
});
