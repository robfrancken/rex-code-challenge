import { api } from '@/lib/api/client';
import type { components } from '@/lib/api/schema';

export type User = components['schemas']['User'];
type LoginRequest = components['schemas']['LoginRequest'];

export const meQueryKey = ['auth', 'me'] as const;

export async function login(input: LoginRequest): Promise<User> {
    const { data } = await api.post<User>('/login', input);

    return data;
}

export async function logout(): Promise<void> {
    await api.post('/logout');
}

export async function fetchMe(): Promise<User> {
    const { data } = await api.get<User>('/api/v1/me');

    return data;
}
