<script setup lang="ts">
import type { CharacterClassSkills } from '../types/characterEditor'

defineProps<{ data: CharacterClassSkills; backgroundSkillIds: number[] }>()
const selected = defineModel<number[]>({ required: true })
</script>

<template>
  <p v-if="data.choice_count !== null">
    Нужно выбрать навыков: {{ data.choice_count }}. Отмечено: {{ selected.length }}. Неполный выбор
    можно сохранить в черновике.
  </p>
  <label v-for="skill in data.options" :key="skill.id">
    <input v-model="selected" type="checkbox" :value="skill.id" />{{ skill.title }}
    <span v-if="backgroundSkillIds.includes(skill.id)">
      — уже даёт предыстория; повторное владение не увеличивает бонус</span
    >
  </label>
  <label
    v-for="id in selected.filter((id) => !data.options.some((option) => option.id === id))"
    :key="id"
  >
    <input v-model="selected" type="checkbox" :value="id" />Недоступный навык №{{ id }} — снимите
    отметку или восстановите доступность
  </label>
  <p>Бонус мастерства прибавляется один раз. Экспертиза здесь не назначается.</p>
</template>

<style scoped>
label {
  display: block;
  margin-block: 0.5rem;
}
</style>
