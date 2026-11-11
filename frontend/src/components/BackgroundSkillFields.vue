<script setup lang="ts">
import type { BackgroundAbilityOptions } from '../api/backgroundAbilityOptions'

defineProps<{ options: BackgroundAbilityOptions }>()
const ids = defineModel<Array<number | null>>({ required: true })
</script>

<template>
  <fieldset :disabled="options.rule_profile_id === null">
    <legend>Владения навыками от предыстории</legend>
    <p v-if="options.rule_profile_id === null">Сначала выберите мир записи.</p>
    <p v-else-if="options.skill_options.length === 0">
      В этом мире нет опубликованных навыков с настроенной характеристикой. Сначала создайте и
      опубликуйте записи навыков.
    </p>
    <p>Выберите разные навыки, владение которыми предусмотрено этой предысторией.</p>
    <label v-for="index in options.required_skill_count" :key="index">
      Навык {{ index }}
      <select v-model="ids[index - 1]" required>
        <option disabled :value="null">Выберите навык</option>
        <option
          v-if="
            ids[index - 1] != null &&
            !options.skill_options.some((option) => option.id === ids[index - 1])
          "
          :value="ids[index - 1]"
          disabled
        >
          Выбранный навык (ID {{ ids[index - 1] }}) недоступен
        </option>
        <option
          v-for="skill in options.skill_options"
          :key="skill.id"
          :value="skill.id"
          :disabled="ids.includes(skill.id) && ids[index - 1] !== skill.id"
        >
          {{ skill.title }}
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
