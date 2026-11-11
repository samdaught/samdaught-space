<script setup lang="ts">
import { computed, ref } from 'vue'
import type { CreationRuleDraft, CreationRuleOptions } from '../api/creationRules'

const props = defineProps<{ options: CreationRuleOptions }>()
const choice = defineModel<CreationRuleDraft['choices'][number]>({ required: true })
const search = ref('')
const types: Record<string, string> = {
  skill: 'Навыки',
  feat: 'Черты',
  spell: 'Заклинания',
  feature: 'Особенности',
  item: 'Предметы и инструменты',
  species_variant: 'Варианты расы',
}
const selectedTypes = new Set(
  props.options.entries
    .filter((entry) => choice.value.entry_ids.includes(entry.id))
    .map((entry) => entry.type),
)
const category = ref(selectedTypes.size === 1 ? [...selectedTypes][0] : '')
const visible = computed(() =>
  props.options.entries.filter(
    (entry) =>
      (!category.value || entry.type === category.value) &&
      (choice.value.mode !== 'expertise' || entry.type === 'skill') &&
      entry.title.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()),
  ),
)
const missing = computed(() =>
  choice.value.entry_ids.filter((id) => !props.options.entries.some((entry) => entry.id === id)),
)
</script>

<template>
  <div class="species-choice-fields">
    <div class="choice-heading">
      <label
        >Подпись для игрока<input
          v-model="choice.title"
          required
          maxlength="120"
          placeholder="Например, Умелый — выберите навык"
      /></label>
      <label
        >Сколько выбрать<input
          v-model.number="choice.count"
          type="number"
          min="1"
          :max="options.max_rows"
          required
      /></label>
    </div>
    <div class="choice-heading">
      <label
        >Показать категорию<select v-model="category">
          <option value="">Все категории</option>
          <option v-for="(label, type) in types" :key="type" :value="type">{{ label }}</option>
        </select></label
      >
      <label>Поиск вариантов<input v-model="search" type="search" placeholder="Название" /></label>
    </div>
    <fieldset class="choice-options">
      <legend>Доступные варианты · отмечено {{ choice.entry_ids.length }}</legend>
      <label v-for="entry in visible" :key="entry.id"
        ><input v-model="choice.entry_ids" type="checkbox" :value="entry.id" />{{
          entry.title
        }}</label
      >
      <label v-for="id in missing" :key="id"
        ><input v-model="choice.entry_ids" type="checkbox" :value="id" />Недоступная запись №{{
          id
        }}
        — уберите её или восстановите доступ</label
      >
      <p v-if="!visible.length" class="field-hint">
        Нет подходящих вариантов. Добавьте и опубликуйте их в архиве или измените поиск.
      </p>
    </fieldset>
    <p v-if="Number(choice.count) > choice.entry_ids.length" class="field-error" role="status">
      Отметьте не меньше {{ choice.count }} вариантов.
    </p>
    <label v-if="category === 'skill' || choice.mode === 'expertise'" class="expertise"
      ><input
        type="checkbox"
        :checked="choice.mode === 'expertise'"
        @change="choice.mode = choice.mode === 'expertise' ? 'grant' : 'expertise'"
      />Даёт экспертизу в уже освоенном навыке</label
    >
  </div>
</template>

<style scoped>
.species-choice-fields {
  display: grid;
  gap: 0.8rem;
}
.choice-heading {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  gap: 0.8rem;
}
label {
  display: grid;
  gap: 0.4rem;
  min-width: 0;
}
.choice-options {
  max-height: 16rem;
  overflow-y: auto;
  margin: 0;
  padding: 0.8rem;
}
.choice-options label,
.expertise {
  display: flex;
  align-items: start;
  gap: 0.6rem;
  font-weight: 400;
  padding: 0.5rem 0;
}
input[type='checkbox'] {
  flex: none;
  margin-top: 0.2rem;
}
@media (max-width: 600px) {
  .choice-heading {
    grid-template-columns: 1fr;
  }
}
</style>
