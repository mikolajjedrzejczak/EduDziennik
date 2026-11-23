// Run inside the Node container after bootstrap. Restores the source on exit.
import { readFile, writeFile } from 'node:fs/promises';

const base = `http://127.0.0.1:${process.env.VITE_PORT ?? 5173}`;
const path = '/resources/js/pages/auth/login.tsx';
const file = `.${path}`;
const original = await readFile(file, 'utf8');
const client = await (await fetch(`${base}/@vite/client`)).text();
const token = client.match(/const wsToken = "([^"]+)"/u)?.[1];
if (!token) throw new Error('Missing Vite WebSocket token');
await fetch(`${base}${path}`);
const socket = new WebSocket(
    `${base.replace('http:', 'ws:')}/?token=${token}`,
    'vite-hmr',
);
try {
    await new Promise((resolve, reject) => {
        const timeout = setTimeout(
            () => reject(new Error('HMR timed out')),
            15000,
        );
        socket.addEventListener('error', reject);
        socket.addEventListener('message', async ({ data }) => {
            const message = JSON.parse(data);
            if (message.type === 'connected') {
                await writeFile(file, `${original}\n// F-002 HMR smoke\n`);
            }
            if (
                message.type === 'update' &&
                message.updates.some((update) => update.path.endsWith(path))
            ) {
                clearTimeout(timeout);
                resolve();
            }
        });
    });
    console.log('Vite WebSocket HMR update: OK');
} finally {
    socket.close();
    await writeFile(file, original);
}
