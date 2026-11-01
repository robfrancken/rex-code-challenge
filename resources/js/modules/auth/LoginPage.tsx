import { Info, Lock, Mail } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { useNavigate, useSearchParams } from 'react-router';
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FieldError } from '@/components/FieldError';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { firstFieldError, isApiProblem, isValidationProblem } from '@/lib/api/problem';
import { isProduction } from '@/lib/env';
import { useDocumentTitle } from '@/lib/useDocumentTitle';
import { login, meQueryKey, type User } from '@/modules/auth/api';
import { safeRedirect } from '@/modules/auth/safeRedirect';

interface LoginForm {
    email: string;
    password: string;
}

const LOGIN_FIELDS = ['email', 'password'] as const satisfies readonly (keyof LoginForm)[];

function loginErrorMessage(error: unknown): string {
    if (isApiProblem(error) && error.status === 429) {
        const wait = error.retryAfter === undefined ? '' : ` Retry in ${error.retryAfter} seconds.`;

        return `Too many attempts, try again shortly.${wait}`;
    }

    return isApiProblem(error) ? error.detail : 'Something went wrong. Please try again.';
}

export function LoginPage() {
    useDocumentTitle('Sign in');
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError,
        formState: { isSubmitting, errors },
    } = useForm<LoginForm>();

    /** A 422 lands under the inputs it names; anything else is one message under the form. */
    function showLoginError(error: unknown) {
        let shownUnderField = false;

        if (isValidationProblem(error)) {
            for (const field of LOGIN_FIELDS) {
                const message = firstFieldError(error, field);
                if (message) {
                    setError(field, { type: 'server', message });
                    shownUnderField = true;
                }
            }
        }

        if (!shownUnderField) {
            setErrorMessage(loginErrorMessage(error));
        }
    }

    const onSubmit = handleSubmit(async (values) => {
        setErrorMessage(null);
        let user: User;

        try {
            user = await login(values);
        } catch (error) {
            showLoginError(error);

            return;
        }

        queryClient.setQueryData(meQueryKey, user);
        await navigate(safeRedirect(searchParams.get('redirect')), { replace: true });
    });

    return (
        <div className="bg-background grid min-h-screen place-items-center bg-[radial-gradient(circle_at_20%_20%,#eef2ff_0,transparent_40%),radial-gradient(circle_at_80%_80%,#f5f3ff_0,transparent_40%)] p-4">
            <Card className="w-full max-w-sm p-7">
                <div className="flex items-center gap-2.5">
                    <div className="grid size-[30px] place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-[13px] font-bold text-white">
                        R
                    </div>
                    <strong className="text-base">Rex CRM</strong>
                </div>
                <h1 className="mt-3.5 text-xl font-semibold">Welcome back</h1>
                <p className="text-muted-foreground text-xs">Sign in to manage your contacts</p>

                <form onSubmit={onSubmit} className="mt-2 space-y-3.5" noValidate>
                    <div className="space-y-1.5">
                        <Label htmlFor="email">Email</Label>
                        <div className="relative">
                            <Mail
                                className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden
                            />
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                className="pl-8"
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                                {...register('email')}
                            />
                        </div>
                        <FieldError id="email-error" message={errors.email?.message} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="password">Password</Label>
                        <div className="relative">
                            <Lock
                                className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden
                            />
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                className="pl-8"
                                aria-invalid={Boolean(errors.password)}
                                aria-describedby={errors.password ? 'password-error' : undefined}
                                {...register('password')}
                            />
                        </div>
                        <FieldError id="password-error" message={errors.password?.message} />
                    </div>

                    {errorMessage && (
                        <Alert variant="destructive">
                            <AlertDescription>{errorMessage}</AlertDescription>
                        </Alert>
                    )}

                    <Button type="submit" className="w-full" disabled={isSubmitting}>
                        Sign in
                    </Button>
                </form>

                {!isProduction() && (
                    <div className="bg-muted mt-4 flex gap-2.5 rounded-[10px] px-3 py-2.5 text-xs text-zinc-700">
                        <Info className="text-muted-foreground mt-px size-4 shrink-0" aria-hidden />
                        <span>Demo account: demo@example.com / password</span>
                    </div>
                )}
            </Card>
        </div>
    );
}
