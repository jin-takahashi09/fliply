import { cpSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'node_modules/kuromoji/dict');
const destination = join(root, 'public/kuromoji/dict');

if (!existsSync(source)) {
    process.exit(0);
}

mkdirSync(join(root, 'public/kuromoji'), { recursive: true });
cpSync(source, destination, { recursive: true });
