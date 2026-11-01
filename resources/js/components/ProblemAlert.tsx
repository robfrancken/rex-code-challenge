import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { problemMessage } from '@/lib/api/problem';

interface ProblemAlertProps {
    error: unknown;
    fallbackTitle?: string;
    fallbackDetail?: string;
    /** Rendered under the detail, for a follow-up such as Retry. */
    action?: ReactNode;
    className?: string;
}

/** A destructive alert with an API problem's title and detail; any other error shows the fallback copy. */
export function ProblemAlert({
    error,
    fallbackTitle,
    fallbackDetail,
    action,
    className,
}: ProblemAlertProps) {
    const { title, detail } = problemMessage(error, fallbackTitle, fallbackDetail);

    return (
        <Alert variant="destructive" className={className}>
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription>
                {detail}
                {action}
            </AlertDescription>
        </Alert>
    );
}
