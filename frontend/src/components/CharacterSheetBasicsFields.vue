<script setup lang="ts">
import type { CharacterSheetBasics } from '../types/characterEditor'

defineProps<{ data: CharacterSheetBasics }>()

function signed(value: number | null): string {
  return value === null ? 'Не рассчитан' : value >= 0 ? '+' + value : String(value)
}
</script>

<template>
  <ul v-if="data.issues.length" aria-label="Незаполненные базовые данные">
    <li v-for="issue in data.issues" :key="issue">{{ issue }}</li>
  </ul>
  <ul aria-label="Границы расчёта">
    <li v-for="limitation in data.limitations" :key="limitation">{{ limitation }}</li>
  </ul>
  <template>
    <dl>
      <dt>Бонус мастерства</dt>
      <dd>{{ signed(data.proficiency_bonus) }}</dd>
      <dt>Максимальные хиты</dt>
      <dd>{{ data.hit_point_max ?? 'Не рассчитаны' }}</dd>
      <dt>Бонус максимальных хитов от архивных выдач</dt>
      <dd>{{ signed(data.hit_point_bonus) }}</dd>
      <dt>Кость хитов</dt>
      <dd>{{ data.hit_die_sides === null ? 'Не настроена' : '1d' + data.hit_die_sides }}</dd>
      <dt>Базовая скорость ходьбы</dt>
      <dd>
        {{ data.base_speed_feet === null ? 'Не рассчитана' : data.base_speed_feet + ' футов' }}
      </dd>
      <dt>Базовая инициатива</dt>
      <dd>{{ signed(data.base_initiative) }}</dd>
      <dt>Базовая КД без доспехов и щита</dt>
      <dd>{{ data.unarmored_base_ac ?? 'Не рассчитана' }}</dd>
    </dl>
    <h3>Спасброски: база и владения класса</h3>
    <div class="table-scroll" role="region" aria-label="Расчёт листа персонажа" tabindex="0">
      <table>
        <thead>
          <tr>
            <th scope="col">Спасбросок</th>
            <th scope="col">Владение от класса</th>
            <th scope="col">Базовый бонус</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="save in data.saving_throws" :key="save.code">
            <th scope="row">{{ save.title }}</th>
            <td>
              {{
                save.class_proficient === null
                  ? 'Не настроено'
                  : save.class_proficient
                    ? 'Да'
                    : 'Нет'
              }}
            </td>
            <td>{{ signed(save.base_bonus) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <h3>Навыки и владения</h3>
    <p v-if="data.skills.length === 0">
      В этом мире нет доступных опубликованных навыков с определением.
    </p>
    <div v-else class="table-scroll" role="region" aria-label="Расчёт листа персонажа" tabindex="0">
      <table>
        <thead>
          <tr>
            <th scope="col">Навык</th>
            <th scope="col">Характеристика</th>
            <th scope="col">Владение от предыстории</th>
            <th scope="col">Владение от класса</th>
            <th scope="col">Другие владения</th>
            <th scope="col">Экспертиза</th>
            <th scope="col">Базовый бонус</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="skill in data.skills" :key="skill.id">
            <th scope="row">{{ skill.title }}</th>
            <td>{{ skill.ability_title }}</td>
            <td>{{ skill.background_proficient ? 'Да' : 'Нет' }}</td>
            <td>{{ skill.class_proficient ? 'Да' : 'Нет' }}</td>
            <td>{{ skill.additional_proficient ? 'Да' : 'Нет' }}</td>
            <td>{{ skill.expertise ? 'Да' : 'Нет' }}</td>
            <td>{{ signed(skill.base_bonus) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p>
      {{
        data.baseline_complete
          ? 'Данные для этого базового расчёта заполнены.'
          : 'Базовый расчёт ещё неполный.'
      }}
      Это не проверка готовности всего персонажа.
    </p>
  </template>
</template>

<style scoped>
table {
  border-collapse: collapse;
}
th,
td {
  padding: 0.25rem 0.5rem;
  text-align: left;
}
dd {
  margin-bottom: 0.5rem;
}
</style>
