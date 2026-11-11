import type { BackgroundToolInput } from './itemDefinitions'
import type { StartingEquipmentInput } from './startingEquipment'

export type BackgroundAbilityOption = {
  code: string
  title: string
}

export type BackgroundAbilityOptions = {
  rule_profile_id: number | null
  required_count: number
  ability_options: BackgroundAbilityOption[]
  ability_codes: string[]
  configured: boolean
  required_skill_count: number
  skill_options: { id: number; title: string }[]
  skill_entry_ids: number[]
  skills_configured: boolean
  feat_options: { id: number; title: string }[]
  feat_entry_id: number | null
  feat_configured: boolean
  tool_options: { id: number; title: string }[]
  tool_groups: { code: string; title: string }[]
  background_tool: BackgroundToolInput | null
  tool_configured: boolean
  equipment_item_options: { id: number; title: string }[]
  starting_equipment: StartingEquipmentInput | null
  starting_equipment_defaults: StartingEquipmentInput
  equipment_max_items: number
  equipment_integer_max: number
}

type BackgroundAbilityOptionsResponse = {
  data: BackgroundAbilityOptions
}

type ValidationErrorResponse = {
  error: {
    message: string
    fields: Record<string, string[]>
  }
}

async function readResponse(response: Response): Promise<BackgroundAbilityOptions> {
  if (response.status === 422) {
    const result = (await response.json()) as ValidationErrorResponse
    throw new Error(Object.values(result.error.fields)[0]?.[0] ?? result.error.message)
  }

  if (response.status === 401) {
    throw new Error('Войдите, чтобы управлять записями архива')
  }

  if (response.status === 403) {
    throw new Error('Недостаточно прав для управления записями архива')
  }

  if (response.status === 404) {
    throw new Error('Предыстория не найдена')
  }

  if (!response.ok) {
    throw new Error('Не удалось выполнить запрос настроек предыстории. HTTP ' + response.status)
  }

  const result = (await response.json()) as BackgroundAbilityOptionsResponse
  return result.data
}

export async function getBackgroundAbilityOptions(id: number): Promise<BackgroundAbilityOptions> {
  const response = await fetch(`/api/v1/catalog-entries/${id}/background-abilities`, {
    credentials: 'same-origin',
  })

  return readResponse(response)
}

export async function getNewBackgroundAbilityOptions(
  originProfileId: number | null = null,
): Promise<BackgroundAbilityOptions> {
  const query = originProfileId === null ? '' : `?rule_profile_id=${originProfileId}`
  return readResponse(
    await fetch('/api/v1/background-ability-options' + query, {
      credentials: 'same-origin',
    }),
  )
}

export async function updateBackgroundAbilityOptions(
  id: number,
  abilityCodes: string[],
): Promise<BackgroundAbilityOptions> {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

  if (!csrf) {
    throw new Error('CSRF-токен не найден')
  }

  const response = await fetch(`/api/v1/catalog-entries/${id}/background-abilities`, {
    method: 'PATCH',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf,
    },
    body: JSON.stringify({ ability_codes: abilityCodes }),
  })

  return readResponse(response)
}
