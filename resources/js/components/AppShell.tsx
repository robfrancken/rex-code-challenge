import { useQueryClient } from '@tanstack/react-query';
import { LogOut, Menu, Users } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { NavLink, Outlet, useNavigate } from 'react-router';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { isApiProblem, problemMessage } from '@/lib/api/problem';
import { initials } from '@/lib/format/initials';
import { cn } from '@/lib/utils';
import { logout } from '@/modules/auth/api';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';

function Brand() {
    return (
        <div className="flex items-center gap-2.5 font-semibold">
            <div className="grid size-[30px] place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-[13px] font-bold text-white">
                R
            </div>
            Rex CRM
        </div>
    );
}

interface SidebarContentProps {
    /** Called when the nav link is followed, so a sheet holding this can close. */
    onNavigate?: () => void;
    onSignOut: () => void;
}

/** The nav and signed-in user block, shared by the desktop sidebar and the phone nav sheet. */
function SidebarContent({ onNavigate, onSignOut }: SidebarContentProps) {
    const user = useCurrentUser();

    return (
        <>
            <nav>
                <NavLink
                    to="/contacts"
                    onClick={onNavigate}
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
                    onClick={onSignOut}
                >
                    <LogOut className="size-4" aria-hidden />
                </Button>
            </div>
        </>
    );
}

const DESKTOP_QUERY = '(min-width: 768px)';

/** Closes the phone nav sheet once the viewport grows to the desktop layout (e.g. rotation). */
function useCloseAtDesktop(close: () => void) {
    useEffect(() => {
        if (typeof window.matchMedia !== 'function') {
            return;
        }

        const query = window.matchMedia(DESKTOP_QUERY);
        const onChange = (event: MediaQueryListEvent) => {
            if (event.matches) {
                close();
            }
        };
        query.addEventListener('change', onChange);

        return () => query.removeEventListener('change', onChange);
    }, [close]);
}

export function AppShell() {
    const [navOpen, setNavOpen] = useState(false);
    const closeNav = useCallback(() => setNavOpen(false), []);
    useCloseAtDesktop(closeNav);
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
        <div className="bg-background flex min-h-screen flex-col md:flex-row">
            <header className="bg-card sticky top-0 z-30 flex items-center justify-between border-b px-4 pt-[env(safe-area-inset-top)] md:hidden">
                <div className="py-2">
                    <Brand />
                </div>
                <Sheet open={navOpen} onOpenChange={setNavOpen}>
                    <SheetTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label="Open navigation">
                            <Menu className="size-5" aria-hidden />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" className="w-[260px] px-3 py-4">
                        <SheetTitle className="sr-only">Navigation</SheetTitle>
                        <SheetDescription className="sr-only">
                            Go to a page or sign out
                        </SheetDescription>
                        <div className="px-2 pb-4">
                            <Brand />
                        </div>
                        <SidebarContent onNavigate={closeNav} onSignOut={signOut} />
                    </SheetContent>
                </Sheet>
            </header>
            <aside className="bg-card sticky top-0 hidden h-svh w-[210px] shrink-0 flex-col self-start border-r px-3 py-4 md:flex">
                <div className="px-2 pb-4">
                    <Brand />
                </div>
                <SidebarContent onSignOut={signOut} />
            </aside>
            <main className="min-w-0 flex-1 px-4 py-4 md:px-7 md:py-6">
                <Outlet />
            </main>
        </div>
    );
}
