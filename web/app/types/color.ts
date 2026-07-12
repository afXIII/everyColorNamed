export type TextContrast = 'light' | 'dark'

export type ColorRow = {
  offset: number
  color_id: number
  hex: string
  name: string
  hue_bucket: string
  text_contrast: TextContrast
  r: number
  g: number
  b: number
}

export type JumpNavEntry = {
  offset: number
  color_id: number
  hex?: string
}

export type Manifest = {
  build_id: string
  status: string
  public_version: string | null
  catalog_level: number
  naming_strategy: string
  total_colors: number
  shard_count: number
  nav_order: string[]
  jump_nav: Record<string, JumpNavEntry>
  generated_at: string
}

export type WindowResponse = {
  offset: number
  from: number | null
  limit: number
  rows: ColorRow[]
}

export type SeedAlias = {
  name: string
  source: string
  source_key: string
}

export type SeedRecord = {
  hex: string
  primary_name: string
  owned_names: string[]
  aliases: SeedAlias[]
  conflicting_names: Array<SeedAlias & { canonical_hex: string; canonical_source: string }>
  lab: { l: number; a: number; b: number }
}

export type ColorDetail = {
  color_id: number
  r: number
  g: number
  b: number
  hex: string
  name: string
  hue_bucket: string
  text_contrast: TextContrast
  nearest_seed_hex: string | null
  nearest_seed_name: string | null
  delta_e: number | null
  l: number
  a: number
  b_lab: number
}

export type ColorShowResponse = {
  color: ColorDetail
  seed: SeedRecord | null
}
