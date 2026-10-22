import { readdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// Enumerate files here: Windows shells do not expand the wildcard for Node.
const files = readdirSync(new URL('../tests/Frontend/', import.meta.url))
  .filter(name => name.endsWith('.test.js'))
  .sort()
  .map(name => new URL(`../tests/Frontend/${name}`, import.meta.url));
const result = spawnSync(process.execPath, ['--test', ...files.map(file => fileURLToPath(file))], { stdio: 'inherit' });
if (result.error) throw result.error;
process.exitCode = result.status ?? 1;
