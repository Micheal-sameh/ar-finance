import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { ConfirmProvider } from '@/Components/ui/ConfirmProvider';
import { AppLayout } from '@/Layouts/AppLayout';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Avarewase Finance';

// Pages under these prefixes render outside the authenticated app chrome
// (login screen, dev-only component sandbox) and must opt out of AppLayout.
const noAppLayoutPrefixes = ['Auth/', 'Dev/'];

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')).then((module) => {
            const component = (module as { default: { layout?: (page: ReactNode) => ReactNode } }).default;

            if (component.layout === undefined && !noAppLayoutPrefixes.some((prefix) => name.startsWith(prefix))) {
                component.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
            }

            return module;
        }),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ConfirmProvider>
                <App {...props} />
            </ConfirmProvider>,
        );
    },
    progress: {
        color: '#2563EB',
    },
});
