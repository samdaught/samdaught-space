<script setup lang="ts">
import { computed, watch } from 'vue'
import type { EntryMechanics, MechanicsOptions } from '../api/entryMechanics'
import type { RulebookData, RuleValue } from '../api/rulebook'

type Formula = {
  base: number
  terms: { kind: string; code: string | null; entry_id: number | null; multiplier: number; divisor: number; round: string }[]
  minimum: number | null
  maximum: number | null
}
type Weapon = {
  category: 'simple_melee' | 'simple_ranged' | 'martial_melee' | 'martial_ranged'
  damage_count: number
  damage_die: number
  damage_type: string
  properties: string[]
  mastery: string | null
  range_normal: number
  range_long: number
  versatile_die: number | null
}
type ItemRules = {
  cost_cp: number
  weight_grams: number
  weapon: Weapon | null
  armor: { category: string; strength_min: number; stealth_disadvantage: boolean } | null
  magic: { rarity: string; attunement: boolean; charges: Formula; recovery: string; plan_min_level: number; prerequisites: RuleValue } | null
}

const props = defineProps<{ itemKind: string; options: MechanicsOptions }>()
const mechanics = defineModel<EntryMechanics>('mechanics', { required: true })
const rulebook = defineModel<RulebookData>('rulebook', { required: true })

const formula = (): Formula => ({ base: 0, terms: [], minimum: 0, maximum: null })
const baseItem = (): ItemRules => ({ cost_cp: 0, weight_grams: 0, weapon: null, armor: null, magic: null })
const baseWeapon = (): Weapon => ({
  category: 'simple_melee',
  damage_count: 1,
  damage_die: 6,
  damage_type: 'bludgeoning',
  properties: [],
  mastery: null,
  range_normal: 0,
  range_long: 0,
  versatile_die: null,
})
const armorCategories = computed(() => (props.options.vocabulary ?? []).filter((row) => row.kind === 'armor'))
const damageTypes = computed(() => (props.options.vocabulary ?? []).filter((row) => row.kind === 'damage'))
const weaponProperties = computed(() => (props.options.vocabulary ?? []).filter((row) => row.kind === 'weapon_property'))
const masteries = computed(() => (props.options.vocabulary ?? []).filter((row) => row.kind === 'mastery'))
const item = computed(() => rulebook.value.item as unknown as ItemRules)

function ensureItem(): void {
  if (!item.value || typeof item.value !== 'object') rulebook.value.item = baseItem() as unknown as RuleValue
  const draft = rulebook.value.item as unknown as ItemRules
  if (props.itemKind === 'weapon' && draft.weapon === null) draft.weapon = baseWeapon()
  if (props.itemKind !== 'weapon') draft.weapon = null
  if (['armor', 'shield'].includes(props.itemKind) && draft.armor === null) {
    draft.armor = { category: props.itemKind === 'shield' ? 'shield' : 'light', strength_min: 0, stealth_disadvantage: false }
  }
  if (!['armor', 'shield'].includes(props.itemKind)) draft.armor = null
  if (['armor', 'shield'].includes(props.itemKind)) {
    if (mechanics.value.armor === null) {
      mechanics.value.armor = { base_ac: props.itemKind === 'shield' ? 2 : 11, dexterity_cap: null, is_shield: props.itemKind === 'shield' }
    }
    mechanics.value.armor.is_shield = props.itemKind === 'shield'
  }
  if (!['armor', 'shield'].includes(props.itemKind)) mechanics.value.armor = null
}

function toggleMagic(enabled: boolean): void {
  item.value.magic = enabled
    ? { rarity: 'common', attunement: false, charges: formula(), recovery: 'dawn', plan_min_level: 1, prerequisites: { op: 'all', conditions: [] } }
    : null
}
function toggleProperty(code: string, enabled: boolean): void {
  const weapon = item.value.weapon
  if (!weapon) return
  weapon.properties = enabled ? [...new Set([...weapon.properties, code])] : weapon.properties.filter((value) => value !== code)
}

watch(() => props.itemKind, ensureItem, { immediate: true })
</script>

<template>
  <section v-if="item" class="item-rules">
    <h2>Игровые свойства предмета</h2>
    <p class="field-hint">Эти значения попадут в лист персонажа. Наличие предмета, его расход и состояние во время сессии сайт не отслеживает.</p>

    <div class="common-grid">
      <label>Стоимость, мм <input v-model.number="item.cost_cp" type="number" min="0" max="1000000000" /></label>
      <label>Вес, г <input v-model.number="item.weight_grams" type="number" min="0" max="1000000000" /></label>
    </div>

    <section v-if="item.weapon" class="item-section">
      <h3>Оружие</h3>
      <div class="common-grid">
        <label>Категория
          <select v-model="item.weapon.category">
            <option value="simple_melee">Простое рукопашное</option><option value="simple_ranged">Простое дальнобойное</option>
            <option value="martial_melee">Воинское рукопашное</option><option value="martial_ranged">Воинское дальнобойное</option>
          </select>
        </label>
        <label>Костей <input v-model.number="item.weapon.damage_count" type="number" min="1" max="20" /></label>
        <label>Кость <select v-model.number="item.weapon.damage_die"><option v-for="die in [4, 6, 8, 10, 12]" :key="die" :value="die">d{{ die }}</option></select></label>
        <label>Тип урона <select v-model="item.weapon.damage_type"><option v-for="type in damageTypes" :key="type.code" :value="type.code">{{ type.title }}</option></select></label>
        <label>Обычная дальность <input v-model.number="item.weapon.range_normal" type="number" min="0" max="100000" /></label>
        <label>Предельная дальность <input v-model.number="item.weapon.range_long" type="number" :min="item.weapon.range_normal" max="100000" /></label>
        <label>Универсальная кость <select v-model.number="item.weapon.versatile_die"><option :value="null">Нет</option><option v-for="die in [4, 6, 8, 10, 12]" :key="die" :value="die">d{{ die }}</option></select></label>
        <label>Мастерство <select v-model="item.weapon.mastery"><option :value="null">Нет</option><option v-for="mastery in masteries" :key="mastery.code" :value="mastery.code">{{ mastery.title }}</option></select></label>
      </div>
      <fieldset class="choice-list"><legend>Свойства оружия</legend><label v-for="property in weaponProperties" :key="property.code"><input type="checkbox" :checked="item.weapon.properties.includes(property.code)" @change="toggleProperty(property.code, ($event.target as HTMLInputElement).checked)" />{{ property.title }}</label></fieldset>
    </section>

    <section v-if="item.armor && mechanics.armor" class="item-section">
      <h3>{{ props.itemKind === 'shield' ? 'Щит' : 'Доспех' }}</h3>
      <div class="common-grid">
        <label>Базовая КД <input v-model.number="mechanics.armor.base_ac" type="number" min="0" max="30" /></label>
        <label>Предел Ловкости <input v-model.number="mechanics.armor.dexterity_cap" type="number" min="0" max="10" placeholder="Без предела" /></label>
        <label>Категория <select v-model="item.armor.category"><option v-for="category in armorCategories" :key="category.code" :value="category.code">{{ category.title }}</option></select></label>
        <label>Требование Силы <input v-model.number="item.armor.strength_min" type="number" min="0" max="30" /></label>
      </div>
      <label><input v-model="item.armor.stealth_disadvantage" type="checkbox" />Помеха на проверки Скрытности</label>
    </section>

    <section class="item-section">
      <h3>Магический предмет</h3>
      <label><input type="checkbox" :checked="item.magic !== null" @change="toggleMagic(($event.target as HTMLInputElement).checked)" />Предмет магический</label>
      <div v-if="item.magic" class="common-grid">
        <label>Редкость <select v-model="item.magic.rarity"><option value="common">Обычный</option><option value="uncommon">Необычный</option><option value="rare">Редкий</option><option value="very_rare">Очень редкий</option><option value="legendary">Легендарный</option><option value="artifact">Артефакт</option></select></label>
        <label>Зарядов <input v-model.number="item.magic.charges.base" type="number" min="0" max="100" /></label>
        <label>Восстановление <select v-model="item.magic.recovery"><option value="none">Нет</option><option value="short_rest">Короткий отдых</option><option value="long_rest">Продолжительный отдых</option><option value="dawn">Рассвет</option></select></label>
        <label>Минимальный уровень <input v-model.number="item.magic.plan_min_level" type="number" min="1" max="20" /></label>
      </div>
      <label v-if="item.magic"><input v-model="item.magic.attunement" type="checkbox" />Требуется настройка</label>
    </section>

    <label class="ready-check"><input v-model="rulebook.ready" type="checkbox" />Игровые свойства предмета заполнены и проверены</label>
  </section>
</template>

<style scoped>
.item-rules { padding-block: 1rem; border-top: 1px solid var(--line); }
h2, h3 { margin: 0 0 .55rem; }
.field-hint { color: var(--muted); max-width: 72ch; }
.item-section { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--line); }
.common-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: .8rem; }
.common-grid label { display: grid; gap: .35rem; }
.choice-list { display: flex; flex-wrap: wrap; gap: .5rem 1rem; margin-top: 1rem; }
.choice-list legend { width: 100%; }
.choice-list label, .ready-check { display: flex; align-items: baseline; gap: .4rem; }
.ready-check { margin-top: 1.25rem; font-weight: 650; }
</style>
