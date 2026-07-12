<script setup lang="ts">
import type { JumpNavEntry } from '~/types/color'

const props = defineProps<{
  navOrder: string[]
  jumpNav: Record<string, JumpNavEntry>
  activeBucket: string | null
}>()

const emit = defineEmits<{
  jump: [offset: number]
  prefetch: [offset: number]
}>()

function onHover(name: string): void {
  const entry = props.jumpNav[name]
  if (entry) {
    emit('prefetch', entry.offset)
  }
}

function onClick(name: string): void {
  const entry = props.jumpNav[name]
  if (entry) {
    emit('jump', entry.offset)
  }
}
</script>

<template>
  <nav class="primary-nav" aria-label="Jump to primary color">
    <p class="primary-nav__label" id="primary-nav-label">Go to</p>
    <div class="primary-nav__list" role="group" aria-labelledby="primary-nav-label">
      <button
        v-for="name in navOrder"
        :key="name"
        type="button"
        class="primary-nav__item"
        :class="{ 'primary-nav__item--active': activeBucket === name }"
        :disabled="!jumpNav[name]"
        :title="jumpNav[name] ? `Go to ${name}` : `${name} not in this catalog build`"
        @mouseenter="onHover(name)"
        @focus="onHover(name)"
        @touchstart.passive="onHover(name)"
        @click="onClick(name)"
      >
        {{ name }}
      </button>
    </div>
  </nav>
</template>

<style scoped>
.primary-nav {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  padding: 1rem;
}

.primary-nav__label {
  margin: 0;
  padding: 0 0.15rem;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #71717a;
}

.primary-nav__list {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.primary-nav__item {
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: 0.4rem;
  background: transparent;
  color: inherit;
  text-align: left;
  transition: background 0.12s ease, color 0.12s ease;
}

.primary-nav__item:hover:not(:disabled),
.primary-nav__item:focus-visible {
  background: rgba(255, 255, 255, 0.08);
  outline: none;
}

.primary-nav__item--active {
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.primary-nav__item:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .primary-nav {
    flex-direction: row;
    align-items: center;
    overflow-x: auto;
    padding: 0.65rem 0.85rem;
    gap: 0.65rem;
    -webkit-overflow-scrolling: touch;
  }

  .primary-nav__label {
    flex: 0 0 auto;
    padding: 0;
  }

  .primary-nav__list {
    flex-direction: row;
    gap: 0.35rem;
  }

  .primary-nav__item {
    flex: 0 0 auto;
    white-space: nowrap;
  }
}
</style>
