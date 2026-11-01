import { Plus, X } from 'lucide-react';
import { useEffect } from 'react';
import { useFieldArray, useForm } from 'react-hook-form';
import { FieldError } from '@/components/FieldError';
import { ProblemAlert } from '@/components/ProblemAlert';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fieldErrors, isValidationProblem, type ApiProblem } from '@/lib/api/problem';
import type { ContactInput } from '@/modules/contacts/api/contacts';

type ListName = 'emails' | 'phones';

interface FormValues {
    name: string;
    emails: { value: string }[];
    phones: { value: string }[];
}

interface ContactFormProps {
    defaultValues?: ContactInput;
    onSubmit: (input: ContactInput) => void;
    onCancel?: () => void;
    submitting: boolean;
    problem: ApiProblem | null;
}

const LIST_KEY = /^(emails|phones)\.(\d+)$/;

function toFormValues(input?: ContactInput): FormValues {
    const wrap = (values?: string[]) =>
        (values && values.length > 0 ? values : ['']).map((value) => ({ value }));

    return { name: input?.name ?? '', emails: wrap(input?.emails), phones: wrap(input?.phones) };
}

function toContactInput(values: FormValues): ContactInput {
    return {
        name: values.name,
        emails: values.emails.map(({ value }) => value),
        phones: values.phones.map(({ value }) => value),
    };
}

/** The form field an API error key points at, or null when no rendered input matches it. */
function fieldFor(
    key: string,
    counts: Record<ListName, number>,
): `name` | `${ListName}.${number}.value` | null {
    if (key === 'name') {
        return 'name';
    }

    const match = LIST_KEY.exec(key);
    if (match) {
        const list = match[1] as ListName;
        const index = Number(match[2]);

        return index < counts[list] ? `${list}.${index}.value` : null;
    }

    return null;
}

type FieldPath = NonNullable<ReturnType<typeof fieldFor>>;

/** Splits API errors into those with a rendered input and the messages that have none. */
function splitProblem(problem: ApiProblem | null, counts: Record<ListName, number>) {
    const matched: [FieldPath, string][] = [];
    const unmatched: string[] = [];

    if (!isValidationProblem(problem)) {
        return { matched, unmatched };
    }

    for (const [key, message] of Object.entries(fieldErrors(problem))) {
        const field = fieldFor(key, counts);
        if (field) {
            matched.push([field, message]);
        } else {
            unmatched.push(message);
        }
    }

    return { matched, unmatched };
}

interface ListFieldProps {
    list: ListName;
    singular: string;
    form: ReturnType<typeof useForm<FormValues>>;
    inputType: string;
}

function ListField({ list, singular, form, inputType }: ListFieldProps) {
    const { fields, append, remove } = useFieldArray({ control: form.control, name: list });
    const title = list === 'emails' ? 'Emails' : 'Phones';

    return (
        <fieldset className="space-y-1.5">
            <legend className="mb-1.5 text-sm font-medium">{title}</legend>
            {fields.map((field, index) => {
                const error = form.formState.errors[list]?.[index]?.value?.message;
                const label = `${singular} ${index + 1}`;
                const errorId = `${list}-${index}-error`;

                return (
                    <div key={field.id}>
                        <div className="flex gap-1.5">
                            <Input
                                type={inputType}
                                aria-label={label}
                                aria-invalid={Boolean(error)}
                                aria-describedby={error ? errorId : undefined}
                                {...form.register(`${list}.${index}.value`)}
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Remove ${label.toLowerCase()}`}
                                disabled={fields.length === 1}
                                onClick={() => remove(index)}
                            >
                                <X className="size-4" aria-hidden />
                            </Button>
                        </div>
                        <FieldError id={errorId} message={error} className="mt-1" />
                    </div>
                );
            })}
            <Button type="button" variant="outline" size="sm" onClick={() => append({ value: '' })}>
                <Plus className="size-3.5" aria-hidden />
                Add {singular.toLowerCase()}
            </Button>
        </fieldset>
    );
}

/** Create/edit form. API validation errors land under their inputs; any others go in a summary alert. */
export function ContactForm({
    defaultValues,
    onSubmit,
    onCancel,
    submitting,
    problem,
}: ContactFormProps) {
    const form = useForm<FormValues>({ defaultValues: toFormValues(defaultValues) });
    const { setError, getValues, formState } = form;
    const isValidation = isValidationProblem(problem);
    const counts = { emails: getValues('emails').length, phones: getValues('phones').length };
    const { unmatched } = splitProblem(problem, counts);

    useEffect(() => {
        for (const [field, message] of splitProblem(problem, {
            emails: getValues('emails').length,
            phones: getValues('phones').length,
        }).matched) {
            setError(field, { type: 'server', message });
        }
    }, [problem, getValues, setError]);

    return (
        <form
            noValidate
            className="flex flex-1 flex-col gap-4 overflow-y-auto px-4 pb-4"
            onSubmit={form.handleSubmit((values) => onSubmit(toContactInput(values)))}
        >
            {problem && !isValidation && <ProblemAlert error={problem} />}
            {unmatched.length > 0 && (
                <Alert variant="destructive">
                    <AlertTitle>Please fix the following</AlertTitle>
                    <AlertDescription>
                        <ul className="list-inside list-disc">
                            {unmatched.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}

            <div className="space-y-1.5">
                <Label htmlFor="contact-name">Name</Label>
                <Input
                    id="contact-name"
                    aria-invalid={Boolean(formState.errors.name)}
                    aria-describedby={formState.errors.name ? 'contact-name-error' : undefined}
                    {...form.register('name')}
                />
                <FieldError id="contact-name-error" message={formState.errors.name?.message} />
            </div>
            <ListField list="emails" singular="Email" form={form} inputType="email" />
            <ListField list="phones" singular="Phone" form={form} inputType="tel" />

            <div className="mt-2 flex justify-end gap-2">
                {onCancel && (
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                )}
                <Button type="submit" disabled={submitting}>
                    {submitting ? 'Saving…' : 'Save contact'}
                </Button>
            </div>
        </form>
    );
}
