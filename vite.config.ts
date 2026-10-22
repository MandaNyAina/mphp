import { rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig, type Plugin } from 'vite';

const hotFile = resolve('public/hot');

/** Writes public/hot while `npm run dev` runs, so PHP knows to emit dev-server (HMR) tags. */
function phpHot(): Plugin {
  return {
    name: 'mphp-hot',
    configureServer(server) {
      const clean = () => rmSync(hotFile, { force: true });

      server.httpServer?.once('listening', () => {
        const url = server.resolvedUrls?.local[0] ?? 'http://127.0.0.1:5173/';
        writeFileSync(hotFile, url.replace(/\/$/, ''));
      });
      server.httpServer?.once('close', clean);
      process.once('exit', clean);
      for (const signal of ['SIGINT', 'SIGTERM'] as const) {
        process.once(signal, () => {
          clean();
          process.exit();
        });
      }
    },
  };
}

export default defineConfig(({ command }) => ({
  // Built files are served from /build/: lazy page chunks must resolve their CSS/JS there.
  base: command === 'build' ? '/build/' : '/',
  plugins: [svelte(), phpHot()],
  publicDir: false, // public/ is PHP's web root, not Vite's static folder
  server: { host: '127.0.0.1', port: 5173, strictPort: true },
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true, // PHP reads public/build/.vite/manifest.json to find the hashed files
    rollupOptions: { input: 'resources/js/app.ts' },
  },
}));
