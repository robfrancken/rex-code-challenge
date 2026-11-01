import type { SearchParams } from '@/modules/contacts/api/contacts';

export const contactKeys = {
    all: ['contacts'] as const,
    list: (params: SearchParams) => [...contactKeys.all, 'list', params] as const,
    detail: (id: string) => [...contactKeys.all, 'detail', id] as const,
};
