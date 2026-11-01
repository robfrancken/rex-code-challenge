import { queryOptions } from '@tanstack/react-query';
import { fetchMe, meQueryKey } from '@/modules/auth/api';

export const meQuery = queryOptions({ queryKey: meQueryKey, queryFn: fetchMe });
