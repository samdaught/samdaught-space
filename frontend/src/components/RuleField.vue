<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  initial,
  ruleLabels,
  type Field,
  type RuleValue,
  type RulebookOptions,
} from '../api/rulebook'
const props = defineProps<{ schema: Field; options: RulebookOptions; disabled?: boolean }>()
const model = defineModel<RuleValue>({ required: true })
const search = ref('')
const object = computed(() => model.value as Record<string, RuleValue>)
const fields = computed(() => {
  const result = { ...props.schema.fields }
  if (result.values && object.value?.kind) {
    const kind = String(object.value.kind)
    const item: Field =
      kind === 'ability'
        ? {
            title: 'Характеристика',
            type: 'select',
            values: ['str', 'dex', 'con', 'int', 'wis', 'cha'],
          }
        : ['size', 'language', 'mastery'].includes(kind)
          ? { title: 'Вариант', type: 'select', vocabulary: kind }
          : {
              title: 'Запись',
              type: 'select',
              relation:
                kind === 'spell_list'
                  ? ['class']
                  : kind === 'weapon_mastery'
                    ? ['item']
                    : ['skill', 'item'],
              stringValue: true,
            }
    result.values = { title: 'Допустимые варианты', type: 'list', item }
  }
  if (result.value && result.kind && result.entry_id) {
    const kind = String(object.value?.kind)
    result.value = ['level', 'class_level', 'ability'].includes(kind)
      ? { title: 'Минимум', type: 'number' }
      : kind === 'spellcasting'
        ? { title: 'Доступ к заклинательству', type: 'boolean' }
        : { title: 'Значение', type: 'text' }
  }
  return result
})
watch(
  () =>
    props.schema.type === 'object' && props.schema.fields?.value
      ? String(object.value?.kind ?? '')
      : '',
  (kind) => {
    if (!kind) return
    if (['level', 'class_level', 'ability'].includes(kind))
      object.value.value = Number(object.value.value)
    else if (kind === 'spellcasting')
      object.value.value =
        object.value.value === true || object.value.value === 'true' || object.value.value === 1
  },
  { immediate: true },
)
const rows = computed(() => model.value as RuleValue[])
const selections = computed(() =>
  props.schema.relation
    ? props.schema.relation.includes('source')
      ? props.options.sources.map((v) => ({ value: v.id, title: v.title }))
      : props.options.entries
          .filter((v) => props.schema.relation!.includes(v.type))
          .map((v) => ({
            value: props.schema.stringValue ? String(v.id) : v.id,
            title: v.title + ' (#' + v.id + ')',
          }))
    : props.schema.vocabulary
      ? props.options.vocabulary
          .filter((v) => v.kind === props.schema.vocabulary)
          .map((v) => ({ value: v.code, title: v.title }))
      : (props.schema.values ?? []).map((v) => ({
          value: props.schema.title === 'Кость' ? Number(v) : v,
          title: ruleLabels[v] ?? v,
        })),
)
const visible = computed(() =>
  selections.value.filter(
    (v) =>
      v.title.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()) ||
      v.value === model.value,
  ),
)
const leaf: Field = {
  title: 'Требование',
  type: 'object',
  fields: {
    kind: {
      title: 'Вид',
      type: 'select',
      values: [
        'level',
        'class_level',
        'ability',
        'entry',
        'proficiency',
        'spellcasting',
        'armor',
        'choice',
      ],
    },
    code: { title: 'Код характеристики / владения / выбора', type: 'text' },
    entry_id: {
      title: 'Связанная запись',
      type: 'select',
      relation: [
        'class',
        'subclass',
        'species',
        'species_variant',
        'feat',
        'feature',
        'skill',
        'spell',
        'item',
      ],
      nullable: true,
    },
    value: { title: 'Минимум или значение', type: 'text' },
  },
}
function valueInput(event: Event) {
  const raw = (event.target as HTMLInputElement).value
  model.value =
    props.schema.type === 'number'
      ? raw === '' && props.schema.nullable
        ? null
        : Number(raw)
      : raw === '' && props.schema.nullable
        ? null
        : raw
}
function toggle() {
  model.value = model.value === null ? initial({ ...props.schema, nullable: false }) : null
}
function add() {
  const value = initial(props.schema.item!)
  if (
    value &&
    typeof value === 'object' &&
    !Array.isArray(value) &&
    'code' in value &&
    props.schema.item?.fields?.code?.title.startsWith('Код')
  )
    value.code = 'rule_' + Math.random().toString(36).slice(2, 10)
  rows.value.push(value)
}
function generateLevels() {
  model.value = Array.from({ length: 20 }, (_, i) => {
    const row = initial(props.schema.item!) as Record<string, RuleValue>
    row.level = i + 1
    row.proficiency_bonus = 2 + Math.floor(i / 4)
    return row
  })
}
function conditionKind(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  model.value =
    value === 'leaf'
      ? { kind: 'level', code: '', entry_id: null, value: 1 }
      : { op: value, conditions: [] }
}
function leafUpdate(value: RuleValue) {
  const next = value as Record<string, RuleValue>
  if (['level', 'class_level', 'ability'].includes(String(next.kind)))
    next.value = Number(next.value)
  if (next.kind === 'spellcasting') next.value = String(next.value) === 'true'
  model.value = next
}
</script>
<template>
  <div class="rule-field">
    <label v-if="schema.nullable && ['object'].includes(schema.type)"
      ><input type="checkbox" :checked="model !== null" :disabled="disabled" @change="toggle" />
      {{ schema.title }}</label
    >
    <template v-if="model !== null || schema.type !== 'object'">
      <details v-if="schema.type === 'object'" :open="!schema.nullable">
        <summary>{{ schema.title }}</summary>
        <RuleField
          v-for="(field, key) in fields"
          :key="key"
          v-model="object[key]!"
          :schema="field"
          :options="options"
          :disabled="disabled"
        />
      </details>
      <details v-else-if="schema.type === 'list'">
        <summary>{{ schema.title }} ({{ rows.length }})</summary>
        <button
          v-if="schema.title === 'Развитие класса 1–20' && rows.length === 0"
          type="button"
          :disabled="disabled"
          @click="generateLevels"
        >
          Создать 20 уровней
        </button>
        <fieldset v-for="(_, i) in rows" :key="i">
          <legend>{{ schema.item?.title }} {{ i + 1 }}</legend>
          <RuleField
            v-model="rows[i]!"
            :schema="schema.item!"
            :options="options"
            :disabled="disabled"
          /><button type="button" :disabled="disabled" @click="rows.splice(i, 1)">Удалить</button>
        </fieldset>
        <button type="button" :disabled="disabled" @click="add">Добавить</button>
      </details>
      <fieldset v-else-if="schema.type === 'condition'">
        <legend>{{ schema.title }}</legend>
        <select :value="object.op ?? 'leaf'" :disabled="disabled" @change="conditionKind">
          <option value="all">Все условия (И)</option>
          <option value="any">Хотя бы одно (ИЛИ)</option>
          <option value="not">Отрицание</option>
          <option value="leaf">Одно требование</option></select
        ><template v-if="object.op"
          ><RuleField
            v-for="(_, i) in object.conditions as RuleValue[]"
            :key="i"
            v-model="(object.conditions as RuleValue[])[i]!"
            :schema="schema"
            :options="options"
            :disabled="disabled"
          /><button
            type="button"
            :disabled="
              disabled || (object.op === 'not' && (object.conditions as RuleValue[]).length === 1)
            "
            @click="
              (object.conditions as RuleValue[]).push({
                kind: 'level',
                code: '',
                entry_id: null,
                value: 1,
              })
            "
          >
            Добавить условие</button
          ><button
            v-if="(object.conditions as RuleValue[]).length"
            type="button"
            :disabled="disabled"
            @click="(object.conditions as RuleValue[]).pop()"
          >
            Удалить последнее
          </button></template
        ><RuleField
          v-else
          :model-value="model"
          :schema="leaf"
          :options="options"
          :disabled="disabled"
          @update:model-value="leafUpdate"
        />
      </fieldset>
      <label v-else-if="schema.type === 'boolean'"
        ><input v-model="model" type="checkbox" :disabled="disabled" /> {{ schema.title }}</label
      >
      <label v-else-if="schema.type === 'select'"
        >{{ schema.title
        }}<input
          v-if="selections.length > 20"
          v-model="search"
          placeholder="Поиск вариантов"
          :disabled="disabled"
        /><select v-model="model" :disabled="disabled">
          <option :value="schema.nullable ? null : ''">Выберите</option>
          <option v-for="option in visible" :key="String(option.value)" :value="option.value">
            {{ option.title }}
          </option>
        </select></label
      >
      <label v-else
        >{{ schema.title
        }}<input
          :type="schema.type === 'number' ? 'number' : 'text'"
          :value="model ?? ''"
          :disabled="disabled"
          @input="valueInput"
      /></label>
    </template>
  </div>
</template>
<style scoped>
.rule-field {
  margin: 0.65rem 0;
}
.rule-field label {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: center;
}
.rule-field details,
.rule-field fieldset {
  padding: 0.75rem;
  border: 1px solid var(--color-border, #554b4b);
  border-radius: 0.35rem;
  margin: 0.6rem 0;
}
.rule-field summary {
  cursor: pointer;
  font-weight: 600;
}
.rule-field input:not([type='checkbox']),
.rule-field select {
  min-width: 10rem;
  max-width: 100%;
  padding: 0.4rem;
}
.rule-field button {
  margin: 0.4rem;
}
</style>
