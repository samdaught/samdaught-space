<script setup lang="ts">
import { computed, watch } from 'vue'
import type { EntryMechanics, MechanicsOptions } from '../api/entryMechanics'
import { emptySpell, spellLevelTitle } from '../api/spells'
import SpellCardContent from './SpellCardContent.vue'

const props = defineProps<{ title: string; published?: boolean; options: MechanicsOptions }>()
const fields = defineModel<EntryMechanics>({ required: true })
const body = defineModel<string>('body', { required: true })
watch(
  () => fields.value.spell,
  (spell) => {
    if (!spell) fields.value.spell = emptySpell()
  },
  { immediate: true },
)
const spell = computed(() => fields.value.spell!)
const classes = computed(() => props.options.entries.filter((entry) => entry.type === 'class'))
const missingClasses = computed(() =>
  fields.value.class_ids.filter((id) => !classes.value.some((entry) => entry.id === id)),
)
</script>

<template>
  <section class="spell-editor" aria-label="Карточка заклинания">
    <div class="spell-editor-layout">
      <div class="spell-fields">
        <h2>Параметры заклинания</h2>
        <div class="spell-field-row">
          <label
            >Уровень<select v-model="spell.level">
              <option v-for="level in 10" :key="level - 1" :value="level - 1">
                {{ spellLevelTitle(level - 1) }}
              </option>
            </select></label
          >
          <label
            >Школа<input v-model="spell.school" maxlength="100" placeholder="Например, Воплощение"
          /></label>
        </div>
        <label
          >Время сотворения<input
            v-model="spell.casting_time"
            maxlength="255"
            placeholder="Например, Реакция: при получении урона"
        /></label>
        <div class="spell-field-row">
          <label
            >Дистанция<input
              v-model="spell.range_text"
              maxlength="255"
              placeholder="Например, 60 футов"
          /></label>
          <label
            >Длительность<input
              v-model="spell.duration"
              maxlength="255"
              placeholder="Например, Мгновенная"
          /></label>
        </div>
        <label
          >Компоненты<input
            v-model="spell.components"
            maxlength="1000"
            placeholder="Вербальный, Соматический; материалы, если нужны"
        /></label>
        <div class="spell-flags">
          <label><input v-model="spell.concentration" type="checkbox" />Концентрация</label
          ><label><input v-model="spell.ritual" type="checkbox" />Ритуал</label>
        </div>
        <label
          >Эффект<textarea
            v-model="body"
            :required="published"
            rows="9"
            placeholder="Что происходит при сотворении заклинания. Здесь же опишите применение ячеек более высокого уровня."
          ></textarea>
        </label>
      </div>
      <aside class="spell-preview" aria-label="Предпросмотр заклинания">
        <p class="eyebrow">Карточка для игрока</p>
        <h3>{{ title || 'Название заклинания' }}</h3>
        <SpellCardContent :spell="spell" :body="body" />
      </aside>
    </div>
    <details class="spell-availability">
      <summary>Кому доступно заклинание · {{ fields.class_ids.length }}</summary>
      <p class="field-hint">Отметьте классы, в списки которых входит это заклинание.</p>
      <div class="spell-class-options">
        <label v-for="entry in classes" :key="entry.id"
          ><input v-model="fields.class_ids" type="checkbox" :value="entry.id" />{{ entry.title
          }}{{ entry.is_published ? '' : ' (черновик)' }}</label
        >
        <label v-for="id in missingClasses" :key="id"
          ><input v-model="fields.class_ids" type="checkbox" :value="id" />Недоступный класс №{{
            id
          }}
          — снимите отметку или восстановите доступ</label
        >
      </div>
      <p v-if="!classes.length" class="field-hint">
        Классов пока нет. Добавьте класс в архив и заполните его базовые свойства.
      </p>
    </details>
    <details class="spell-availability">
      <summary>Источники и проверка</summary>
      <slot name="advanced" />
    </details>
  </section>
</template>

<style scoped>
.spell-editor {
  margin-block: 1rem;
  min-width: 0;
}
.spell-editor-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 1.5rem;
  align-items: start;
}
.spell-fields {
  display: grid;
  gap: 0.9rem;
  min-width: 0;
}
.spell-fields h2 {
  margin: 0 0 0.2rem;
  font-size: 1.5rem;
}
.spell-fields label {
  display: grid;
  gap: 0.35rem;
  min-width: 0;
}
.spell-fields input:not([type='checkbox']),
.spell-fields select,
.spell-fields textarea {
  width: 100%;
}
.spell-field-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
}
.spell-flags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1.5rem;
}
.spell-flags label,
.spell-class-options label {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-height: 44px;
}
.spell-preview {
  min-width: 0;
  border: 1px solid var(--line-strong);
  border-radius: 12px;
  background: var(--paper);
  padding: 1.2rem;
}
.spell-preview .eyebrow {
  margin-top: 0;
}
.spell-preview h3 {
  margin: 0 0 1rem;
  font: 700 1.25rem/1.4 var(--font-heading);
  overflow-wrap: anywhere;
}
.spell-availability {
  padding: 1rem;
  margin-top: 1rem;
  border: 1px solid var(--line);
  border-radius: 10px;
}
.spell-class-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
  gap: 0.2rem 1rem;
}
@media (max-width: 1000px) {
  .spell-editor-layout {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 500px) {
  .spell-field-row {
    grid-template-columns: 1fr;
  }
}
</style>
