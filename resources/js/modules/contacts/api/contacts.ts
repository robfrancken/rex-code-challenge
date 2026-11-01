import { api } from '@/lib/api/client';
import type { components, operations } from '@/lib/api/schema';

export type Contact = components['schemas']['Contact'];
export type ContactInput = components['schemas']['ContactInput'];
export type ContactPage = components['schemas']['ContactPage'];
export type CallResult = components['schemas']['CallResult'];
export type SearchParams = NonNullable<operations['searchContacts']['parameters']['query']>;

const CONTACTS_URL = '/api/v1/contacts';
const IDEMPOTENCY_HEADER = 'Idempotency-Key';

export async function searchContacts(params: SearchParams): Promise<ContactPage> {
    const { data } = await api.get<ContactPage>(CONTACTS_URL, { params });

    return data;
}

export async function getContact(id: string): Promise<Contact> {
    const { data } = await api.get<components['schemas']['ContactResponse']>(
        `${CONTACTS_URL}/${id}`,
    );

    return data.data;
}

export async function createContact(input: ContactInput, idempotencyKey: string): Promise<Contact> {
    const { data } = await api.post<components['schemas']['ContactResponse']>(CONTACTS_URL, input, {
        headers: { [IDEMPOTENCY_HEADER]: idempotencyKey },
    });

    return data.data;
}

export async function updateContact(id: string, input: ContactInput): Promise<Contact> {
    const { data } = await api.put<components['schemas']['ContactResponse']>(
        `${CONTACTS_URL}/${id}`,
        input,
    );

    return data.data;
}

export async function deleteContact(id: string): Promise<void> {
    await api.delete(`${CONTACTS_URL}/${id}`);
}

export async function callContact(
    id: string,
    phone: string,
    idempotencyKey: string,
): Promise<CallResult> {
    const { data } = await api.post<components['schemas']['CallResponse']>(
        `${CONTACTS_URL}/${id}/calls`,
        { phone },
        { headers: { [IDEMPOTENCY_HEADER]: idempotencyKey } },
    );

    return data.data;
}
