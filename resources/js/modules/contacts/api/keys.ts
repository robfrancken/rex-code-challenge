import type { SearchParams } from '@/modules/contacts/api/contacts';

export const contactKeys = {
    all: ['contacts'] as const,
    lists: () => [...contactKeys.all, 'list'] as const,
    list: (params: SearchParams) => [...contactKeys.lists(), params] as const,
    detail: (id: string) => [...contactKeys.all, 'detail', id] as const,
};
