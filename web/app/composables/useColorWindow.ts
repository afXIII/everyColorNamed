import type { ColorRow, WindowResponse } from '~/types/color'
import {
  MAX_CACHE_WINDOWS,
  PREFETCH_WINDOWS,
  WINDOW_SIZE,
  windowKeyForOffset,
  windowOffsetForKey,
} from '~/composables/useCatalogConstants'

class WindowCache {
  private cache = new Map<number, ColorRow[]>()
  private order: number[] = []

  has(key: number): boolean {
    return this.cache.has(key)
  }

  peek(key: number): ColorRow[] | undefined {
    return this.cache.get(key)
  }

  touch(key: number): void {
    if (!this.cache.has(key)) return
    this.order = this.order.filter((entry) => entry !== key)
    this.order.push(key)
  }

  set(key: number, rows: ColorRow[]): void {
    if (this.cache.has(key)) {
      this.order = this.order.filter((entry) => entry !== key)
    } else if (this.cache.size >= MAX_CACHE_WINDOWS) {
      const evict = this.order.shift()
      if (evict !== undefined) {
        this.cache.delete(evict)
      }
    }

    this.cache.set(key, rows)
    this.order.push(key)
  }

  getRow(offset: number): ColorRow | undefined {
    const key = windowKeyForOffset(offset)
    const rows = this.peek(key)
    if (!rows) return undefined
    return rows.find((row) => row.offset === offset)
  }
}

const cache = new WindowCache()
const inflight = new Map<number, Promise<ColorRow[]>>()
const rowMap = shallowRef(new Map<number, ColorRow>())
const rowVersion = ref(0)

export function useColorWindow() {
  const apiBase = useApiBase()
  const version = useCatalogVersion()

  function publishRows(rows: ColorRow[]): void {
    if (rows.length === 0) return

    const map = rowMap.value
    for (const row of rows) {
      map.set(row.offset, row)
    }
    rowVersion.value += 1
  }

  function fetchWindow(key: number): Promise<ColorRow[]> {
    if (key < 0) return Promise.resolve([])

    if (cache.has(key)) {
      cache.touch(key)
      return Promise.resolve(cache.peek(key)!)
    }

    const pending = inflight.get(key)
    if (pending) return pending

    const promise = (async () => {
      const offset = windowOffsetForKey(key)
      const url = apiUrl(`colors/window?offset=${offset}&limit=${WINDOW_SIZE}`, version.value, apiBase)
      const response = await fetch(url)

      if (!response.ok) {
        throw new Error(`Window fetch failed (${response.status})`)
      }

      const payload: WindowResponse = await response.json()
      cache.set(key, payload.rows)
      publishRows(payload.rows)
      return payload.rows
    })().finally(() => {
      inflight.delete(key)
    })

    inflight.set(key, promise)
    return promise
  }

  function prefetchAroundOffset(offset: number): void {
    const key = windowKeyForOffset(offset)
    for (let delta = -PREFETCH_WINDOWS; delta <= PREFETCH_WINDOWS; delta += 1) {
      void fetchWindow(key + delta).catch(() => {})
    }
  }

  function getRow(offset: number): ColorRow | undefined {
    void rowVersion.value
    return rowMap.value.get(offset) ?? cache.getRow(offset)
  }

  function ensureRange(start: number, end: number): void {
    const startKey = windowKeyForOffset(Math.max(0, start)) - PREFETCH_WINDOWS
    const endKey = windowKeyForOffset(Math.max(0, end)) + PREFETCH_WINDOWS

    for (let key = startKey; key <= endKey; key += 1) {
      void fetchWindow(key).catch(() => {})
    }
  }

  return {
    rowVersion,
    fetchWindow,
    prefetchAroundOffset,
    getRow,
    ensureRange,
  }
}
