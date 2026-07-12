export const ROW_HEIGHT = 72
export const WINDOW_SIZE = 120
export const OVERSCAN = 24
export const MAX_CACHE_WINDOWS = 48
/** Extra API windows to fetch beyond the visible range (each side). */
export const PREFETCH_WINDOWS = 3

/**
 * Browsers clamp element scrollHeight (~16–33M px). A full level-10 list at
 * 72px/row needs ~1.2B px, so we cap the spacer and map scroll ↔ index.
 */
export const SAFE_SCROLL_HEIGHT = 5_000_000

/**
 * Trackpad/wheel gain when using scaled scroll. 1 = one row per ROW_HEIGHT of
 * delta (normal list speed). Slightly above 1 softens the compressed scrollbar
 * without feeling sluggish.
 */
export const WHEEL_SLOWDOWN = 1.25

/** Max rows to jump from a single wheel event (lets trackpad flings travel). */
export const WHEEL_MAX_ROWS = 160

export function useApiBase(): string {
  const config = useRuntimeConfig()
  return config.public.apiBase as string
}

export function windowKeyForOffset(offset: number): number {
  return Math.floor(offset / WINDOW_SIZE)
}

export function windowOffsetForKey(key: number): number {
  return key * WINDOW_SIZE
}
