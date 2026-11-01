/** The part of an email after the last `@`, lower-cased; empty when there is no `@`. */
export function emailDomain(email: string): string {
    const at = email.lastIndexOf('@');

    return at === -1 ? '' : email.slice(at + 1).toLowerCase();
}
