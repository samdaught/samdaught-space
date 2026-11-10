<script setup lang="ts">
import type { CreationRuleDraft, CreationRuleOptions } from '../api/creationRules'
const props = defineProps<{ options: CreationRuleOptions }>()
const fields = defineModel<CreationRuleDraft>({ required: true })
function addChoice(): void {
  let number = 1
  while (fields.value.choices.some((choice) => choice.code === 'choice_' + number)) number++
  fields.value.choices.push({
    code: 'choice_' + number,
    title: '',
    count: 1,
    mode: 'grant',
    entry_ids: [],
  })
}
</script>
<template>
  <fieldset>
    <legend>Выдачи при создании персонажа первого уровня</legend>
    <label
      >Дополнительные максимальные хиты на первом уровне<input
        v-model.number="fields.hit_point_bonus"
        type="number"
        min="-2147483648"
        max="2147483647"
        step="1"
    /></label>
    <p>
      Обычно 0. Укажите постоянный бонус этой записи. Кость хитов класса и модификатор Телосложения
      сервер прибавит отдельно; повторять их здесь не нужно.
    </p>
    <p>
      Фиксированные записи добавятся в лист автоматически. Для выбираемых записей задайте отдельные
      группы: например, заговоры и заклинания первого круга. Счётчиков боя и расходования ресурсов
      здесь нет.
    </p>
    <p>
      Запись инструмента здесь означает владение инструментом. Сам предмет добавляется отдельно в
      снаряжение.
    </p>
    <fieldset v-for="(grant, index) in fields.grants" :key="index">
      <legend>Фиксированная выдача {{ index + 1 }}</legend>
      <label
        >Режим<select v-model="grant.mode">
          <option v-for="mode in options.modes" :key="mode.code" :value="mode.code">
            {{ mode.title }}
          </option>
        </select></label
      >
      <label
        >Запись<select v-model="grant.entry_id" required>
          <option :value="null" disabled>Выберите запись</option>
          <option
            v-if="
              grant.entry_id !== null &&
              !options.entries.some((entry) => entry.id === grant.entry_id)
            "
            :value="grant.entry_id"
            disabled
          >
            Недоступная запись №{{ grant.entry_id }}
          </option>
          <option
            v-for="entry in options.entries.filter(
              (entry) => grant.mode !== 'expertise' || entry.type === 'skill',
            )"
            :key="entry.id"
            :value="entry.id"
          >
            {{ entry.title }} ({{ entry.type }})
          </option>
        </select></label
      >
      <button type="button" @click="fields.grants.splice(index, 1)">Удалить выдачу</button>
    </fieldset>
    <button
      type="button"
      :disabled="fields.grants.length >= options.max_rows"
      @click="fields.grants.push({ entry_id: null, mode: 'grant' })"
    >
      Добавить фиксированную выдачу
    </button>
    <fieldset v-for="(choice, index) in fields.choices" :key="index">
      <legend>Группа выбора {{ index + 1 }}</legend>
      <label
        >Постоянный код<input
          v-model="choice.code"
          pattern="[a-z][a-z0-9_]{0,31}"
          maxlength="32"
          required
      /></label>
      <p>
        Код связывает личные выборы с правилом. При изменении смысла группы используйте новый код.
      </p>
      <label>Название для игрока<input v-model="choice.title" maxlength="120" required /></label>
      <label
        >Сколько выбрать<input
          v-model.number="choice.count"
          type="number"
          min="1"
          :max="options.max_rows"
          step="1"
          required
      /></label>
      <label
        >Режим<select v-model="choice.mode">
          <option v-for="mode in options.modes" :key="mode.code" :value="mode.code">
            {{ mode.title }}
          </option>
        </select></label
      >
      <label
        v-for="entry in options.entries.filter(
          (entry) => choice.mode !== 'expertise' || entry.type === 'skill',
        )"
        :key="entry.id"
        ><input v-model="choice.entry_ids" type="checkbox" :value="entry.id" />{{ entry.title }} ({{
          entry.type
        }})</label
      >
      <label
        v-for="id in choice.entry_ids.filter(
          (id) => !options.entries.some((entry) => entry.id === id),
        )"
        :key="id"
        ><input v-model="choice.entry_ids" type="checkbox" :value="id" />Недоступный вариант №{{
          id
        }}
        — снимите отметку</label
      >
      <button type="button" @click="fields.choices.splice(index, 1)">Удалить группу</button>
    </fieldset>
    <button
      type="button"
      :disabled="fields.choices.length >= props.options.max_rows"
      @click="addChoice"
    >
      Добавить группу выбора
    </button>
    <p>
      Пустые списки означают, что дополнительных выдач и выборов нет. Особенность или черта может
      задавать собственные выдачи и группы.
    </p>
  </fieldset>
</template>
<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
fieldset {
  margin-block: 0.75rem;
}
</style>
