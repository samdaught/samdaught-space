export type ClassCard = {
  primary_abilities: string[]
  roleplay: string
  portraits: { url: string; alt: string }[]
  features: { level: number; title: string; description: string }[]
  subclass_label: string
}

export type ClassDetails = {
  hit_die_sides: number | null
  saving_throw_codes: string[]
  skill_choice_count: number | null
  skills: string[]
  training: { kind: string; value: string }[]
}

export function emptyClassCard(): ClassCard {
  return { primary_abilities: [], roleplay: '', portraits: [], features: [], subclass_label: '' }
}
