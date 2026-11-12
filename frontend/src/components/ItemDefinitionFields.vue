<script setup lang="ts">
import type { ItemDefinitionDraft, ItemDefinitionOptions } from '../api/itemDefinitions'

defineProps<{ options: ItemDefinitionOptions }>()
const fields = defineModel<ItemDefinitionDraft>({ required: true })
</script>

<template>
  <fieldset>
    <legend>Свойства предмета</legend>
    <label>
      Вид предмета
      <select v-model="fields.item_kind_code" required>
        <option disabled value="">Выберите вид предмета</option>
        <option v-for="kind in options.item_kinds" :key="kind.code" :value="kind.code">
          {{ kind.title }}
        </option>
      </select>
    </label>
    <label v-if="fields.item_kind_code === options.tool_kind_code">
      Группа инструментов
      <select v-model="fields.tool_group_code" required>
        <option disabled value="">Выберите группу</option>
        <option v-for="group in options.tool_groups" :key="group.code" :value="group.code">
          {{ group.title }}
        </option>
      </select>
    </label>
    <label v-if="fields.item_kind_code === options.tool_kind_code">
      Характеристика по умолчанию
      <select v-model="fields.tool_ability_code">
        <option value="">Не задана: зависит от конкретной проверки</option>
        <option
          v-for="ability in options.ability_options"
          :key="ability.code"
          :value="ability.code"
        >
          {{ ability.title }}
        </option>
      </select>
    </label>
    <p v-if="fields.item_kind_code === options.tool_kind_code" class="field-hint">
      У инструмента нет обязательной единственной характеристики. Указывайте значение только если мир
      явно установил его как удобный вариант по умолчанию.
    </p>
    <p v-if="['weapon', 'armor', 'shield'].includes(fields.item_kind_code)">
      После выбора вида заполните ниже боевые свойства: урон или КД, вес, стоимость и при необходимости
      магические свойства.
    </p>
    <p v-else-if="fields.item_kind_code !== ''">
      Ниже можно указать вес, стоимость и магические свойства предмета.
    </p>
  </fieldset>
</template>

<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}

.field-hint {
  margin: 0;
  color: var(--muted);
}
</style>
