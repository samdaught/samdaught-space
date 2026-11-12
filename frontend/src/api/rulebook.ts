export type RuleValue =
  | null
  | string
  | number
  | boolean
  | RuleValue[]
  | { [key: string]: RuleValue }

export type RulebookData = Record<string, RuleValue>

export type RulebookOptions = {
  entries: { id: number; type: string; title: string; is_published: boolean }[]
  vocabulary: { kind: string; code: string; title: string; group_code: string }[]
  targets: string[]
  recovery: string[]
  features: string[]
}

export function emptyRulebook(): RulebookData {
  return {
    parent_entry_id: null,
    variant_required: false,
    subclass_level: 3,
    selection_level: 1,
    feature_kind: 'general',
    repeatable: false,
    distinct_choices: [],
    prerequisites: { op: 'all', conditions: [] },
    effects: [],
    choices: [],
    casting_choice_code: null,
    casting_ability_code: null,
    spell_list_class_ids: [],
    unlocks: [],
    progression: [],
    spell_grants: [],
    spell_picks: [],
    resources: [],
    item: null,
  }
}

export async function getRulebookOptions(profile: number | null): Promise<RulebookOptions> {
  const response = await fetch(
    '/api/v1/rulebook/options' + (profile === null ? '' : '?rule_profile_id=' + profile),
    { credentials: 'same-origin' },
  )
  if (!response.ok) throw new Error('Не удалось загрузить правила архива')
  return (await response.json()).data
}
