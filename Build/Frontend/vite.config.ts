import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const here = (path: string): string => fileURLToPath(new URL(path, import.meta.url));

/**
 * Built exactly the way CONTRACT.md says a third party builds against the shadcn/ui runtime:
 * React, ReactDOM and the JSX runtime are external and rewritten to the runtime's companion
 * modules, so the browser resolves them through TYPO3's import map to the one copy the shell
 * already loaded. Nothing here bundles a second React.
 */
export default defineConfig({
  resolve: {
    alias: { '@': here('./src') },
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: here('../../Resources/Public/JavaScript'),
    emptyOutDir: false,
    target: 'es2022',
    sourcemap: false,
    minify: 'oxc',
    lib: {
      entry: { 'jev-module': here('./src/main.tsx') },
      formats: ['es'],
    },
    rollupOptions: {
      external: ['react', 'react-dom', 'react/jsx-runtime', '@webconsulting/shadcn-ui/runtime.js'],
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/jev-[name].js',
        paths: {
          react: '@webconsulting/shadcn-ui/react.js',
          'react-dom': '@webconsulting/shadcn-ui/react-dom.js',
          'react/jsx-runtime': '@webconsulting/shadcn-ui/jsx-runtime.js',
        },
      },
    },
  },
});
