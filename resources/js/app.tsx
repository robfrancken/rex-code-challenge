import { createRoot } from 'react-dom/client';

const container = document.getElementById('app');

if (container) {
    createRoot(container).render(<p>Rex CRM</p>);
}
