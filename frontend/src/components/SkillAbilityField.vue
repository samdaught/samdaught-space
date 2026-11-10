<script setup lang="ts">
import type { AbilityDefinition } from '../api/abilityDefinitions'

defineProps<{ options: AbilityDefinition[] }>()
const code = defineModel<string>({ required: true })
</script>

<template>
  <fieldset>
    <legend>Свойства навыка</legend>
    <label>
      Характеристика по умолчанию
      <select v-model="code" required>
        <option disabled value="">Выберите характеристику</option>
        <option
          v-if="code !== '' && !options.some((option) => option.code === code)"
          :value="code"
          disabled
        >
          Сохранённая характеристика недоступна
        </option>
        <option v-for="ability in options" :key="ability.code" :value="ability.code">
          {{ ability.title }}
        </option>
      </select>
    </label>
    <p>Эта характеристика будет использоваться при обычном расчёте бонуса навыка у персонажа.</p>
  </fieldset>
</template>

<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
</style>
