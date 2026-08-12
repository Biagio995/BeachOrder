import 'vuetify/dist/vuetify.min.css'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as directives from 'vuetify/directives'

// Components are auto-imported by vite-plugin-vuetify (see vite.config.ts).
// Styles come from the single dist CSS bundle above — NOT from per-component
// .css imports (those are stubbed in vite.config to avoid DevTunnel 504s).
export const vuetify = createVuetify({
  directives,
  theme: {
    defaultTheme: 'beach',
    themes: {
      beach: {
        dark: false,
        colors: {
          primary: '#0B6E6B',
          secondary: '#1A8A86',
          accent: '#E07A5F',
          surface: '#FFFFFF',
          background: '#E8F4F3',
          error: '#C23B22',
          success: '#2A9D8F',
          warning: '#E9C46A',
          info: '#457B9D',
        },
      },
    },
  },
  defaults: {
    VBtn: {
      rounded: 'lg',
      elevation: 0,
    },
    VCard: {
      rounded: 'lg',
      elevation: 0,
    },
  },
})
