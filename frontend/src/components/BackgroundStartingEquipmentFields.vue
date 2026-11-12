<script setup lang="ts">
import type { BackgroundAbilityOptions } from '../api/backgroundAbilityOptions'
import type { StartingEquipmentDraft } from '../api/startingEquipment'

const props = defineProps<{ options: BackgroundAbilityOptions }>()
const equipment = defineModel<StartingEquipmentDraft>({ required: true })

function addItem(): void {
  if (equipment.value.items.length < props.options.equipment_max_items) {
    equipment.value.items.push({ item_entry_id: null, quantity: 1 })
  }
}

function removeItem(index: number): void {
  equipment.value.items.splice(index, 1)
}
</script>

<template>
  <fieldset>
    <legend>Стартовое снаряжение</legend>
    <p>Выберите представление, которое соответствует исходным данным. Неизвестный состав нельзя заменять вымышленными предметами.</p>
    <label>
      <input v-model="equipment.mode" type="radio" value="source" />
      Точный текст из источника
    </label>
    <label>
      <input v-model="equipment.mode" type="radio" value="structured" />
      Нормализованный комплект и денежная альтернатива
    </label>

    <label v-if="equipment.mode === 'source'">
      Точный текст стартового комплекта
      <textarea
        v-model="equipment.source_text"
        maxlength="10000"
        rows="5"
        required
      />
    </label>
    <p v-if="equipment.mode === 'source'" class="field-hint">
      Такой текст показывается в архиве, но не выдаётся автоматически листу персонажа до отдельной нормализации.
    </p>

    <template v-else>
      <p>Игрок выбирает комплект А или деньги Б. Суммы задаются в медных монетах: 100 ММ = 1 ЗМ.</p>
      <label>
        Деньги в комплекте А, ММ
        <input
          v-model.number="equipment.package_money_cp"
          type="number"
          min="0"
          :max="options.equipment_integer_max"
          step="1"
          required
        />
      </label>
      <label>
        <input v-model="equipment.include_background_tool" type="checkbox" />
        Включить один инструмент, выбранный для владения от этой предыстории
      </label>
      <p v-if="equipment.include_background_tool">
        Сохраняется ссылка на выбор инструмента персонажа. Дублировать его отдельной строкой не требуется.
      </p>
      <p v-if="options.rule_profile_id === null">
        Для выбора предметов укажите мир записи.
      </p>
      <p v-else-if="options.equipment_item_options.length === 0">
        Нет доступных опубликованных предметов. Их можно добавить в архиве отдельно.
      </p>
      <div v-for="(line, index) in equipment.items" :key="index" class="equipment-line">
        <label>
          Предмет {{ index + 1 }}
          <select
            v-model="line.item_entry_id"
            :disabled="options.rule_profile_id === null"
            required
          >
            <option disabled :value="null">Выберите предмет</option>
            <option
              v-if="
                line.item_entry_id !== null &&
                !options.equipment_item_options.some((item) => item.id === line.item_entry_id)
              "
              :value="line.item_entry_id"
              disabled
            >
              Выбранный предмет (ID {{ line.item_entry_id }}) недоступен
            </option>
            <option
              v-for="item in options.equipment_item_options"
              :key="item.id"
              :value="item.id"
              :disabled="
                equipment.items.some(
                  (other, otherIndex) => otherIndex !== index && other.item_entry_id === item.id,
                )
              "
            >
              {{ item.title }}
            </option>
          </select>
        </label>
        <label>
          Количество
          <input
            v-model.number="line.quantity"
            type="number"
            min="1"
            :max="options.equipment_integer_max"
            step="1"
            required
          />
        </label>
        <button type="button" @click="removeItem(index)">Удалить строку {{ index + 1 }}</button>
      </div>
      <button
        type="button"
        :disabled="
          equipment.items.length >= options.equipment_max_items || options.rule_profile_id === null
        "
        @click="addItem"
      >
        Добавить предмет в комплект А
      </button>
      <label>
        Денежная альтернатива Б, ММ
        <input
          v-model.number="equipment.alternative_money_cp"
          type="number"
          min="0"
          :max="options.equipment_integer_max"
          step="1"
          required
        />
      </label>
    </template>
  </fieldset>
</template>

<style scoped>
fieldset,
label,
.equipment-line {
  display: grid;
  gap: 0.5rem;
}
.equipment-line {
  border-top: 1px solid;
  padding-top: 0.5rem;
}
.field-hint {
  margin: 0;
  color: var(--muted);
}
</style>
