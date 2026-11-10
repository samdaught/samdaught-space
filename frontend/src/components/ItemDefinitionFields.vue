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
      <select v-model="fields.tool_ability_code" required>
        <option disabled value="">Выберите характеристику</option>
        <option
          v-for="ability in options.ability_options"
          :key="ability.code"
          :value="ability.code"
        >
          {{ ability.title }}
        </option>
      </select>
    </label>
    <p v-if="fields.item_kind_code !== '' && fields.item_kind_code !== options.tool_kind_code">
      Свойства этого предмета пока указываются в описании. Автоматические расчёты оружия, брони и
      расходников ещё не подключены.
    </p>
  </fieldset>
</template>

<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
</style>
