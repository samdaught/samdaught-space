export type BasicRuleOptions = {
  skill_options: { id: number; title: string }[]
  max_class_skill_options: number
  hit_dice: { sides: number; title: string }[]
  ability_options: { code: string; title: string }[]
  required_saving_throw_count: number
  max_speed_feet: number
}
export type BasicRuleDraft = {
  species_speed_feet: number | ''
  class_hit_die_sides: number | null
  class_saving_throw_codes: string[]
  class_skill_choice_count: number | ''
  class_skill_entry_ids: number[]
}
export type BasicRuleInput =
  | { species_speed_feet: number }
  | {
      class_hit_die_sides: number
      class_saving_throw_codes: string[]
      class_skill_choice_count: number
      class_skill_entry_ids: number[]
    }

export async function getBasicRuleOptions(
  originProfileId: number | null,
): Promise<BasicRuleOptions> {
  const query = originProfileId === null ? '' : '?origin_profile_id=' + originProfileId
  const response = await fetch('/api/v1/basic-rule-options' + query, { credentials: 'same-origin' })
  if (!response.ok)
    throw new Error('Не удалось загрузить базовые свойства вида и класса. HTTP ' + response.status)
  return ((await response.json()) as { data: BasicRuleOptions }).data
}

export function getBasicRuleInput(
  category: string,
  draft: BasicRuleDraft,
  options: BasicRuleOptions,
): BasicRuleInput | null {
  if (category === 'species') {
    const speed = draft.species_speed_feet
    return typeof speed === 'number' &&
      Number.isSafeInteger(speed) &&
      speed >= 0 &&
      speed <= options.max_speed_feet
      ? { species_speed_feet: speed }
      : null
  }
  if (category === 'class') {
    const die = draft.class_hit_die_sides
    const codes = draft.class_saving_throw_codes
    const count = draft.class_skill_choice_count
    const skills = draft.class_skill_entry_ids
    if (
      die === null ||
      typeof count !== 'number' ||
      !Number.isSafeInteger(count) ||
      count < 0 ||
      count > options.max_class_skill_options ||
      skills.length < count ||
      skills.length > options.max_class_skill_options ||
      new Set(skills).size !== skills.length ||
      skills.some((id) => !options.skill_options.some((option) => option.id === id)) ||
      !options.hit_dice.some((option) => option.sides === die) ||
      codes.length !== options.required_saving_throw_count ||
      new Set(codes).size !== codes.length ||
      codes.some((code) => !options.ability_options.some((option) => option.code === code))
    )
      return null
    return {
      class_hit_die_sides: die,
      class_saving_throw_codes: [...codes],
      class_skill_choice_count: count,
      class_skill_entry_ids: [...skills],
    }
  }
  return null
}
