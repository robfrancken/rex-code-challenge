import { Mail, Pencil, Phone, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { ProblemAlert } from '@/components/ProblemAlert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { isApiProblem } from '@/lib/api/problem';
import { emailDomain } from '@/lib/format/emailDomain';
import { formatPhone } from '@/lib/format/phone';
import { relativeTime } from '@/lib/format/relativeTime';
import { useDocumentTitle } from '@/lib/useDocumentTitle';
import { useCallContact, useContact } from '@/modules/contacts/api/hooks';
import { CallOutcome } from '@/modules/contacts/components/CallOutcome';
import { ContactAvatar } from '@/modules/contacts/components/ContactAvatar';
import { ContactNotFound } from '@/modules/contacts/components/ContactNotFound';
import { DeleteContactDialog } from '@/modules/contacts/components/DeleteContactDialog';
import { PhoneNumber } from '@/modules/contacts/components/PhoneNumber';
import type { ContactSheetState } from '@/modules/contacts/routes/ContactSheet';

function DetailSkeleton() {
    return (
        <div className="space-y-4" data-testid="detail-skeleton" aria-busy="true">
            <Skeleton className="h-24 w-full rounded-xl" />
            <div className="grid gap-4 md:grid-cols-2">
                <Skeleton className="h-32 rounded-xl" />
                <Skeleton className="h-32 rounded-xl" />
            </div>
        </div>
    );
}

/** Keyed by id so call state never carries over from one contact to the next. */
export function ContactDetailPage() {
    const { id = '' } = useParams();

    return <ContactDetail key={id} id={id} />;
}

function ContactDetail({ id }: { id: string }) {
    const navigate = useNavigate();
    const { data: contact, error, isPending } = useContact(id);
    const { call, last, error: callError, isPending: isCalling } = useCallContact(id);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    useDocumentTitle(contact?.name ?? 'Contact');

    if (isPending) {
        return <DetailSkeleton />;
    }

    if (!contact) {
        if (isApiProblem(error) && error.code === 'contact_not_found') {
            return <ContactNotFound />;
        }

        return <ProblemAlert error={error} />;
    }

    return (
        <>
            <nav className="text-muted-foreground mb-3 text-xs">
                <Link to="/contacts" className="hover:underline">
                    Contacts
                </Link>{' '}
                › {contact.name}
            </nav>
            <Card className="flex-row items-center gap-4 p-[18px]">
                <ContactAvatar id={contact.id} name={contact.name} size="lg" />
                <div className="flex-1">
                    <h1 className="text-[22px] font-semibold tracking-tight">{contact.name}</h1>
                    {contact.updated_at && (
                        <div className="text-muted-foreground text-xs">
                            Updated {relativeTime(contact.updated_at)}
                        </div>
                    )}
                </div>
                <Button variant="outline" asChild>
                    <Link
                        to="edit"
                        state={{ returnTo: `/contacts/${contact.id}` } satisfies ContactSheetState}
                    >
                        <Pencil className="size-4" aria-hidden />
                        Edit
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    className="border-red-200 text-red-700 hover:text-red-800"
                    onClick={() => setConfirmingDelete(true)}
                >
                    <Trash2 className="size-4" aria-hidden />
                    Delete
                </Button>
            </Card>

            <div className="mt-4 grid gap-4 md:grid-cols-[1.1fr_1fr]">
                <Card className="gap-0 py-0">
                    <h2 className="flex items-center gap-2 px-4 pt-3.5 text-sm font-semibold">
                        <Phone className="text-muted-foreground size-4" aria-hidden />
                        Phones
                    </h2>
                    <ul className="py-1.5">
                        {contact.phones.map((phone, index) => (
                            <li
                                key={phone}
                                className="flex items-center justify-between border-t px-4 py-2.5 first:border-t-0"
                            >
                                <PhoneNumber value={phone} />
                                <Button
                                    size="sm"
                                    variant={index === 0 ? 'default' : 'outline'}
                                    disabled={isCalling}
                                    aria-label={`Call ${formatPhone(phone)}`}
                                    onClick={() => void call(phone)}
                                >
                                    <Phone className="size-3.5" aria-hidden />
                                    Call
                                </Button>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card className="gap-0 py-0">
                    <h2 className="flex items-center gap-2 px-4 pt-3.5 text-sm font-semibold">
                        <Mail className="text-muted-foreground size-4" aria-hidden />
                        Emails
                    </h2>
                    <ul className="py-1.5">
                        {contact.emails.map((email) => (
                            <li
                                key={email}
                                className="flex items-center justify-between border-t px-4 py-2.5 text-sm first:border-t-0"
                            >
                                {email}
                                <Badge variant="outline">{emailDomain(email)}</Badge>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <CallOutcome result={last} error={callError} isPending={isCalling} />

            {confirmingDelete && (
                <DeleteContactDialog
                    contact={contact}
                    onClose={() => setConfirmingDelete(false)}
                    onDeleted={() => void navigate('/contacts')}
                />
            )}
        </>
    );
}
