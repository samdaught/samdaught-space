export type EntryMechanics = {
  innate_ability_code: string | null
  unarmored_defense: { base_ac: number; ability_code: string | null; allows_shield: boolean } | null
  bonuses: { code: string; amount: number }[]
  traits: { kind: string; value: string }[]
  equipment: {
    id: number | null
    title: string
    money_cp: number
    items: { item_entry_id: number; quantity: number }[]
  }[]
  spell: {
    level: number
    school: string
    casting_time: string
    range_text: string
    components: string
    duration: string
    concentration: boolean
    ritual: boolean
  } | null
  spellcasting: {
    enabled: boolean
    ability_code: string | null
    cantrip_count: number
    known_count: number
    prepared_count: number
    slot_count: number
  } | null
  spell_ids: number[]
  class_ids: number[]
  armor: { base_ac: number; dexterity_cap: number | null; is_shield: boolean } | null
}
export type MechanicsOptions = {
  vocabulary?: { kind: string; code: string; title: string }[]
  entries: { id: number; title: string; type: string; is_published: boolean }[]
  abilities: { code: string; title: string }[]
  bonuses: Record<string, string>
  traits: Record<string, string>
}
export function emptyMechanics(): EntryMechanics {
  return {
    innate_ability_code: null,
    unarmored_defense: null,
    bonuses: [],
    traits: [],
    equipment: [],
    spell: null,
    spellcasting: null,
    spell_ids: [],
    class_ids: [],
    armor: null,
  }
}
export function mechanicsInput(category: string, data: EntryMechanics): EntryMechanics {
  const source = [
    'species',
    'class',
    'background',
    'feat',
    'feature',
    'subclass',
    'species_variant',
  ].includes(category)
  return {
    ...data,
    innate_ability_code: source ? data.innate_ability_code : null,
    unarmored_defense: source ? data.unarmored_defense : null,
    bonuses: source || category === 'item' ? data.bonuses : [],
    traits: source ? data.traits : [],
    equipment: source ? data.equipment : [],
    spell:
      category === 'spell'
        ? (data.spell ?? {
            level: 0,
            school: '',
            casting_time: '',
            range_text: '',
            components: '',
            duration: '',
            concentration: false,
            ritual: false,
          })
        : null,
    spellcasting:
      category === 'class'
        ? data.spellcasting?.enabled
          ? data.spellcasting
          : {
              enabled: false,
              ability_code: null,
              cantrip_count: 0,
              known_count: 0,
              prepared_count: 0,
              slot_count: 0,
            }
        : null,
    class_ids: category === 'spell' ? data.class_ids : [],
    spell_ids: category === 'class' ? data.spell_ids : [],
    armor: category === 'item' ? data.armor : null,
  }
}
export async function getMechanicsOptions(profile: number | null): Promise<MechanicsOptions> {
  const response = await fetch(
    '/api/v1/entry-mechanics/options' + (profile === null ? '' : '?origin_profile_id=' + profile),
    { credentials: 'same-origin' },
  )
  if (!response.ok)
    throw new Error('Не удалось загрузить варианты механики. HTTP ' + response.status)
  return ((await response.json()) as { data: MechanicsOptions }).data
}
