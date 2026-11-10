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
import CharacterOriginPicker from '../../components/CharacterOriginPicker.vue'

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
    draft.value.rule_choices,
    draft.value.base_scores,
    draft.value.background_increases,
  ]),
)
const optionsReady = computed(
  () =>
    options.value !== null &&
    JSON.stringify(editorContext(options.value.context)) === contextKey.value &&
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
    rule_choices: { ...(state.creation.rule_selections ?? {}) },
    creation_choices: Object.fromEntries(
      Object.entries(state.creation.selections).map(([key, ids]) => [key, [...ids]]),
    ),
    equipment_choices: { ...(state.starting_packages?.selections ?? {}) },
    starting_equipped_ids: state.inventory.other_items
      .filter((item) => item.source_kind === 'creation' && item.is_equipped)
      .map((item) => item.item_entry_id),
    sheet_details: { ...state.sheet_details },
  }
  for (const group of state.creation.scalar_groups ?? []) draft.value.rule_choices[group.key] ??= []
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
      draft.value.rule_choices,
      draft.value.base_scores,
      draft.value.background_increases,
    )
    if (request !== optionsRequest || requestedKey !== optionsKey.value) return
    const previousGroups = new Set(options.value?.creation.groups.map((group) => group.key) ?? [])
    const wasClean = !dirty.value
    options.value = data
    for (const group of data.creation.scalar_groups) draft.value.rule_choices[group.key] ??= []
    for (const key of Object.keys(draft.value.creation_choices))
      if (previousGroups.has(key) && !data.creation.groups.some((group) => group.key === key))
        delete draft.value.creation_choices[key]
    for (const key of Object.keys(draft.value.rule_choices))
      if (!data.creation.scalar_groups.some((group) => group.key === key))
        delete draft.value.rule_choices[key]
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
  ) {
    draft.value.rule_choices = Object.fromEntries(
      Object.entries(draft.value.rule_choices).filter(([key]) => key === 'profile:languages'),
    )
    draft.value.creation_choices = {}
    draft.value.equipment_choices = {}
    draft.value.starting_equipped_ids = []
  }
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
  if (previous?.class_entry_id !== draft.value.class_entry_id) draft.value.subclass_entry_id = null
  if (previous?.species_entry_id !== draft.value.species_entry_id)
    draft.value.species_variant_entry_id = null
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
const liveAbilities = computed(() =>
  (options.value?.ability_scores.abilities ?? []).map((ability) => {
    const base = draft.value.base_scores[ability.code] ?? null
    const increase = draft.value.background_increases[ability.code] ?? 0
    const total =
      base === null
        ? null
        : Math.max(
            1,
            Math.min(
              20,
              base + increase + (options.value?.creation.bonuses?.['ability_' + ability.code] ?? 0),
            ),
          )
    return {
      code: ability.code,
      title: ability.title,
      base,
      increase,
      total,
      modifier: total === null ? null : Math.floor((total - 10) / 2),
    }
  }),
)
function signed(value: number | null): string {
  return value === null ? '—' : value >= 0 ? '+' + value : String(value)
}
const knownLanguages = computed(() =>
  [
    ...new Set(
      (options.value?.creation.traits ?? [])
        .filter((trait) => trait.kind === 'language')
        .map((trait) => trait.value),
    ),
    draft.value.sheet_details.languages,
  ]
    .filter(Boolean)
    .join(', '),
)
const sections = [
  'Основа',
  'Предыстория',
  'Раса',
  'Класс',
  'Характеристики',
  'Навыки',
  'Языки и инструменты',
  'Заклинания',
  'Снаряжение',
  'Характер персонажа',
  'Внешность',
]
</script>

<template>
  <div class="character-builder">
    <header class="builder-heading">
      <div>
        <p class="eyebrow">Мастерская персонажа</p>
        <h1>{{ characterId === null ? 'Создание персонажа' : 'Редактор персонажа' }}</h1>
        <p>
          От первой идеи до готового героя. Выберите происхождение, соберите способности и
          расскажите свою историю.
        </p>
      </div>
      <RouterLink to="/cabinet">Мои персонажи ↗</RouterLink>
    </header>
    <p v-if="loading" role="status">Загружаем редактор…</p>
    <p v-if="pageError" role="alert">{{ pageError }}</p>
    <template v-if="!loading && !pageError && savedState?.character.status !== 'ready'">
      <div class="builder-abilities" role="region" aria-label="Текущие характеристики персонажа">
        <div v-for="ability in liveAbilities" :key="ability.code">
          <span>{{ ability.title }}</span
          ><strong
            >{{ ability.total ?? '—' }} <small>({{ signed(ability.modifier) }})</small></strong
          >
        </div>
        <p v-if="!liveAbilities.length">Характеристики появятся после загрузки вариантов.</p>
      </div>
      <nav class="builder-index" aria-label="Разделы редактора">
        <a v-for="(title, index) in sections" :key="title" :href="`#builder-section-${index + 1}`"
          >{{ index + 1 }}. {{ title }}</a
        ><a href="#current-sheet">Текущий лист ↓</a>
      </nav>
      <p v-if="optionsLoading" role="status">Обновляем варианты и особенности…</p>
      <div v-if="optionsError" role="alert">
        <p>{{ optionsError }}</p>
        <button type="button" :disabled="saving || optionsLoading" @click="loadOptions">
          Повторить загрузку вариантов
        </button>
      </div>
      <form
        class="builder-form"
        novalidate
        :aria-busy="saving || optionsLoading"
        @submit.prevent="submit(false)"
      >
        <section id="builder-section-1" class="builder-card" aria-labelledby="builder-title-1">
          <div class="builder-card-heading">
            <h2 id="builder-title-1"><span>01</span> Основа</h2>
            <RouterLink to="/cabinet">Открыть персонажа</RouterLink>
          </div>
          <p class="builder-hint">
            Начните с имени. Дальше шаг за шагом соберите своего персонажа — незавершённые выборы
            можно оставить на потом.
          </p>
          <fieldset class="builder-fields two-columns" :disabled="saving">
            <label
              >Имя персонажа<input
                v-model="draft.name"
                maxlength="255"
                placeholder="Как зовут вашего героя?"
                required /></label
            ><label
              >Уровень<select :value="draft.target_level" disabled>
                <option :value="draft.target_level">{{ draft.target_level }}</option></select
              ><small>Сейчас доступно создание персонажей 1-го уровня.</small></label
            >
          </fieldset>
          <p v-if="draft.target_level !== 1" role="alert">
            Этот черновик другого уровня можно сохранить, но завершение доступно только для первого
            уровня.
          </p>
        </section>

        <section id="builder-section-2" class="builder-card" aria-labelledby="builder-title-2">
          <div class="builder-card-heading">
            <h2 id="builder-title-2"><span>02</span> Предыстория</h2>
          </div>
          <p class="builder-hint">
            Жизнь до приключений определяет начальные навыки, инструменты, черту происхождения и
            снаряжение. Мир меняет доступные варианты.
          </p>
          <fieldset class="builder-fields" :disabled="saving">
            <label
              >Мир и правила<select v-model="draft.rule_profile_id" required>
                <option disabled :value="null">Выберите мир</option>
                <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
                  {{ profile.title }}
                </option>
              </select></label
            >
            <small
              >После смены мира навыки класса и выборы предыстории потребуется выбрать
              заново.</small
            >
            <label
              >Предыстория<select
                v-model="draft.background_entry_id"
                :disabled="draft.rule_profile_id === null || !optionsReady"
              >
                <option :value="null">Выберите предысторию</option>
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
                  Недоступная предыстория №{{ draft.background_entry_id }}
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
          <article v-if="optionsReady && options?.origins.background_entry_id" class="builder-lore">
            <h3>{{ options.origins.background_entry_id.title }}</h3>
            <p>{{ options.origins.background_entry_id.body }}</p>
            <CharacterBackgroundBenefitsFields
              v-model="draft.background_choices"
              :data="options.benefits"
              :preview-stale="dirty"
              section="background"
            />
            <fieldset class="builder-fields" :disabled="saving || !optionsReady">
              <CreationChoicesFields
                v-model="draft.creation_choices"
                :data="options.creation"
                section="features"
                :source-id="draft.background_entry_id"
                :show-entries="false"
                :show-diagnostics="false"
              />
            </fieldset>
          </article>
          <p v-else>Выберите предысторию, чтобы увидеть её историю и особенности.</p>
        </section>

        <section id="builder-section-3" class="builder-card" aria-labelledby="builder-title-3">
          <h2 id="builder-title-3"><span>03</span> Раса</h2>
          <p class="builder-hint">
            Выберите вид персонажа слева. Справа появятся его описание и особенности из архива
            вашего мира.
          </p>
          <fieldset v-if="optionsReady && options?.creation.scalar_groups.length">
            <legend>Выборы происхождения и особенностей</legend>
            <div v-for="group in options.creation.scalar_groups" :key="group.key">
              <p>{{ group.source_title }}: {{ group.title }} — {{ group.count }}</p>
              <label v-for="option in group.options" :key="option.value" style="display: block"
                ><input
                  v-model="draft.rule_choices[group.key]"
                  type="checkbox"
                  :value="option.value"
                  @focus="draft.rule_choices[group.key] ??= []"
                />
                {{ option.title }}</label
              >
            </div>
          </fieldset>
          <CharacterOriginPicker
            v-model="draft.species_entry_id"
            title="Расы и виды"
            :entries="options?.entry_options.species ?? []"
            :origin="optionsReady ? (options?.origins.species_entry_id ?? null) : null"
            :disabled="saving || !optionsReady || draft.rule_profile_id === null"
            ><p v-if="optionsReady && options?.reference.species_speed_feet != null">
              Базовая скорость: {{ options.reference.species_speed_feet }} футов.
            </p></CharacterOriginPicker
          >
          <fieldset class="builder-fields" :disabled="saving">
            <p>Размер определяется видом и выбором его варианта.</p>
            <CreationChoicesFields
              v-if="options"
              v-model="draft.creation_choices"
              :data="options.creation"
              section="features"
              :source-id="draft.species_entry_id"
              :show-entries="false"
              :show-diagnostics="false"
            />
          </fieldset>
        </section>

        <section id="builder-section-4" class="builder-card" aria-labelledby="builder-title-4">
          <h2 id="builder-title-4"><span>04</span> Класс</h2>
          <p class="builder-hint">
            Класс определяет роль в приключениях, доступные способности и владения.
          </p>
          <label v-if="optionsReady && options?.entry_options.species_variant.length"
            >Вариант вида<select v-model="draft.species_variant_entry_id">
              <option :value="null">Выберите</option>
              <option
                v-for="variant in options.entry_options.species_variant"
                :key="variant.id"
                :value="variant.id"
              >
                {{ variant.title }}
              </option>
            </select></label
          >
          <CharacterOriginPicker
            v-model="draft.class_entry_id"
            title="Классы"
            :entries="options?.entry_options.class ?? []"
            :origin="optionsReady ? (options?.origins.class_entry_id ?? null) : null"
            :disabled="saving || !optionsReady || draft.rule_profile_id === null"
            ><p v-if="optionsReady && options?.reference.hit_die_sides != null">
              Кость хитов: d{{ options.reference.hit_die_sides }}.
            </p></CharacterOriginPicker
          >
          <fieldset v-if="options" class="builder-fields" :disabled="saving || !optionsReady">
            <CreationChoicesFields
              v-model="draft.creation_choices"
              :data="options.creation"
              section="features"
              :exclude-source-ids="[draft.species_entry_id ?? 0, draft.background_entry_id ?? 0]"
            />
          </fieldset>
        </section>

        <section id="builder-section-5" class="builder-card" aria-labelledby="builder-title-5">
          <h2 id="builder-title-5"><span>05</span> Характеристики</h2>
          <p class="builder-hint">
            Распределите стандартный массив между шестью характеристиками, затем добавьте увеличения
            от предыстории.
          </p>
          <template v-if="options"
            ><p>
              Стандартный массив: {{ options.ability_scores.available_values.join(', ') }}. Каждое
              значение можно использовать один раз.
            </p>
            <fieldset class="builder-fields ability-grid" :disabled="saving || !optionsReady">
              <label v-for="ability in options.ability_scores.abilities" :key="ability.code"
                >{{ ability.title
                }}<select v-model="draft.base_scores[ability.code]">
                  <option :value="null">Не выбрано</option>
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
                  </option></select
                ><small
                  >Итого:
                  {{
                    liveAbilities.find((item) => item.code === ability.code)?.total ?? '—'
                  }}</small
                ></label
              >
            </fieldset>
            <h3>Увеличения от предыстории</h3>
            <p v-if="!options.background_abilities.configured">
              Сначала выберите предысторию с настроенными увеличениями характеристик.
            </p>
            <p v-else>
              Распределено {{ increasesAssigned }} из
              {{ options.background_abilities.point_budget }}. Варианты: +2/+1 или +1/+1/+1.
            </p>
            <fieldset
              class="builder-fields ability-grid"
              :disabled="saving || !optionsReady || !options.background_abilities.configured"
            >
              <label v-for="ability in options.background_abilities.abilities" :key="ability.code"
                >{{ ability.title
                }}<select v-model="draft.background_increases[ability.code]">
                  <option :value="0">Без увеличения</option>
                  <option
                    v-for="amount in options.background_abilities.max_per_ability"
                    :key="amount"
                    :value="amount"
                  >
                    +{{ amount }}
                  </option>
                </select></label
              >
            </fieldset></template
          >
        </section>

        <section id="builder-section-6" class="builder-card" aria-labelledby="builder-title-6">
          <h2 id="builder-title-6"><span>06</span> Навыки</h2>
          <p class="builder-hint">
            Выберите, в чём ваш герой особенно хорош. Навыки от предыстории добавляются
            автоматически.
          </p>
          <fieldset v-if="options" class="builder-fields" :disabled="saving || !optionsReady">
            <h3>Навыки класса</h3>
            <p v-if="!options.class_skills.configured">Выберите класс с настроенными навыками.</p>
            <label v-if="optionsReady && options?.entry_options.subclass.length"
              >Подкласс<select v-model="draft.subclass_entry_id">
                <option :value="null">Выберите</option>
                <option
                  v-for="variant in options.entry_options.subclass"
                  :key="variant.id"
                  :value="variant.id"
                >
                  {{ variant.title }}
                </option>
              </select></label
            >
            <CharacterClassSkillsFields
              v-model="draft.class_skill_entry_ids"
              :data="options.class_skills"
              :background-skill-ids="options.benefits.skills.map((skill) => skill.id)"
            /><CreationChoicesFields
              v-model="draft.creation_choices"
              :data="options.creation"
              section="skills"
            />
          </fieldset>
        </section>

        <section id="builder-section-7" class="builder-card" aria-labelledby="builder-title-7">
          <h2 id="builder-title-7"><span>07</span> Языки и инструменты</h2>
          <p class="builder-hint">Языки и владения определяются происхождением и особенностями.</p>
          <fieldset class="builder-fields" :disabled="saving || !optionsReady">
            <CharacterBackgroundBenefitsFields
              v-if="options"
              v-model="draft.background_choices"
              :data="options.benefits"
              :preview-stale="!optionsReady"
              section="tools"
            />
            <CreationChoicesFields
              v-if="options"
              v-model="draft.creation_choices"
              :data="options.creation"
              section="tools"
              :show-entries="false"
              :show-diagnostics="false"
            />
          </fieldset>
        </section>
        <section id="builder-section-8" class="builder-card" aria-labelledby="builder-title-8">
          <h2 id="builder-title-8"><span>08</span> Заклинания</h2>
          <p class="builder-hint">
            Выберите заклинания класса и дополнительных источников. Магическая характеристика
            задаётся источником или вашим выбором.
          </p>
          <fieldset class="builder-fields" :disabled="saving || !optionsReady">
            <CreationChoicesFields
              v-if="options"
              v-model="draft.creation_choices"
              :data="options.creation"
              section="spells"
              :show-diagnostics="false"
            />
            <details>
              <summary>Ручные поправки листа</summary>
              <div class="builder-grid">
                <label
                  >Атака заклинанием (пусто — рассчитать)<input
                    v-model.number="draft.sheet_details.spell_attack_bonus"
                    type="number"
                    step="1"
                /></label>
                <label
                  >СЛ спасброска (пусто — рассчитать)<input
                    v-model.number="draft.sheet_details.spell_save_dc"
                    type="number"
                    min="1"
                    step="1"
                /></label>
              </div>
            </details>
          </fieldset>
        </section>

        <section id="builder-section-9" class="builder-card" aria-labelledby="builder-title-9">
          <h2 id="builder-title-9"><span>09</span> Снаряжение</h2>
          <p class="builder-hint">
            Выберите стартовый комплект предыстории и добавьте вещи, которые герой возьмёт в
            приключение.
          </p>
          <fieldset
            v-if="options?.starting_packages"
            class="builder-fields"
            :disabled="saving || !optionsReady"
          >
            <fieldset v-for="group in options.starting_packages.groups" :key="group.source_id">
              <legend>{{ group.source_title }} — стартовое снаряжение</legend>
              <label v-for="pack in group.options" :key="pack.id"
                ><input
                  v-model="draft.equipment_choices[String(group.source_id)]"
                  type="radio"
                  :name="'starting-package-' + group.source_id"
                  :value="pack.id"
                  :disabled="!pack.available"
                />{{ pack.title }} · {{ pack.money_cp / 100 }} зм
                <span v-if="!pack.available">(недоступен)</span></label
              >
              <ul
                v-for="pack in group.options.filter(
                  (pack) => pack.id === draft.equipment_choices[String(group.source_id)],
                )"
                :key="pack.id"
              >
                <li v-for="item in pack.items" :key="item.item_entry_id">
                  {{ item.title }} × {{ item.quantity }}
                </li>
              </ul>
              <button
                v-if="draft.equipment_choices[String(group.source_id)]"
                type="button"
                @click="delete draft.equipment_choices[String(group.source_id)]"
              >
                Очистить выбор
              </button>
            </fieldset>
            <p
              v-for="source in Object.keys(draft.equipment_choices).filter(
                (source) =>
                  !options?.starting_packages.groups.some(
                    (group) => String(group.source_id) === source,
                  ),
              )"
              :key="source"
            >
              Устаревший комплект.
              <button type="button" @click="delete draft.equipment_choices[source]">
                Убрать выбор
              </button>
            </p>
          </fieldset>
          <fieldset
            v-if="options"
            class="builder-fields"
            :disabled="saving || !optionsReady || draft.rule_profile_id === null"
          >
            <CharacterBackgroundBenefitsFields
              v-model="draft.background_choices"
              :data="options.benefits"
              :preview-stale="dirty"
              section="equipment"
            /><CharacterInventoryFields
              v-if="inventoryOptions"
              v-model="draft.inventory_items"
              v-model:starting-equipped-ids="draft.starting_equipped_ids"
              :data="inventoryOptions"
              :background-stale="dirty"
            />
          </fieldset>
        </section>

        <section id="builder-section-10" class="builder-card" aria-labelledby="builder-title-10">
          <h2 id="builder-title-10"><span>10</span> Характер персонажа</h2>
          <p class="builder-hint">
            Что делает героя узнаваемым? Расскажите о его прошлом, мотивации, привычках и слабостях.
            Пары ярких деталей достаточно для начала истории.
          </p>
          <fieldset class="builder-fields" :disabled="saving">
            <label
              >История и мотивация<textarea
                v-model="draft.sheet_details.biography"
                maxlength="10000"
                rows="5"
                placeholder="Кем был ваш герой и почему отправился в приключение?"
              ></textarea></label
            ><label
              >Черты характера и заметки<textarea
                v-model="draft.sheet_details.notes"
                maxlength="10000"
                rows="4"
                placeholder="Привычки, идеалы, привязанности и слабости"
              ></textarea>
            </label>
          </fieldset>
        </section>

        <section id="builder-section-11" class="builder-card" aria-labelledby="builder-title-11">
          <h2 id="builder-title-11"><span>11</span> Внешность</h2>
          <p class="builder-hint">
            Опишите то, что замечают при первой встрече: лицо, одежду, осанку или особую примету.
          </p>
          <fieldset class="builder-fields" :disabled="saving">
            <label
              >Краткое описание внешности<textarea
                v-model="draft.sheet_details.appearance"
                maxlength="10000"
                rows="4"
                placeholder="Как выглядит ваш персонаж?"
              ></textarea>
            </label>
          </fieldset>
        </section>

        <section
          id="current-sheet"
          class="builder-card current-sheet"
          aria-labelledby="current-sheet-title"
        >
          <p class="eyebrow">Ваш герой</p>
          <h2 id="current-sheet-title">Текущий лист персонажа</h2>
          <h3>{{ draft.name || 'Безымянный персонаж' }}</h3>
          <p>
            {{
              [
                options?.origins.species_entry_id?.title,
                options?.origins.class_entry_id?.title,
                options?.origins.background_entry_id?.title,
              ]
                .filter(Boolean)
                .join(' · ') || 'Выберите расу, класс и предысторию'
            }}
            · Уровень {{ draft.target_level }}
          </p>
          <div class="builder-abilities sheet-abilities">
            <div v-for="ability in liveAbilities" :key="ability.code">
              <span>{{ ability.title }}</span
              ><strong
                >{{ ability.total ?? '—' }} <small>({{ signed(ability.modifier) }})</small></strong
              >
            </div>
          </div>
          <p>
            Характеристики выше обновляются при вводе. Полный расчёт хитов, навыков и владений
            обновляется после сохранения черновика.
          </p>
          <fieldset class="builder-fields ability-grid" :disabled="saving">
            <label
              >КД (пусто — рассчитать по экипировке)<input
                v-model.number="draft.sheet_details.armor_class"
                type="number"
                min="1"
                max="2147483647"
                step="1" /></label
            ><label
              >Скорость, футов<input
                v-model.number="draft.sheet_details.speed_feet"
                type="number"
                min="0"
                max="2147483647"
                step="1" /></label
            ><label
              >Инициатива<input
                v-model.number="draft.sheet_details.initiative"
                type="number"
                min="-2147483648"
                max="2147483647"
                step="1"
            /></label>
          </fieldset>
          <dl v-if="savedState && !dirty && optionsReady">
            <dt>КД / скорость / инициатива</dt>
            <dd>
              {{ savedState.sheet_basics.armor_class ?? '—' }} /
              {{ savedState.sheet_basics.speed_feet ?? '—' }} фт. /
              {{ signed(savedState.sheet_basics.initiative ?? null) }}
            </dd>
            <dt>Атака заклинанием / СЛ</dt>
            <dd>
              {{ signed(savedState.sheet_basics.spell_attack_bonus ?? null) }} /
              {{ savedState.sheet_basics.spell_save_dc ?? '—' }}
            </dd>
            <dt>Размер и языки</dt>
            <dd>
              {{ savedState.sheet_basics.size || '—' }} ·
              {{ savedState.sheet_basics.languages || '—' }}
            </dd>
          </dl>
          <ul v-if="options?.creation.traits?.length">
            <li v-for="(trait, index) in options.creation.traits" :key="index">
              {{ trait.value }} · {{ trait.source_title }}
            </li>
          </ul>
          <CharacterSheetBasicsFields
            v-if="savedState && !dirty && optionsReady"
            :data="savedState.sheet_basics"
          />
          <div class="two-columns sheet-overview">
            <div>
              <h3>Навыки и языки</h3>
              <ul v-if="options">
                <li v-for="skill in options.benefits.skills" :key="skill.id">
                  {{ skill.title }} · предыстория
                </li>
                <li
                  v-for="skill in options.class_skills.options.filter((skill) =>
                    draft.class_skill_entry_ids.includes(skill.id),
                  )"
                  :key="skill.id"
                >
                  {{ skill.title }} · класс
                </li>
              </ul>
              <p>Языки: {{ knownLanguages || 'Пока не указаны' }}</p>
              <p>
                Инструмент:
                {{
                  options?.benefits.tool.options.find(
                    (tool) => tool.id === draft.background_choices.tool_entry_id,
                  )?.title ||
                  options?.benefits.tool.effective_tool?.title ||
                  'Пока не выбран'
                }}
              </p>
            </div>
            <div>
              <h3>Снаряжение</h3>
              <p v-if="!draft.inventory_items.length">Личные предметы пока не добавлены.</p>
              <ul>
                <li v-for="(item, index) in draft.inventory_items" :key="item.id ?? index">
                  {{
                    inventoryOptions?.item_options.find(
                      (option) => option.id === item.item_entry_id,
                    )?.title || 'Не выбран'
                  }}
                  × {{ item.quantity }}
                </li>
              </ul>
              <p>
                Предыстория:
                {{
                  draft.background_choices.equipment_choice === 'package'
                    ? 'Комплект А'
                    : draft.background_choices.equipment_choice === 'money'
                      ? 'Деньги Б'
                      : 'Снаряжение не выбрано'
                }}
              </p>
            </div>
          </div>
          <details v-if="options">
            <summary>Особенности и заклинания</summary>
            <ul>
              <li v-for="entry in options.creation.entries" :key="entry.id">{{ entry.title }}</li>
            </ul>
            <div v-for="group in options.creation.groups" :key="group.key">
              <p v-if="draft.creation_choices[group.key]?.length">
                {{ group.title }}:
                {{
                  group.options
                    .filter((entry) => draft.creation_choices[group.key]?.includes(entry.id))
                    .map((entry) => entry.title)
                    .join(', ')
                }}
              </p>
            </div>
          </details>
          <fieldset class="builder-fields" :disabled="saving">
            <label
              >Ресурсы и счётчики<textarea
                v-model="draft.sheet_details.resources"
                maxlength="10000"
                placeholder="Способности с ограниченным числом использований и условия их восстановления"
              ></textarea>
            </label>
          </fieldset>
          <details v-if="savedState && !dirty">
            <summary>Что осталось заполнить</summary>
            <ul v-if="savedState.readiness.issues.length">
              <li v-for="issue in savedState.readiness.issues" :key="issue">{{ issue }}</li>
            </ul>
            <p v-else>Обязательные выборы заполнены. Можно завершить создание.</p>
          </details>
          <p>
            При завершении проверяются обязательные выборы. Готовый лист сохраняется; редактирование
            завершённого листа пока недоступно.
          </p>
          <button
            type="button"
            :disabled="saving || !optionsReady || autosavePaused || draft.target_level !== 1"
            @click="submit(true)"
          >
            Завершить создание персонажа
          </button>
        </section>
        <div v-if="submitError" role="alert" class="builder-error">
          <p>{{ submitError }}</p>
          <button
            v-if="autosavePaused && !revisionConflict"
            type="button"
            :disabled="saving"
            @click="resumeAutosave"
          >
            Повторить сохранение
          </button>
        </div>
        <p v-if="notice" role="status">{{ notice }}</p>
        <footer class="builder-savebar">
          <div>
            <strong>Черновик персонажа</strong>
            <p class="save-status" role="status" aria-live="polite">{{ saveStatus }}</p>
            <small>Автосохранение после ввода имени и выбора мира.</small>
          </div>
          <div class="builder-actions">
            <button
              v-if="characterId !== null"
              type="button"
              :disabled="saving || loading"
              @click="reloadSaved"
            >
              Загрузить сохранённый</button
            ><button type="submit" :disabled="saving || !optionsReady || autosavePaused">
              {{ saving ? 'Сохраняем…' : 'Сохранить черновик' }}
            </button>
          </div>
        </footer>
      </form>
    </template>
    <RouterLink
      v-if="savedState?.character.status === 'ready'"
      :to="`/characters/${savedState.character.id}`"
      >Открыть готовый лист</RouterLink
    >
  </div>
</template>

<style scoped>
.character-builder {
  --builder-radius: 16px;
}
.builder-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.5rem;
  margin-bottom: 1.75rem;
}
.builder-heading h1 {
  margin: 0.4rem 0 0.75rem;
}
.builder-heading p:not(.eyebrow) {
  max-width: 46rem;
  color: var(--muted);
}
.builder-heading > a {
  flex-shrink: 0;
  margin-top: 0.5rem;
  font-size: 0.85rem;
}
.builder-abilities {
  position: sticky;
  top: 0.5rem;
  z-index: 10;
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.5rem;
  padding: 0.6rem;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--lavender);
  box-shadow: 0 5px 20px #35243e0a;
}
.builder-abilities > div {
  text-align: center;
  padding: 0.7rem 0.2rem;
  border: 1px solid var(--line);
  border-radius: 9px;
  background: var(--paper);
}
.builder-abilities span {
  display: block;
  font-size: 0.61rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted);
}
.builder-abilities strong {
  display: block;
  font-size: 1.3rem;
  margin-top: 0.25rem;
  color: var(--accent);
}
.builder-abilities small {
  font-size: 0.85rem;
}
.builder-index {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem 1rem;
  margin: 1.25rem 0 1.75rem;
}
.builder-index a {
  font-size: 0.75rem;
  color: var(--muted);
  text-decoration: none;
}
.builder-index a:hover {
  color: var(--accent);
  text-decoration: underline;
}
.builder-card {
  margin-block: 1.25rem;
  padding: clamp(1.15rem, 2.5vw, 2rem);
  border: 1px solid var(--line);
  border-radius: var(--builder-radius);
  background: var(--paper);
  box-shadow: 0 4px 18px #35243e04;
  scroll-margin-top: 8rem;
}
.builder-card h2 {
  display: flex;
  gap: 0.8rem;
  align-items: baseline;
  margin: 0 0 1rem;
  font-size: 1.65rem;
}
.builder-card h2 > span {
  font: 500 0.8rem var(--font-body);
  color: var(--accent);
  opacity: 0.7;
}
.builder-card-heading {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
}
.builder-card-heading > a {
  font-size: 0.8rem;
}
.builder-hint {
  background: var(--lavender);
  border-left: 2px solid var(--gold);
  padding: 0.8rem 1rem;
  border-radius: 0 8px 8px 0;
  font-size: 0.87rem;
  color: var(--muted);
  margin: 0 0 1.4rem;
}
.builder-fields {
  display: grid;
  gap: 1rem;
  border: 0;
  padding: 0;
  margin: 1rem 0 0;
  min-width: 0;
  background: transparent;
}
.builder-fields label {
  display: grid;
  gap: 0.4rem;
  margin: 0;
  min-width: 0;
}
.builder-fields small {
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 400;
}
.two-columns {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.25rem;
}
.ability-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.builder-lore > p {
  white-space: pre-wrap;
}
.builder-fields :deep(label:has(input[type='checkbox'], input[type='radio'])) {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}
.builder-fields :deep(fieldset) {
  min-width: 0;
}
.builder-fields :deep(p) {
  margin-block: 0.25rem 0.75rem;
}
.current-sheet {
  background: var(--lavender);
}
.sheet-abilities {
  position: static;
  box-shadow: none;
  padding: 0;
  border: 0;
  margin: 1.5rem 0;
}
.builder-savebar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.25rem;
  box-shadow: 0 4px 24px #35243e14;
}
.builder-savebar strong {
  font-size: 0.85rem;
}
.builder-savebar p {
  font-size: 0.8rem;
  margin: 0.2rem 0;
}
.builder-savebar small {
  color: var(--muted);
  font-size: 0.72rem;
}
.builder-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.builder-actions button {
  font-size: 0.8rem;
}
.builder-actions button[type='submit'] {
  background: var(--ink);
  color: var(--on-ink);
}
.builder-error {
  white-space: pre-wrap;
  border-left: 3px solid var(--danger);
  padding: 1rem;
}
@media (max-width: 760px) {
  .builder-heading {
    display: block;
  }
  .builder-abilities {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    position: static;
  }
  .builder-savebar {
    position: static;
    flex-direction: column;
    align-items: stretch;
  }
  .builder-actions button {
    flex: 1;
  }
  .builder-card {
    scroll-margin-top: 1rem;
  }
}
@media (max-width: 540px) {
  .two-columns,
  .ability-grid {
    grid-template-columns: 1fr;
  }
  .builder-card-heading {
    display: block;
  }
  .builder-card-heading > a {
    display: inline-block;
    margin-bottom: 1rem;
  }
}
</style>
