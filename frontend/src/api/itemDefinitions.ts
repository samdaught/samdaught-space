export type ItemDefinitionInput = {
  item_kind_code: string
  tool_group_code: string | null
  tool_ability_code: string | null
}

export type ItemDefinitionDraft = {
  item_kind_code: string
  tool_group_code: string
  tool_ability_code: string
}

export type ItemDefinitionOptions = {
  tool_kind_code: string
  item_kinds: { code: string; title: string }[]
  tool_groups: { code: string; title: string }[]
  ability_options: { code: string; title: string }[]
}

export function getItemDefinitionInput(
  draft: ItemDefinitionDraft,
  options: ItemDefinitionOptions,
): ItemDefinitionInput | null {
  if (!options.item_kinds.some((kind) => kind.code === draft.item_kind_code)) return null
  if (draft.item_kind_code === options.tool_kind_code) {
    if (draft.tool_group_code === '') return null
    return {
      item_kind_code: draft.item_kind_code,
      tool_group_code: draft.tool_group_code,
      tool_ability_code: draft.tool_ability_code === '' ? null : draft.tool_ability_code,
    }
  }
  return { item_kind_code: draft.item_kind_code, tool_group_code: null, tool_ability_code: null }
}

export type BackgroundToolInput =
  | { tool_entry_id: number; tool_group_code: null }
  | { tool_entry_id: null; tool_group_code: string }

export type BackgroundToolDraft = {
  tool_entry_id: number | null
  tool_group_code: string | null
}

export async function getItemDefinitionOptions(): Promise<ItemDefinitionOptions> {
  const response = await fetch('/api/v1/item-definition-options', { credentials: 'same-origin' })
  if (!response.ok) {
    throw new Error('Не удалось загрузить свойства предметов. HTTP ' + response.status)
  }
  const result = (await response.json()) as { data: ItemDefinitionOptions }
  return result.data
}

export function getBackgroundToolInput(draft: BackgroundToolDraft): BackgroundToolInput | null {
  if (draft.tool_entry_id !== null && draft.tool_group_code === null) {
    return { tool_entry_id: draft.tool_entry_id, tool_group_code: null }
  }
  if (
    draft.tool_entry_id === null &&
    draft.tool_group_code !== null &&
    draft.tool_group_code !== ''
  ) {
    return { tool_entry_id: null, tool_group_code: draft.tool_group_code }
  }
  return null
}
