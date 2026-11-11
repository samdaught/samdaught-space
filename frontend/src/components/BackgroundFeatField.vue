<script setup lang="ts">
import type { BackgroundAbilityOptions } from '../api/backgroundAbilityOptions'

defineProps<{ options: BackgroundAbilityOptions }>()
const id = defineModel<number | null>({ required: true })
</script>

<template>
  <fieldset :disabled="options.rule_profile_id === null">
    <legend>Черта от предыстории</legend>
    <p v-if="options.rule_profile_id === null">Сначала выберите мир записи.</p>
    <p v-else-if="options.feat_options.length === 0">
      В этом мире нет опубликованных черт категории «Происхождение». Сначала создайте и
      опубликуйте черту.
    </p>
    <label>
      Черта происхождения
      <select v-model="id" required>
        <option disabled :value="null">Выберите черту</option>
        <option
          v-if="id !== null && !options.feat_options.some((option) => option.id === id)"
          :value="id"
          disabled
        >
          Выбранная черта (ID {{ id }}) недоступна
        </option>
        <option v-for="feat in options.feat_options" :key="feat.id" :value="feat.id">
          {{ feat.title }}
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
