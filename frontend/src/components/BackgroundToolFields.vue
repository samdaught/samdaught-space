<script setup lang="ts">
import { computed } from 'vue'
import type { BackgroundAbilityOptions } from '../api/backgroundAbilityOptions'
import type { BackgroundToolDraft } from '../api/itemDefinitions'

defineProps<{ options: BackgroundAbilityOptions }>()
const target = defineModel<BackgroundToolDraft>({ required: true })
const mode = computed({
  get: () => (target.value.tool_group_code === null ? 'fixed' : 'group'),
  set: (value: string) => {
    target.value = { tool_entry_id: null, tool_group_code: value === 'group' ? '' : null }
  },
})
</script>

<template>
  <fieldset>
    <legend>Владение инструментом от предыстории</legend>
    <label>
      Как определяется инструмент
      <select v-model="mode">
        <option value="fixed">Конкретный инструмент</option>
        <option value="group">Один инструмент из группы на выбор игрока</option>
      </select>
    </label>
    <template v-if="mode === 'fixed'">
      <p v-if="options.rule_profile_id === null">
        Сначала выберите мир записи.
      </p>
      <p v-else-if="options.tool_options.length === 0">
        Нет доступных опубликованных инструментов. Сначала создайте предмет с видом «Инструмент» и
        опубликуйте его.
      </p>
      <label>
        Инструмент
        <select
          v-model="target.tool_entry_id"
          :disabled="options.rule_profile_id === null"
          required
        >
          <option disabled :value="null">Выберите инструмент</option>
          <option
            v-if="
              target.tool_entry_id !== null &&
              !options.tool_options.some((option) => option.id === target.tool_entry_id)
            "
            :value="target.tool_entry_id"
            disabled
          >
            Выбранный инструмент (ID {{ target.tool_entry_id }}) недоступен
          </option>
          <option v-for="tool in options.tool_options" :key="tool.id" :value="tool.id">
            {{ tool.title }}
          </option>
        </select>
      </label>
    </template>
    <template v-else>
      <label>
        Группа инструментов
        <select v-model="target.tool_group_code" required>
          <option disabled value="">Выберите группу</option>
          <option v-for="group in options.tool_groups" :key="group.code" :value="group.code">
            {{ group.title }}
          </option>
        </select>
      </label>
      <p>
        Предыстория даёт выбор одного инструмента из этой группы. Конкретный инструмент выбирается
        при создании персонажа.
      </p>
    </template>
    <p>Это правило владения. Сам предмет в инвентарь здесь не добавляется.</p>
  </fieldset>
</template>

<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
</style>
