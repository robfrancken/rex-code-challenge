import { Link } from 'react-router';
import { Button } from '@/components/ui/button';
import { useDocumentTitle } from '@/lib/useDocumentTitle';

export function NotFound() {
    useDocumentTitle('Page not found');

    return (
        <div className="grid min-h-[50vh] place-items-center p-6 text-center">
            <div className="space-y-3">
                <h1 className="text-xl font-semibold">Page not found</h1>
                <p className="text-muted-foreground text-sm">
                    The page you are looking for does not exist.
                </p>
                <Button asChild>
                    <Link to="/contacts">Back to contacts</Link>
                </Button>
            </div>
        </div>
    );
}
