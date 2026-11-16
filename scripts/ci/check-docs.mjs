// Lekka walidacja dokumentacji bez zależności: linki lokalne, komplet specyfikacji,
// unikalność tras, graf zależności funkcji. Uruchomienie: node scripts/ci/check-docs.mjs
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const errors = [];
const fail = (msg) => errors.push(msg);

function walk(dir, out = []) {
    for (const name of readdirSync(dir)) {
        if (['node_modules', 'vendor', '.git', 'storage', 'public'].includes(name)) continue;
        const p = join(dir, name);
        if (statSync(p).isDirectory()) walk(p, out);
        else if (p.endsWith('.md')) out.push(p);
    }
    return out;
}

const slug = (h) =>
    h
        .trim()
        .toLowerCase()
        .replace(/[`*_~]/g, '')
        .replace(/[^\p{L}\p{N}\s-]/gu, '')
        .replace(/\s/g, '-');
const anchorsCache = new Map();
function anchors(file) {
    if (!anchorsCache.has(file)) {
        const set = new Set();
        let fence = false;
        for (const line of readFileSync(file, 'utf8').split('\n')) {
            if (/^```/.test(line)) fence = !fence;
            const m = !fence && line.match(/^#{1,6}\s+(.*)$/);
            if (m) set.add(slug(m[1]));
        }
        anchorsCache.set(file, set);
    }
    return anchorsCache.get(file);
}

// 1. Lokalne linki Markdown (pliki; kotwice w docs/ i plikach .md).
const mdFiles = walk(root);
for (const file of mdFiles) {
    const rel = relative(root, file);
    let fence = false;
    readFileSync(file, 'utf8')
        .split('\n')
        .forEach((line, i) => {
            if (/^```/.test(line)) fence = !fence;
            if (fence) return;
            const clean = line.replace(/`[^`]*`/g, '');
            for (const m of clean.matchAll(/\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g)) {
                const target = m[1];
                if (/^(https?:|mailto:|tel:)/.test(target)) continue;
                const [path, frag] = target.split('#');
                const dest = path ? resolve(dirname(file), decodeURI(path)) : file;
                if (!dest.startsWith(root) || !existsSync(dest)) {
                    fail(`${rel}:${i + 1}: martwy link lokalny (${target})`);
                } else if (frag && dest.endsWith('.md') && !anchors(dest).has(decodeURI(frag).toLowerCase())) {
                    fail(`${rel}:${i + 1}: brak kotwicy #${frag} w ${relative(root, dest)}`);
                }
            }
        });
}

// 2. Indeks funkcji: komplet specyfikacji, odwołania, graf zależności.
const featuresDir = join(root, 'docs/features');
const index = readFileSync(join(featuresDir, 'FEATURE_INDEX.md'), 'utf8');
const rows = [...index.matchAll(/^\|\s*(F-\d{3})\s*\|[^|]*\|[^|]*\|[^|]*\|([^|]*)\|\s*\[spec\]\(([^)]+)\)/gm)];
const ids = new Set(rows.map((r) => r[1]));
const deps = new Map();
for (const [, id, depCell, spec] of rows) {
    if (!existsSync(join(featuresDir, spec))) fail(`FEATURE_INDEX: brak pliku specyfikacji ${spec} dla ${id}`);
    deps.set(id, depCell.match(/F-\d{3}/g) ?? []);
}
for (const f of readdirSync(featuresDir)) {
    const m = f.match(/^(F-\d{3})\.md$/);
    if (m && !ids.has(m[1])) fail(`docs/features/${f} nie występuje w FEATURE_INDEX`);
}
for (const [id, list] of deps)
    for (const d of list) if (!ids.has(d)) fail(`${id} zależy od nieznanej funkcji ${d}`);
const state = new Map();
function visit(id, stack) {
    if (state.get(id) === 2) return;
    if (state.get(id) === 1) return fail(`Cykl zależności: ${[...stack, id].join(' -> ')}`);
    state.set(id, 1);
    for (const d of deps.get(id) ?? []) visit(d, [...stack, id]);
    state.set(id, 2);
}
for (const id of deps.keys()) visit(id, []);

// 3. Unikalność tras w ROUTES.md (metoda+URL oraz nazwa).
const routes = readFileSync(join(root, 'docs/http/ROUTES.md'), 'utf8');
const seenRoute = new Map();
const seenName = new Map();
for (const m of routes.matchAll(/^\|\s*(GET|POST|PUT|PATCH|DELETE)\s*\|\s*([^|\s]+)\s*\|\s*([^|\s]+)\s*\|/gm)) {
    const [, method, url, name] = m;
    const key = `${method} ${url}`;
    if (seenRoute.has(key)) fail(`ROUTES: zduplikowana trasa ${key}`);
    seenRoute.set(key, true);
    if (name !== '—' && seenName.has(name)) fail(`ROUTES: zduplikowana nazwa trasy ${name}`);
    seenName.set(name, true);
}
if (seenRoute.size === 0) fail('ROUTES: nie rozpoznano żadnej trasy (zmieniony format tabeli?)');

if (errors.length) {
    console.error(`Walidacja dokumentacji: ${errors.length} błędów\n- ${errors.join('\n- ')}`);
    process.exit(1);
}
console.log(`Dokumentacja OK: ${mdFiles.length} plików .md, ${ids.size} funkcji, ${seenRoute.size} tras.`);
