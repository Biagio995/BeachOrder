import { defineConfig, loadEnv, type Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'
import { fileURLToPath, URL } from 'node:url'

function isPerComponentVuetifyCss(id: string): boolean {
  const normalized = id.replace(/\\/g, '/')
  // Keep the global stylesheet (vuetify/styles → lib/styles/main.css).
  // Stub only per-component CSS that floods DevTunnel with 504s.
  if (normalized.includes('/vuetify/lib/styles/')) return false
  return (
    normalized.includes('/vuetify/lib/components/') ||
    normalized.includes('/vuetify/lib/labs/')
  )
}

/**
 * Vuetify 3 components do `import "./X.css"`. Through DevTunnel those become
 * hundreds of CSS requests (504). Stub per-component CSS only — global styles
 * still come from `import 'vuetify/styles'` in plugins/vuetify.ts.
 */
function stubVuetifyComponentCss(): Plugin {
  return {
    name: 'stub-vuetify-component-css',
    enforce: 'pre',
    resolveId(source, importer) {
      if (!source.endsWith('.css')) return null

      const absoluteHint = source.replace(/\\/g, '/')
      if (isPerComponentVuetifyCss(absoluteHint)) {
        return '\0vuetify-component-css-stub:' + source
      }

      if (!importer) return null
      const importerNorm = importer.replace(/\\/g, '/')
      // Relative "./VBtn.css" imported from a component file
      if (
        (importerNorm.includes('/vuetify/lib/components/') ||
          importerNorm.includes('/vuetify/lib/labs/')) &&
        !absoluteHint.includes('/styles/')
      ) {
        return '\0vuetify-component-css-stub:' + source
      }

      return null
    },
    load(id) {
      if (id.startsWith('\0vuetify-component-css-stub:')) return ''
      return null
    },
  }
}

/** Reverb WebSocket (Pusher protocol) — proxied so DevTunnel/LAN use the same origin as Vite. */
const reverbProxy = {
  '/app': {
    target: 'http://127.0.0.1:8080',
    changeOrigin: true,
    ws: true,
  },
}

export default defineConfig(({ mode }) => {
  // Public product name, shared by JS (import.meta.env.VITE_APP_NAME, see
  // src/config/brand.ts) and index.html (%VITE_APP_NAME%).
  // Vite's HTML env replacement leaves placeholders for undefined variables
  // untouched, so resolve the default here — before it runs — to guarantee
  // the placeholder can never leak into served HTML. Empty string counts as
  // unset. process.env wins over .env files, matching Vite's own precedence.
  const fileEnv = loadEnv(mode, process.cwd(), '')
  const appName =
    process.env.VITE_APP_NAME?.trim() || fileEnv.VITE_APP_NAME?.trim() || 'Dalposto'
  process.env.VITE_APP_NAME = appName

  return {
  plugins: [
    vue(),
    stubVuetifyComponentCss(),
    // Do NOT set styles: 'none' — that also voids `vuetify/styles`.
    vuetify({ autoImport: true }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  optimizeDeps: {
    include: ['vue', 'vue-router', 'pinia', 'axios', 'vue-i18n', 'vuetify'],
    esbuildOptions: {
      plugins: [
        {
          name: 'stub-vuetify-component-css-esbuild',
          setup(build) {
            build.onResolve({ filter: /\.css$/ }, (args) => {
              const candidate = args.path.replace(/\\/g, '/')
              const importer = (args.importer || '').replace(/\\/g, '/')

              if (candidate.includes('/vuetify/lib/styles/') || candidate.includes('/styles/main.css')) {
                return null
              }

              if (isPerComponentVuetifyCss(candidate)) {
                return { path: args.path, namespace: 'vuetify-component-css-stub' }
              }

              if (
                (importer.includes('/vuetify/lib/components/') ||
                  importer.includes('/vuetify/lib/labs/')) &&
                args.path.endsWith('.css')
              ) {
                return { path: args.path, namespace: 'vuetify-component-css-stub' }
              }

              return null
            })
            build.onLoad({ filter: /.*/, namespace: 'vuetify-component-css-stub' }, () => ({
              contents: '',
              loader: 'js',
            }))
          },
        },
      ],
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      // Branding / product uploads (Laravel public disk via storage:link)
      '/storage': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      ...reverbProxy,
    },
  },
  // DevTunnel / remote: use `npm run dev:tunnel` (build + preview).
  // Bundled assets = few HTTP requests; Vite ESM dev floods the tunnel.
  preview: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/storage': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      ...reverbProxy,
    },
  },
  }
})
