<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import type { ClassCard } from '../api/classCard'
import type { BasicRuleDraft, BasicRuleOptions } from '../api/basicRules'
import type { CreationRuleDraft, CreationRuleOptions } from '../api/creationRules'
import type { EntryMechanics, MechanicsOptions } from '../api/entryMechanics'
import { rulebookSchema, type RulebookData, type RulebookOptions } from '../api/rulebook'
import ClassCardContent from './ClassCardContent.vue'
import CreationRuleFields from './CreationRuleFields.vue'
import EntryMechanicsFields from './EntryMechanicsFields.vue'
import RuleField from './RuleField.vue'

const props = defineProps<{
  title: string
  published?: boolean
  basicOptions: BasicRuleOptions
  creationOptions: CreationRuleOptions
  mechanicsOptions: MechanicsOptions
  rulebookOptions: RulebookOptions
}>()
const card = defineModel<ClassCard>({ required: true })
const body = defineModel<string>('body', { required: true })
const basic = defineModel<BasicRuleDraft>('basic', { required: true })
const creation = defineModel<CreationRuleDraft>('creation', { required: true })
const mechanics = defineModel<EntryMechanics>('mechanics', { required: true })
const rulebook = defineModel<RulebookData>('rulebook', { required: true })
const preview = ref(false)
const advanced = ref(false)
let revealingInvalid = false
const abilityTitles = computed(() => props.basicOptions.ability_options)
const training = computed({
  get: () => mechanics.value.traits.filter((trait) => ['armor', 'weapon'].includes(trait.kind)),
  set: (value) => {
    mechanics.value.traits = [
      ...mechanics.value.traits.filter((trait) => !['armor', 'weapon'].includes(trait.kind)),
      ...value,
    ]
  },
})
const armorOptions = computed(() =>
  (props.mechanicsOptions.vocabulary ?? []).filter((row) => row.kind === 'armor'),
)
const hasTraining = (kind: string, value: string) =>
  training.value.some((row) => row.kind === kind && row.value === value)
function toggleTraining(kind: string, value: string, checked: boolean): void {
  const rows = training.value.filter((row) => row.kind !== kind || row.value !== value)
  if (checked) rows.push({ kind, value })
  training.value = rows
}
function addFeature(): void {
  if (card.value.features.length < 100)
    card.value.features.push({ level: 1, title: '', description: '' })
}
async function revealInvalid(event: Event): Promise<void> {
  if (!preview.value || revealingInvalid) return
  event.preventDefault()
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
const advancedSchema = computed(() => {
  const schema = rulebookSchema('class')
  const omitted = ['subclass_level', 'feature_kind', 'repeatable', 'distinct_choices']
  return {
    ...schema,
    title: 'Дополнительные правила класса',
    fields: Object.fromEntries(
      Object.entries(schema.fields ?? {}).filter(([key]) => !omitted.includes(key)),
    ),
  }
})
</script>

<template>
  <section class="class-editor" aria-label="Карточка класса" @invalid.capture="revealInvalid">
    <header class="class-editor-header">
      <div>
        <h2>Карточка класса</h2>
        <p>Короткий редактор того, что увидит игрок.</p>
      </div>
      <button type="button" :aria-pressed="preview" @click="preview = !preview">
        {{ preview ? 'Вернуться к заполнению' : 'Посмотреть карточку' }}
      </button>
    </header>
    <article v-if="preview" class="class-preview" aria-label="Предпросмотр карточки класса">
      <span class="eyebrow">Предпросмотр</span>
      <h3>{{ title || 'Название класса' }}</h3>
      <ClassCardContent
        :card="card"
        :details="{
          hit_die_sides: basic.class_hit_die_sides,
          saving_throw_codes: basic.class_saving_throw_codes,
          skill_choice_count:
            basic.class_skill_choice_count === '' ? null : basic.class_skill_choice_count,
          skills: basic.class_skill_entry_ids.map(
            (id) =>
              basicOptions.skill_options.find((skill) => skill.id === id)?.title ?? `Навык №${id}`,
          ),
          training,
        }"
        :body="body"
        :title="title"
      />
    </article>
    <div v-show="!preview" class="class-edit-fields">
      <section class="class-section">
        <h3>Описание и иллюстрации</h3>
        <label
          >Краткое описание<textarea
            v-model="body"
            :required="published"
            rows="4"
            placeholder="Кто выбирает этот класс и как он действует в приключении"
          ></textarea>
        </label>
        <div v-for="(portrait, index) in card.portraits" :key="index" class="portrait-row">
          <label
            >Ссылка на иллюстрацию {{ index + 1
            }}<input
              v-model="portrait.url"
              required
              maxlength="2048"
              placeholder="https://… или /images/…"
          /></label>
          <label
            >Описание изображения<input
              v-model="portrait.alt"
              maxlength="255"
              placeholder="Для пользователей, которые не видят изображение"
          /></label>
          <button type="button" @click="card.portraits.splice(index, 1)">Убрать</button>
        </div>
        <button
          v-if="card.portraits.length < 2"
          type="button"
          @click="card.portraits.push({ url: '', alt: '' })"
        >
          Добавить иллюстрацию
        </button>
      </section>
      <section class="class-section">
        <h3>Основные свойства</h3>
        <fieldset class="ability-checks">
          <legend>Основные характеристики</legend>
          <label v-for="ability in abilityTitles" :key="ability.code"
            ><input v-model="card.primary_abilities" type="checkbox" :value="ability.code" />{{
              ability.title
            }}</label
          >
        </fieldset>
        <div class="class-grid">
          <label
            >Кость хитов<select v-model="basic.class_hit_die_sides" required>
              <option disabled :value="null">Выберите кость</option>
              <option v-for="die in basicOptions.hit_dice" :key="die.sides" :value="die.sides">
                {{ die.title }}
              </option>
            </select></label
          >
          <label v-for="index in basicOptions.required_saving_throw_count" :key="index"
            >Спасбросок {{ index
            }}<select v-model="basic.class_saving_throw_codes[index - 1]" required>
              <option disabled value="">Выберите характеристику</option>
              <option
                v-for="ability in abilityTitles"
                :key="ability.code"
                :value="ability.code"
                :disabled="
                  basic.class_saving_throw_codes.includes(ability.code) &&
                  basic.class_saving_throw_codes[index - 1] !== ability.code
                "
              >
                {{ ability.title }}
              </option>
            </select></label
          >
        </div>
      </section>
      <section class="class-section">
        <h3>Владения и навыки</h3>
        <fieldset class="ability-checks">
          <legend>Оружие</legend>
          <label
            ><input
              type="checkbox"
              :checked="hasTraining('weapon', 'simple')"
              @change="
                toggleTraining('weapon', 'simple', ($event.target as HTMLInputElement).checked)
              "
            />Простое оружие</label
          >
          <label
            ><input
              type="checkbox"
              :checked="hasTraining('weapon', 'martial')"
              @change="
                toggleTraining('weapon', 'martial', ($event.target as HTMLInputElement).checked)
              "
            />Воинское оружие</label
          >
        </fieldset>
        <fieldset v-if="armorOptions.length" class="ability-checks">
          <legend>Доспехи</legend>
          <label v-for="armor in armorOptions" :key="armor.code"
            ><input
              type="checkbox"
              :checked="hasTraining('armor', armor.code)"
              @change="
                toggleTraining('armor', armor.code, ($event.target as HTMLInputElement).checked)
              "
            />{{ armor.title }}</label
          >
        </fieldset>
        <label
          >Сколько навыков выбирает игрок<input
            v-model.number="basic.class_skill_choice_count"
            type="number"
            min="0"
            :max="basicOptions.max_class_skill_options"
            required
        /></label>
        <fieldset class="skill-options">
          <legend>Доступные навыки</legend>
          <p>Отметьте список, из которого игрок выберет навыки.</p>
          <label v-for="skill in basicOptions.skill_options" :key="skill.id"
            ><input v-model="basic.class_skill_entry_ids" type="checkbox" :value="skill.id" />{{
              skill.title
            }}</label
          >
          <label
            v-for="id in basic.class_skill_entry_ids.filter(
              (id) => !basicOptions.skill_options.some((skill) => skill.id === id),
            )"
            :key="id"
            ><input v-model="basic.class_skill_entry_ids" type="checkbox" :value="id" />Недоступный
            навык №{{ id }}</label
          >
        </fieldset>
      </section>
      <section class="class-section">
        <h3>Особенности класса</h3>
        <p class="field-hint">
          Это карточки для игрока. Механику выдач и эффектов при необходимости добавьте ниже.
        </p>
        <p v-if="!card.features.length" class="class-empty">
          Добавьте первую особенность, например «Ярость» на 1-м уровне.
        </p>
        <fieldset v-for="(feature, index) in card.features" :key="index" class="feature-editor">
          <legend>{{ feature.title || `Особенность ${index + 1}` }}</legend>
          <div class="class-grid">
            <label
              >Уровень<input
                v-model.number="feature.level"
                type="number"
                min="1"
                max="20"
                required /></label
            ><label
              >Название<input v-model="feature.title" required maxlength="255" placeholder="Ярость"
            /></label>
          </div>
          <label
            >Что умеет персонаж<textarea
              v-model="feature.description"
              required
              maxlength="10000"
              rows="3"
              placeholder="Объясните эффект простыми словами"
            ></textarea>
          </label>
          <button type="button" @click="card.features.splice(index, 1)">Удалить особенность</button>
        </fieldset>
        <button type="button" :disabled="card.features.length >= 100" @click="addFeature">
          Добавить особенность
        </button>
      </section>
      <section class="class-section">
        <h3>Путь класса</h3>
        <div class="class-grid">
          <label
            >Название выбора подкласса<input
              v-model="card.subclass_label"
              maxlength="255"
              placeholder="Путь варвара" /></label
          ><label
            >Подкласс доступен с уровня<input
              v-model.number="rulebook.subclass_level"
              type="number"
              min="1"
              max="20"
              required
          /></label>
        </div>
        <label
          >Как отыгрывать<textarea
            v-model="card.roleplay"
            maxlength="10000"
            rows="3"
            placeholder="Идеи для характера, мотивации и места в мире"
          ></textarea>
        </label>
      </section>
    </div>
    <details class="class-advanced" @toggle="advanced = ($event.target as HTMLDetailsElement).open">
      <summary>Дополнительные настройки механики</summary>
      <p class="field-hint">
        Заклинательства, стартовые комплекты, точные эффекты, выдачи по уровням и технические
        правила.
      </p>
      <div v-if="advanced">
        <EntryMechanicsFields
          v-model="mechanics"
          category="class"
          :options="mechanicsOptions"
        /><CreationRuleFields v-model="creation" :options="creationOptions" /><RuleField
          v-model="rulebook"
          :schema="advancedSchema"
          :options="rulebookOptions"
        />
      </div>
    </details>
  </section>
</template>

<style scoped>
.class-editor {
  margin-block: 1rem;
  min-width: 0;
}
.class-editor-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  flex-wrap: wrap;
  gap: 0.8rem;
  padding-bottom: 1.2rem;
  border-bottom: 2px solid var(--line-strong);
}
.class-editor-header h2,
.class-editor-header p {
  margin: 0;
}
.class-editor-header p,
.field-hint {
  color: var(--muted);
}
.class-section {
  padding-block: 1.3rem;
  border-bottom: 1px solid var(--line);
}
.class-section h3 {
  margin: 0 0 1rem;
  font-size: 1.3rem;
}
.class-edit-fields label {
  display: grid;
  gap: 0.4rem;
  min-width: 0;
}
.class-edit-fields input:not([type='checkbox']),
.class-edit-fields textarea,
.class-edit-fields select {
  width: 100%;
}
.class-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
  margin-block: 1rem;
}
.portrait-row,
.feature-editor {
  display: grid;
  gap: 0.7rem;
  margin-block: 1rem;
  padding: 1rem;
  border: 1px solid var(--line-strong);
  border-radius: 10px;
  background: var(--paper);
}
.ability-checks,
.skill-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem 1rem;
  margin: 1rem 0;
}
.ability-checks legend,
.skill-options legend {
  width: 100%;
  font-weight: 700;
}
.ability-checks label,
.skill-options label {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.skill-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}
.skill-options p {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--muted);
}
.class-empty {
  padding: 0.8rem;
  border: 1px dashed var(--line-strong);
  border-radius: 8px;
  color: var(--muted);
}
.class-advanced {
  margin-top: 1.2rem;
  padding: 1rem;
  border: 1px solid var(--line-strong);
  border-radius: 10px;
}
.class-advanced summary {
  cursor: pointer;
  font-weight: 700;
}
.class-preview {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px solid var(--line-strong);
  border-radius: 12px;
  background: var(--paper);
}
.class-preview h3 {
  margin: 0.2rem 0 1rem;
}
@media (max-width: 680px) {
  .class-grid {
    grid-template-columns: 1fr;
  }
  .portrait-row {
    grid-template-columns: 1fr;
  }
}
</style>
