<script setup lang="ts">
import type { CreationBenefits } from '../types/characterEditor'
defineProps<{ data: CreationBenefits }>()
const selections = defineModel<Record<string, number[]>>({ required: true })
</script>
<template>
  <p>
    Выдачи берутся из выбранных вида, класса, предыстории и черт. Выбранная особенность может
    открыть дополнительные группы.
  </p>
  <fieldset v-for="group in data.groups" :key="group.key">
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
      />{{ entry.title }} ({{ entry.type }})
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
  <div
    v-for="key in Object.keys(selections).filter(
      (key) => selections[key]?.length && !data.groups.some((group) => group.key === key),
    )"
    :key="key"
  >
    <p>Группа {{ key }} больше не действует.</p>
    <button type="button" @click="delete selections[key]">Убрать устаревший выбор</button>
  </div>
  <ul v-if="data.issues.length">
    <li v-for="issue in data.issues" :key="issue">{{ issue }}</li>
  </ul>
  <h3>Особенности, черты, заклинания и дополнительные владения для листа</h3>
  <details v-for="entry in data.entries" :key="entry.id">
    <summary>
      {{ entry.title }} ({{ entry.type === 'item' ? 'владение инструментом' : entry.type }})
    </summary>
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
