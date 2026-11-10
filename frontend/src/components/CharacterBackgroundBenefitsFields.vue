<script setup lang="ts">
import type { BackgroundBenefitsDraft, CharacterBackgroundBenefits } from '../types/characterEditor'

defineProps<{ data: CharacterBackgroundBenefits; previewStale: boolean }>()
const choices = defineModel<BackgroundBenefitsDraft>({ required: true })

function formatMoney(cp: number): string {
  return `${Math.floor(cp / 100)} ЗМ ${cp % 100} ММ`
}
</script>

<template>
  <h3>Владения навыками</h3>
  <ul v-if="data.skills.length">
    <li v-for="skill in data.skills" :key="skill.id">
      <details>
        <summary>{{ skill.title }}</summary>
        <p>{{ skill.body }}</p>
      </details>
    </li>
  </ul>
  <p v-else>Доступные владения навыками от предыстории не определены.</p>

  <h3>Черта происхождения</h3>
  <details v-if="data.feat">
    <summary>{{ data.feat.title }}</summary>
    <p>{{ data.feat.body }}</p>
  </details>
  <p v-else>Доступная черта происхождения не определена.</p>
  <p>Эффекты черты пока учитываются вручную по её описанию.</p>

  <h3>Владение инструментом</h3>
  <label v-if="data.tool.requires_choice">
    Выберите один инструмент: {{ data.tool.group_title }}
    <select v-model="choices.tool_entry_id">
      <option :value="null">Пока не выбран</option>
      <option
        v-if="
          choices.tool_entry_id !== null &&
          !data.tool.options.some((tool) => tool.id === choices.tool_entry_id)
        "
        :value="choices.tool_entry_id"
        disabled
      >
        Выбранный инструмент (ID {{ choices.tool_entry_id }}) недоступен
      </option>
      <option v-for="tool in data.tool.options" :key="tool.id" :value="tool.id">
        {{ tool.title }}
      </option>
    </select>
  </label>
  <p v-if="data.tool.requires_choice && data.tool.options.length === 0">
    В разрешённой группе сейчас нет доступных инструментов.
  </p>
  <details v-if="data.tool.effective_tool">
    <summary>По сохранённым данным: {{ data.tool.effective_tool.title }}</summary>
    <p>{{ data.tool.effective_tool.body }}</p>
  </details>
  <p v-else-if="!data.tool.requires_choice">
    Фиксированный инструмент предыстории недоступен или не настроен.
  </p>

  <h3>Стартовое снаряжение</h3>
  <label>
    Вариант от предыстории
    <select v-model="choices.equipment_choice">
      <option :value="null">Пока не выбран</option>
      <option value="package" :disabled="!data.equipment.package_available">Комплект А</option>
      <option value="money" :disabled="!data.equipment.money_available">
        Деньги Б{{
          data.equipment.alternative_money_cp !== null
            ? ': ' + formatMoney(data.equipment.alternative_money_cp)
            : ''
        }}
      </option>
    </select>
  </label>
  <details v-if="data.equipment.package">
    <summary>Состав комплекта А</summary>
    <ul>
      <li v-for="item in data.equipment.package.items" :key="item.id">
        {{ item.title }} × {{ item.quantity }}
      </li>
    </ul>
    <p>Деньги: {{ formatMoney(data.equipment.package.money_cp) }}</p>
    <p v-if="data.equipment.package.include_background_tool">
      Включает один экземпляр допустимого инструмента по сохранённому выбору.
    </p>
    <p v-if="!data.equipment.package_available">Комплект сейчас недоступен.</p>
  </details>
  <p v-if="previewStale">Сохраните черновик, чтобы обновить результат выдач предыстории.</p>
  <template v-else-if="data.equipment.preview">
    <h4>Снаряжение от предыстории по сохранённым данным</h4>
    <ul>
      <li v-for="item in data.equipment.preview.items" :key="item.id">
        {{ item.title }} × {{ item.quantity }}
      </li>
    </ul>
    <p>Деньги: {{ formatMoney(data.equipment.preview.money_cp) }}</p>
  </template>
</template>

<style scoped>
label {
  display: grid;
  gap: 0.5rem;
}
p {
  white-space: pre-wrap;
}
</style>
