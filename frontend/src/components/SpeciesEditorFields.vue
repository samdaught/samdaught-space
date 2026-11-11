<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import type { SpeciesCard } from '../api/speciesCard'
import type { BasicRuleDraft, BasicRuleOptions } from '../api/basicRules'
import type { CreationRuleDraft, CreationRuleOptions } from '../api/creationRules'
import type { EntryMechanics, MechanicsOptions } from '../api/entryMechanics'
import {
  rulebookSchema,
  type RulebookData,
  type RulebookOptions,
  type RuleValue,
} from '../api/rulebook'
import SpeciesCardContent from './SpeciesCardContent.vue'
import SpeciesChoiceFields from './SpeciesChoiceFields.vue'
import CreationRuleFields from './CreationRuleFields.vue'
import RuleField from './RuleField.vue'
import EntryMechanicsFields from './EntryMechanicsFields.vue'

const props = defineProps<{
  title: string
  published?: boolean
  basicOptions: BasicRuleOptions
  creationOptions: CreationRuleOptions
  mechanicsOptions: MechanicsOptions
  rulebookOptions: RulebookOptions
}>()
const card = defineModel<SpeciesCard>({ required: true })
const body = defineModel<string>('body', { required: true })
const basic = defineModel<BasicRuleDraft>('basic', { required: true })
const creation = defineModel<CreationRuleDraft>('creation', { required: true })
const mechanics = defineModel<EntryMechanics>('mechanics', { required: true })
const rulebook = defineModel<RulebookData>('rulebook', { required: true })
const preview = ref(false)
const advanced = ref(false)
let revealingInvalid = false
async function revealInvalid(event: Event): Promise<void> {
  if (!preview.value && !revealingInvalid) return
  event.preventDefault()
  if (revealingInvalid) return
  revealingInvalid = true
  preview.value = false
  await nextTick()
  revealingInvalid = false
  const field = event.target
  if (
    field instanceof HTMLInputElement ||
    field instanceof HTMLTextAreaElement ||
    field instanceof HTMLSelectElement
  ) {
    field.focus()
    field.reportValidity()
  }
}
const sizes = computed(() =>
  props.rulebookOptions.vocabulary.filter((value) => value.kind === 'size'),
)
type SizeChoice = { code: string; title: string; kind: string; count: number; values: string[] }
const sizeChoice = computed(() =>
  (rulebook.value.choices as SizeChoice[]).find((choice) => choice.kind === 'size'),
)
const size = computed({
  get: () =>
    sizeChoice.value
      ? 'choice'
      : (mechanics.value.traits.find((trait) => trait.kind === 'size')?.value ?? ''),
  set: (value: string) => {
    mechanics.value.traits = mechanics.value.traits.filter((trait) => trait.kind !== 'size')
    rulebook.value.choices = (rulebook.value.choices as SizeChoice[]).filter(
      (choice) => choice.kind !== 'size',
    ) as RuleValue
    if (value === 'choice') {
      const choices = rulebook.value.choices as SizeChoice[]
      choices.push({
        code: uniqueCode(choices, 'size'),
        title: 'Размер существа',
        kind: 'size',
        count: 1,
        values: [],
      })
    } else if (value) mechanics.value.traits.push({ kind: 'size', value })
  },
})
function uniqueCode(rows: { code: string }[], prefix: string): string {
  let number = 1
  while (rows.some((row) => row.code === `${prefix}_${number}`)) number++
  return `${prefix}_${number}`
}
function addChoice(type: string, title: string): void {
  creation.value.choices.push({
    code: uniqueCode(creation.value.choices, 'species_choice'),
    title,
    count: 1,
    mode: 'grant',
    entry_ids: props.creationOptions.entries
      .filter((entry) => entry.type === type)
      .map((entry) => entry.id),
  })
}
const advancedSchema = computed(() => {
  const schema = rulebookSchema('species')
  const omitted = [
    'subclass_level',
    'feature_kind',
    'repeatable',
    'distinct_choices',
    'casting_ability_code',
    'spell_list_class_ids',
  ]
  return {
    ...schema,
    title: 'Дополнительные правила расы',
    fields: Object.fromEntries(
      Object.entries(schema.fields ?? {}).filter(([key]) => !omitted.includes(key)),
    ),
  }
})
</script>

<template>
  <section class="species-editor" aria-label="Карточка расы" @invalid.capture="revealInvalid">
    <header class="species-editor-header">
      <div>
        <h2>Карточка расы</h2>
        <p>Описание, особенности и выборы, которые увидит игрок.</p>
      </div>
      <button type="button" :aria-pressed="preview" @click="preview = !preview">
        {{ preview ? 'Вернуться к заполнению' : 'Посмотреть карточку' }}
      </button>
    </header>

    <article v-if="preview" class="species-preview" aria-label="Предпросмотр карточки расы">
      <span class="eyebrow">Предпросмотр</span>
      <h3>{{ title || 'Название расы' }}</h3>
      <SpeciesCardContent :card="card" :body="body" :title="title" />
      <div v-if="size === 'choice' || creation.choices.length" class="preview-choices">
        <p class="field-hint">Здесь игрок сделает выбор при создании персонажа.</p>
        <label v-if="sizeChoice"
          >{{ sizeChoice.title
          }}<select disabled>
            <option>Не выбрано</option>
            <option v-for="value in sizeChoice.values" :key="value">
              {{ sizes.find((size) => size.code === value)?.title ?? value }}
            </option>
          </select></label
        >
        <label v-for="choice in creation.choices" :key="choice.code"
          >{{ choice.title
          }}<select disabled>
            <option>Не выбрано</option>
            <option v-for="id in choice.entry_ids" :key="id">
              {{
                creationOptions.entries.find((entry) => entry.id === id)?.title ?? `Запись №${id}`
              }}
            </option>
          </select></label
        >
      </div>
      <p v-if="!body && !card.features.length" class="field-hint">
        Добавьте описание и особенности — они появятся здесь.
      </p>
    </article>

    <div v-show="!preview" class="species-edit-fields">
      <section class="species-section">
        <h3>Описание и внешность</h3>
        <label
          >Краткое описание<textarea
            v-model="body"
            :required="published"
            rows="4"
            placeholder="Кто они, как выглядят и чем отличаются от других рас"
          ></textarea>
        </label>
        <div class="species-field-row">
          <label
            >Возраст<input
              v-model="card.age"
              maxlength="255"
              placeholder="Взрослеют к 20 годам, живут около 100 лет"
          /></label>
          <label
            >Рост<input v-model="card.height" maxlength="255" placeholder="Обычно 150–190 см"
          /></label>
        </div>
        <div v-for="(portrait, index) in card.portraits" :key="index" class="species-portrait-row">
          <label
            >Ссылка на иллюстрацию {{ index + 1
            }}<input
              v-model="portrait.url"
              maxlength="2048"
              required
              placeholder="https://… или /images/…"
          /></label>
          <label
            >Описание изображения<input
              v-model="portrait.alt"
              maxlength="255"
              placeholder="Для пользователей, которые не видят изображение"
          /></label>
          <button type="button" @click="card.portraits.splice(index, 1)">Убрать иллюстрацию</button>
        </div>
        <button
          v-if="card.portraits.length < 2"
          type="button"
          @click="card.portraits.push({ url: '', alt: '' })"
        >
          Добавить иллюстрацию
        </button>
      </section>

      <section class="species-section">
        <h3>Основные свойства</h3>
        <div class="species-field-row">
          <label
            >Скорость ходьбы, футов<input
              v-model.number="basic.species_speed_feet"
              type="number"
              min="0"
              :max="basicOptions.max_speed_feet"
              step="1"
              required
          /></label>
          <label
            >Размер существа<select v-model="size">
              <option value="">Не указан</option>
              <option v-for="option in sizes" :key="option.code" :value="option.code">
                {{ option.title }}
              </option>
              <option
                v-if="size && size !== 'choice' && !sizes.some((option) => option.code === size)"
                :value="size"
              >
                {{ size }}
              </option>
              <option value="choice">Игрок выбирает размер</option>
            </select></label
          >
        </div>
        <fieldset v-if="sizeChoice" class="size-options">
          <legend>Какие размеры доступны</legend>
          <label v-for="option in sizes" :key="option.code"
            ><input
              v-model="sizeChoice.values"
              type="checkbox"
              :value="option.code"
              :required="sizeChoice.values.length === 0"
            />{{ option.title }}</label
          >
          <p v-if="!sizeChoice.values.length" class="field-error">Выберите хотя бы один размер.</p>
        </fieldset>
      </section>

      <section class="species-section">
        <h3>Особенности</h3>
        <p class="field-hint">
          Каждая особенность станет отдельной карточкой: например, «Тёмное зрение» или «Оружие
          дыхания».
        </p>
        <p v-if="!card.features.length" class="species-empty">Добавьте первую особенность расы.</p>
        <fieldset
          v-for="(feature, index) in card.features"
          :key="index"
          class="species-feature-editor"
        >
          <legend>{{ feature.title || `Особенность ${index + 1}` }}</legend>
          <label
            >Название<input
              v-model="feature.title"
              required
              maxlength="255"
              placeholder="Тёмное зрение"
          /></label>
          <label
            >Что умеет персонаж<textarea
              v-model="feature.description"
              required
              maxlength="10000"
              rows="3"
              placeholder="Ты видишь в темноте на расстоянии до 60 футов."
            ></textarea>
          </label>
          <button type="button" @click="card.features.splice(index, 1)">Удалить особенность</button>
        </fieldset>
        <button
          type="button"
          :disabled="card.features.length >= 50"
          @click="card.features.push({ title: '', description: '' })"
        >
          Добавить особенность
        </button>
        <p class="field-hint">
          Текст объясняет способность. Выбор навыка или черты добавьте ниже; автоматические бонусы и
          другие эффекты — в дополнительных настройках.
        </p>
      </section>

      <section class="species-section">
        <h3>Как отыгрывать</h3>
        <label
          >Советы и идеи<textarea
            v-model="card.roleplay"
            maxlength="10000"
            rows="3"
            placeholder="Отношение к миру, традиции, идеи для характера персонажа"
          ></textarea>
        </label>
      </section>

      <section class="species-section">
        <h3>Что выбирает игрок</h3>
        <p v-if="!creation.choices.length" class="species-empty">
          Если раса даёт навык, черту или другую способность на выбор, добавьте её здесь.
        </p>
        <fieldset
          v-for="(choice, index) in creation.choices"
          :key="choice.code"
          class="species-feature-editor"
        >
          <legend>{{ choice.title || `Выбор ${index + 1}` }}</legend>
          <SpeciesChoiceFields v-model="creation.choices[index]!" :options="creationOptions" />
          <button type="button" @click="creation.choices.splice(index, 1)">Удалить выбор</button>
        </fieldset>
        <div class="species-actions">
          <button
            type="button"
            :disabled="creation.choices.length >= creationOptions.max_rows"
            @click="addChoice('skill', 'Выберите навык')"
          >
            Добавить выбор навыка
          </button>
          <button
            type="button"
            :disabled="creation.choices.length >= creationOptions.max_rows"
            @click="addChoice('feat', 'Выберите черту')"
          >
            Добавить выбор черты
          </button>
          <button
            type="button"
            :disabled="creation.choices.length >= creationOptions.max_rows"
            @click="addChoice('species_variant', 'Выберите вариант происхождения')"
          >
            Другой выбор
          </button>
        </div>
      </section>
    </div>

    <details
      class="species-advanced"
      @toggle="advanced = ($event.target as HTMLDetailsElement).open"
    >
      <summary>Дополнительные настройки механики</summary>
      <p class="field-hint">
        Автоматические бонусы, языки, сопротивления, врождённая магия и способности по уровням.
      </p>
      <div v-if="advanced">
        <EntryMechanicsFields v-model="mechanics" category="species" :options="mechanicsOptions" />
        <CreationRuleFields v-model="creation" :options="creationOptions" />
        <RuleField v-model="rulebook" :schema="advancedSchema" :options="rulebookOptions" />
      </div>
    </details>
  </section>
</template>

<style scoped>
.species-editor {
  margin-block: 1rem;
  min-width: 0;
}
.species-editor-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  flex-wrap: wrap;
  gap: 0.8rem;
  border-bottom: 2px solid var(--line-strong);
  padding-bottom: 1.2rem;
}
.species-editor-header h2 {
  margin: 0;
  font-size: 1.65rem;
}
.species-editor-header p {
  margin: 0.4rem 0 0;
  color: var(--muted);
}
.species-editor-header button {
  margin: 0;
}
.species-section {
  padding-block: 1.3rem;
  border-bottom: 1px solid var(--line);
}
.species-section h3 {
  margin: 0 0 1rem;
  font-size: 1.3rem;
}
.species-edit-fields label,
.preview-choices label {
  display: grid;
  gap: 0.4rem;
  min-width: 0;
}
.species-edit-fields input:not([type='checkbox']),
.species-edit-fields textarea,
.species-edit-fields select {
  width: 100%;
}
.species-field-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}
.species-portrait-row,
.species-feature-editor {
  margin-block: 1rem;
  padding: 1rem;
  border: 1px solid var(--line-strong);
  border-radius: 10px;
  background: var(--paper);
  display: grid;
  gap: 0.7rem;
}
.species-empty {
  color: var(--muted);
  margin: 0 0 0.8rem;
}
.species-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.species-actions button {
  margin: 0;
}
.size-options {
  margin-top: 0.8rem;
}
.size-options label {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-right: 1rem;
  min-height: 44px;
}
.species-preview {
  padding: clamp(1rem, 3vw, 2rem);
  margin-block: 1.2rem;
  border: 1px solid var(--line-strong);
  border-radius: 12px;
  background: var(--paper);
}
.species-preview h3 {
  margin: 0.5rem 0 1rem;
  font-size: 1.7rem;
}
.preview-choices {
  padding: 1rem;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--canvas);
  display: grid;
  gap: 0.8rem;
  margin-top: 1rem;
}
.species-advanced {
  margin-top: 1.4rem;
  padding: 1rem;
  border: 1px solid var(--line);
  border-radius: 10px;
}
.species-advanced summary {
  min-height: 28px;
}
@media (max-width: 600px) {
  .species-field-row {
    grid-template-columns: 1fr;
  }
  .species-editor-header button {
    width: 100%;
  }
}
</style>
