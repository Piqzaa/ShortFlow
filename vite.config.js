import { defineConfig } from 'vite';
export default defineConfig({base:'/build/',publicDir:false,build:{outDir:'public/build',emptyOutDir:true,rollupOptions:{input:'assets/app.js',output:{entryFileNames:'app.js',assetFileNames:asset=>asset.names?.some(n=>n.endsWith('.css'))?'app.css':'[name]-[hash][extname]'}}}});
