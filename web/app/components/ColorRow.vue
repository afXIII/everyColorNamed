<script setup lang="ts">
import type { ColorRow } from '~/types/color'
import { formatHex } from '~/utils/color'
import { ROW_HEIGHT } from '~/composables/useCatalogConstants'

const props = defineProps<{
  row: ColorRow | undefined
  /** Pixel offset within the visible window (not absolute catalog index). */
  top: number
}>()

const emit = defineEmits<{
  select: [row: ColorRow]
}>()

const style = computed(() => ({
  height: `${ROW_HEIGHT}px`,
  transform: `translateY(${props.top}px)`,
}))

const textClass = computed(() =>
  props.row?.text_contrast === 'light' ? 'color-row--light-text' : 'color-row--dark-text',
)
</script>

<template>
  <button
    v-if="row"
    type="button"
    class="color-row"
    :class="textClass"
    :style="{ ...style, backgroundColor: formatHex(row.hex) }"
    @click="emit('select', row)"
  >
    <span class="color-row__name">{{ row.name }}</span>
    <span class="color-row__hex">{{ formatHex(row.hex) }}</span>
    <span class="color-row__bucket">{{ row.hue_bucket }}</span>
  </button>
  <div v-else class="color-row color-row--placeholder" :style="style" />
</template>

<style scoped>
.color-row {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 1rem;
  align-items: center;
  padding: 0 1.25rem;
  border: none;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  text-align: left;
}

.color-row:hover {
  outline: 1px solid rgba(255, 255, 255, 0.18);
  outline-offset: -1px;
  z-index: 1;
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
