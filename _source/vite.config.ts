import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({base:'./',plugins:[react()],build:{outDir:'build',emptyOutDir:true,rollupOptions:{output:{entryFileNames:'assets/app.js',assetFileNames:'assets/[name][extname]'}}}});
