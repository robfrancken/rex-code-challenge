import {
    Check,
    Loader2,
    PhoneCall,
    PhoneMissed,
    PhoneOff,
    Voicemail,
    type LucideIcon,
} from 'lucide-react';
import { ProblemAlert } from '@/components/ProblemAlert';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { isApiProblem } from '@/lib/api/problem';
import { formatPhone } from '@/lib/format/phone';
import { relativeTime } from '@/lib/format/relativeTime';
import { cn } from '@/lib/utils';
import type { CallResult } from '@/modules/contacts/api/contacts';

interface OutcomeStyle {
    label: string;
    Icon: LucideIcon;
    card: string;
    icon: string;
}

const GREEN = { card: 'border-green-200 bg-green-50', icon: 'bg-green-500' };
const AMBER = { card: 'border-amber-200 bg-amber-50', icon: 'bg-amber-500' };
const INDIGO = { card: 'border-indigo-200 bg-indigo-50', icon: 'bg-indigo-500' };
const RED = { card: 'border-red-200 bg-red-50', icon: 'bg-red-500' };

const OUTCOMES: Record<CallResult['outcome'], OutcomeStyle> = {
    answered: { label: 'Call answered', Icon: Check, ...GREEN },
    no_answer: { label: 'No answer', Icon: PhoneMissed, ...AMBER },
    busy: { label: 'Line busy', Icon: PhoneOff, ...AMBER },
    voicemail: { label: 'Went to voicemail', Icon: Voicemail, ...INDIGO },
    rejected: { label: 'Call rejected', Icon: PhoneOff, ...RED },
    failed: { label: 'Call failed', Icon: PhoneOff, ...RED },
};

interface CallOutcomeProps {
    result: CallResult | undefined;
    error: unknown;
    isPending: boolean;
}

function ErrorAlert({ error }: { error: unknown }) {
    if (isApiProblem(error) && error.code === 'idempotency_request_in_progress') {
        return (
            <Alert>
                <AlertTitle>Call already in progress</AlertTitle>
                <AlertDescription>{error.detail}</AlertDescription>
            </Alert>
        );
    }

    return (
        <ProblemAlert
            error={error}
            fallbackTitle="Call could not be placed"
            fallbackDetail="Something went wrong. Please try again."
        />
    );
}

function ResultCard({ result }: { result: CallResult }) {
    const { label, Icon, card, icon } = OUTCOMES[result.outcome];

    return (
        <div className={cn('flex items-start gap-3 rounded-xl border p-4', card)}>
            <div
                className={cn(
                    'grid size-8 shrink-0 place-items-center rounded-full text-white',
                    icon,
                )}
            >
                <Icon className="size-4" aria-hidden />
            </div>
            <div>
                <strong>{label}</strong>
                <div className="text-muted-foreground text-xs">
                    Dialled {formatPhone(result.phone)} · {relativeTime(result.placed_at)}
                </div>
            </div>
        </div>
    );
}

/** The outcome of the latest call: pending, the last result, or a problem from the API. */
export function CallOutcome({ result, error, isPending }: CallOutcomeProps) {
    const hasContent = isPending || Boolean(error) || result !== undefined;

    return (
        <div className="mt-4 space-y-3" aria-live="polite">
            {isPending && (
                <div className="text-muted-foreground flex items-center gap-2 text-sm">
                    <Loader2 className="size-4 animate-spin" aria-hidden />
                    Placing call…
                </div>
            )}
            {Boolean(error) && <ErrorAlert error={error} />}
            {result && <ResultCard result={result} />}
            {!hasContent && (
                <div className="text-muted-foreground flex items-center gap-2 rounded-xl border border-dashed p-4 text-sm">
                    <PhoneCall className="size-4" aria-hidden />
                    No calls yet. Use Call next to a number.
                </div>
            )}
        </div>
    );
}
