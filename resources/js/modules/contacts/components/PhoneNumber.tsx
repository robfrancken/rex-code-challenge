import { formatPhone } from '@/lib/format/phone';

export function PhoneNumber({ value }: { value: string }) {
    return <span className="font-mono text-[12.5px]">{formatPhone(value)}</span>;
}
