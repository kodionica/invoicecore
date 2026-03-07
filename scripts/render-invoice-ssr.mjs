import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const readStdin = async () =>
    new Promise((resolve) => {
        let data = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => {
            data += chunk;
        });
        process.stdin.on('end', () => resolve(data));
    });

const payloadRaw = (await readStdin()).trim();
const payload = payloadRaw ? JSON.parse(payloadRaw) : {};

const bundlePath = path.resolve(process.cwd(), 'bootstrap/ssr/renderInvoiceDocument.js');
if (!fs.existsSync(bundlePath)) {
    console.error('SSR bundle not found. Run "npm run build:ssr" first.');
    process.exit(1);
}

const { render } = await import(pathToFileURL(bundlePath).href);
const html = render(payload);
process.stdout.write(html);
