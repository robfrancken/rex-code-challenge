import { useQueryClient } from '@tanstack/react-query';
import { LogOut, Users } from 'lucide-react';
import { NavLink, Outlet, useNavigate } from 'react-router';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { initials } from '@/lib/format/initials';
import { cn } from '@/lib/utils';
import { logout } from '@/modules/auth/api';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';

export function AppShell() {
    const user = useCurrentUser();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    async function signOut() {
        await logout();
        await navigate('/login');
        queryClient.clear();
    }

    return (
        <div className="flex min-h-screen bg-background">
            <aside className="flex w-[210px] shrink-0 flex-col border-r bg-card px-3 py-4">
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
                            cn('flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm', isActive && 'bg-accent font-medium')
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
                        <div className="truncate text-xs text-muted-foreground">{user.email}</div>
                    </div>
                    <Button variant="ghost" size="icon" aria-label="Sign out" title="Sign out" onClick={signOut}>
                        <LogOut className="size-4" aria-hidden />
                    </Button>
                </div>
            </aside>
            <main className="flex-1 px-7 py-6">
                <Outlet />
            </main>
        </div>
    );
}
