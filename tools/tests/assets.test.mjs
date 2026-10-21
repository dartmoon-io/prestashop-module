/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {copyFile, mkdir, mkdtemp, readFile, readdir, rm, symlink, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {setTimeout as delay} from 'node:timers/promises';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {test} from 'node:test';

const root = fileURLToPath(new URL('../../', import.meta.url));

function launch(directory, watch = false) {
  const child = spawn(process.execPath, ['node_modules/vite/bin/vite.js', 'build', ...(watch ? ['--watch', '--mode', 'development'] : [])], {
    cwd: directory,
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  let output = '';
  child.stdout.on('data', chunk => { output += chunk; });
  child.stderr.on('data', chunk => { output += chunk; });
  const finished = new Promise((resolve, reject) => {
    child.once('error', reject);
    child.once('close', code => resolve({code, output}));
  });

  return {child, finished, output: () => output};
}

async function waitFor(check, process) {
  const deadline = Date.now() + 30000;
  while (Date.now() < deadline) {
    if (process.child.exitCode !== null) {
      throw new Error(`Vite watch exited early: ${process.output()}`);
    }
    try {
      if (await check()) return;
    } catch (error) {
      if (error.code !== 'ENOENT' && !(error instanceof SyntaxError)) throw error;
    }
    await delay(100);
  }
  throw new Error(`Timed out waiting for rebuilt assets: ${process.output()}`);
}

async function officeAssets(directory, side) {
  const manifest = JSON.parse(await readFile(join(directory, 'views/assets/manifest.json'), 'utf8'));
  const script = manifest[`_dev/js/${side}.js`];
  const stylesheet = manifest[`_dev/scss/${side}.scss`];
  const dependencies = new Set();
  async function readScript(entry) {
    if (dependencies.has(entry.file)) return '';
    dependencies.add(entry.file);
    return await readFile(join(directory, 'views/assets', entry.file), 'utf8')
      + (await Promise.all((entry.imports ?? []).map(key => readScript(manifest[key])))).join('\n');
  }
  return {
    scriptPath: join(directory, 'views/assets', script.file),
    script: await readScript(script),
    styles: await readFile(join(directory, 'views/assets', stylesheet.file), 'utf8'),
  };
}

test('Vite builds four entries, loads JS modules and watches JS/SCSS dependencies', {timeout: 90000}, async () => {
  const directory = await mkdtemp(join(tmpdir(), 'prestashop-vite-'));
  let watcher;
  try {
    for (const path of ['_dev/js', '_dev/scss', '_dev/images', 'views/templates/admin', 'views/css', 'views/assets']) {
      await mkdir(join(directory, path), {recursive: true});
    }
    for (const path of ['vite.config.js', 'package.json']) {
      await copyFile(join(root, path), join(directory, path));
    }
    await symlink(join(root, 'node_modules'), join(directory, 'node_modules'), 'junction');
    await writeFile(join(directory, 'views/templates/admin/keep.tpl'), 'Preserve template');
    await writeFile(join(directory, 'views/css/custom.css'), '/* Preserve custom asset */');
    await writeFile(join(directory, 'views/assets/obsolete.js'), 'Old build output');
    await writeFile(join(directory, '_dev/scss/_tokens.scss'), '$tone: #123456;');
    await writeFile(join(directory, '_dev/images/marker.svg'), '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><path d="M0 0h1v1H0z"/></svg>');
    await writeFile(join(directory, '_dev/js/shared.js'), 'export const value = "initial";');
    await writeFile(join(directory, '_dev/js/lazy.js'), 'export const value = "lazy";');
    for (const side of ['front', 'back']) {
      await writeFile(join(directory, `_dev/js/${side}.js`), `
        import {value} from './shared.js';
        globalThis.${side}Fixture = value;
        globalThis.${side}Ready = import('./lazy.js').then(module => { globalThis.${side}Lazy = module.value; });
      `);
      await writeFile(join(directory, `_dev/scss/${side}.scss`), `
        @use 'tokens';
        .fixture-${side} { color: tokens.$tone; background: url('../images/marker.svg'); }
      `);
    }

    const production = await launch(directory).finished;
    assert.equal(production.code, 0, production.output);
    await assert.rejects(readFile(join(directory, 'views/assets/obsolete.js')), {code: 'ENOENT'});
    for (const side of ['front', 'back']) {
      const asset = await officeAssets(directory, side);
      // Load Vite's normal ES output, including shared and dynamic imports.
      await import(pathToFileURL(asset.scriptPath));
      await globalThis[`${side}Ready`];
      assert.equal(globalThis[`${side}Fixture`], 'initial');
      assert.equal(globalThis[`${side}Lazy`], 'lazy');
      assert.match(asset.styles, new RegExp(`\\.fixture-${side}`));
      assert.match(asset.styles, /#123456/);
      assert.match(asset.styles, /data:image\/svg\+xml/);
    }
    assert.equal(await readFile(join(directory, 'views/templates/admin/keep.tpl'), 'utf8'), 'Preserve template');
    assert.equal(await readFile(join(directory, 'views/css/custom.css'), 'utf8'), '/* Preserve custom asset */');

    watcher = launch(directory, true);
    await waitFor(async () => {
      return (await officeAssets(directory, 'front')).script.includes('initial');
    }, watcher);
    await writeFile(join(directory, '_dev/js/shared.js'), 'export const value = "updated";');
    await writeFile(join(directory, '_dev/scss/_tokens.scss'), '$tone: #654321;');
    await waitFor(async () => {
      for (const side of ['front', 'back']) {
        const asset = await officeAssets(directory, side);
        if (!asset.script.includes('updated') || !asset.styles.includes('#654321')) return false;
      }
      return true;
    }, watcher);
    // Vite may deduplicate identical or empty CSS; each manifest entry must
    // still resolve to the current stylesheet for its office.
    for (const side of ['front', 'back']) {
      await writeFile(join(directory, `_dev/scss/${side}.scss`), '');
    }
    await waitFor(async () => {
      return (await officeAssets(directory, 'front')).styles.trim() === ''
        && (await officeAssets(directory, 'back')).styles.trim() === '';
    }, watcher);
    watcher.child.kill('SIGTERM');
    await watcher.finished;
    watcher = null;

    const rebuilt = await launch(directory).finished;
    assert.equal(rebuilt.code, 0, rebuilt.output);
    for (const side of ['front', 'back']) {
      const asset = await officeAssets(directory, side);
      assert.doesNotMatch(asset.script, /sourceMappingURL/);
      assert.equal(asset.styles.trim(), '');
    }
    assert.equal((await readdir(join(directory, 'views/assets'))).some(file => file.endsWith('.map')), false);
    await writeFile(join(directory, '_dev/js/front.js'), 'const broken = ;');
    const failed = await launch(directory).finished;
    assert.notEqual(failed.code, 0, 'Invalid source should fail the asset build.');
  } finally {
    if (watcher) {
      watcher.child.kill('SIGTERM');
      await watcher.finished;
    }
    for (const side of ['front', 'back']) {
      delete globalThis[`${side}Fixture`];
      delete globalThis[`${side}Ready`];
      delete globalThis[`${side}Lazy`];
    }
    await rm(directory, {recursive: true, force: true});
  }
});
