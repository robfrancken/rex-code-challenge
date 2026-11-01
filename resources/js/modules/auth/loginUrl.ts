/** Builds the login URL that sends the user back to `location` (a path plus query) after signing in. */
export function loginUrl(location: string): string {
    return '/login?redirect=' + encodeURIComponent(location);
}
