import { useRouteError } from 'react-router';
import { ProblemAlert } from '@/components/ProblemAlert';

/** Route error boundary: shows an API problem's title and detail, never a raw error message. */
export function RouteError() {
    return (
        <div className="mx-auto max-w-md p-6">
            <ProblemAlert
                error={useRouteError()}
                fallbackDetail="An unexpected error occurred. Please try again."
            />
        </div>
    );
}
