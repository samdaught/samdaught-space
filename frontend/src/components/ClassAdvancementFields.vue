<script setup lang="ts">
import { computed } from 'vue'
import type { RulebookData, RuleValue } from '../api/rulebook'

type Slot = { level: number; count: number }
type ProgressionRow = {
  level: number
  proficiency_bonus: number
  kind: 'none' | 'spellcasting' | 'pact'
  cantrip_count: number
  known_count: number
  prepared_count: number
  pact_level: number
  recovery: 'none' | 'short_rest' | 'long_rest' | 'dawn' | 'initiative'
  change_on: 'level' | 'long_rest' | 'short_rest'
  slots: Slot[]
}
type Grant = { entry_id: number; level: number; level_basis: 'class' | 'character' }
type Resource = {
  code: string
  title: string
  maximum: { base: number; terms: { kind: string; code: string | null; entry_id: number | null; multiplier: number; divisor: number; round: string }[]; minimum: number | null; maximum: number | null }
  recovery: 'none' | 'short_rest' | 'long_rest' | 'dawn' | 'initiative'
  level: number
}

const props = defineProps<{
  entries: { id: number; type: string; title: string; is_published: boolean }[]
}>()
const model = defineModel<RulebookData>({ required: true })
const spellIds = defineModel<number[]>('spellIds', { required: true })
const abilityOptions = [
  { code: 'str', title: 'Сила' },
  { code: 'dex', title: 'Ловкость' },
  { code: 'con', title: 'Телосложение' },
  { code: 'int', title: 'Интеллект' },
  { code: 'wis', title: 'Мудрость' },
  { code: 'cha', title: 'Харизма' },
]

const progression = computed(() => (Array.isArray(model.value.progression) ? model.value.progression : []) as unknown as ProgressionRow[])
const grants = computed(() => (Array.isArray(model.value.unlocks) ? model.value.unlocks : []) as unknown as Grant[])
const resources = computed(() => (Array.isArray(model.value.resources) ? model.value.resources : []) as unknown as Resource[])
const grantOptions = computed(() => props.entries.filter((entry) => ['feature', 'feat', 'spell', 'skill', 'item'].includes(entry.type)))
const spellOptions = computed(() => props.entries.filter((entry) => entry.type === 'spell'))

function defaultRow(level: number): ProgressionRow {
  return {
    level,
    proficiency_bonus: 2 + Math.floor((level - 1) / 4),
    kind: 'none',
    cantrip_count: 0,
    known_count: 0,
    prepared_count: 0,
    pact_level: 0,
    recovery: 'long_rest',
    change_on: 'level',
    slots: [],
  }
}
function initialiseProgression(): void {
  model.value.progression = Array.from({ length: 20 }, (_, index) => defaultRow(index + 1)) as unknown as RuleValue
}
function slotCount(row: ProgressionRow, spellLevel: number): number {
  return row.slots.find((slot) => slot.level === spellLevel)?.count ?? 0
}
function setSlotCount(row: ProgressionRow, spellLevel: number, value: number): void {
  const count = Math.max(0, Math.min(20, Number.isFinite(value) ? Math.floor(value) : 0))
  const existing = row.slots.find((slot) => slot.level === spellLevel)
  if (existing) existing.count = count
  else row.slots.push({ level: spellLevel, count })
  row.slots = row.slots.filter((slot) => slot.count > 0).sort((left, right) => left.level - right.level)
}
function addGrant(): void {
  grants.value.push({ entry_id: 0, level: 1, level_basis: 'class' })
}
function addResource(): void {
  resources.value.push({
    code: 'resource_' + (resources.value.length + 1),
    title: '',
    maximum: {
      base: 0,
      terms: [{ kind: 'class_level', code: null, entry_id: null, multiplier: 1, divisor: 1, round: 'floor' }],
      minimum: 0,
      maximum: null,
    },
    recovery: 'short_rest',
    level: 1,
  })
}
function resourceMode(resource: Resource): 'class_level' | 'fixed' {
  const term = resource.maximum.terms[0]
  return term?.kind === 'class_level' && resource.maximum.terms.length === 1 ? 'class_level' : 'fixed'
}
function setResourceMode(resource: Resource, mode: 'class_level' | 'fixed'): void {
  resource.maximum.terms = mode === 'class_level'
    ? [{ kind: 'class_level', code: null, entry_id: null, multiplier: 1, divisor: 1, round: 'floor' }]
    : []
}
</script>

<template>
  <section class="class-advancement" aria-labelledby="advancement-title">
    <h3 id="advancement-title">Развитие класса 1–20</h3>
    <p class="field-hint">
      Здесь задаётся то, что изменяется с уровнем: доступ к магии, ячейки, выдаваемые особенности
      и счётчики. Значения бонуса мастерства уже выставлены по правилам 2024.
    </p>
    <label class="casting-ability">
      Магическая характеристика класса
      <select v-model="model.casting_ability_code">
        <option :value="null">Класс не использует магию</option>
        <option v-for="ability in abilityOptions" :key="ability.code" :value="ability.code">
          {{ ability.title }}
        </option>
      </select>
    </label>
    <button v-if="progression.length !== 20" type="button" @click="initialiseProgression">
      Создать таблицу 1–20
    </button>
    <div v-else class="table-scroll" role="region" aria-label="Таблица развития класса" tabindex="0">
      <table>
        <thead>
          <tr>
            <th scope="col">Уровень</th>
            <th scope="col">Мастерство</th>
            <th scope="col">Магия</th>
            <th scope="col">Заговоры</th>
            <th scope="col">Изученные</th>
            <th scope="col">Подготовленные</th>
            <th v-for="spellLevel in 9" :key="spellLevel" scope="col">{{ spellLevel }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in progression" :key="row.level">
            <th scope="row">{{ row.level }}</th>
            <td>{{ row.proficiency_bonus >= 0 ? '+' : '' }}{{ row.proficiency_bonus }}</td>
            <td>
              <select v-model="row.kind" :aria-label="`Магия на ${row.level}-м уровне`">
                <option value="none">Нет</option>
                <option value="spellcasting">Заклинатель</option>
                <option value="pact">Магия договора</option>
              </select>
              <label v-if="row.kind === 'pact'" class="pact-level">Круг
                <input v-model.number="row.pact_level" type="number" min="1" max="9" />
              </label>
            </td>
            <td><input v-model.number="row.cantrip_count" type="number" min="0" max="100" /></td>
            <td><input v-model.number="row.known_count" type="number" min="0" max="100" /></td>
            <td><input v-model.number="row.prepared_count" type="number" min="0" max="100" /></td>
            <td v-for="spellLevel in 9" :key="spellLevel">
              <input
                :value="slotCount(row, spellLevel)"
                type="number"
                min="0"
                max="20"
                :aria-label="`Ячейки ${spellLevel}-го круга на ${row.level}-м уровне`"
                @input="setSlotCount(row, spellLevel, Number(($event.target as HTMLInputElement).value))"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <section class="advancement-block">
      <h4>Список заклинаний класса</h4>
      <p class="field-hint">Отметьте все заклинания, доступные этому классу. Уровневая таблица выше определяет, какие из них увидит игрок на каждом уровне.</p>
      <div class="spell-list">
        <label v-for="spell in spellOptions" :key="spell.id">
          <input v-model="spellIds" type="checkbox" :value="spell.id" />
          {{ spell.title }}{{ spell.is_published ? '' : ' (черновик)' }}
        </label>
      </div>
    </section>

    <section class="advancement-block">
      <h4>Особенности, которые выдаются автоматически</h4>
      <p class="field-hint">Добавьте отдельные записи особенностей, черт, заклинаний или предметов в нужный уровень.</p>
      <div v-for="(grant, index) in grants" :key="index" class="inline-row">
        <label>С уровня <input v-model.number="grant.level" type="number" min="1" max="20" /></label>
        <label>Запись
          <select v-model.number="grant.entry_id">
            <option :value="0" disabled>Выберите</option>
            <option v-for="entry in grantOptions" :key="entry.id" :value="entry.id">{{ entry.title }}{{ entry.is_published ? '' : ' (черновик)' }}</option>
          </select>
        </label>
        <button type="button" @click="grants.splice(index, 1)">Убрать</button>
      </div>
      <button type="button" @click="addGrant">Добавить выдачу</button>
    </section>

    <section class="advancement-block">
      <h4>Счётчики класса</h4>
      <p class="field-hint">Они попадут в готовый лист с максимумом и условием восстановления. Трата во время сессии не отслеживается.</p>
      <div v-for="(resource, index) in resources" :key="index" class="resource-row">
        <label>Название <input v-model="resource.title" maxlength="120" placeholder="Ки" /></label>
        <label>Доступен с <input v-model.number="resource.level" type="number" min="1" max="20" /></label>
        <label>Максимум
          <select :value="resourceMode(resource)" @change="setResourceMode(resource, ($event.target as HTMLSelectElement).value as 'class_level' | 'fixed')">
            <option value="class_level">Равен уровню класса</option>
            <option value="fixed">Фиксированное число</option>
          </select>
        </label>
        <label v-if="resourceMode(resource) === 'fixed'">Количество <input v-model.number="resource.maximum.base" type="number" min="0" max="100" /></label>
        <label>Восстановление
          <select v-model="resource.recovery">
            <option value="short_rest">Короткий отдых</option>
            <option value="long_rest">Продолжительный отдых</option>
            <option value="dawn">Рассвет</option>
            <option value="initiative">Инициатива</option>
            <option value="none">Не восстанавливается</option>
          </select>
        </label>
        <button type="button" @click="resources.splice(index, 1)">Убрать</button>
      </div>
      <button type="button" @click="addResource">Добавить счётчик</button>
    </section>

    <label class="ready-check"><input v-model="model.ready" type="checkbox" />Правила развития этого класса заполнены и проверены</label>
  </section>
</template>

<style scoped>
.class-advancement { padding-block: 1.25rem; border-top: 1px solid var(--line); }
h3, h4 { margin: 0 0 .5rem; }
.field-hint { color: var(--muted); max-width: 70ch; }
.casting-ability { display: grid; gap: .35rem; width: min(100%, 22rem); margin-block: .85rem; }
.table-scroll { overflow-x: auto; border: 1px solid var(--line); border-radius: 8px; }
table { border-collapse: collapse; min-width: 980px; width: 100%; }
th, td { padding: .45rem; border-bottom: 1px solid var(--line); text-align: left; }
td input, td select { min-width: 4.25rem; width: 100%; }
.advancement-block { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--line); }
.pact-level { display: grid; grid-template-columns: auto 3rem; gap: .2rem; margin-top: .35rem; font-size: .78rem; }
.inline-row, .resource-row { display: flex; flex-wrap: wrap; align-items: end; gap: .75rem; margin-block: .75rem; }
.inline-row label, .resource-row label { display: grid; gap: .35rem; min-width: 10rem; }
.spell-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .5rem 1rem; max-height: 19rem; overflow: auto; padding: .75rem; border: 1px solid var(--line); border-radius: 8px; }
.spell-list label { display: flex; align-items: baseline; gap: .4rem; }
.ready-check { display: flex; gap: .5rem; align-items: center; margin-top: 1.25rem; font-weight: 650; }
</style>
