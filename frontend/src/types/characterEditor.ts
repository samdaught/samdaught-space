import type { SpeciesCard } from '../api/speciesCard'
import type { SpellDefinition } from '../api/spells'
import type { ClassCard, ClassDetails } from '../api/classCard'

export type CharacterAbilityScore = {
  code: string
  title: string
  base_score: number | null
  base_modifier: number | null
  source_bonus?: number
  applied_increase: number
  total_score: number | null
  total_modifier: number | null
}

export type CharacterAbilityScores = {
  method: 'standard_array'
  available_values: number[]
  calculation: {
    base_scores_complete: boolean
    background_increases_complete: boolean
    issues: string[]
  }
  abilities: CharacterAbilityScore[]
}

export type CharacterBackgroundAbilities = {
  background_entry_id: number | null
  rule_profile_id: number
  revision: number
  configured: boolean
  selection_valid: boolean
  complete: boolean
  point_budget: number
  max_per_ability: number
  needs_redistribution: boolean
  issues: string[]
  abilities: { code: string; title: string; increase: number }[]
}

export type BackgroundBenefitEntry = { id: number; title: string; body: string | null }
export type BackgroundEquipmentPreview = {
  items: { id: number; title: string; quantity: number }[]
  money_cp: number
}
export type BackgroundBenefitsDraft = {
  tool_entry_id: number | null
  equipment_choice: 'package' | 'money' | null
}
export type CharacterBackgroundBenefits = {
  background_entry_id: number | null
  rule_profile_id: number
  revision: number
  selection_valid: boolean
  needs_reselection: boolean
  skills: (BackgroundBenefitEntry & { ability_code: string })[]
  skills_complete: boolean
  feat: BackgroundBenefitEntry | null
  feat_complete: boolean
  tool: {
    configured: boolean
    requires_choice: boolean
    group_title: string | null
    options: { id: number; title: string }[]
    selected_tool_entry_id: number | null
    effective_tool: BackgroundBenefitEntry | null
    complete: boolean
  }
  equipment: {
    configured: boolean
    source_text: string | null
    choice: BackgroundBenefitsDraft['equipment_choice']
    package_available: boolean
    money_available: boolean
    package: (BackgroundEquipmentPreview & { include_background_tool: boolean }) | null
    alternative_money_cp: number | null
    preview: BackgroundEquipmentPreview | null
    complete: boolean
    issues: string[]
  }
  complete: boolean
  issues: string[]
}

export type CharacterClassSkills = {
  class_entry_id: number | null
  rule_profile_id: number
  revision: number
  selection_valid: boolean
  configured: boolean
  choice_count: number | null
  options: { id: number; title: string }[]
  selected_skill_entry_ids: number[]
  effective_skill_entry_ids: number[]
  needs_reselection: boolean
  complete: boolean
  issues: string[]
}

export type CharacterSheetBasics = {
  armor_untrained?: boolean
  stealth_disadvantage?: boolean
  armor_speed_penalty?: number
  resources?: { title: string; maximum: number; recovery: string; source_title: string }[]
  weapons?: {
    id: number
    title: string
    attack_bonus: number | null
    damage: string
    damage_type: string
    mastery: string | null
    mastery_available: boolean
    proficient: boolean
  }[]
  spellcasting_sources?: {
    source_id: number
    source_title: string
    ability_code: string | null
    attack_bonus: number | null
    save_dc: number | null
    spells: {
      id: number
      title: string
      grants?: {
        always_prepared: boolean
        count_against_limit: boolean
        cast_with_slots: boolean
        free_uses: number
        recovery: string
        cast_level: number | null
      }[]
    }[]
  }[]
  armor_class?: number | null
  speed_feet?: number | null
  initiative?: number | null
  spell_attack_bonus?: number | null
  spell_save_dc?: number | null
  languages?: string
  size?: string
  traits?: { source_id: number; source_title: string; kind: string; value: string }[]
  rule_profile_id: number
  species_entry_id: number | null
  class_entry_id: number | null
  background_entry_id: number | null
  target_level: number
  class_skill_revision: number
  class_skill_effective_entry_ids: number[]
  class_skills_complete: boolean
  baseline_complete: boolean
  ability_totals: Record<string, number | null>
  proficiency_bonus: number | null
  base_hit_point_max: number | null
  hit_point_bonus: number
  hit_point_max: number | null
  hit_die_sides: number | null
  base_speed_feet: number | null
  base_initiative: number | null
  unarmored_base_ac: number | null
  saving_throws: {
    code: string
    title: string
    ability_modifier: number | null
    class_proficient: boolean | null
    base_bonus: number | null
  }[]
  skills: {
    id: number
    title: string
    ability_code: string
    ability_title: string
    background_proficient: boolean
    class_proficient: boolean
    additional_proficient: boolean
    expertise: boolean
    base_bonus: number | null
  }[]
  issues: string[]
  limitations: string[]
}

export type InventoryItemInput = {
  id: number | null
  item_entry_id: number
  quantity: number
  is_equipped: boolean
  note: string
}
export type InventoryItemDraft = {
  id: number | null
  item_entry_id: number | null
  quantity: number | ''
  is_equipped: boolean
  note: string
}
export type InventoryItem = InventoryItemInput & {
  id: number
  source_kind: string
  source_title: string
  available: boolean
  title: string
  body: string | null
}
export type CharacterInventory = {
  rule_profile_id: number
  background_entry_id: number | null
  background_choice_revision: number
  revision: number
  item_options: { id: number; title: string }[]
  manual_items: InventoryItem[]
  manual_items_valid: boolean
  other_items: InventoryItem[]
  background: {
    complete: boolean
    items: { id: number; title: string; quantity: number }[]
    money_cp: number | null
    issues: string[]
  }
  max_items: number
  max_quantity: number
  max_note_length: number
}

export type CharacterContext = {
  subclass_entry_id: number | null
  species_variant_entry_id: number | null
  rule_profile_id: number | null
  target_level: number | ''
  species_entry_id: number | null
  class_entry_id: number | null
  background_entry_id: number | null
}
export type CharacterEditorDraft = CharacterContext & {
  name: string
  base_scores: Record<string, number | null>
  background_increases: Record<string, number>
  background_choices: BackgroundBenefitsDraft
  class_skill_entry_ids: number[]
  inventory_items: InventoryItemDraft[]
  starting_equipped_ids: number[]
  equipment_choices: Record<string, number>
  rule_choices: Record<string, string[]>
  creation_choices: Record<string, number[]>
  sheet_details: SheetDetailsDraft
}
export type CharacterEditorInput = Omit<
  CharacterEditorDraft,
  'rule_profile_id' | 'target_level' | 'inventory_items' | 'sheet_details'
> & {
  revision: number
  rule_profile_id: number
  target_level: number
  inventory_items: InventoryItemInput[]
  sheet_details: SheetDetails
  finish: boolean
}
export type StartingPackages = {
  groups: {
    source_id: number
    source_title: string
    selected_id: number | null
    options: {
      id: number
      title: string
      money_cp: number
      available: boolean
      items: { item_entry_id: number; title: string; quantity: number }[]
    }[]
  }[]
  selections: Record<string, number>
  items: { item_entry_id: number; title: string; quantity: number; source_title: string }[]
  money_cp: number
  complete: boolean
  issues: string[]
}
export type CharacterEditorOptions = {
  starting_packages: StartingPackages
  context: CharacterContext
  entry_options: Record<
    'species' | 'class' | 'background' | 'subclass' | 'species_variant',
    { id: number; title: string }[]
  >
  ability_scores: CharacterAbilityScores
  background_abilities: CharacterBackgroundAbilities
  benefits: CharacterBackgroundBenefits
  class_skills: CharacterClassSkills
  inventory: CharacterInventory
  creation: CreationBenefits
  reference: { species_speed_feet: number | null; hit_die_sides: number | null }
  profile_title: string | null
  origins: Record<
    'species_entry_id' | 'class_entry_id' | 'background_entry_id',
    {
      id: number
      title: string
      body: string | null
      species_card?: SpeciesCard | null
      class_card?: ClassCard | null
      class_details?: ClassDetails | null
    } | null
  >
}
export type CharacterEditorState = CharacterEditorOptions & {
  character: CharacterContext & {
    id: number
    user_id: number
    name: string
    status: 'draft' | 'ready'
    revision: number
    finished_at: string | null
  }
  sheet_basics: CharacterSheetBasics
  sheet_details: SheetDetails
  readiness: { complete: boolean; issues: string[] }
}

export type SheetDetails = {
  armor_class: number | null
  speed_feet: number | null
  initiative: number | null
  spell_attack_bonus: number | null
  spell_save_dc: number | null
  size: string
  languages: string
  appearance: string
  biography: string
  resources: string
  notes: string
}
export type SheetDetailsDraft = Omit<
  SheetDetails,
  'armor_class' | 'speed_feet' | 'initiative' | 'spell_attack_bonus' | 'spell_save_dc'
> & {
  armor_class: number | null | ''
  speed_feet: number | null | ''
  initiative: number | null | ''
  spell_attack_bonus: number | null | ''
  spell_save_dc: number | null | ''
}
export type CreationBenefits = {
  rule_selections: Record<string, string[]>
  scalar_groups: {
    key: string
    source_title: string
    title: string
    kind: string
    count: number
    options: { value: string; title: string }[]
    selected_values: string[]
  }[]
  resources: {
    source_id: number
    source_title: string
    title: string
    maximum: number
    recovery: string
  }[]
  ability_caps?: Record<string, number>

  bonuses?: Record<string, number>
  traits?: { source_id: number; source_title: string; kind: string; value: string }[]
  spellcasting?: {
    configured: boolean
    enabled: boolean
    ability_code: string | null
    slot_count: number
    slot_level?: number
    issues: string[]
  }
  casters?: {
    source_id: number
    source_title: string
    enabled: boolean
    kind?: string
    slots?: { spell_level: number; count: number }[]
    recovery?: string
  }[]
  hit_point_bonus: number
  configured: boolean
  complete: boolean
  selections: Record<string, number[]>
  groups: {
    key: string
    source_title: string
    title: string
    count: number
    mode: string
    options: {
      id: number
      type: string
      title: string
      body?: string | null
      spell?: SpellDefinition | null
    }[]
    selected_entry_ids: number[]
  }[]
  entries: {
    id: number
    type: string
    title: string
    body: string | null
    spell?: SpellDefinition | null
  }[]
  skill_entry_ids: number[]
  proficient_skill_entry_ids: number[]
  expertise_skill_entry_ids: number[]
  validation_errors: string[]
  issues: string[]
}
