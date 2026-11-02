import { defineConfig } from 'vite';
export default defineConfig({base:'/build/',publicDir:false,build:{outDir:'public/build',emptyOutDir:true,rollupOptions:{input:'assets/app.js',output:{entryFileNames:'app.js',assetFileNames:asset=>asset.names?.some(n=>n.endsWith('.css'))?'app.css':'[name]-[hash][extname]',
  // Lazy previews share pure drawing helpers, never the app entry with startup side effects.
  manualChunks:id=>/\/assets\/(horizon-sequence|edit-sequence)\.js$/.test(id.replaceAll('\\','/'))?'landing-sequences':undefined
}}}});
