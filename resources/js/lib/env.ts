/** Reads the Laravel environment name from the `app-env` meta tag; a missing tag counts as production. */
export function isProduction(): boolean {
    const environment =
        document.querySelector('meta[name="app-env"]')?.getAttribute('content') ?? 'production';

    return environment === 'production';
}
