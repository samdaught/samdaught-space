import type { RulebookData } from './rulebook'
import type { SpeciesCard } from './speciesCard'
import type { ClassCard } from './classCard'
import type { EntryMechanics } from './entryMechanics'
import type { PaginationMeta } from './pagination'
import type { CreationRuleInput } from './creationRules'
import type { BackgroundToolInput } from './itemDefinitions'
import type { StartingEquipmentInput } from './startingEquipment'

export type { PaginationMeta } from './pagination'

type ValidationErrorResponse = {
  error: {
    code: 'VALIDATION_FAILED'
    message: string
    fields: Record<string, string[]>
  }
}

export type CatalogEntryType = {
  code: string
  title: string
}

type CatalogEntryTypesResponse = {
  data: CatalogEntryType[]
}

export async function getCatalogEntryTypes(): Promise<CatalogEntryType[]> {
  const response = await fetch('/api/v1/catalog-entry-types')

  if (!response.ok) {
    throw new Error('Не удалось загрузить категории. HTTP ' + response.status)
  }

  const result = (await response.json()) as CatalogEntryTypesResponse

  return result.data
}

export type NewCatalogEntry = {
  species_card?: SpeciesCard
  class_card?: ClassCard
  rulebook?: RulebookData
  revision?: number
  mechanics?: EntryMechanics
  creation_rules?: CreationRuleInput
  species_speed_feet?: number
  class_hit_die_sides?: number
  class_skill_choice_count?: number
  class_skill_entry_ids?: number[]
  class_saving_throw_codes?: string[]
  starting_equipment?: StartingEquipmentInput
  item_kind_code?: string
  tool_group_code?: string | null
  tool_ability_code?: string | null
  background_tool?: BackgroundToolInput
  feat_category_code?: string
  feat_entry_id?: number
  skill_entry_ids?: number[]
  ability_codes?: string[]
  skill_ability_code?: string
  type: string
  slug?: string
  title: string
  rule_profile_id: number
  source_id: number | null
  body: string
}

export type CatalogEntry = {
  species_card?: SpeciesCard | null
  class_card?: ClassCard | null
  rulebook?: RulebookData
  revision?: number
  mechanics?: EntryMechanics
  creation_rules?: CreationRuleInput | null
  species_speed_feet?: number | null
  class_hit_die_sides?: number | null
  class_skill_choice_count?: number | null
  class_skill_entry_ids?: number[]
  class_saving_throw_codes?: string[]
  starting_equipment?: StartingEquipmentInput | null
  item_kind_code?: string | null
  tool_group_code?: string | null
  tool_ability_code?: string | null
  background_tool?: BackgroundToolInput | null
  feat_category_code?: string | null
  feat_entry_id?: number | null
  skill_entry_ids?: number[]
  skill_ability_code?: string | null
  id: number
  type: string
  slug: string
  title: string
  rule_profile_id: number
  source_id: number | null
  body: string | null
}

type CreatedCatalogEntryResponse = {
  data: CatalogEntry
}

export type CatalogEntryDetails = CatalogEntry & {
  is_published: boolean
}

export type CatalogEntryUpdateInput = {
  species_card?: SpeciesCard
  class_card?: ClassCard
  rulebook?: RulebookData
  revision?: number
  mechanics?: EntryMechanics
  creation_rules?: CreationRuleInput
  species_speed_feet?: number
  class_hit_die_sides?: number
  class_skill_choice_count?: number
  class_skill_entry_ids?: number[]
  class_saving_throw_codes?: string[]
  starting_equipment?: StartingEquipmentInput
  item_kind_code?: string
  tool_group_code?: string | null
  tool_ability_code?: string | null
  background_tool?: BackgroundToolInput
  feat_category_code?: string
  feat_entry_id?: number
  skill_entry_ids?: number[]
  ability_codes?: string[]
  skill_ability_code?: string
  title: string
  body: string
  source_id: number | null
}

type CatalogEntryDetailsResponse = {
  data: CatalogEntryDetails
}

export async function getCatalogEntry(id: number): Promise<CatalogEntryDetails> {
  const response = await fetch(`/api/v1/catalog-entries/${id}`, {
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error('Не удалось загрузить запись. HTTP ' + response.status)
  }

  const result = (await response.json()) as CatalogEntryDetailsResponse

  return result.data
}

export async function updateCatalogEntry(
  id: number,
  input: CatalogEntryUpdateInput,
): Promise<CatalogEntryDetails> {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]')
  const csrf = csrfMeta?.getAttribute('content')

  if (!csrf) {
    throw new Error('CSRF-токен не найден')
  }

  const response = await fetch(`/api/v1/catalog-entries/${id}`, {
    method: 'PATCH',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf,
    },
    body: JSON.stringify(input),
  })

  if (response.status === 409)
    throw new Error(
      'Запись или её связи изменились. Перезагрузите запись перед сохранением. Введённые данные оставлены в форме.',
    )
  if (response.status === 422) {
    const result = (await response.json()) as ValidationErrorResponse
    const firstMessage = Object.values(result.error.fields)[0]?.[0]

    throw new Error(firstMessage ?? result.error.message)
  }

  if (!response.ok) {
    throw new Error('Не удалось сохранить запись. HTTP ' + response.status)
  }

  const result = (await response.json()) as CatalogEntryDetailsResponse

  return result.data
}

export async function createCatalogEntry(input: NewCatalogEntry): Promise<CatalogEntry> {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]')
  const csrf = csrfMeta?.getAttribute('content')

  if (!csrf) {
    throw new Error('CSRF-токен не найден')
  }

  const response = await fetch('/api/v1/catalog-entries', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf,
    },
    body: JSON.stringify(input),
  })

  if (response.status === 422) {
    const result = (await response.json()) as ValidationErrorResponse
    const messages = Object.values(result.error.fields)
    const firstMessage = messages[0]?.[0]

    throw new Error(firstMessage ?? result.error.message)
  }

  if (!response.ok) {
    throw new Error('Не удалось создать запись. HTTP ' + response.status)
  }

  const result = (await response.json()) as CreatedCatalogEntryResponse

  return result.data
}

export async function copyCatalogEntryToWorld(
  id: number,
  ruleProfileId: number,
): Promise<CatalogEntry> {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]')
  const csrf = csrfMeta?.getAttribute('content')

  if (!csrf) {
    throw new Error('CSRF-токен не найден')
  }

  const response = await fetch(`/api/v1/catalog-entries/${id}/copy-to-world`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf,
    },
    body: JSON.stringify({ rule_profile_id: ruleProfileId }),
  })

  if (response.status === 422) {
    const result = (await response.json()) as ValidationErrorResponse
    const firstMessage = Object.values(result.error.fields)[0]?.[0]

    throw new Error(firstMessage ?? result.error.message)
  }

  if (!response.ok) {
    const result = (await response.json().catch(() => null)) as {
      message?: string
      error?: { message?: string }
    } | null

    throw new Error(
      result?.error?.message ??
        result?.message ??
        'Не удалось скопировать запись. HTTP ' + response.status,
    )
  }

  const result = (await response.json()) as CreatedCatalogEntryResponse

  return result.data
}

export type CatalogEntryListItem = {
  id: number
  title: string
  type: string
  type_title: string
  rule_profile_id: number
  rule_profile_title: string
  is_published: boolean
}

export type CatalogEntriesResponse = {
  data: CatalogEntryListItem[]
  meta: PaginationMeta
}

export type CatalogEntriesQuery = {
  page?: number
  q?: string
  sort?: 'title' | 'type' | 'profile' | 'status'
  direction?: 'asc' | 'desc'
}

export async function getCatalogEntries({
  page = 1,
  q = '',
  sort = 'title',
  direction = 'asc',
}: CatalogEntriesQuery = {}): Promise<CatalogEntriesResponse> {
  const params = new URLSearchParams({
    page: String(page),
    sort,
    direction,
  })

  if (q.trim() !== '') params.set('q', q.trim())

  const response = await fetch('/api/v1/catalog-entries?' + params.toString(), {
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error('Не удалось загрузить записи. HTTP ' + response.status)
  }

  const result = (await response.json()) as CatalogEntriesResponse

  return result
}

export type CatalogEntryPublication = {
  id: number
  is_published: boolean
}

type CatalogEntryPublicationResponse = {
  data: CatalogEntryPublication
}

export async function setCatalogEntryPublished(
  id: number,
  isPublished: boolean,
): Promise<CatalogEntryPublication> {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]')
  const csrf = csrfMeta?.getAttribute('content')

  if (!csrf) {
    throw new Error('CSRF-токен не найден')
  }

  const action = isPublished ? 'publish' : 'unpublish'
  const response = await fetch(`/api/v1/catalog-entries/${id}/${action}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-CSRF-Token': csrf,
    },
  })

  if (response.status === 422) {
    const result = (await response.json()) as ValidationErrorResponse
    const firstMessage = Object.values(result.error.fields)[0]?.[0]

    throw new Error(firstMessage ?? result.error.message)
  }

  if (!response.ok) {
    throw new Error('Не удалось изменить статус публикации. HTTP ' + response.status)
  }

  const result = (await response.json()) as CatalogEntryPublicationResponse

  return result.data
}
