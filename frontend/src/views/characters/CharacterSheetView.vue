<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getCharacterEditor, type CharacterEditorState } from '../../api/characterEditor'
import CharacterSheetBasicsFields from '../../components/CharacterSheetBasicsFields.vue'
const sheet = ref<HTMLElement | null>(null)
const closedDetails = new Set<HTMLDetailsElement>()
function preparePrint(): void {
  for (const detail of sheet.value?.querySelectorAll('details') ?? []) {
    if (!detail.open) {
      closedDetails.add(detail)
      detail.open = true
    }
  }
}
function restorePrint(): void {
  for (const detail of closedDetails) detail.open = false
  closedDetails.clear()
}
function printSheet(): void {
  window.print()
}
onMounted(() => {
  window.addEventListener('beforeprint', preparePrint)
  window.addEventListener('afterprint', restorePrint)
})
onBeforeUnmount(() => {
  ++request
  window.removeEventListener('beforeprint', preparePrint)
  window.removeEventListener('afterprint', restorePrint)
  restorePrint()
})
const route = useRoute()
const state = ref<CharacterEditorState | null>(null)
const loading = ref(false)
const error = ref('')
let request = 0
async function load(): Promise<void> {
  const current = ++request,
    id = Number(route.params.id)
  if (!Number.isSafeInteger(id) || id < 1) {
    loading.value = false
    state.value = null
    error.value = 'Некорректный ID персонажа'
    return
  }
  loading.value = true
  error.value = ''
  state.value = null
  try {
    const data = await getCharacterEditor(id)
    if (current === request) {
      state.value = data
      document.title = data.character.name + ' — Лист персонажа'
    }
  } catch (cause) {
    if (current === request)
      error.value = cause instanceof Error ? cause.message : 'Не удалось открыть лист'
  } finally {
    if (current === request) loading.value = false
  }
}
watch(
  () => route.params.id,
  () => {
    void load()
  },
  { immediate: true },
)
</script>
<template>
  <RouterLink class="print-hide" to="/cabinet">К моим персонажам</RouterLink>
  <p v-if="loading" role="status">Загружаем лист…</p>
  <p v-if="error" role="alert">
    {{ error }} <button type="button" :disabled="loading" @click="load">Повторить</button>
  </p>
  <article v-if="state" ref="sheet" class="character-sheet">
    <button
      v-if="state.character.status === 'ready'"
      class="print-hide"
      type="button"
      @click="printSheet"
    >
      Печать / сохранить PDF
    </button>
    <h1>{{ state.character.name }}</h1>
    <p>
      {{ state.profile_title }} · уровень {{ state.character.target_level }} ·
      {{ state.character.status === 'ready' ? 'Готовый лист' : 'Черновик' }}
    </p>
    <RouterLink
      v-if="state.character.status === 'draft'"
      :to="`/characters/${state.character.id}/edit`"
      >Редактировать черновик</RouterLink
    >
    <p v-else>
      Лист сохранён при завершении создания. Здесь нет счётчиков боя и автоматического расходования
      ресурсов.
    </p>
    <section>
      <h2>Происхождение и класс</h2>
      <details v-for="(origin, key) in state.origins" :key="key">
        <summary>{{ origin?.title ?? 'Не выбрано' }}</summary>
        <p>{{ origin?.body }}</p>
      </details>
    </section>
    <section>
      <h2>Характеристики</h2>
      <div class="table-scroll" role="region" aria-label="Характеристики персонажа" tabindex="0">
        <table>
          <thead>
            <tr>
              <th scope="col">Характеристика</th>
              <th scope="col">База</th>
              <th scope="col">Предыстория</th>
              <th scope="col">Другие бонусы</th>
              <th scope="col">Итого</th>
              <th scope="col">Модификатор</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ability in state.ability_scores.abilities" :key="ability.code">
              <th scope="row">{{ ability.title }}</th>
              <td>{{ ability.base_score ?? '—' }}</td>
              <td>+{{ ability.applied_increase }}</td>
              <td>{{ ability.source_bonus ?? 0 }}</td>
              <td>{{ ability.total_score ?? '—' }}</td>
              <td>
                {{
                  ability.total_modifier === null
                    ? '—'
                    : ability.total_modifier >= 0
                      ? '+' + ability.total_modifier
                      : ability.total_modifier
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <section>
      <h2>Поля листа</h2>
      <dl>
        <dt>Максимальное здоровье</dt>
        <dd>{{ state.sheet_basics.hit_point_max ?? 'Не рассчитано' }}</dd>
        <dt>КД в снаряжении</dt>
        <dd>
          {{ state.sheet_basics.armor_class ?? state.sheet_details.armor_class ?? 'Не заполнена' }}
        </dd>
        <dt>Скорость</dt>
        <dd>
          {{
            state.sheet_basics.speed_feet ??
            state.sheet_details.speed_feet ??
            state.sheet_basics.base_speed_feet ??
            'Не рассчитана'
          }}
          футов
        </dd>
        <dt>Инициатива</dt>
        <dd>
          {{
            state.sheet_basics.initiative ??
            state.sheet_details.initiative ??
            state.sheet_basics.base_initiative ??
            'Не рассчитана'
          }}
        </dd>
        <dt>Бонус атаки заклинанием</dt>
        <dd>
          {{
            state.sheet_basics.spell_attack_bonus ??
            state.sheet_details.spell_attack_bonus ??
            'Не указан'
          }}
        </dd>
        <dt>СЛ спасброска от заклинаний</dt>
        <dd>
          {{
            state.sheet_basics.spell_save_dc ?? state.sheet_details.spell_save_dc ?? 'Не указана'
          }}
        </dd>
        <dt>Размер</dt>
        <dd>{{ state.sheet_basics.size || state.sheet_details.size || 'Не заполнен' }}</dd>
        <dt>Языки</dt>
        <dd>
          {{ state.sheet_basics.languages || state.sheet_details.languages || 'Не заполнены' }}
        </dd>
      </dl>
    </section>
    <CharacterSheetBasicsFields :data="state.sheet_basics" />
    <p v-if="state.sheet_basics.armor_untrained">
      Доспехи без подготовки: нельзя накладывать заклинания; помеха атакам и проверкам/спасброскам
      Силы и Ловкости.
    </p>
    <p v-if="state.sheet_basics.stealth_disadvantage">Доспехи дают помеху Скрытности.</p>
    <section v-if="state.sheet_basics.resources?.length">
      <h2>Ресурсы</h2>
      <p v-for="(resource, index) in state.sheet_basics.resources" :key="index">
        {{ resource.title }}: {{ resource.maximum }} · {{ resource.recovery }} ·
        {{ resource.source_title }}
      </p>
    </section>
    <section v-if="state.sheet_basics.weapons?.length">
      <h2>Оружие</h2>
      <p v-for="weapon in state.sheet_basics.weapons" :key="weapon.id">
        {{ weapon.title }} · атака {{ weapon.attack_bonus ?? '—' }} · урон {{ weapon.damage }} ({{
          weapon.damage_type
        }}) · {{ weapon.proficient ? 'Есть владение' : 'Без владения'
        }}<span v-if="weapon.mastery_available"> · мастерство {{ weapon.mastery }}</span>
      </p>
    </section>
    <section
      v-for="caster in state.creation.casters?.filter((c) => c.enabled && c.slots?.length) ?? []"
      :key="caster.source_id"
    >
      <h2>Ячейки: {{ caster.source_title }}</h2>
      <p v-for="slot in caster.slots" :key="slot.spell_level">
        Круг {{ slot.spell_level }}: {{ slot.count }}
      </p>
      <p>Восстановление: {{ caster.recovery }}</p>
    </section>

    <section
      v-for="(source, index) in state.sheet_basics.spellcasting_sources ?? []"
      :key="source.source_id + ':' + index"
    >
      <h2>Магия: {{ source.source_title }}</h2>
      <p>Атака {{ source.attack_bonus ?? '—' }} · СЛ {{ source.save_dc ?? '—' }}</p>
      <ul>
        <li v-for="spell in source.spells" :key="spell.id">
          {{ spell.title }}
          <p v-for="(grant, i) in spell.grants ?? []" :key="i">
            {{ grant.always_prepared ? 'Всегда подготовлено. ' : ''
            }}{{ grant.count_against_limit ? 'Входит в лимит. ' : 'Сверх лимита. '
            }}{{ grant.cast_with_slots ? 'Доступно через ячейки. ' : '' }}Бесплатных применений:
            {{ grant.free_uses }}; восстановление: {{ grant.recovery }}
          </p>
        </li>
      </ul>
    </section>
    <section v-if="state.sheet_basics.traits?.length">
      <h2>Владения и свойства</h2>
      <ul>
        <li v-for="(trait, index) in state.sheet_basics.traits" :key="index">
          {{ trait.value }} · {{ trait.source_title }}
        </li>
      </ul>
    </section>
    <section>
      <h2>Особенности, черты, заклинания и дополнительные владения</h2>
      <details v-for="entry in state.creation.entries" :key="entry.id">
        <summary>
          {{ entry.title }} ({{ entry.type === 'item' ? 'владение инструментом' : entry.type }})
        </summary>
        <p>{{ entry.body }}</p>
      </details>
    </section>
    <section>
      <h2>Инструмент предыстории</h2>
      <p>{{ state.benefits.tool.effective_tool?.title ?? 'Не выбран' }}</p>
    </section>
    <section>
      <h2>Снаряжение</h2>
      <h3>От предыстории</h3>
      <ul>
        <li v-for="item in state.inventory.background.items" :key="item.id">
          {{ item.title }} × {{ item.quantity }}
        </li>
      </ul>
      <p v-if="state.inventory.background.money_cp !== null">
        Деньги: {{ Math.floor(state.inventory.background.money_cp / 100) }} ЗМ
        {{ state.inventory.background.money_cp % 100 }} ММ
      </p>
      <template v-if="state.starting_packages"
        ><h3>Стартовые комплекты</h3>
        <ul>
          <li v-for="(item, index) in state.starting_packages.items" :key="index">
            {{ item.title }} × {{ item.quantity }} · {{ item.source_title }}
          </li>
        </ul>
        <p>Деньги комплектов: {{ state.starting_packages.money_cp / 100 }} зм</p></template
      >
      <h3>Остальные предметы</h3>
      <ul>
        <li
          v-for="item in [...state.inventory.manual_items, ...state.inventory.other_items]"
          :key="item.id"
        >
          {{ item.title }} × {{ item.quantity }}{{ item.is_equipped ? ' — экипирован' : '' }}
          <p v-if="item.note">{{ item.note }}</p>
          <details v-if="item.body">
            <summary>Описание предмета</summary>
            <p>{{ item.body }}</p>
          </details>
        </li>
      </ul>
    </section>
    <section>
      <h2>Ресурсы и пометки класса</h2>
      <p>{{ state.sheet_details.resources || 'Не указаны' }}</p>
    </section>
    <section>
      <h2>Внешность</h2>
      <p>{{ state.sheet_details.appearance }}</p>
      <h2>История</h2>
      <p>{{ state.sheet_details.biography }}</p>
      <h2>Заметки</h2>
      <p>{{ state.sheet_details.notes }}</p>
    </section>
  </article>
</template>
<style scoped>
p,
dd {
  white-space: pre-wrap;
}
th,
td {
  text-align: left;
  padding: 0.25rem 0.5rem;
}
section {
  margin-block: 1.25rem;
}
</style>
