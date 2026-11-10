<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  CharacterEditorError,
  emptyCharacterDraft,
  editorContext,
  getCharacterEditor,
  getCharacterEditorOptions,
  getCharacterEditorInput,
  saveCharacterEditor,
  type CharacterEditorDraft,
  type CharacterEditorOptions,
  type CharacterEditorState,
} from '../../api/characterEditor'
import { getRuleProfiles, type RuleProfile } from '../../api/ruleProfiles'
import CharacterBackgroundBenefitsFields from '../../components/CharacterBackgroundBenefitsFields.vue'
import CharacterClassSkillsFields from '../../components/CharacterClassSkillsFields.vue'
import CharacterInventoryFields from '../../components/CharacterInventoryFields.vue'
import CharacterSheetBasicsFields from '../../components/CharacterSheetBasicsFields.vue'
import CreationChoicesFields from '../../components/CreationChoicesFields.vue'
import SheetDetailsFields from '../../components/SheetDetailsFields.vue'

import {
  afterSuccessfulSave,
  navigationPending,
  useUnsavedChanges,
} from '../../composables/useUnsavedChanges'

const route = useRoute()
const router = useRouter()
const characterId = ref<number | null>(null)
const revision = ref(0)
const draft = ref<CharacterEditorDraft>(emptyCharacterDraft())
const options = ref<CharacterEditorOptions | null>(null)
const savedState = ref<CharacterEditorState | null>(null)
const profiles = ref<RuleProfile[]>([])
const loading = ref(false)
const saving = ref(false)
const optionsLoading = ref(false)
const pageError = ref('')
const optionsError = ref('')
const submitError = ref('')
const notice = ref('')
const savedFingerprint = ref(JSON.stringify(emptyCharacterDraft()))
const autosavePaused = ref(false)
const revisionConflict = ref(false)
const uncertainCreation = ref(false)
const savedAt = ref('')
let autosaveTimer: ReturnType<typeof setTimeout> | undefined
useUnsavedChanges(
  () => dirty.value,
  () => saving.value,
)
onBeforeUnmount(() => {
  clearTimeout(autosaveTimer)
  ++optionsRequest
  ++pageRequest
})
const loadedOptionsKey = ref('')
const step = ref(1)
const viewModeKey = 'samdaught.character-editor.view-mode'
function readViewMode(): 'full' | 'steps' {
  try {
    return window.localStorage.getItem(viewModeKey) === 'full' ? 'full' : 'steps'
  } catch {
    return 'steps'
  }
}
const viewMode = ref<'full' | 'steps'>(readViewMode())
watch(viewMode, (value) => {
  try {
    window.localStorage.setItem(viewModeKey, value)
  } catch {}
})
function isSectionVisible(number: number): boolean {
  return viewMode.value === 'full' || step.value === number
}
const steps = [
  'Основное',
  'Характеристики',
  'Навыки и особенности',
  'Предыстория',
  'Лист и снаряжение',
  'Проверка',
]
let optionsRequest = 0
let pageRequest = 0

function fingerprint(): string {
  return JSON.stringify(draft.value)
}
const dirty = computed(() => fingerprint() !== savedFingerprint.value)
const contextKey = computed(() => JSON.stringify(editorContext(draft.value)))
const optionsKey = computed(() =>
  JSON.stringify([
    editorContext(draft.value),
    Object.fromEntries(
      Object.entries(draft.value.creation_choices).filter(([, ids]) => ids.length > 0),
    ),
    draft.value.class_skill_entry_ids,
  ]),
)
const optionsReady = computed(
  () =>
    options.value !== null &&
    JSON.stringify(options.value.context) === contextKey.value &&
    loadedOptionsKey.value === optionsKey.value &&
    !optionsLoading.value &&
    optionsError.value === '',
)
const inventoryOptions = computed(() =>
  options.value === null
    ? null
    : { ...options.value.inventory, other_items: savedState.value?.inventory.other_items ?? [] },
)
const increasesAssigned = computed(() =>
  Object.values(draft.value.background_increases).reduce((sum, amount) => sum + amount, 0),
)

function applyState(state: CharacterEditorState): void {
  characterId.value = state.character.id
  revision.value = state.character.revision
  options.value = state
  savedState.value = state
  draft.value = {
    ...editorContext(state.character),
    name: state.character.name,
    base_scores: Object.fromEntries(
      state.ability_scores.abilities.map((ability) => [ability.code, ability.base_score]),
    ),
    background_increases: Object.fromEntries(
      state.background_abilities.abilities.map((ability) => [ability.code, ability.increase]),
    ),
    background_choices: {
      tool_entry_id: state.benefits.tool.selected_tool_entry_id,
      equipment_choice: state.benefits.equipment.choice,
    },
    class_skill_entry_ids: [...state.class_skills.selected_skill_entry_ids],
    inventory_items: state.inventory.manual_items.map(
      ({ id, item_entry_id, quantity, is_equipped, note }) => ({
        id,
        item_entry_id,
        quantity,
        is_equipped,
        note,
      }),
    ),
    creation_choices: Object.fromEntries(
      Object.entries(state.creation.selections).map(([key, ids]) => [key, [...ids]]),
    ),
    sheet_details: { ...state.sheet_details },
  }
  for (const group of state.creation.groups) draft.value.creation_choices[group.key] ??= []
  loadedOptionsKey.value = optionsKey.value
  savedFingerprint.value = fingerprint()
  optionsError.value = ''
}

async function loadOptions(): Promise<void> {
  const request = ++optionsRequest
  const context = editorContext(draft.value)
  const requestedKey = optionsKey.value
  optionsLoading.value = true
  optionsError.value = ''
  try {
    const data = await getCharacterEditorOptions(
      context,
      draft.value.creation_choices,
      draft.value.class_skill_entry_ids,
    )
    if (request !== optionsRequest || requestedKey !== optionsKey.value) return
    const previousGroups = new Set(options.value?.creation.groups.map((group) => group.key) ?? [])
    const previousSpecies = options.value?.context.species_entry_id
    const wasClean = !dirty.value
    options.value = data
    if (
      draft.value.sheet_details.speed_feet === null &&
      previousSpecies !== data.context.species_entry_id &&
      data.reference?.species_speed_feet !== null &&
      data.reference?.species_speed_feet !== undefined
    )
      draft.value.sheet_details.speed_feet = data.reference.species_speed_feet
    for (const key of Object.keys(draft.value.creation_choices))
      if (previousGroups.has(key) && !data.creation.groups.some((group) => group.key === key))
        delete draft.value.creation_choices[key]
    for (const group of data.creation.groups) draft.value.creation_choices[group.key] ??= []
    loadedOptionsKey.value = requestedKey
    for (const ability of data.ability_scores.abilities)
      if (!(ability.code in draft.value.base_scores)) draft.value.base_scores[ability.code] = null
    for (const ability of data.background_abilities.abilities)
      if (!(ability.code in draft.value.background_increases))
        draft.value.background_increases[ability.code] = 0
    if (wasClean) savedFingerprint.value = fingerprint()
  } catch (cause) {
    if (request === optionsRequest && requestedKey === optionsKey.value)
      optionsError.value =
        cause instanceof Error ? cause.message : 'Не удалось загрузить варианты редактора'
  } finally {
    if (request === optionsRequest) optionsLoading.value = false
  }
}

async function loadPage(id: number | null): Promise<void> {
  clearTimeout(autosaveTimer)
  const preserveDraft = id !== null && id === characterId.value && savedState.value !== null
  const request = ++pageRequest
  ++optionsRequest
  loading.value = true
  pageError.value = ''
  submitError.value = ''
  notice.value = ''
  try {
    const loadedProfiles = await getRuleProfiles()
    const state = id === null ? null : await getCharacterEditor(id)
    if (request !== pageRequest) return
    profiles.value = loadedProfiles
    autosavePaused.value = false
    revisionConflict.value = false
    uncertainCreation.value = false
    savedAt.value = ''
    if (state !== null) {
      if (state.character.status === 'ready') {
        applyState(state)
        await router.replace(`/characters/${state.character.id}`)
        return
      }
      applyState(state)
    } else {
      characterId.value = null
      revision.value = 0
      draft.value = emptyCharacterDraft()
      savedState.value = null
      savedFingerprint.value = fingerprint()
      options.value = null
      step.value = 1
      await loadOptions()
    }
  } catch (cause) {
    if (request === pageRequest) {
      const message = cause instanceof Error ? cause.message : 'Не удалось открыть редактор'
      if (preserveDraft) submitError.value = message + '. Введённые данные оставлены в форме.'
      else pageError.value = message
    }
  } finally {
    if (request === pageRequest) {
      loading.value = false
      optionsLoading.value = false
    }
  }
}

watch(
  () => route.params.id,
  async (raw) => {
    const id = raw === undefined ? null : Number(raw)
    if (id !== null && (!Number.isSafeInteger(id) || id < 1)) {
      pageError.value = 'Некорректный ID персонажа'
      return
    }

    if (
      id !== null &&
      characterId.value === id &&
      savedState.value !== null &&
      pageError.value === ''
    )
      return
    await loadPage(id)
  },
  { immediate: true },
)

watch(optionsKey, async () => {
  if (loading.value || saving.value) return
  const previous = options.value?.context
  if (
    previous &&
    JSON.stringify(previous) === contextKey.value &&
    loadedOptionsKey.value === optionsKey.value
  )
    return
  if (
    previous &&
    ['rule_profile_id', 'species_entry_id', 'class_entry_id', 'background_entry_id'].some(
      (key) => previous[key as keyof typeof previous] !== draft.value[key as keyof typeof previous],
    )
  )
    draft.value.creation_choices = {}
  if (
    previous?.rule_profile_id !== draft.value.rule_profile_id ||
    previous?.class_entry_id !== draft.value.class_entry_id
  )
    draft.value.class_skill_entry_ids = []
  if (
    previous?.rule_profile_id !== draft.value.rule_profile_id ||
    previous?.background_entry_id !== draft.value.background_entry_id
  ) {
    draft.value.background_increases = {}
    draft.value.background_choices = { tool_entry_id: null, equipment_choice: null }
  }
  notice.value = ''
  await loadOptions()
})

async function reloadSaved(): Promise<void> {
  if (saving.value || loading.value || characterId.value === null) return
  if (
    dirty.value &&
    !window.confirm('Заменить введённое сохранённой версией? Локальные изменения будут потеряны.')
  )
    return
  clearTimeout(autosaveTimer)
  await loadPage(characterId.value)
  if (!submitError.value && !pageError.value) autosavePaused.value = false
}

async function submit(finish = false, automatic = false): Promise<void> {
  clearTimeout(autosaveTimer)
  if (automatic && navigationPending.value) return
  if (autosavePaused.value) return
  if (
    finish &&
    !window.confirm(
      'Завершить создание? Сервер проверит выборы и сохранит готовый лист. Редактирование готового листа пока недоступно.',
    )
  )
    return
  if (
    loading.value ||
    saving.value ||
    !optionsReady.value ||
    options.value === null ||
    pageError.value !== ''
  )
    return
  submitError.value = ''
  notice.value = ''
  let input
  try {
    input = getCharacterEditorInput(draft.value, revision.value, options.value, finish)
  } catch (cause) {
    if (!automatic) submitError.value = cause instanceof Error ? cause.message : 'Проверьте поля'
    return
  }
  saving.value = true
  const wasNew = characterId.value === null
  try {
    applyState(await saveCharacterEditor(characterId.value, input))
    savedAt.value = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
    if (savedState.value?.character.status === 'ready') {
      notice.value = 'Лист завершён и сохранён.'
      try {
        await afterSuccessfulSave(() => router.replace(`/characters/${characterId.value}`))
      } catch {
        notice.value = 'Лист завершён. Откройте его из кабинета.'
      }
      return
    }
    notice.value = automatic ? 'Черновик сохранён автоматически.' : 'Черновик целиком сохранён.'
    if (wasNew) {
      try {
        const failure = await afterSuccessfulSave(() =>
          router.replace(`/characters/${characterId.value}/edit`),
        )
        if (failure)
          notice.value =
            'Черновик сохранён, но адрес не обновлён. Продолжайте заполнение здесь или откройте персонажа из кабинета.'
      } catch {
        notice.value =
          'Черновик сохранён, но адрес не обновлён. Продолжайте заполнение здесь или откройте персонажа из кабинета.'
      }
    }
  } catch (cause) {
    autosavePaused.value = true
    submitError.value =
      (cause instanceof Error ? cause.message : 'Не удалось сохранить черновик') +
      '\nАвтосохранение остановлено. Ввод оставлен в форме.'
    revisionConflict.value = cause instanceof CharacterEditorError && cause.status === 409
    uncertainCreation.value = wasNew && !(cause instanceof CharacterEditorError)
    if (uncertainCreation.value)
      submitError.value +=
        '\nОтвет о создании не получен: перед повтором проверьте кабинет в другой вкладке, чтобы не создать дубликат.'
    if (revisionConflict.value)
      submitError.value +=
        ' Перед продолжением скопируйте нужные правки и загрузите сохранённую версию.'
  } finally {
    saving.value = false
  }
}

function resumeAutosave(): void {
  if (revisionConflict.value) return
  if (
    uncertainCreation.value &&
    !window.confirm(
      'Проверьте кабинет в другой вкладке. Повторить создание, только если этот персонаж там не появился?',
    )
  )
    return
  autosavePaused.value = false
  void submit()
}
watch([() => fingerprint(), optionsReady, loading, saving, navigationPending], () => {
  clearTimeout(autosaveTimer)
  if (
    loading.value ||
    navigationPending.value ||
    saving.value ||
    autosavePaused.value ||
    !dirty.value ||
    !optionsReady.value ||
    !draft.value.name.trim() ||
    draft.value.rule_profile_id === null ||
    savedState.value?.character.status === 'ready'
  )
    return
  autosaveTimer = setTimeout(() => {
    void submit(false, true)
  }, 1800)
})
const saveStatus = computed(() =>
  saving.value
    ? 'Сохраняем…'
    : autosavePaused.value
      ? 'Сохранение остановлено — проверьте ошибку ниже.'
      : dirty.value
        ? 'Есть несохранённые изменения.'
        : savedState.value
          ? `Сохранено${savedAt.value ? ' в ' + savedAt.value : ''}.`
          : 'Новый персонаж ещё не сохранён.',
)
</script>

<template>
  <h1>{{ characterId === null ? 'Создание персонажа' : 'Редактор персонажа' }}</h1>
  <RouterLink to="/cabinet">К моим персонажам</RouterLink>
  <p>
    Черновик сохраняется автоматически после ввода имени и выбора мира. Незавершённые выборы можно
    оставить на потом. Кнопка сохранения также доступна.
  </p>
  <p class="save-status" role="status" aria-live="polite">{{ saveStatus }}</p>
  <p v-if="loading" role="status">Загружаем редактор…</p>
  <p v-if="pageError" role="alert">{{ pageError }}</p>
  <button
    v-if="characterId !== null"
    type="button"
    :disabled="loading || saving"
    @click="reloadSaved"
  >
    Загрузить сохранённый черновик вместо введённого
  </button>
  <fieldset
    v-if="!loading && !pageError && savedState?.character.status !== 'ready'"
    class="editor-mode"
    :disabled="saving"
  >
    <legend>Вид редактора</legend>
    <label
      ><input v-model="viewMode" type="radio" name="character-editor-view" value="full" />Полная
      карточка</label
    >
    <label
      ><input v-model="viewMode" type="radio" name="character-editor-view" value="steps" />Пошаговый
      вид</label
    >
  </fieldset>
  <nav
    v-if="!loading && !pageError && viewMode === 'steps'"
    class="editor-steps"
    aria-label="Шаги создания персонажа"
  >
    <ol>
      <li v-for="(title, index) in steps" :key="title">
        <button
          type="button"
          :disabled="saving"
          :aria-current="step === index + 1 ? 'step' : undefined"
          @click="step = index + 1"
        >
          {{ index + 1 }}. {{ title }}
        </button>
      </li>
    </ol>
  </nav>
  <form
    v-if="!loading && !pageError && savedState?.character.status !== 'ready'"
    class="character-form"
    novalidate
    @submit.prevent="submit(false)"
    :aria-busy="saving"
  >
    <fieldset v-show="isSectionVisible(1)" :disabled="saving">
      <legend>Основные сведения</legend>
      <label
        >Мир и правила<select v-model="draft.rule_profile_id" required>
          <option disabled :value="null">Выберите профиль</option>
          <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
            {{ profile.title }}
          </option>
        </select></label
      >
      <p>
        После смены мира выборы навыков класса и предыстории нужно заполнить заново. Базовые
        характеристики и ручной инвентарь сохраняются в форме.
      </p>
      <label>Имя персонажа<input v-model="draft.name" maxlength="255" required /></label>
      <p>
        <strong>Уровень: {{ draft.target_level }}</strong
        >. Первая версия — создание персонажа первого уровня.
      </p>
      <p v-if="draft.target_level !== 1" role="alert">
        Этот прежний черновик другого уровня сохранён. Его можно редактировать, но завершение пока
        доступно только для первого уровня.
      </p>
      <p>
        Характеристики: стандартный массив. Хиты от класса и Телосложения, модификаторы и владения
        рассчитываются автоматически. Варианты вида, класса и предыстории берутся из архива.
        Биографию, заметки и поля раздела «Лист» заполняйте вручную.
      </p>
      <label
        >Вид<select
          v-model="draft.species_entry_id"
          :disabled="draft.rule_profile_id === null || !optionsReady"
        >
          <option :value="null">Пока не выбран</option>
          <option
            v-if="
              draft.species_entry_id !== null &&
              !options?.entry_options.species.some((entry) => entry.id === draft.species_entry_id)
            "
            :value="draft.species_entry_id"
            disabled
          >
            Выбранный вид недоступен (ID {{ draft.species_entry_id }})
          </option>
          <option
            v-for="entry in options?.entry_options.species ?? []"
            :key="entry.id"
            :value="entry.id"
          >
            {{ entry.title }}
          </option>
        </select></label
      >
      <label
        >Класс<select
          v-model="draft.class_entry_id"
          :disabled="draft.rule_profile_id === null || !optionsReady"
        >
          <option :value="null">Пока не выбран</option>
          <option
            v-if="
              draft.class_entry_id !== null &&
              !options?.entry_options.class.some((entry) => entry.id === draft.class_entry_id)
            "
            :value="draft.class_entry_id"
            disabled
          >
            Выбранный класс недоступен (ID {{ draft.class_entry_id }})
          </option>
          <option
            v-for="entry in options?.entry_options.class ?? []"
            :key="entry.id"
            :value="entry.id"
          >
            {{ entry.title }}
          </option>
        </select></label
      >
      <label
        >Предыстория<select
          v-model="draft.background_entry_id"
          :disabled="draft.rule_profile_id === null || !optionsReady"
        >
          <option :value="null">Пока не выбрана</option>
          <option
            v-if="
              draft.background_entry_id !== null &&
              !options?.entry_options.background.some(
                (entry) => entry.id === draft.background_entry_id,
              )
            "
            :value="draft.background_entry_id"
            disabled
          >
            Выбранная предыстория недоступна (ID {{ draft.background_entry_id }})
          </option>
          <option
            v-for="entry in options?.entry_options.background ?? []"
            :key="entry.id"
            :value="entry.id"
          >
            {{ entry.title }}
          </option>
        </select></label
      >
    </fieldset>
    <template v-if="options"
      ><details
        v-for="(origin, key) in options.origins"
        v-show="isSectionVisible(1) && origin"
        :key="key"
      >
        <summary>{{ origin?.title }}</summary>
        <p>{{ origin?.body }}</p>
      </details></template
    >
    <p v-if="isSectionVisible(1) && options?.reference">
      Базовая скорость вида: {{ options.reference.species_speed_feet ?? 'не настроена' }} футов.
      Кость хитов класса:
      {{
        options.reference.hit_die_sides === null
          ? 'не настроена'
          : 'd' + options.reference.hit_die_sides
      }}.
    </p>
    <p v-if="optionsLoading" role="status">
      Загружаем варианты выбранного мира, класса и предыстории…
    </p>
    <p v-if="optionsError" role="alert">{{ optionsError }}</p>
    <button
      v-if="optionsError"
      type="button"
      :disabled="saving || optionsLoading"
      @click="loadOptions"
    >
      Повторить загрузку вариантов
    </button>
    <template v-if="options">
      <fieldset
        v-show="isSectionVisible(2)"
        class="ability-fields"
        :disabled="saving || !optionsReady"
      >
        <legend>Базовые характеристики</legend>
        <p>
          Стандартный массив: {{ options.ability_scores.available_values.join(', ') }}. Каждое
          значение используется не больше одного раза.
        </p>
        <label v-for="ability in options.ability_scores.abilities" :key="ability.code"
          >{{ ability.title }}
          <select v-model="draft.base_scores[ability.code]">
            <option :value="null">Пока не распределена</option>
            <option
              v-for="value in options.ability_scores.available_values"
              :key="value"
              :value="value"
              :disabled="
                Object.entries(draft.base_scores).some(
                  ([code, score]) => code !== ability.code && score === value,
                )
              "
            >
              {{ value }}
            </option>
          </select>
        </label>
      </fieldset>

      <fieldset
        v-show="isSectionVisible(2)"
        class="ability-fields"
        :disabled="saving || !optionsReady || !options.background_abilities.configured"
      >
        <legend>Увеличения характеристик от предыстории</legend>
        <p v-if="!options.background_abilities.configured">
          Выберите предысторию с тремя настроенными характеристиками. Распределение можно оставить
          незавершённым.
        </p>
        <p v-else>
          Распределено {{ increasesAssigned }} из {{ options.background_abilities.point_budget }}.
          Можно +2/+1 или +1/+1/+1.
        </p>
        <label v-for="ability in options.background_abilities.abilities" :key="ability.code"
          >{{ ability.title }}
          <select v-model="draft.background_increases[ability.code]">
            <option :value="0">Без увеличения</option>
            <option
              v-for="amount in options.background_abilities.max_per_ability"
              :key="amount"
              :value="amount"
            >
              +{{ amount }}
            </option>
          </select>
        </label>
      </fieldset>
      <fieldset
        v-show="isSectionVisible(3)"
        :disabled="saving || !optionsReady || !options.class_skills.configured"
      >
        <legend>Навыки класса</legend>
        <p v-if="!options.class_skills.configured">
          Выберите класс с настроенным правилом навыков. Незавершённый черновик можно сохранить без
          выбора навыков.
        </p>
        <CharacterClassSkillsFields
          v-model="draft.class_skill_entry_ids"
          :data="options.class_skills"
          :background-skill-ids="options.benefits.skills.map((skill) => skill.id)"
        />
      </fieldset>
      <fieldset v-show="isSectionVisible(3)" :disabled="saving || !optionsReady">
        <legend>Архивные особенности, черты и заклинания</legend>
        <CreationChoicesFields v-model="draft.creation_choices" :data="options.creation" />
      </fieldset>
      <fieldset
        v-show="isSectionVisible(4)"
        :disabled="saving || !optionsReady || !options.benefits.selection_valid"
      >
        <legend>Выдачи и выборы предыстории</legend>
        <CharacterBackgroundBenefitsFields
          v-model="draft.background_choices"
          :data="options.benefits"
          :preview-stale="dirty"
        />
      </fieldset>
      <fieldset
        v-if="inventoryOptions"
        v-show="isSectionVisible(5)"
        :disabled="saving || !optionsReady || draft.rule_profile_id === null"
      >
        <legend>Снаряжение</legend>
        <CharacterInventoryFields
          v-model="draft.inventory_items"
          :data="inventoryOptions"
          :background-stale="dirty"
        />
      </fieldset>

      <fieldset v-show="isSectionVisible(5)" :disabled="saving">
        <legend>Поля листа персонажа</legend>
        <SheetDetailsFields v-model="draft.sheet_details" />
      </fieldset>
    </template>
    <section v-show="isSectionVisible(6)">
      <h2>Проверка и завершение</h2>
      <p>
        При завершении сервер проверит все обязательные выборы и сохранит неизменяемый лист.
        Редактировать готовый лист игрок пока не может.
      </p>
      <ul v-if="savedState && !dirty">
        <li v-for="issue in savedState.readiness.issues" :key="issue">{{ issue }}</li>
      </ul>
      <p v-else>
        Можно сразу сохранить и завершить заполненный лист. Если данных не хватает, сервер покажет
        ошибки и оставит ввод в форме.
      </p>
      <button
        type="button"
        :disabled="saving || !optionsReady || autosavePaused || draft.target_level !== 1"
        @click="submit(true)"
      >
        Сохранить и завершить создание
      </button>
    </section>
    <div v-if="viewMode === 'steps'">
      <button type="button" :disabled="saving || step <= 1" @click="step--">Назад</button
      ><button type="button" :disabled="saving || step >= steps.length" @click="step++">
        Далее
      </button>
    </div>
    <button type="submit" :disabled="saving || !optionsReady || autosavePaused">
      {{ saving ? 'Сохраняем весь черновик…' : 'Сохранить черновик' }}
    </button>
    <p v-if="submitError" role="alert" class="error">{{ submitError }}</p>
    <button
      v-if="autosavePaused && !revisionConflict"
      type="button"
      :disabled="saving"
      @click="resumeAutosave"
    >
      Повторить сохранение
    </button>
    <p v-if="notice" role="status">{{ notice }}</p>
  </form>
  <RouterLink
    v-if="savedState?.character.status === 'ready'"
    :to="`/characters/${savedState.character.id}`"
    >Открыть готовый лист</RouterLink
  >
  <section v-if="savedState && !loading && !pageError" aria-labelledby="saved-sheet">
    <h2 id="saved-sheet">Расчёт по сохранённому черновику</h2>
    <p v-if="dirty || !optionsReady">Сохраните изменения всего черновика, чтобы обновить расчёт.</p>
    <template v-else>
      <h3>Характеристики</h3>
      <div class="table-scroll" role="region" aria-label="Характеристики персонажа" tabindex="0">
        <table>
          <thead>
            <tr>
              <th scope="col">Характеристика</th>
              <th scope="col">База</th>
              <th scope="col">Предыстория</th>
              <th scope="col">Итого</th>
              <th scope="col">Модификатор</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ability in savedState.ability_scores.abilities" :key="ability.code">
              <th scope="row">{{ ability.title }}</th>
              <td>{{ ability.base_score ?? 'Не распределена' }}</td>
              <td>+{{ ability.applied_increase }}</td>
              <td>{{ ability.total_score ?? 'Не рассчитано' }}</td>
              <td>
                {{
                  ability.total_modifier === null
                    ? 'Не рассчитан'
                    : ability.total_modifier >= 0
                      ? '+' + ability.total_modifier
                      : ability.total_modifier
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <CharacterSheetBasicsFields :data="savedState.sheet_basics" />
    </template>
  </section>
</template>

<style scoped>
.error {
  white-space: pre-wrap;
}
</style>
