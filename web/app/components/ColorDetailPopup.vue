<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import type { ColorDetail, ColorRow } from '~/types/color'
import { copyText, formatHex, normalizeHex, rgbToHsl } from '~/utils/color'

function hexToRgb(hex: string): { r: number; g: number; b: number } {
  const normalized = normalizeHex(hex)
  return {
    r: Number.parseInt(normalized.slice(0, 2), 16),
    g: Number.parseInt(normalized.slice(2, 4), 16),
    b: Number.parseInt(normalized.slice(4, 6), 16),
  }
}

function previewToDetail(preview: ColorRow): ColorDetail {
  return {
    color_id: preview.color_id,
    hex: preview.hex,
    name: preview.name,
    r: preview.r,
    g: preview.g,
    b: preview.b,
    hue_bucket: preview.hue_bucket,
    text_contrast: preview.text_contrast,
    l: 0,
    a: 0,
    b_lab: 0,
    nearest_seed_hex: null,
    nearest_seed_name: null,
    delta_e: null,
  }
}

function hexFallbackDetail(hex: string): ColorDetail {
  const { r, g, b } = hexToRgb(hex)

  return {
    color_id: 0,
    hex: normalizeHex(hex),
    name: 'Loading…',
    r,
    g,
    b,
    hue_bucket: '…',
    text_contrast: (r * 299 + g * 587 + b * 114) / 1000 >= 128 ? 'dark' : 'light',
    l: 0,
    a: 0,
    b_lab: 0,
    nearest_seed_hex: null,
    nearest_seed_name: null,
    delta_e: null,
  }
}

const props = defineProps<{
  hex: string
  preview?: ColorRow | null
}>()

const emit = defineEmits<{
  close: []
}>()

const apiBase = useApiBase()
const version = useCatalogVersion()

const { data, isLoading, isFetching, isError } = useQuery({
  queryKey: computed(() => ['color-detail', props.hex, version.value]),
  queryFn: async () => {
    const response = await fetch(apiUrl(`colors/${props.hex}`, version.value, apiBase))
    if (!response.ok) throw new Error('Failed to load color')
    return response.json()
  },
  staleTime: 10 * 60 * 1000,
})

const detail = computed<ColorDetail | null>(() => data.value?.color ?? null)
const seed = computed(() => data.value?.seed ?? null)

const display = computed(() => {
  if (detail.value) return detail.value
  if (props.preview) return previewToDetail(props.preview)

  return hexFallbackDetail(props.hex)
})

const hsl = computed(() => {
  if (!display.value) return null
  return rgbToHsl(display.value.r, display.value.g, display.value.b)
})

const copied = ref<string | null>(null)
const showExtrasPending = computed(() => isFetching.value && !detail.value)

async function doCopy(label: string, value: string): Promise<void> {
  const ok = await copyText(value)
  if (ok) {
    copied.value = label
    window.setTimeout(() => {
      if (copied.value === label) copied.value = null
    }, 1500)
  }
}

function onBackdropClick(event: MouseEvent): void {
  if (event.target === event.currentTarget) {
    emit('close')
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    emit('close')
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="popup-backdrop" role="dialog" aria-modal="true" @click="onBackdropClick">
    <div v-if="display" class="popup" :style="{ borderColor: formatHex(display.hex) }">
      <button type="button" class="popup__close" aria-label="Close" @click="emit('close')">
        ×
      </button>

      <div
        class="popup__swatch"
        :class="display.text_contrast === 'light' ? 'popup__swatch--light' : 'popup__swatch--dark'"
        :style="{ backgroundColor: formatHex(display.hex) }"
      >
        <h2 class="popup__title">{{ display.name }}</h2>
        <p class="popup__subtitle">{{ formatHex(display.hex) }}</p>
      </div>

      <div class="popup__body">
        <dl class="popup__grid">
          <div>
            <dt>RGB</dt>
            <dd>{{ display.r }}, {{ display.g }}, {{ display.b }}</dd>
          </div>
          <div v-if="hsl">
            <dt>HSL</dt>
            <dd>{{ hsl.h }}°, {{ hsl.s }}%, {{ hsl.l }}%</dd>
          </div>
          <div>
            <dt>Lab</dt>
            <dd v-if="detail">
              L {{ detail.l.toFixed(1) }}, a {{ detail.a.toFixed(1) }}, b {{ detail.b_lab.toFixed(1) }}
            </dd>
            <dd v-else class="popup__pending">Loading…</dd>
          </div>
          <div>
            <dt>Hue bucket</dt>
            <dd>{{ display.hue_bucket }}</dd>
          </div>
        </dl>

        <div v-if="seed" class="popup__section">
          <h3>Also known as</h3>
          <ul class="popup__aka">
            <li v-for="alias in seed.aliases" :key="`${alias.source_key}-${alias.name}`">
              <strong>{{ alias.name }}</strong>
              <span>{{ alias.source }}</span>
            </li>
          </ul>
        </div>

        <div
          v-else-if="detail?.nearest_seed_name && detail.delta_e !== null && detail.delta_e > 0"
          class="popup__section"
        >
          <h3>Nearest seed</h3>
          <p>
            {{ detail.nearest_seed_name }}
            <span class="popup__muted">({{ formatHex(detail.nearest_seed_hex || '') }}, ΔE {{ detail.delta_e.toFixed(2) }})</span>
          </p>
        </div>

        <p v-else-if="showExtrasPending" class="popup__pending popup__section">Loading aliases…</p>

        <div v-if="isError && !detail" class="popup__section popup__error">
          Could not load full color details.
        </div>

        <div class="popup__actions">
          <button type="button" @click="doCopy('hex', formatHex(display.hex))">
            {{ copied === 'hex' ? 'Copied!' : 'Copy hex' }}
          </button>
          <button type="button" @click="doCopy('name', display.name)">
            {{ copied === 'name' ? 'Copied!' : 'Copy name' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.popup-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.72);
}

.popup {
  position: relative;
  width: min(520px, 100%);
  max-height: min(90vh, 720px);
  overflow: auto;
  border-radius: 1rem;
  border: 2px solid rgba(255, 255, 255, 0.15);
  background: #18181b;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
}

.popup__close {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  z-index: 2;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.35);
  color: #fff;
  font-size: 1.35rem;
  line-height: 1;
}

.popup__swatch {
  padding: 2.5rem 1.5rem 1.75rem;
}

.popup__swatch--light {
  color: #fff;
}

.popup__swatch--dark {
  color: #111;
}

.popup__title {
  margin: 0;
  font-size: 1.75rem;
}

.popup__subtitle {
  margin: 0.35rem 0 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  opacity: 0.85;
}

.popup__body {
  padding: 1.25rem 1.5rem 1.5rem;
}

.popup__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem 1rem;
  margin: 0;
}

.popup__grid dt {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #a1a1aa;
}

.popup__grid dd {
  margin: 0.15rem 0 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.9rem;
}

.popup__section {
  margin-top: 1.25rem;
}

.popup__section h3 {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}

.popup__aka {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.35rem;
}

.popup__aka li {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  font-size: 0.9rem;
}

.popup__aka span {
  color: #a1a1aa;
  font-size: 0.8rem;
}

.popup__muted {
  color: #a1a1aa;
}

.popup__pending {
  color: #a1a1aa;
  font-size: 0.85rem;
}

.popup__error {
  color: #fca5a5;
}

.popup__actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.25rem;
}

.popup__actions button {
  padding: 0.55rem 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 0.5rem;
  background: rgba(255, 255, 255, 0.06);
  color: inherit;
}
</style>
