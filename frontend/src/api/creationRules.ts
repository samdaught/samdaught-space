export type CreationMode = 'grant' | 'expertise'
export type CreationRuleInput = {
  hit_point_bonus: number
  grants: { entry_id: number; mode: CreationMode }[]
  choices: { code: string; title: string; count: number; mode: CreationMode; entry_ids: number[] }[]
}
export type CreationRuleDraft = {
  hit_point_bonus?: number | ''
  grants: { entry_id: number | null; mode: CreationMode }[]
  choices: {
    code: string
    title: string
    count: number | ''
    mode: CreationMode
    entry_ids: number[]
  }[]
}
export type CreationRuleOptions = {
  entries: { id: number; type: string; title: string }[]
  source_types: string[]
  max_options: number
  max_rows: number
  modes: { code: CreationMode; title: string }[]
}
export async function getCreationRuleOptions(
  profileId: number | null,
): Promise<CreationRuleOptions> {
  const query = profileId === null ? '' : '?rule_profile_id=' + profileId
  const response = await fetch('/api/v1/creation-rule-options' + query, {
    credentials: 'same-origin',
  })
  if (!response.ok) throw new Error('Не удалось загрузить варианты выдач. HTTP ' + response.status)
  return ((await response.json()) as { data: CreationRuleOptions }).data
}
export function getCreationRuleInput(
  draft: CreationRuleDraft,
  options: CreationRuleOptions,
): CreationRuleInput | null {
  const hitPointBonus = draft.hit_point_bonus ?? 0
  if (
    !Number.isInteger(hitPointBonus) ||
    Number(hitPointBonus) < -2147483648 ||
    Number(hitPointBonus) > 2147483647
  )
    return null
  if (draft.grants.length > options.max_rows || draft.choices.length > options.max_rows) return null
  const allowed = (id: number | null, mode: CreationMode) =>
    options.entries.some(
      (entry) => entry.id === id && (mode !== 'expertise' || entry.type === 'skill'),
    ) && options.modes.some((option) => option.code === mode)
  if (
    draft.grants.some((grant) => !allowed(grant.entry_id, grant.mode)) ||
    new Set(draft.grants.map((grant) => grant.entry_id)).size !== draft.grants.length
  )
    return null
  if (new Set(draft.choices.map((choice) => choice.code)).size !== draft.choices.length) return null
  for (const choice of draft.choices)
    if (
      !/^[a-z][a-z0-9_]{0,31}$/.test(choice.code) ||
      !choice.title.trim() ||
      choice.title.length > 120 ||
      typeof choice.count !== 'number' ||
      !Number.isInteger(choice.count) ||
      choice.count < 1 ||
      choice.count > options.max_rows ||
      choice.entry_ids.length < choice.count ||
      choice.entry_ids.length > options.max_options ||
      new Set(choice.entry_ids).size !== choice.entry_ids.length ||
      choice.entry_ids.some((id) => !allowed(id, choice.mode))
    )
      return null
  return {
    hit_point_bonus: hitPointBonus as number,
    grants: draft.grants.map((grant) => ({ entry_id: grant.entry_id!, mode: grant.mode })),
    choices: draft.choices.map((choice) => ({
      ...choice,
      title: choice.title.trim(),
      count: choice.count as number,
      entry_ids: [...choice.entry_ids],
    })),
  }
}
