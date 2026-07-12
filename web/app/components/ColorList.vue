<script setup lang="ts">
import type { ColorRow } from '~/types/color'
import { formatHex } from '~/utils/color'
import {
  OVERSCAN,
  ROW_HEIGHT,
  SAFE_SCROLL_HEIGHT,
  WHEEL_MAX_ROWS,
  WHEEL_SLOWDOWN,
} from '~/composables/useCatalogConstants'

const props = defineProps<{
  totalColors: number
}>()

const emit = defineEmits<{
  select: [row: ColorRow]
}>()

const scroller = ref<HTMLElement | null>(null)
const viewportHeight = ref(720)
const scrollTop = ref(0)
const scrollRaf = ref<number | null>(null)
const startIndex = ref(0)
const ignoreScrollUntil = ref(0)
/** Fractional row position so trackpad inertia stays smooth. */
const floatIndex = ref(0)

const { rowVersion, getRow, ensureRange, prefetchAroundOffset } = useColorWindow()

const naturalHeight = computed(() => props.totalColors * ROW_HEIGHT)
const scaled = computed(() => naturalHeight.value > SAFE_SCROLL_HEIGHT)
const spacerHeight = computed(() => (scaled.value ? SAFE_SCROLL_HEIGHT : naturalHeight.value))

const visibleCount = computed(() => Math.max(1, Math.ceil(viewportHeight.value / ROW_HEIGHT)))
const maxStartIndex = computed(() => Math.max(0, props.totalColors - visibleCount.value))

const visibleEnd = computed(() =>
  Math.min(props.totalColors - 1, startIndex.value + visibleCount.value + OVERSCAN),
)

/** Keep the row window locked to the viewport (scaled) or to the true pixel offset. */
const windowOffset = computed(() =>
  scaled.value ? scrollTop.value : startIndex.value * ROW_HEIGHT,
)

const renderedRows = computed(() => {
  void rowVersion.value
  const rows: Array<{ index: number; row: ColorRow | undefined }> = []
  for (let index = startIndex.value; index <= visibleEnd.value; index += 1) {
    rows.push({ index, row: getRow(index) })
  }
  return rows
})

function maxScrollTop(): number {
  return Math.max(0, spacerHeight.value - viewportHeight.value)
}

function indexFromScrollTop(top: number): number {
  const maxScroll = maxScrollTop()
  if (!scaled.value) {
    return Math.min(maxStartIndex.value, Math.max(0, Math.floor(top / ROW_HEIGHT)))
  }
  if (maxScroll <= 0 || maxStartIndex.value <= 0) return 0
  return Math.min(
    maxStartIndex.value,
    Math.max(0, Math.round((top / maxScroll) * maxStartIndex.value)),
  )
}

function scrollTopForIndex(index: number): number {
  const maxScroll = maxScrollTop()
  const clamped = Math.min(maxStartIndex.value, Math.max(0, index))
  if (!scaled.value) {
    return clamped * ROW_HEIGHT
  }
  if (maxStartIndex.value <= 0 || maxScroll <= 0) return 0
  return (clamped / maxStartIndex.value) * maxScroll
}

function syncViewport(): void {
  const element = scroller.value
  if (!element) return
  viewportHeight.value = Math.min(element.clientHeight, window.innerHeight)
}

function applyStartIndex(index: number): void {
  startIndex.value = Math.min(maxStartIndex.value, Math.max(0, Math.round(index)))
  ensureRange(startIndex.value, visibleEnd.value)
}

function jumpToIndex(index: number, opts: { prefetch?: boolean; fromWheel?: boolean } = {}): void {
  const element = scroller.value
  if (!element) return

  const clamped = Math.min(maxStartIndex.value, Math.max(0, index))
  const rendered = Math.round(clamped)
  const top = scrollTopForIndex(rendered)

  ignoreScrollUntil.value = performance.now() + (opts.fromWheel ? 32 : 50)
  floatIndex.value = clamped
  applyStartIndex(rendered)
  scrollTop.value = top
  element.scrollTop = top

  if (opts.prefetch !== false) {
    prefetchAroundOffset(rendered)
  }
}

function onScroll(): void {
  if (performance.now() < ignoreScrollUntil.value) return
  if (scrollRaf.value !== null) return

  scrollRaf.value = window.requestAnimationFrame(() => {
    scrollRaf.value = null
    if (performance.now() < ignoreScrollUntil.value) return
    const element = scroller.value
    if (!element) return
    scrollTop.value = element.scrollTop
    const index = indexFromScrollTop(element.scrollTop)
    floatIndex.value = index
    applyStartIndex(index)
  })
}

/**
 * Scaled mode compresses 16.7M rows into ~5M px, so native wheel is far too
 * fast. Drive index at ~normal list speed from wheel deltas instead — still
 * receives the trackpad's inertia stream of events, so flings feel natural.
 */
function onWheel(event: WheelEvent): void {
  if (!scaled.value) return

  event.preventDefault()

  let dy = event.deltaY
  if (event.deltaMode === 1) {
    dy *= ROW_HEIGHT
  } else if (event.deltaMode === 2) {
    dy *= viewportHeight.value
  }

  const deltaRows = dy / (ROW_HEIGHT * WHEEL_SLOWDOWN)
  const capped = Math.max(-WHEEL_MAX_ROWS, Math.min(WHEEL_MAX_ROWS, deltaRows))
  const next = Math.min(maxStartIndex.value, Math.max(0, floatIndex.value + capped))

  // Skip no-op micro-updates, but keep floatIndex so tiny deltas accumulate.
  if (Math.round(next) === startIndex.value && Math.abs(capped) < 0.5) {
    floatIndex.value = next
    return
  }

  jumpToIndex(next, { fromWheel: true, prefetch: Math.abs(capped) >= 1 })
}

function scrollToOffset(offset: number): void {
  syncViewport()
  jumpToIndex(offset)
}

let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  syncViewport()
  applyStartIndex(0)
  prefetchAroundOffset(0)

  const element = scroller.value
  if (element) {
    element.addEventListener('wheel', onWheel, { passive: false })

    if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(() => {
        syncViewport()
        applyStartIndex(startIndex.value)
      })
      resizeObserver.observe(element)
    }
  }
})

onUnmounted(() => {
  if (scrollRaf.value !== null) {
    window.cancelAnimationFrame(scrollRaf.value)
  }
  scroller.value?.removeEventListener('wheel', onWheel)
  resizeObserver?.disconnect()
})

defineExpose({ scrollToOffset })
</script>

<template>
  <div ref="scroller" class="color-list" @scroll.passive="onScroll">
    <div class="color-list__inner" :style="{ height: `${spacerHeight}px` }">
      <div
        class="color-list__window"
        :style="{ transform: `translateY(${windowOffset}px)` }"
      >
        <button
          v-for="item in renderedRows"
          :key="item.index"
          type="button"
          class="color-row"
          :class="item.row
            ? item.row.text_contrast === 'light'
              ? 'color-row--light-text'
              : 'color-row--dark-text'
            : 'color-row--placeholder'"
          :style="item.row
            ? { height: `${ROW_HEIGHT}px`, backgroundColor: formatHex(item.row.hex) }
            : { height: `${ROW_HEIGHT}px` }"
          :disabled="!item.row"
          @click="item.row && emit('select', item.row)"
        >
          <template v-if="item.row">
            <span class="color-row__name">{{ item.row.name }}</span>
            <span class="color-row__hex">{{ formatHex(item.row.hex) }}</span>
            <span class="color-row__bucket">{{ item.row.hue_bucket }}</span>
          </template>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.color-list {
  flex: 1;
  overflow: auto;
  position: relative;
  background: #121214;
  min-height: 0;
}

.color-list__inner {
  position: relative;
  width: 100%;
}

.color-list__window {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  will-change: transform;
}

.color-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 1rem;
  align-items: center;
  width: 100%;
  padding: 0 1.25rem;
  border: none;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  text-align: left;
  box-sizing: border-box;
}

.color-row:hover:not(:disabled) {
  outline: 1px solid rgba(255, 255, 255, 0.18);
  outline-offset: -1px;
}

.color-row--placeholder {
  background: #1a1a1d;
  pointer-events: none;
}

.color-row--light-text {
  color: #fff;
}

.color-row--dark-text {
  color: #111;
}

.color-row__name {
  font-weight: 600;
  font-size: 1rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.color-row__hex,
.color-row__bucket {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.85rem;
  opacity: 0.85;
}

.color-row__bucket {
  min-width: 4.5rem;
  text-align: right;
}
</style>
