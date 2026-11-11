<script setup lang="ts">
import SpeciesEditorFields from '../../components/SpeciesEditorFields.vue'
import ClassEditorFields from '../../components/ClassEditorFields.vue'
import SpellEditorFields from '../../components/SpellEditorFields.vue'
import { emptySpeciesCard } from '../../api/speciesCard'
import { emptyClassCard } from '../../api/classCard'
import { useRulebook } from '../../composables/useRulebook'
import EntryMechanicsFields from '../../components/EntryMechanicsFields.vue'
import ItemRuleFields from '../../components/ItemRuleFields.vue'
import { useEntryMechanics } from '../../composables/useEntryMechanics'
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getRuleProfiles, type RuleProfile } from '../../api/ruleProfiles'
import {
  getCatalogEntryTypes,
  type CatalogEntryType,
  createCatalogEntry,
  type NewCatalogEntry,
  type CatalogEntry,
} from '../../api/catalogEntries'
import { getContentSourceOptions, type ContentSourceOption } from '../../api/contentSources'
import BackgroundAbilityFields from '../../components/BackgroundAbilityFields.vue'
import BackgroundSkillFields from '../../components/BackgroundSkillFields.vue'
import SkillAbilityField from '../../components/SkillAbilityField.vue'
import FeatCategoryField from '../../components/FeatCategoryField.vue'
import BackgroundFeatField from '../../components/BackgroundFeatField.vue'
import BackgroundToolFields from '../../components/BackgroundToolFields.vue'
import ItemDefinitionFields from '../../components/ItemDefinitionFields.vue'
import {
  getBasicRuleOptions,
  getBasicRuleInput,
  type BasicRuleOptions,
  type BasicRuleDraft,
} from '../../api/basicRules'
import BackgroundStartingEquipmentFields from '../../components/BackgroundStartingEquipmentFields.vue'
import { getStartingEquipmentInput, type StartingEquipmentDraft } from '../../api/startingEquipment'
import {
  getItemDefinitionOptions,
  getItemDefinitionInput,
  getBackgroundToolInput,
  type ItemDefinitionDraft,
  type ItemDefinitionOptions,
  type BackgroundToolDraft,
} from '../../api/itemDefinitions'
import { getFeatCategories, type FeatCategory } from '../../api/featCategories'
import { getAbilityDefinitions, type AbilityDefinition } from '../../api/abilityDefinitions'
import {
  getNewBackgroundAbilityOptions,
  type BackgroundAbilityOptions,
} from '../../api/backgroundAbilityOptions'

import CreationRuleFields from '../../components/CreationRuleFields.vue'
import { useCreationRuleFields } from '../../composables/useCreationRuleFields'

import { useFormChanges } from '../../composables/useFormChanges'
import { afterSuccessfulSave } from '../../composables/useUnsavedChanges'

const router = useRouter()
const speciesCard = ref(emptySpeciesCard())
const classCard = ref(emptyClassCard())

const form = ref({
  type: '',
  title: '',
  body: '',
})

const profiles = ref<RuleProfile[]>([])
const optionsLoading = ref(true)
const optionsError = ref('')
const entryTypes = ref<CatalogEntryType[]>([])
const sourceOptions = ref<ContentSourceOption[]>([])
const ruleProfileId = ref<number | null>(null)
const sourceId = ref<number | null>(null)
const saving = ref(false)
const submitError = ref('')
const createdEntry = ref<CatalogEntry | null>(null)
const abilityRules = ref<BackgroundAbilityOptions | null>(null)
const abilityCodes = ref<string[]>([])
const skillEntryIds = ref<Array<number | null>>([])
const rulesLoading = ref(false)
const rulesError = ref('')
const skillAbilityCode = ref('')
const skillAbilities = ref<AbilityDefinition[]>([])
const skillLoading = ref(false)
const skillError = ref('')
const featCategoryCode = ref('')
const featCategories = ref<FeatCategory[]>([])
const featLoading = ref(false)
const featError = ref('')
const featEntryId = ref<number | null>(null)
const itemFields = ref<ItemDefinitionDraft>({
  item_kind_code: '',
  tool_group_code: '',
  tool_ability_code: '',
})
const itemOptions = ref<ItemDefinitionOptions | null>(null)
const itemLoading = ref(false)
const itemError = ref('')
const backgroundTool = ref<BackgroundToolDraft>({ tool_entry_id: null, tool_group_code: null })
const startingEquipment = ref<StartingEquipmentDraft | null>(null)
const basicRuleOptions = ref<BasicRuleOptions | null>(null)
const basicRuleFields = ref<BasicRuleDraft>({
  species_speed_feet: '',
  class_hit_die_sides: null,
  class_saving_throw_codes: [],
  class_skill_choice_count: '',
  class_skill_entry_ids: [],
})
const basicRulesLoading = ref(false)
const basicRulesError = ref('')
const creation = useCreationRuleFields(
  () => ruleProfileId.value,
  () => form.value.type,
)

const mechanics = useEntryMechanics(
  () => ruleProfileId.value,
  () => form.value.type,
)
const rulebook = useRulebook(
  () => ruleProfileId.value,
  () => form.value.type,
)

const changes = useFormChanges(
  () => [
    form.value,
    speciesCard.value,
    classCard.value,
    abilityCodes.value,
    skillEntryIds.value,
    skillAbilityCode.value,
    featCategoryCode.value,
    featEntryId.value,
    itemFields.value,
    backgroundTool.value,
    startingEquipment.value,
    basicRuleFields.value,
    creation.fields.value,
    mechanics.fields.value,
    rulebook.fields.value,
    ruleProfileId.value,
    sourceId.value,
  ],
  () => saving.value,
)

async function loadBasicRuleOptions(): Promise<void> {
  if (basicRulesLoading.value || saving.value) return
  basicRulesLoading.value = true
  basicRulesError.value = ''
  const requestedProfileId = ruleProfileId.value
  try {
    const options = await getBasicRuleOptions(requestedProfileId)
    if (requestedProfileId !== ruleProfileId.value) return
    basicRuleOptions.value = options
    const codes = basicRuleFields.value.class_saving_throw_codes
    basicRuleFields.value.class_saving_throw_codes = Array.from(
      { length: basicRuleOptions.value.required_saving_throw_count },
      (_, index) => codes[index] ?? '',
    )
  } catch (cause) {
    if (requestedProfileId === ruleProfileId.value)
      basicRulesError.value =
        cause instanceof Error
          ? cause.message
          : 'Не удалось загрузить базовые свойства вида и класса'
  } finally {
    basicRulesLoading.value = false
    if (
      requestedProfileId !== ruleProfileId.value &&
      (form.value.type === 'species' || form.value.type === 'class')
    )
      void loadBasicRuleOptions()
  }
}

async function loadItemOptions(): Promise<void> {
  if (itemLoading.value || saving.value) return
  itemLoading.value = true
  itemError.value = ''
  try {
    itemOptions.value = await getItemDefinitionOptions()
  } catch (cause) {
    itemError.value =
      cause instanceof Error ? cause.message : 'Не удалось загрузить свойства предметов'
  } finally {
    itemLoading.value = false
  }
}

async function loadFeatCategories(): Promise<void> {
  if (featLoading.value || saving.value) return
  featLoading.value = true
  featError.value = ''
  try {
    featCategories.value = await getFeatCategories()
  } catch (cause) {
    featError.value = cause instanceof Error ? cause.message : 'Не удалось загрузить категории черт'
  } finally {
    featLoading.value = false
  }
}

async function loadSkillAbilityOptions(): Promise<void> {
  if (skillLoading.value || saving.value) return
  skillLoading.value = true
  skillError.value = ''
  try {
    skillAbilities.value = await getAbilityDefinitions()
  } catch (cause) {
    skillError.value =
      cause instanceof Error ? cause.message : 'Не удалось загрузить характеристики навыка'
  } finally {
    skillLoading.value = false
  }
}

async function loadAbilityOptions() {
  if (rulesLoading.value || saving.value) return
  const requestedProfileId = ruleProfileId.value
  rulesLoading.value = true
  rulesError.value = ''
  try {
    const options = await getNewBackgroundAbilityOptions(requestedProfileId)
    if (requestedProfileId !== ruleProfileId.value) return
    abilityRules.value = options
    if (abilityCodes.value.length === 0) abilityCodes.value = Array(options.required_count).fill('')
    if (skillEntryIds.value.length === 0)
      skillEntryIds.value = Array(options.required_skill_count).fill(null)
    if (startingEquipment.value === null)
      startingEquipment.value = { ...options.starting_equipment_defaults, items: [] }
  } catch (cause) {
    if (requestedProfileId === ruleProfileId.value) {
      rulesError.value =
        cause instanceof Error ? cause.message : 'Не удалось загрузить поля предыстории'
    }
  } finally {
    rulesLoading.value = false
    if (form.value.type === 'background' && requestedProfileId !== ruleProfileId.value)
      void loadAbilityOptions()
  }
}

watch(
  () => form.value.type,
  (type) => {
    if (type === 'species' && basicRuleFields.value.species_speed_feet === '')
      basicRuleFields.value.species_speed_feet = 30
    if (type === 'background') void loadAbilityOptions()
    if (type === 'skill' && skillAbilities.value.length === 0) void loadSkillAbilityOptions()
    if (type === 'feat' && featCategories.value.length === 0) void loadFeatCategories()
    if (type === 'item' && itemOptions.value === null) void loadItemOptions()
    if ((type === 'species' || type === 'class') && basicRuleOptions.value === null)
      void loadBasicRuleOptions()
  },
)

watch(ruleProfileId, () => {
  if (form.value.type === 'background') void loadAbilityOptions()
  basicRuleOptions.value = null
  if (form.value.type === 'species' || form.value.type === 'class') void loadBasicRuleOptions()
})

onMounted(async () => {
  try {
    const [loadedProfiles, loadedSources, loadedTypes] = await Promise.all([
      getRuleProfiles(),
      getContentSourceOptions(),
      getCatalogEntryTypes(),
    ])
    profiles.value = loadedProfiles
    sourceOptions.value = loadedSources
    entryTypes.value = loadedTypes
  } catch (cause) {
    optionsError.value =
      cause instanceof Error ? cause.message : 'Не удалось загрузить данные формы'
  } finally {
    optionsLoading.value = false
  }
})

async function submit() {
  if (
    saving.value ||
    createdEntry.value !== null ||
    optionsLoading.value ||
    optionsError.value !== ''
  ) {
    return
  }

  submitError.value = ''

  if (form.value.type === '') {
    submitError.value = 'Выберите категорию записи'
    return
  }

  if (ruleProfileId.value === null) {
    submitError.value = 'Выберите мир записи'
    return
  }

  if (
    form.value.type === 'background' &&
    (rulesLoading.value || rulesError.value || abilityRules.value === null)
  )
    return

  if (
    form.value.type === 'skill' &&
    (skillLoading.value || skillError.value !== '' || skillAbilities.value.length === 0)
  )
    return

  if (
    form.value.type === 'feat' &&
    (featLoading.value || featError.value !== '' || featCategories.value.length === 0)
  )
    return

  if (form.value.type === 'feat' && featCategoryCode.value === '') {
    submitError.value = 'Выберите категорию черты'
    return
  }

  if (!rulebook.ready.value) {
    submitError.value = rulebook.error.value || 'Дождитесь загрузки правил книги'
    return
  }
  if (!mechanics.ready.value) {
    submitError.value = mechanics.error.value || 'Дождитесь загрузки механики'
    return
  }
  if (!creation.ready.value) {
    submitError.value = 'Дождитесь загрузки правил выдач или повторите загрузку'
    return
  }
  if (creation.input.value === null) {
    submitError.value = 'Проверьте фиксированные выдачи и группы выбора'
    return
  }

  const itemInput =
    itemOptions.value === null ? null : getItemDefinitionInput(itemFields.value, itemOptions.value)
  if (form.value.type === 'item') {
    if (itemLoading.value || itemError.value !== '' || itemOptions.value === null) return
    if (itemInput === null) {
      submitError.value = 'Заполните свойства выбранного вида предмета'
      return
    }
  }

  const basicRuleInput =
    basicRuleOptions.value === null
      ? null
      : getBasicRuleInput(form.value.type, basicRuleFields.value, basicRuleOptions.value)
  if (form.value.type === 'species' || form.value.type === 'class') {
    if (basicRulesLoading.value || basicRulesError.value !== '' || basicRuleOptions.value === null)
      return
    if (basicRuleInput === null) {
      submitError.value = 'Заполните базовые свойства выбранного вида или класса'
      return
    }
  }
  const toolInput = getBackgroundToolInput(backgroundTool.value)
  const equipmentInput =
    abilityRules.value === null
      ? null
      : getStartingEquipmentInput(
          startingEquipment.value,
          abilityRules.value.equipment_max_items,
          abilityRules.value.equipment_integer_max,
        )
  const selectedSkills: number[] = []
  if (form.value.type === 'background') {
    if (equipmentInput === null) {
      submitError.value = 'Проверьте суммы, выбранные предметы и количества стартового комплекта'
      return
    }
    if (toolInput === null) {
      submitError.value = 'Выберите конкретный инструмент или группу инструментов'
      return
    }
    if (featEntryId.value === null) {
      submitError.value = 'Выберите черту происхождения'
      return
    }
    for (const id of skillEntryIds.value) {
      if (id === null) {
        submitError.value = 'Выберите все навыки предыстории'
        return
      }
      selectedSkills.push(id)
    }
    if (selectedSkills.length !== abilityRules.value?.required_skill_count) {
      submitError.value = 'Выберите все навыки предыстории'
      return
    }
  }

  const input: NewCatalogEntry = {
    ...(form.value.type === 'species' ? { species_card: speciesCard.value } : {}),
    ...(form.value.type === 'class' ? { class_card: classCard.value } : {}),
    mechanics: mechanics.input.value,
    rulebook: rulebook.fields.value,
    ...(creation.input.value === undefined ? {} : { creation_rules: creation.input.value }),
    type: form.value.type,
    title: form.value.title,
    body: form.value.body,
    rule_profile_id: ruleProfileId.value,
    source_id: sourceId.value,
    ...(form.value.type === 'background' &&
    featEntryId.value !== null &&
    toolInput !== null &&
    equipmentInput !== null
      ? {
          ability_codes: [...abilityCodes.value],
          skill_entry_ids: selectedSkills,
          feat_entry_id: featEntryId.value,
          background_tool: toolInput,
          starting_equipment: equipmentInput,
        }
      : {}),
    ...(form.value.type === 'skill' ? { skill_ability_code: skillAbilityCode.value } : {}),
    ...(form.value.type === 'feat' ? { feat_category_code: featCategoryCode.value } : {}),
    ...(form.value.type === 'item' && itemInput !== null ? itemInput : {}),
    ...((form.value.type === 'species' || form.value.type === 'class') && basicRuleInput !== null
      ? basicRuleInput
      : {}),
  }

  saving.value = true

  try {
    createdEntry.value = await createCatalogEntry(input)
    changes.markSaved()

    const failure = await afterSuccessfulSave(() => router.replace('/admin/archive/entries'))

    if (failure) {
      submitError.value = 'Черновик создан, но переход отменён. Перейдите к списку записей.'
    }
  } catch (cause) {
    submitError.value =
      createdEntry.value !== null
        ? 'Черновик создан, но не удалось открыть список. Перейдите к списку записей.'
        : cause instanceof Error
          ? cause.message
          : 'Не удалось сохранить запись'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <h1>Новая запись архива</h1>

  <RouterLink to="/admin/archive/entries">К списку записей</RouterLink>

  <p v-if="optionsLoading">Загружаем данные формы…</p>
  <p v-else-if="optionsError" role="alert">{{ optionsError }}</p>

  <form @submit.prevent="submit">
    <fieldset :disabled="saving || createdEntry !== null || optionsLoading || optionsError !== ''">
      <label>
        Категория
        <select v-model="form.type" :disabled="optionsLoading || optionsError !== ''" required>
          <option disabled value="">Выберите категорию</option>

          <option v-for="entryType in entryTypes" :key="entryType.code" :value="entryType.code">
            {{ entryType.title }}
          </option>
        </select>
      </label>

      <template v-if="form.type !== ''">
        <label>
          Название
          <input v-model="form.title" required maxlength="255" />
        </label>

        <label>
          Мир / набор правил
          <select
            v-model="ruleProfileId"
            :disabled="optionsLoading || optionsError !== ''"
            required
          >
            <option disabled :value="null">Выберите мир</option>

            <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
              {{ profile.title }}
            </option>
          </select>
        </label>

        <label>
          Источник
          <select v-model="sourceId" :disabled="optionsLoading || optionsError !== ''">
            <option :value="null">Без источника</option>

            <option v-for="source in sourceOptions" :key="source.id" :value="source.id">
              {{ source.title }}
            </option>
          </select>
        </label>

        <label v-if="!['species', 'class', 'spell'].includes(form.type)">
          Описание
          <textarea v-model="form.body"></textarea>
        </label>

        <section v-if="form.type === 'background'">
          <p v-if="rulesLoading" role="status">Загружаем настройки предыстории…</p>
          <p v-if="rulesError" role="alert">
            {{ rulesError }}
            <button type="button" @click="loadAbilityOptions">Повторить загрузку</button>
          </p>
          <BackgroundAbilityFields
            v-if="abilityRules"
            v-model="abilityCodes"
            :options="abilityRules"
          />
          <BackgroundSkillFields
            v-if="abilityRules"
            v-model="skillEntryIds"
            :options="abilityRules"
          />
          <BackgroundFeatField v-if="abilityRules" v-model="featEntryId" :options="abilityRules" />
          <BackgroundToolFields
            v-if="abilityRules"
            v-model="backgroundTool"
            :options="abilityRules"
          />
          <BackgroundStartingEquipmentFields
            v-if="abilityRules && startingEquipment"
            v-model="startingEquipment"
            :options="abilityRules"
          />
        </section>

        <section v-if="form.type === 'skill'">
          <p v-if="skillLoading" role="status">Загружаем характеристики…</p>
          <p v-if="skillError" role="alert">
            {{ skillError }}
            <button type="button" @click="loadSkillAbilityOptions">Повторить загрузку</button>
          </p>
          <SkillAbilityField
            v-if="skillAbilities.length > 0"
            v-model="skillAbilityCode"
            :options="skillAbilities"
          />
        </section>

        <section v-if="form.type === 'feat'">
          <p v-if="featLoading" role="status">Загружаем категории черт…</p>
          <p v-if="featError" role="alert">
            {{ featError }}
            <button type="button" @click="loadFeatCategories">Повторить загрузку</button>
          </p>
          <FeatCategoryField
            v-if="featCategories.length > 0"
            v-model="featCategoryCode"
            :options="featCategories"
          />
        </section>

        <section v-if="form.type === 'species' || form.type === 'class'">
          <p v-if="basicRulesLoading" role="status">Загружаем базовые свойства…</p>
          <p v-if="basicRulesError" role="alert">
            {{ basicRulesError }}
            <button type="button" @click="loadBasicRuleOptions">Повторить загрузку</button>
          </p>
        </section>
        <section v-if="form.type === 'item'">
          <p v-if="itemLoading" role="status">Загружаем свойства предметов…</p>
          <p v-if="itemError" role="alert">
            {{ itemError }}
            <button type="button" @click="loadItemOptions">Повторить загрузку</button>
          </p>
          <ItemDefinitionFields v-if="itemOptions" v-model="itemFields" :options="itemOptions" />
        </section>

        <p v-if="creation.loading.value" role="status">Загружаем варианты выдач…</p>
        <p v-if="creation.error.value" role="alert">
          {{ creation.error.value }}
          <button
            type="button"
            :disabled="creation.loading.value || saving"
            @click="creation.load()"
          >
            Повторить
          </button>
        </p>
        <CreationRuleFields
          v-if="
            !['species', 'class'].includes(form.type) &&
            creation.ready.value &&
            creation.supported.value &&
            creation.options.value
          "
          v-model="creation.fields.value"
          :options="creation.options.value"
        />

        <p v-if="rulebook.error.value" role="alert">
          {{ rulebook.error.value }}
          <button type="button" @click="rulebook.load()">Повторить</button>
        </p>
        <section v-if="!['species', 'class', 'spell', 'item'].includes(form.type) && rulebook.options.value" class="rules-check">
          <h2>Проверка правил</h2>
          <p>Описание и указанные выше выдачи — единственные правила этой записи. Отметьте после проверки.</p>
          <label><input v-model="rulebook.fields.value.ready" type="checkbox" />Правила записи заполнены и проверены</label>
        </section>
        <p v-if="mechanics.error.value" role="alert">
          {{ mechanics.error.value }}
          <button type="button" @click="mechanics.load()">Повторить загрузку механики</button>
        </p>
        <ItemRuleFields
          v-if="form.type === 'item' && mechanics.options.value && rulebook.ready.value"
          v-model:mechanics="mechanics.fields.value"
          v-model:rulebook="rulebook.fields.value"
          :item-kind="itemFields.item_kind_code"
          :options="mechanics.options.value"
        />
        <EntryMechanicsFields
          v-if="!['species', 'class', 'spell', 'item'].includes(form.type) && mechanics.options.value"
          v-model="mechanics.fields.value"
          :category="form.type"
          :options="mechanics.options.value"
        />
        <SpellEditorFields
          v-if="form.type === 'spell' && mechanics.ready.value && mechanics.options.value"
          v-model="mechanics.fields.value"
          v-model:body="form.body"
          :title="form.title"
          :options="mechanics.options.value"
        >
          <template #advanced>
            <label><input v-model="rulebook.fields.value.ready" type="checkbox" />Правила доступности заклинания заполнены и проверены</label>
          </template>
        </SpellEditorFields>
        <SpeciesEditorFields
          v-if="
            form.type === 'species' &&
            basicRuleOptions &&
            creation.ready.value &&
            creation.options.value &&
            mechanics.ready.value &&
            mechanics.options.value &&
            rulebook.ready.value &&
            rulebook.options.value
          "
          v-model="speciesCard"
          v-model:body="form.body"
          v-model:basic="basicRuleFields"
          v-model:creation="creation.fields.value"
          v-model:mechanics="mechanics.fields.value"
          v-model:rulebook="rulebook.fields.value"
          :title="form.title"
          :basic-options="basicRuleOptions"
          :creation-options="creation.options.value"
          :mechanics-options="mechanics.options.value"
          :rulebook-options="rulebook.options.value"
        />
        <ClassEditorFields
          v-if="
            form.type === 'class' &&
            basicRuleOptions &&
            creation.ready.value &&
            creation.options.value &&
            mechanics.ready.value &&
            mechanics.options.value &&
            rulebook.ready.value &&
            rulebook.options.value
          "
          v-model="classCard"
          v-model:body="form.body"
          v-model:basic="basicRuleFields"
          v-model:creation="creation.fields.value"
          v-model:mechanics="mechanics.fields.value"
          v-model:rulebook="rulebook.fields.value"
          :title="form.title"
          :basic-options="basicRuleOptions"
          :creation-options="creation.options.value"
          :mechanics-options="mechanics.options.value"
          :rulebook-options="rulebook.options.value"
        />
        <button
          type="submit"
          :disabled="
            saving ||
            !creation.ready.value ||
            optionsLoading ||
            optionsError !== '' ||
            createdEntry !== null ||
            (form.type === 'background' &&
              (rulesLoading || rulesError !== '' || abilityRules === null)) ||
            (form.type === 'skill' &&
              (skillLoading || skillError !== '' || skillAbilities.length === 0)) ||
            (form.type === 'feat' &&
              (featLoading || featError !== '' || featCategories.length === 0)) ||
            (form.type === 'item' && (itemLoading || itemError !== '' || itemOptions === null)) ||
            ((form.type === 'species' || form.type === 'class') &&
              (basicRulesLoading || basicRulesError !== '' || basicRuleOptions === null))
          "
        >
          {{ saving ? 'Сохраняем…' : 'Сохранить черновик' }}
        </button>
      </template>
    </fieldset>

    <p v-if="submitError" role="alert">{{ submitError }}</p>

    <p v-if="createdEntry" role="status">Черновик «{{ createdEntry.title }}» сохранён.</p>
  </form>
</template>
