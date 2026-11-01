import { Link } from 'react-router';
import { Button } from '@/components/ui/button';

export function ContactNotFound() {
    return (
        <div className="space-y-3 py-10 text-center">
            <h2 className="text-lg font-semibold">Contact not found</h2>
            <p className="text-muted-foreground text-sm">It may have been deleted.</p>
            <Button asChild>
                <Link to="/contacts">Back to contacts</Link>
            </Button>
        </div>
    );
}
