<script setup lang="ts">
import type { BasicRuleDraft, BasicRuleOptions } from '../api/basicRules'

defineProps<{ category: string; options: BasicRuleOptions }>()
const fields = defineModel<BasicRuleDraft>({ required: true })
</script>

<template>
  <fieldset v-if="category === 'species'">
    <legend>Базовые свойства вида</legend>
    <label
      >Скорость ходьбы, футов<input
        v-model.number="fields.species_speed_feet"
        type="number"
        min="0"
        :max="options.max_speed_feet"
        step="1"
        required
    /></label>
    <p>
      Размер, языки и постоянные бонусы задаются ниже в механике записи. Выбираемые особенности
      задаются в выдачах.
    </p>
  </fieldset>
  <fieldset v-else-if="category === 'class'">
    <legend>Базовые свойства класса</legend>
    <label
      >Кость хитов<select v-model="fields.class_hit_die_sides" required>
        <option disabled :value="null">Выберите кость хитов</option>
        <option v-for="die in options.hit_dice" :key="die.sides" :value="die.sides">
          {{ die.title }}
        </option>
      </select></label
    >
    <label v-for="index in options.required_saving_throw_count" :key="index">
      Владение спасброском {{ index }}
      <select v-model="fields.class_saving_throw_codes[index - 1]" required>
        <option disabled value="">Выберите характеристику</option>
        <option
          v-for="ability in options.ability_options"
          :key="ability.code"
          :value="ability.code"
          :disabled="
            fields.class_saving_throw_codes.includes(ability.code) &&
            fields.class_saving_throw_codes[index - 1] !== ability.code
          "
        >
          {{ ability.title }}
        </option>
      </select>
    </label>
    <label
      >Сколько навыков выбирает игрок
      <input
        v-model.number="fields.class_skill_choice_count"
        type="number"
        min="0"
        :max="options.max_class_skill_options"
        step="1"
        required
      />
    </label>
    <fieldset>
      <legend>Разрешённые навыки класса</legend>
      <p>Отметьте весь список вариантов. Конкретные навыки игрок выберет у персонажа.</p>
      <p v-if="options.skill_options.length === 0">
        В мире нет опубликованных навыков с определением.
      </p>
      <label v-for="skill in options.skill_options" :key="skill.id">
        <input v-model="fields.class_skill_entry_ids" type="checkbox" :value="skill.id" />{{
          skill.title
        }}
      </label>
      <label
        v-for="id in fields.class_skill_entry_ids.filter(
          (id) => !options.skill_options.some((option) => option.id === id),
        )"
        :key="id"
      >
        <input v-model="fields.class_skill_entry_ids" type="checkbox" :value="id" />Недоступный
        навык №{{ id }} — снимите отметку или восстановите доступность
      </label>
    </fieldset>
    <p>
      Списки заклинаний, заклинательство и стартовые комплекты задаются ниже. Особенности и
      дополнительные владения — в выдачах.
    </p>
  </fieldset>
</template>

<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
</style>
