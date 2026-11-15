import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../../../resources/css/app.css';
import Probe from './probe';

void createInertiaApp({
    resolve: () => Probe,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
