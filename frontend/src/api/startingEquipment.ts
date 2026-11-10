export type StartingEquipmentInput = {
  package_money_cp: number
  alternative_money_cp: number
  include_background_tool: boolean
  items: { item_entry_id: number; quantity: number }[]
}

export type StartingEquipmentDraft = {
  package_money_cp: number
  alternative_money_cp: number
  include_background_tool: boolean
  items: { item_entry_id: number | null; quantity: number }[]
}

export function getStartingEquipmentInput(
  draft: StartingEquipmentDraft | null,
  maxItems: number,
  maxInteger: number,
): StartingEquipmentInput | null {
  if (draft === null || draft.items.length > maxItems) return null
  for (const money of [draft.package_money_cp, draft.alternative_money_cp]) {
    if (!Number.isSafeInteger(money) || money < 0 || money > maxInteger) return null
  }
  const items: StartingEquipmentInput['items'] = []
  for (const item of draft.items) {
    if (
      item.item_entry_id === null ||
      !Number.isSafeInteger(item.item_entry_id) ||
      item.item_entry_id < 1 ||
      !Number.isSafeInteger(item.quantity) ||
      item.quantity < 1 ||
      item.quantity > maxInteger ||
      items.some((previous) => previous.item_entry_id === item.item_entry_id)
    )
      return null
    items.push({ item_entry_id: item.item_entry_id, quantity: item.quantity })
  }
  return {
    package_money_cp: draft.package_money_cp,
    alternative_money_cp: draft.alternative_money_cp,
    include_background_tool: draft.include_background_tool,
    items,
  }
}
