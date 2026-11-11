<script setup lang="ts">
import type { CharacterInventory, InventoryItemDraft } from '../types/characterEditor'

const props = defineProps<{ data: CharacterInventory; backgroundStale: boolean }>()
const items = defineModel<InventoryItemDraft[]>({ required: true })
const startingEquippedIds = defineModel<number[]>('startingEquippedIds', { default: () => [] })

function addItem(): void {
  if (items.value.length < props.data.max_items) {
    items.value.push({ id: null, item_entry_id: null, quantity: 1, is_equipped: false, note: '' })
  }
}

function removeItem(index: number): void {
  items.value.splice(index, 1)
}

function formatMoney(cp: number): string {
  return `${Math.floor(cp / 100)} ЗМ ${cp % 100} ММ`
}
</script>

<template>
  <h3>От предыстории</h3>
  <p v-if="backgroundStale">
    Есть несохранённые изменения. Сохраните черновик, чтобы увидеть актуальный состав от
    предыстории.
  </p>
  <template v-else>
    <ul v-if="data.background.items.length">
      <li v-for="item in data.background.items" :key="item.id">
        {{ item.title }} × {{ item.quantity }}
      </li>
    </ul>
    <p v-if="data.background.money_cp !== null">
      Деньги от предыстории: {{ formatMoney(data.background.money_cp) }}
    </p>
    <p v-if="!data.background.complete">Снаряжение от предыстории ещё не определено полностью.</p>
    <ul v-if="data.background.issues.length">
      <li v-for="issue in data.background.issues" :key="issue">{{ issue }}</li>
    </ul>
  </template>
  <h3>Добавлено в черновике</h3>
  <p>
    Добавьте личные вещи. КД от настроенных доспехов и щита, а также постоянные бонусы экипировки
    рассчитываются автоматически после сохранения.
  </p>
  <p v-if="data.item_options.length === 0">
    В этом мире нет доступных опубликованных предметов.
  </p>
  <div v-for="(item, index) in items" :key="item.id ?? 'new-' + index" class="inventory-line">
    <label>
      Предмет {{ index + 1 }}
      <select v-model="item.item_entry_id" required>
        <option disabled :value="null">Выберите предмет</option>
        <option
          v-if="
            item.item_entry_id !== null &&
            !data.item_options.some((option) => option.id === item.item_entry_id)
          "
          :value="item.item_entry_id"
          disabled
        >
          Выбранный предмет (ID {{ item.item_entry_id }}) недоступен
        </option>
        <option v-for="option in data.item_options" :key="option.id" :value="option.id">
          {{ option.title }}
        </option>
      </select>
    </label>
    <label
      >Количество<input
        v-model.number="item.quantity"
        type="number"
        min="1"
        :max="data.max_quantity"
        step="1"
        required
    /></label>
    <label><input v-model="item.is_equipped" type="checkbox" />Экипирован</label>
    <label
      >Заметка<textarea v-model="item.note" :maxlength="data.max_note_length"></textarea>
    </label>
    <button type="button" @click="removeItem(index)">Удалить строку {{ index + 1 }}</button>
  </div>
  <button type="button" :disabled="items.length >= data.max_items" @click="addItem">
    Добавить предмет
  </button>
  <template v-if="data.other_items.length">
    <h3>Другие выдачи</h3>
    <ul>
      <li v-for="item in data.other_items" :key="item.id">
        {{ item.title }} × {{ item.quantity }} — {{ item.source_title
        }}{{ item.is_equipped ? ', экипирован' : '' }}
        <label v-if="item.source_kind === 'creation'"
          ><input
            v-model="startingEquippedIds"
            type="checkbox"
            :value="item.item_entry_id"
          />Экипировать полученный предмет</label
        >
        <p v-if="item.note">{{ item.note }}</p>
        <p v-if="!item.available">Запись предмета сейчас недоступна.</p>
      </li>
    </ul>
  </template>
</template>

<style scoped>
label,
.inventory-line {
  display: grid;
  gap: 0.5rem;
}
.inventory-line {
  border-top: 1px solid;
  padding: 0.5rem 0;
}
p {
  white-space: pre-wrap;
}
</style>
