// @vitest-environment node
import openapiTS, { astToString, COMMENT_HEADER } from 'openapi-typescript';
import committedSchema from '@/lib/api/schema.d.ts?raw';
import contract from '../../../../app/Modules/Contacts/Presentation/Http/openapi.json?raw';

/**
 * The SPA's API types are generated from the backend contract and committed. This regenerates
 * them exactly as `pnpm run api:types` does, so a contract change without new types fails here.
 */
describe('schema.d.ts', () => {
    it('matches the types generated from openapi.json', { timeout: 30_000 }, async () => {
        const generated = COMMENT_HEADER + astToString(await openapiTS(contract, { silent: true }));

        expect(committedSchema, 'schema.d.ts is stale: run `pnpm run api:types`').toBe(generated);
    });
});
