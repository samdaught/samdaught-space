<script setup lang="ts">
import { computed } from 'vue'
import type { CreationBenefits } from '../types/characterEditor'
const props = withDefaults(
  defineProps<{
    data: CreationBenefits
    section?: 'all' | 'features' | 'skills' | 'spells' | 'tools'
    sourceId?: number | null
    excludeSourceIds?: number[]
    showEntries?: boolean
    showDiagnostics?: boolean
  }>(),
  { section: 'all', showEntries: true, showDiagnostics: true },
)
function category(types: string[]): string {
  if (types.length && types.every((type) => type === 'spell')) return 'spells'
  if (types.length && types.every((type) => type === 'skill')) return 'skills'
  if (
    types.length &&
    types.every((type) => type === 'item' || type === 'language' || type === 'tool')
  )
    return 'tools'
  return 'features'
}
const groups = computed(() =>
  props.data.groups.filter(
    (group) =>
      (props.section === 'all' ||
        category(group.options.map((entry) => entry.type)) === props.section) &&
      (props.sourceId === undefined || parseInt(group.key, 10) === props.sourceId) &&
      !props.excludeSourceIds?.includes(parseInt(group.key, 10)),
  ),
)
const entries = computed(() =>
  props.data.entries.filter(
    (entry) =>
      props.showEntries && (props.section === 'all' || category([entry.type]) === props.section),
  ),
)
const selections = defineModel<Record<string, number[]>>({ required: true })
function entryTypeTitle(type: string): string {
  const titles: Record<string, string> = {
    feat: 'черта',
    feature: 'особенность',
    spell: 'заклинание',
    skill: 'навык',
    item: 'владение инструментом',
    language: 'язык',
    tool: 'инструмент',
    species: 'вид',
    class: 'класс',
    background: 'предыстория',
  }
  return titles[type] ?? 'особенность'
}
</script>
<template>
  <p v-if="showDiagnostics && (section === 'all' || section === 'features')">
    Выдачи берутся из выбранных вида, класса, предыстории и черт. Выбранная особенность может
    открыть дополнительные группы.
  </p>
  <fieldset v-for="group in groups" :key="group.key">
    <legend>{{ group.source_title }} — {{ group.title }}</legend>
    <p>Выберите {{ group.count }}. Отмечено: {{ selections[group.key]?.length ?? 0 }}.</p>
    <p v-if="group.mode === 'expertise'">
      Для экспертизы нужен навык, которым персонаж уже владеет.
    </p>
    <label v-for="entry in group.options" :key="entry.id">
      <input
        v-model="selections[group.key]"
        type="checkbox"
        :value="entry.id"
        :disabled="
          group.mode === 'expertise' &&
          !data.proficient_skill_entry_ids.includes(entry.id) &&
          !selections[group.key]?.includes(entry.id)
        "
      />{{ entry.title }} ({{ entryTypeTitle(entry.type) }})
    </label>
    <label
      v-for="id in (selections[group.key] ?? []).filter(
        (id) => !group.options.some((entry) => entry.id === id),
      )"
      :key="id"
      ><input v-model="selections[group.key]" type="checkbox" :value="id" />Недоступный вариант №{{
        id
      }}
      — снимите отметку</label
    >
  </fieldset>
  <p v-if="!groups.length && !entries.length && section !== 'all'">
    Дополнительных выборов пока нет. Они появятся, если их дают выбранные вид, класс или черта.
  </p>
  <div
    v-for="key in Object.keys(selections).filter(
      (key) => selections[key]?.length && !data.groups.some((group) => group.key === key),
    )"
    v-show="showDiagnostics && (section === 'all' || section === 'features')"
    :key="key"
  >
    <p>Группа {{ key }} больше не действует.</p>
    <button type="button" @click="delete selections[key]">Убрать устаревший выбор</button>
  </div>
  <ul v-if="showDiagnostics && data.issues.length && (section === 'all' || section === 'features')">
    <li v-for="issue in data.issues" :key="issue">{{ issue }}</li>
  </ul>
  <h3 v-if="entries.length">Полученные особенности и владения</h3>
  <details v-for="entry in entries" :key="entry.id">
    <summary>{{ entry.title }} ({{ entryTypeTitle(entry.type) }})</summary>
    <p v-if="entry.spell">
      {{ entry.spell.level === 0 ? 'Заговор' : 'Круг ' + entry.spell.level }} ·
      {{ entry.spell.school }}<br />Время: {{ entry.spell.casting_time || '—' }} · Дистанция:
      {{ entry.spell.range_text || '—' }}<br />Компоненты: {{ entry.spell.components || '—' }} ·
      Длительность: {{ entry.spell.duration || '—'
      }}<span v-if="entry.spell.concentration"> · Концентрация</span
      ><span v-if="entry.spell.ritual"> · Ритуал</span>
    </p>
    <p>{{ entry.body }}</p>
  </details>
</template>
<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
fieldset {
  margin-block: 0.75rem;
}
p {
  white-space: pre-wrap;
}
</style>
