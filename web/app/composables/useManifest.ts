import { useQuery } from '@tanstack/vue-query'
import type { Manifest } from '~/types/color'

export function useManifest() {
  const apiBase = useApiBase()
  const version = useCatalogVersion()

  return useQuery({
    queryKey: computed(() => ['manifest', version.value]),
    queryFn: async (): Promise<Manifest> => {
      const response = await fetch(apiUrl('manifest', version.value, apiBase))
      if (!response.ok) {
        throw new Error(`Manifest fetch failed (${response.status})`)
      }
      return response.json()
    },
  })
}
