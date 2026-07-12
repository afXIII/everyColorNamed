// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  ssr: false,
  devtools: { enabled: false },
  runtimeConfig: {
    public: {
      // Local Herd API by default; production build sets NUXT_PUBLIC_API_BASE=/api
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://everycolornamed.test/api',
    },
  },
  app: {
    head: {
      title: 'everyColorNamed',
      meta: [{ name: 'robots', content: 'noindex' }],
    },
  },
  css: ['~/assets/css/main.css'],
})
