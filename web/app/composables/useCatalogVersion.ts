export function useCatalogVersion(): ComputedRef<string | null> {
  const route = useRoute()

  return computed(() => {
    const raw = route.query.v
    if (typeof raw !== 'string' || raw.length === 0) return null
    return raw
  })
}

export function useApiUrl(path: string): ComputedRef<string> {
  const apiBase = useApiBase()
  const version = useCatalogVersion()

  return computed(() => apiUrl(path, version.value, apiBase))
}

export function apiUrl(path: string, version: string | null, apiBase: string): string {
  const base = apiBase.replace(/\/$/, '')
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  let url = `${base}${normalizedPath}`

  if (version) {
    url += `${url.includes('?') ? '&' : '?'}v=${encodeURIComponent(version)}`
  }

  return url
}
