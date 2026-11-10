<script setup lang="ts">
import type { BackgroundAbilityOptions } from '../api/backgroundAbilityOptions'

defineProps<{ options: BackgroundAbilityOptions }>()
const codes = defineModel<string[]>({ required: true })
</script>

<template>
  <fieldset>
    <legend>Характеристики предыстории</legend>
    <p>Выберите три разные характеристики, которые эта предыстория разрешает увеличивать.</p>
    <label v-for="index in options.required_count" :key="index">
      Характеристика {{ index }}
      <select v-model="codes[index - 1]" required>
        <option disabled value="">Выберите характеристику</option>
        <option
          v-for="ability in options.ability_options"
          :key="ability.code"
          :value="ability.code"
          :disabled="codes.includes(ability.code) && codes[index - 1] !== ability.code"
        >
          {{ ability.title }}
        </option>
      </select>
    </label>
  </fieldset>
</template>

<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
</style>
