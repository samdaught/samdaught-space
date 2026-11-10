import type {
  CharacterContext,
  CharacterEditorDraft,
  CharacterEditorInput,
  CharacterEditorOptions,
  CharacterEditorState,
  InventoryItemDraft,
  InventoryItemInput,
  CharacterInventory,
} from '../types/characterEditor'
export type {
  CharacterContext,
  CharacterEditorDraft,
  CharacterEditorInput,
  CharacterEditorOptions,
  CharacterEditorState,
} from '../types/characterEditor'

export function getInventoryItemsInput(
  draft: InventoryItemDraft[],
  limits: CharacterInventory,
): InventoryItemInput[] | null {
  if (draft.length > limits.max_items) return null
  const items: InventoryItemInput[] = []
  for (const row of draft) {
    if (
      row.id !== null &&
      (!Number.isSafeInteger(row.id) || row.id < 1 || items.some((item) => item.id === row.id))
    )
      return null
    if (
      row.item_entry_id === null ||
      !Number.isSafeInteger(row.item_entry_id) ||
      row.item_entry_id < 1 ||
      typeof row.quantity !== 'number' ||
      !Number.isSafeInteger(row.quantity) ||
      row.quantity < 1 ||
      row.quantity > limits.max_quantity ||
      typeof row.is_equipped !== 'boolean' ||
      typeof row.note !== 'string' ||
      [...row.note].length > limits.max_note_length
    )
      return null
    items.push({
      id: row.id,
      item_entry_id: row.item_entry_id,
      quantity: row.quantity,
      is_equipped: row.is_equipped,
      note: row.note,
    })
  }
  return items
}
export function emptyCharacterDraft(): CharacterEditorDraft {
  return {
    name: '',
    rule_profile_id: null,
    target_level: 1,
    subclass_entry_id: null,
    species_variant_entry_id: null,
    species_entry_id: null,
    class_entry_id: null,
    background_entry_id: null,
    base_scores: {},
    background_increases: {},
    background_choices: { tool_entry_id: null, equipment_choice: null },
    class_skill_entry_ids: [],
    inventory_items: [],
    starting_equipped_ids: [],
    equipment_choices: {},
    rule_choices: {},
    creation_choices: {},
    sheet_details: {
      armor_class: null,
      speed_feet: null,
      initiative: null,
      spell_attack_bonus: null,
      spell_save_dc: null,
      size: '',
      languages: '',
      appearance: '',
      biography: '',
      resources: '',
      notes: '',
    },
  }
}

export function editorContext(draft: CharacterContext): CharacterContext {
  return {
    rule_profile_id: draft.rule_profile_id,
    target_level: draft.target_level,
    subclass_entry_id: draft.subclass_entry_id ?? null,
    species_variant_entry_id: draft.species_variant_entry_id ?? null,
    species_entry_id: draft.species_entry_id,
    class_entry_id: draft.class_entry_id,
    background_entry_id: draft.background_entry_id,
  }
}

export function getCharacterEditorInput(
  draft: CharacterEditorDraft,
  revision: number,
  options: CharacterEditorOptions,
  finish = false,
): CharacterEditorInput {
  if (!draft.name.trim()) throw new Error('Введите имя персонажа')
  if (draft.rule_profile_id === null) throw new Error('Выберите мир и правила')
  if (
    typeof draft.target_level !== 'number' ||
    !Number.isInteger(draft.target_level) ||
    draft.target_level < 1 ||
    draft.target_level > 20
  )
    throw new Error('Уровень должен быть целым числом от 1 до 20')
  const items = getInventoryItemsInput(draft.inventory_items, options.inventory)
  if (items === null) throw new Error('Заполните строки инвентаря или удалите незаполненные строки')
  const increases: Record<string, number> = {}
  for (const [code, amount] of Object.entries(draft.background_increases)) {
    if (
      !Number.isInteger(amount) ||
      amount < 0 ||
      amount > options.background_abilities.max_per_ability
    )
      throw new Error('Проверьте увеличения характеристик предыстории')
    if (amount > 0) increases[code] = amount
  }
  return {
    ...editorContext(draft),
    name: draft.name.trim(),
    rule_profile_id: draft.rule_profile_id,
    target_level: draft.target_level,
    revision,
    base_scores: { ...draft.base_scores },
    background_increases: increases,
    background_choices: {
      tool_entry_id: draft.background_choices.tool_entry_id,
      equipment_choice: draft.background_choices.equipment_choice,
    },
    class_skill_entry_ids: [...draft.class_skill_entry_ids],
    inventory_items: items,
    equipment_choices: { ...draft.equipment_choices },
    starting_equipped_ids: [...draft.starting_equipped_ids],
    rule_choices: Object.fromEntries(
      Object.entries(draft.rule_choices).filter(([, values]) => values.length > 0),
    ),
    creation_choices: Object.fromEntries(
      Object.entries(draft.creation_choices)
        .filter(([, ids]) => ids.length > 0)
        .map(([key, ids]) => [key, [...ids]]),
    ),
    sheet_details: {
      ...draft.sheet_details,
      armor_class: draft.sheet_details.armor_class === '' ? null : draft.sheet_details.armor_class,
      speed_feet: draft.sheet_details.speed_feet === '' ? null : draft.sheet_details.speed_feet,
      initiative: draft.sheet_details.initiative === '' ? null : draft.sheet_details.initiative,
      spell_attack_bonus:
        draft.sheet_details.spell_attack_bonus === ''
          ? null
          : draft.sheet_details.spell_attack_bonus,
      spell_save_dc:
        draft.sheet_details.spell_save_dc === '' ? null : draft.sheet_details.spell_save_dc,
    },
    finish,
  }
}

export class CharacterEditorError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message)
  }
}

async function readResponse<T>(response: Response): Promise<T> {
  if (response.status === 422) {
    const result = (await response.json()) as {
      error: { message: string; fields: Record<string, string[]> }
    }
    throw new CharacterEditorError(
      Object.values(result.error.fields).flat().join('\n') || result.error.message,
      422,
    )
  }
  if (response.status === 409)
    throw new CharacterEditorError(
      'Черновик изменён в другой вкладке. Ввод остался в форме. Загрузите сохранённый черновик перед повторным сохранением.',
      409,
    )
  if (response.status === 401) throw new Error('Войдите, чтобы работать со своими персонажами')
  if (response.status === 403) throw new Error('Изменять можно только черновик персонажа')
  if (response.status === 404) throw new Error('Персонаж или запрошенные данные не найдены')
  if (!response.ok)
    throw new Error(
      'Не удалось выполнить запрос редактора. HTTP ' +
        response.status +
        (response.headers.get('X-Request-ID')
          ? '. Код запроса: ' + response.headers.get('X-Request-ID')
          : ''),
    )
  return ((await response.json()) as { data: T }).data
}

export async function getCharacterEditor(id: number): Promise<CharacterEditorState> {
  return readResponse(await fetch(`/api/v1/characters/${id}`, { credentials: 'same-origin' }))
}

export async function getCharacterEditorOptions(
  context: CharacterContext,
  choices: Record<string, number[]> = {},
  classSkillIds: number[] = [],
  ruleChoices: Record<string, string[]> = {},
  baseScores: Record<string, number | null> = {},
  increases: Record<string, number> = {},
): Promise<CharacterEditorOptions> {
  const normalized = editorContext(context)
  if (normalized.target_level === '') normalized.target_level = 1
  return readResponse(
    await fetch('/api/v1/character-editor/options', {
      method: 'POST',
      headers: csrfHeaders(),
      credentials: 'same-origin',
      body: JSON.stringify({
        context: normalized,
        rule_choices: ruleChoices,
        base_scores: baseScores,
        background_increases: increases,
        creation_choices: choices,
        class_skill_entry_ids: classSkillIds,
      }),
    }),
  )
}

export async function saveCharacterEditor(
  id: number | null,
  input: CharacterEditorInput,
): Promise<CharacterEditorState> {
  return readResponse(
    await fetch(id === null ? '/api/v1/characters' : `/api/v1/characters/${id}`, {
      method: id === null ? 'POST' : 'PATCH',
      credentials: 'same-origin',
      headers: csrfHeaders(),
      body: JSON.stringify({
        ...editorContext(input),
        revision: input.revision,
        name: input.name,
        base_scores: input.base_scores,
        background_increases: input.background_increases,
        class_skill_entry_ids: input.class_skill_entry_ids,
        background_choices: {
          tool_entry_id: input.background_choices.tool_entry_id,
          equipment_choice: input.background_choices.equipment_choice,
        },
        inventory_items: input.inventory_items.map(
          ({ id, item_entry_id, quantity, is_equipped, note }) => ({
            id,
            item_entry_id,
            quantity,
            is_equipped,
            note,
          }),
        ),
        creation_choices: input.creation_choices,
        equipment_choices: input.equipment_choices,
        starting_equipped_ids: input.starting_equipped_ids,
        sheet_details: {
          armor_class: input.sheet_details.armor_class,
          speed_feet: input.sheet_details.speed_feet,
          initiative: input.sheet_details.initiative,
          spell_attack_bonus: input.sheet_details.spell_attack_bonus,
          spell_save_dc: input.sheet_details.spell_save_dc,
          size: input.sheet_details.size,
          languages: input.sheet_details.languages,
          appearance: input.sheet_details.appearance,
          biography: input.sheet_details.biography,
          resources: input.sheet_details.resources,
          notes: input.sheet_details.notes,
        },
        finish: input.finish,
      }),
    }),
  )
}

function csrfHeaders(): Record<string, string> {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (!csrf) throw new Error('CSRF-токен не найден')
  return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }
}

export async function deleteCharacter(id: number, revision: number): Promise<void> {
  await readResponse(
    await fetch(`/api/v1/characters/${id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: csrfHeaders(),
      body: JSON.stringify({ revision }),
    }),
  )
}
