import type { ColorRow } from '~/types/color'
import { normalizeHex } from '~/utils/color'

function readHexParam(): string | null {
  if (import.meta.server) return null

  const raw = new URL(window.location.href).searchParams.get('hex')
  if (!raw) return null

  return normalizeHex(raw)
}

function syncHexParam(hex: string | null): void {
  if (import.meta.server) return

  const url = new URL(window.location.href)
  if (hex) {
    url.searchParams.set('hex', hex.toLowerCase())
  } else {
    url.searchParams.delete('hex')
  }

  window.history.replaceState(window.history.state, '', url)
}

export function useColorPopup() {
  const popupHex = ref<string | null>(null)
  const popupPreview = ref<ColorRow | null>(null)

  function open(row: ColorRow): void {
    popupPreview.value = row
    popupHex.value = row.hex
    syncHexParam(row.hex)
  }

  function openHex(hex: string, preview: ColorRow | null = null): void {
    popupPreview.value = preview
    popupHex.value = normalizeHex(hex)
    syncHexParam(popupHex.value)
  }

  function close(): void {
    popupPreview.value = null
    popupHex.value = null
    syncHexParam(null)
  }

  onMounted(() => {
    const fromUrl = readHexParam()
    if (fromUrl) {
      popupHex.value = fromUrl
    }

    window.addEventListener('popstate', onPopState)
  })

  onUnmounted(() => {
    window.removeEventListener('popstate', onPopState)
  })

  function onPopState(): void {
    const fromUrl = readHexParam()
    if (fromUrl) {
      popupHex.value = fromUrl
      popupPreview.value = null
      return
    }

    popupHex.value = null
    popupPreview.value = null
  }

  return {
    popupHex,
    popupPreview,
    open,
    openHex,
    close,
  }
}
