<script setup lang="ts">
import type { SheetDetailsDraft } from '../types/characterEditor'
const details = defineModel<SheetDetailsDraft>({ required: true })
</script>
<template>
  <p>
    КД в снаряжении и дополнительные пометки заполняются вручную. Пустые скорость и инициатива
    используют базовые значения; их можно уточнить с учётом особенностей. Максимальные хиты
    рассчитает сервер при сохранении. Ведения боя и расходования ресурсов нет.
  </p>
  <label
    >КД с выбранным снаряжением<input
      v-model.number="details.armor_class"
      type="number"
      min="1"
      max="2147483647"
      step="1"
  /></label>
  <label>Размер<input v-model="details.size" maxlength="10000" /></label>
  <label
    >Скорость ходьбы, футов<input
      v-model.number="details.speed_feet"
      type="number"
      min="0"
      max="2147483647"
      step="1"
  /></label>
  <label
    >Инициатива с учётом выбранных особенностей<input
      v-model.number="details.initiative"
      type="number"
      min="-2147483648"
      max="2147483647"
      step="1"
  /></label>
  <label
    >Бонус атаки заклинанием, если нужен<input
      v-model.number="details.spell_attack_bonus"
      type="number"
      min="-2147483648"
      max="2147483647"
      step="1"
  /></label>
  <label
    >СЛ спасброска от заклинаний, если нужна<input
      v-model.number="details.spell_save_dc"
      type="number"
      min="1"
      max="2147483647"
      step="1"
  /></label>
  <label>Языки<textarea v-model="details.languages" maxlength="10000"></textarea></label>
  <label>Внешность<textarea v-model="details.appearance" maxlength="10000"></textarea></label>
  <label
    >История персонажа<textarea v-model="details.biography" maxlength="10000"></textarea>
  </label>
  <label
    >Ресурсы и пометки класса<textarea
      v-model="details.resources"
      maxlength="10000"
      placeholder="Например, количество использований способности по описанию в архиве"
    ></textarea>
  </label>
  <label>Другие заметки<textarea v-model="details.notes" maxlength="10000"></textarea></label>
</template>
<style scoped>
label {
  display: grid;
  gap: 0.5rem;
}
</style>
