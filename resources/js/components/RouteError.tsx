import { useRouteError } from 'react-router';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { isApiProblem } from '@/lib/api/problem';

/** Route error boundary: shows an API problem's title and detail, never a raw error message. */
export function RouteError() {
    const error = useRouteError();
    const title = isApiProblem(error) ? error.title : 'Something went wrong';
    const detail = isApiProblem(error) ? error.detail : 'An unexpected error occurred. Please try again.';

    return (
        <div className="mx-auto max-w-md p-6">
            <Alert variant="destructive">
                <AlertTitle>{title}</AlertTitle>
                <AlertDescription>{detail}</AlertDescription>
            </Alert>
        </div>
    );
}
