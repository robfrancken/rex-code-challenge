import type { Contact, ContactPage } from '@/modules/contacts/api/contacts';

export function makeContact(overrides: Partial<Contact> = {}): Contact {
    return {
        id: 'c0ffee00-0000-4000-8000-000000000001',
        name: 'Chloe Smith',
        emails: ['chloe@rex.com.au'],
        phones: ['+61298765432'],
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

export function makePage(data: Contact[], meta: Partial<ContactPage['meta']> = {}): ContactPage {
    return {
        data,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            path: '/api/v1/contacts',
            per_page: 25,
            next_cursor: null,
            prev_cursor: null,
            ...meta,
        },
    };
}
