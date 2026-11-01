import { useQueryClient } from '@tanstack/react-query';
import { LogOut, Users } from 'lucide-react';
import { toast } from 'sonner';
import { NavLink, Outlet, useNavigate } from 'react-router';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { isApiProblem, problemMessage } from '@/lib/api/problem';
import { initials } from '@/lib/format/initials';
import { cn } from '@/lib/utils';
import { logout } from '@/modules/auth/api';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';

export function AppShell() {
    const user = useCurrentUser();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    /** A 401 means the session had already ended, which is the outcome signing out wants. */
    async function signOut() {
        try {
            await logout();
        } catch (error) {
            if (!(isApiProblem(error) && error.status === 401)) {
                const { title, detail } = problemMessage(error, 'Could not sign out');
                toast.error(title, { description: detail });

                return;
            }
        }

        await navigate('/login');
        queryClient.clear();
    }

    return (
        <div className="bg-background flex min-h-screen">
            <aside className="bg-card sticky top-0 flex h-svh w-[210px] shrink-0 flex-col self-start border-r px-3 py-4">
                <div className="flex items-center gap-2.5 px-2 pb-4 font-semibold">
                    <div className="grid size-[30px] place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-[13px] font-bold text-white">
                        R
                    </div>
                    Rex CRM
                </div>
                <nav>
                    <NavLink
                        to="/contacts"
                        className={({ isActive }) =>
                            cn(
                                'flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm',
                                isActive && 'bg-accent font-medium',
                            )
                        }
                    >
                        <Users className="size-4" aria-hidden />
                        Contacts
                    </NavLink>
                </nav>
                <div className="mt-auto flex items-center gap-2.5 border-t px-2 pt-3">
                    <Avatar>
                        <AvatarFallback>{initials(user.name)}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1 text-sm">
                        <div className="truncate">{user.name}</div>
                        <div className="text-muted-foreground truncate text-xs">{user.email}</div>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Sign out"
                        title="Sign out"
                        onClick={signOut}
                    >
                        <LogOut className="size-4" aria-hidden />
                    </Button>
                </div>
            </aside>
            <main className="min-w-0 flex-1 px-7 py-6">
                <Outlet />
            </main>
        </div>
    );
}
