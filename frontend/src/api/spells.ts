export type SpellDefinition = {
  level: number
  school: string
  casting_time: string
  range_text: string
  components: string
  duration: string
  concentration: boolean
  ritual: boolean
}

export function emptySpell(): SpellDefinition {
  return {
    level: 0,
    school: '',
    casting_time: '',
    range_text: '',
    components: '',
    duration: '',
    concentration: false,
    ritual: false,
  }
}

export function spellLevelTitle(level: number): string {
  return level === 0 ? 'Заговор' : `${level}-й уровень`
}
