<script setup lang="ts">
import type { ColorRow } from '~/types/color'
import ColorList from '~/components/ColorList.vue'
import ColorDetailPopup from '~/components/ColorDetailPopup.vue'
import PrimaryColorNav from '~/components/PrimaryColorNav.vue'

const { data: manifest, isLoading, isError, error } = useManifest()
const { popupHex, popupPreview, open, close } = useColorPopup()
const { prefetchAroundOffset } = useColorWindow()

const listRef = ref<InstanceType<typeof ColorList> | null>(null)

function onJump(offset: number): void {
  listRef.value?.scrollToOffset(offset)
}

function onPrefetch(offset: number): void {
  prefetchAroundOffset(offset)
}

function onSelect(row: ColorRow): void {
  open(row)
}
</script>

<template>
  <div class="browse">
    <header class="browse__header">
      <div>
        <h1 class="browse__title">everyColorNamed</h1>
        <p v-if="manifest" class="browse__meta">
          {{ manifest.total_colors.toLocaleString() }} colors
          <span v-if="manifest.public_version">· v{{ manifest.public_version }}</span>
          <span v-if="manifest.status === 'released'">· released</span>
        </p>
      </div>
      <a
        class="browse__repo"
        href="https://github.com/afXIII/everyColorNamed"
        target="_blank"
        rel="noopener noreferrer"
      >
        GitHub
      </a>
    </header>

    <div v-if="isLoading" class="browse__state">Loading catalog…</div>
    <div v-else-if="isError" class="browse__state browse__state--error">
      {{ error instanceof Error ? error.message : 'Failed to load catalog.' }}
    </div>

    <div v-else-if="manifest" class="browse__body">
      <aside class="browse__nav">
        <PrimaryColorNav
          :nav-order="manifest.nav_order"
          :jump-nav="manifest.jump_nav"
          :active-bucket="null"
          @jump="onJump"
          @prefetch="onPrefetch"
        />
      </aside>

      <ColorList
        ref="listRef"
        :total-colors="manifest.total_colors"
        @select="onSelect"
      />
    </div>

    <ColorDetailPopup
      v-if="popupHex"
      :hex="popupHex"
      :preview="popupPreview"
      @close="close"
    />
  </div>
</template>

<style scoped>
.browse {
  display: flex;
  flex-direction: column;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}

.browse__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: #0f0f10;
}

.browse__repo {
  flex-shrink: 0;
  margin-top: 0.2rem;
  font-size: 0.85rem;
  color: #a1a1aa;
  text-decoration: none;
}

.browse__repo:hover,
.browse__repo:focus-visible {
  color: #e8e8ea;
  text-decoration: underline;
}

.browse__title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
}

.browse__meta {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  color: #a1a1aa;
}

.browse__body {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.browse__nav {
  flex: 0 0 11rem;
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  background: #0f0f10;
  overflow-y: auto;
}

.browse__state {
  padding: 3rem 1.25rem;
  color: #a1a1aa;
}

.browse__state--error {
  color: #fca5a5;
}

@media (max-width: 768px) {
  .browse__body {
    flex-direction: column;
  }

  .browse__nav {
    flex: none;
    width: 100%;
    overflow: visible;
    border-right: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  }
}
</style>
