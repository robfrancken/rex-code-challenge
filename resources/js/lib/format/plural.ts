/** A count with its English noun, adding `s` unless the count is exactly one: "1 contact", "2 phones". */
export function plural(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}
