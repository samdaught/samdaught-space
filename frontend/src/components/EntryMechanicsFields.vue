<script setup lang="ts">
import { computed } from 'vue'
import type { EntryMechanics, MechanicsOptions } from '../api/entryMechanics'
const props = defineProps<{ category: string; options: MechanicsOptions }>()
const fields = defineModel<EntryMechanics>({ required: true })
const sources = computed(() =>
  ['species', 'class', 'background', 'feat', 'feature', 'subclass', 'species_variant'].includes(
    props.category,
  ),
)
const available = (type: string) => props.options.entries.filter((entry) => entry.type === type)
const traitValues = (kind: string) =>
  kind === 'weapon'
    ? [
        { code: 'simple', title: 'Простое оружие' },
        { code: 'martial', title: 'Воинское оружие' },
      ]
    : (props.options.vocabulary ?? []).filter(
        (row) => row.kind === (kind === 'resistance' ? 'damage' : kind),
      )
const spellTexts = [
  ['school', 'Школа'],
  ['casting_time', 'Время накладывания'],
  ['range_text', 'Дистанция'],
  ['components', 'Компоненты'],
  ['duration', 'Длительность'],
] as const
</script>
<template>
  <fieldset v-if="category === 'spell' && fields.spell">
    <legend>Параметры заклинания и классы</legend>
    <label
      >Круг (0 — заговор)<input
        v-model.number="fields.spell.level"
        type="number"
        min="0"
        max="9"
        required
    /></label>
    <label v-for="[key, title] in spellTexts" :key="key"
      >{{ title
      }}<input
        v-model="fields.spell[key]"
        :maxlength="key === 'components' ? 1000 : key === 'school' ? 100 : 255"
    /></label>
    <label><input v-model="fields.spell.concentration" type="checkbox" />Концентрация</label
    ><label><input v-model="fields.spell.ritual" type="checkbox" />Ритуал</label>
    <h3>Списки классов</h3>
    <p>Отметьте классы, которым доступно заклинание. Связь доступна также в настройках класса.</p>
    <p v-if="!available('class').length">
      Сначала создайте класс с базовыми свойствами. Публиковать его заранее не требуется.
    </p>
    <label v-for="entry in available('class')" :key="entry.id"
      ><input v-model="fields.class_ids" type="checkbox" :value="entry.id" />{{ entry.title
      }}{{ entry.is_published ? '' : ' (черновик)' }}</label
    >
    <label
      v-for="id in fields.class_ids.filter(
        (id) => !available('class').some((entry) => entry.id === id),
      )"
      :key="id"
      ><input v-model="fields.class_ids" type="checkbox" :value="id" />Недоступный класс №{{ id }} —
      снимите отметку</label
    >
  </fieldset>
  <fieldset v-if="sources || category === 'item'">
    <legend>Постоянные бонусы и владения</legend>
    <p>
      Эти значения учитываются автоматически. Условные эффекты опишите в тексте. Бонусы предмета
      действуют при его экипировке.
    </p>
    <div v-for="(bonus, index) in fields.bonuses" :key="index" class="mechanic-row">
      <label
        >Параметр<select v-model="bonus.code" required>
          <option disabled value="">Выберите параметр</option>
          <option
            v-for="(title, code) in options.bonuses"
            v-show="
              category !== 'item' ||
              ['armor_class', 'speed', 'initiative', 'spell_attack', 'spell_save'].includes(code)
            "
            :key="code"
            :value="code"
          >
            {{ title }}
          </option>
        </select></label
      ><label
        >Бонус<input
          v-model.number="bonus.amount"
          type="number"
          min="-100"
          max="100"
          required /></label
      ><button type="button" @click="fields.bonuses.splice(index, 1)">Удалить бонус</button>
    </div>
    <button
      type="button"
      :disabled="fields.bonuses.length >= 100"
      @click="fields.bonuses.push({ code: '', amount: 0 })"
    >
      Добавить бонус
    </button>
    <template v-if="sources"
      ><div v-for="(trait, index) in fields.traits" :key="index" class="mechanic-row">
        <label
          >Свойство<select v-model="trait.kind">
            <option v-for="(title, kind) in options.traits" :key="kind" :value="kind">
              {{ title }}
            </option>
          </select></label
        ><label
          >Значение<select v-if="traitValues(trait.kind).length" v-model="trait.value">
            <option value="" disabled>Выберите</option>
            <option
              v-if="trait.value && !traitValues(trait.kind).some((row) => row.code === trait.value)"
              :value="trait.value"
            >
              {{ trait.value }} (ранее сохранено)
            </option>
            <option v-for="row in traitValues(trait.kind)" :key="row.code" :value="row.code">
              {{ row.title }}
            </option></select
          ><input v-else v-model="trait.value" maxlength="255" required /></label
        ><button type="button" @click="fields.traits.splice(index, 1)">Удалить свойство</button>
      </div>
      <button
        type="button"
        :disabled="fields.traits.length >= 100"
        @click="fields.traits.push({ kind: 'language', value: '' })"
      >
        Добавить язык, владение или свойство
      </button></template
    >
  </fieldset>
  <fieldset v-if="sources">
    <legend>Врождённая магия и защита без доспехов</legend>
    <label
      >Характеристика заклинаний из выдач этой записи<select v-model="fields.innate_ability_code">
        <option :value="null">Нет врождённой магии</option>
        <option v-for="ability in options.abilities" :key="ability.code" :value="ability.code">
          {{ ability.title }}
        </option>
      </select></label
    >
    <p>
      Для класса основная магическая характеристика задаётся в заклинательстве выше. Это поле нужно
      виду, черте или особенности, которая отдельно даёт заклинания.
    </p>
    <button
      v-if="!fields.unarmored_defense"
      type="button"
      @click="fields.unarmored_defense = { base_ac: 10, ability_code: null, allows_shield: true }"
    >
      Настроить защиту без доспехов</button
    ><template v-else
      ><label
        >Базовая КД<input
          v-model.number="fields.unarmored_defense.base_ac"
          type="number"
          min="0"
          max="30"
          required /></label
      ><label
        >Дополнительная характеристика<select v-model="fields.unarmored_defense.ability_code">
          <option :value="null">Без дополнительной характеристики</option>
          <option v-for="ability in options.abilities" :key="ability.code" :value="ability.code">
            {{ ability.title }}
          </option>
        </select></label
      ><label
        ><input v-model="fields.unarmored_defense.allows_shield" type="checkbox" />Можно
        использовать щит</label
      >
      <p>
        КД = база + модификатор Ловкости + модификатор выбранной характеристики. Применяется лучший
        допустимый вариант, только без доспехов.
      </p>
      <button type="button" @click="fields.unarmored_defense = null">
        Убрать защиту без доспехов
      </button></template
    >
  </fieldset>
  <fieldset v-if="sources">
    <legend>Стартовые комплекты</legend>
    <p>
      Игрок выбирает один из заданных комплектов этой записи. Можно создать денежную альтернативу
      без предметов. Комплект предыстории из её основных настроек добавляется отдельно.
    </p>
    <fieldset v-for="(pack, index) in fields.equipment" :key="pack.id ?? 'new-' + index">
      <legend>Комплект {{ index + 1 }}</legend>
      <label>Название<input v-model="pack.title" maxlength="120" required /></label
      ><label
        >Деньги, медные монеты<input
          v-model.number="pack.money_cp"
          type="number"
          min="0"
          max="100000000"
          required
      /></label>
      <div v-for="(item, line) in pack.items" :key="line" class="mechanic-row">
        <label
          >Предмет<select v-model="item.item_entry_id" required>
            <option :value="0" disabled>Выберите предмет</option>
            <option v-for="entry in available('item')" :key="entry.id" :value="entry.id">
              {{ entry.title }}
            </option>
          </select></label
        ><label
          >Количество<input
            v-model.number="item.quantity"
            type="number"
            min="1"
            max="10000"
            required /></label
        ><button type="button" @click="pack.items.splice(line, 1)">Убрать предмет</button>
      </div>
      <button
        type="button"
        :disabled="pack.items.length >= 100"
        @click="pack.items.push({ item_entry_id: 0, quantity: 1 })"
      >
        Добавить предмет</button
      ><button type="button" @click="fields.equipment.splice(index, 1)">Удалить комплект</button>
    </fieldset>
    <button
      type="button"
      :disabled="fields.equipment.length >= 100"
      @click="fields.equipment.push({ id: null, title: '', money_cp: 0, items: [] })"
    >
      Добавить комплект
    </button>
  </fieldset>
  <fieldset v-if="category === 'item'">
    <legend>Доспех или щит</legend>
    <button
      v-if="!fields.armor"
      type="button"
      @click="fields.armor = { base_ac: 10, dexterity_cap: null, is_shield: false }"
    >
      Настроить защиту</button
    ><template v-else
      ><label
        ><input v-model="fields.armor.is_shield" type="checkbox" />Щит (значение прибавляется к
        КД)</label
      ><label
        >{{ fields.armor.is_shield ? 'Бонус КД' : 'Базовая КД'
        }}<input
          v-model.number="fields.armor.base_ac"
          type="number"
          min="0"
          max="30"
          required /></label
      ><label
        >Ограничение модификатора Ловкости<select v-model="fields.armor.dexterity_cap">
          <option :value="null">Без ограничения</option>
          <option v-for="cap in [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10]" :key="cap" :value="cap">
            {{ cap }}
          </option>
        </select></label
      ><button type="button" @click="fields.armor = null">Убрать параметры защиты</button></template
    >
  </fieldset>
</template>
<style scoped>
.mechanic-row {
  display: flex;
  gap: 1rem;
  align-items: end;
  flex-wrap: wrap;
  margin-block: 1rem;
}
.mechanic-row label {
  flex: 1;
  min-width: 9rem;
  margin: 0;
}
label:has(input[type='checkbox']) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
fieldset {
  min-width: 0;
}
</style>
