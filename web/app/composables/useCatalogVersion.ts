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

  return computed(() => {
    const url = new URL(path, apiBase.endsWith('/') ? apiBase : `${apiBase}/`)
    if (version.value) {
      url.searchParams.set('v', version.value)
    }
    return url.toString()
  })
}

export function apiUrl(path: string, version: string | null, apiBase: string): string {
  const url = new URL(path, apiBase.endsWith('/') ? apiBase : `${apiBase}/`)
  if (version) {
    url.searchParams.set('v', version)
  }
  return url.toString()
}
