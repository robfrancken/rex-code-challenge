import { Outlet } from 'react-router';

export function ContactsListPage() {
    return (
        <>
            <h1 className="text-xl font-semibold">Contacts</h1>
            <Outlet />
        </>
    );
}
