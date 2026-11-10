<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getCatalogEntry,
  updateCatalogEntry,
  type CatalogEntryDetails,
  type CatalogEntryUpdateInput,
} from '../../api/catalogEntries'
import { getContentSourceOptions, type ContentSourceOption } from '../../api/contentSources'
import {
  getBackgroundAbilityOptions,
  type BackgroundAbilityOptions,
} from '../../api/backgroundAbilityOptions'
import BackgroundAbilityFields from '../../components/BackgroundAbilityFields.vue'
import BackgroundSkillFields from '../../components/BackgroundSkillFields.vue'
import SkillAbilityField from '../../components/SkillAbilityField.vue'
import FeatCategoryField from '../../components/FeatCategoryField.vue'
import BackgroundFeatField from '../../components/BackgroundFeatField.vue'
import BackgroundToolFields from '../../components/BackgroundToolFields.vue'
import ItemDefinitionFields from '../../components/ItemDefinitionFields.vue'
import BasicRuleDefinitionFields from '../../components/BasicRuleDefinitionFields.vue'
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

import CreationRuleFields from '../../components/CreationRuleFields.vue'
import { useCreationRuleFields } from '../../composables/useCreationRuleFields'

import ProfileAvailabilityFields from '../../components/ProfileAvailabilityFields.vue'

import { useFormChanges } from '../../composables/useFormChanges'

const route = useRoute()
const router = useRouter()

const entry = ref<CatalogEntryDetails | null>(null)
const loading = ref(true)
const error = ref('')
const sourceOptions = ref<ContentSourceOption[]>([])
const saving = ref(false)
const saved = ref(false)
const submitError = ref('')
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
  () => entry.value?.origin_profile_id ?? null,
  () => entry.value?.type ?? '',
)

async function loadBasicRuleOptions(): Promise<void> {
  if (basicRulesLoading.value || saving.value) return
  basicRulesLoading.value = true
  basicRulesError.value = ''
  try {
    basicRuleOptions.value = await getBasicRuleOptions(entry.value?.origin_profile_id ?? null)
    const codes = basicRuleFields.value.class_saving_throw_codes
    basicRuleFields.value.class_saving_throw_codes = Array.from(
      { length: basicRuleOptions.value.required_saving_throw_count },
      (_, index) => codes[index] ?? '',
    )
  } catch (cause) {
    basicRulesError.value =
      cause instanceof Error ? cause.message : 'Не удалось загрузить базовые свойства вида и класса'
  } finally {
    basicRulesLoading.value = false
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

const form = ref<CatalogEntryUpdateInput>({
  title: '',
  body: '',
  source_id: null,
})

const changes = useFormChanges(
  () => [
    form.value,
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
  ],
  () => saving.value,
  () => !loading.value,
)

function applyAbilityOptions(options: BackgroundAbilityOptions): void {
  const codes: string[] = []
  for (let index = 0; index < options.required_count; index++) {
    codes.push(options.ability_codes[index] ?? '')
  }

  abilityRules.value = options
  abilityCodes.value = codes
  const skills: Array<number | null> = []
  for (let index = 0; index < options.required_skill_count; index++)
    skills.push(options.skill_entry_ids[index] ?? null)
  skillEntryIds.value = skills
  featEntryId.value = options.feat_entry_id
  backgroundTool.value =
    options.background_tool === null
      ? { tool_entry_id: null, tool_group_code: null }
      : { ...options.background_tool }
  const equipment = options.starting_equipment ?? options.starting_equipment_defaults
  startingEquipment.value = { ...equipment, items: equipment.items.map((item) => ({ ...item })) }
}

async function loadAbilityOptions(id: number): Promise<void> {
  if (rulesLoading.value || saving.value) return

  rulesLoading.value = true
  rulesError.value = ''

  try {
    applyAbilityOptions(await getBackgroundAbilityOptions(id))
  } catch (cause) {
    rulesError.value =
      cause instanceof Error ? cause.message : 'Не удалось загрузить настройки предыстории'
  } finally {
    rulesLoading.value = false
  }
}

onMounted(async () => {
  try {
    const id = Number(route.params.id)

    if (!Number.isSafeInteger(id) || id < 1) {
      throw new Error('Некорректный ID записи')
    }

    const [loadedEntry, loadedSources] = await Promise.all([
      getCatalogEntry(id),
      getContentSourceOptions(),
    ])

    entry.value = loadedEntry
    sourceOptions.value = loadedSources

    form.value = {
      title: loadedEntry.title,
      body: loadedEntry.body ?? '',
      source_id: loadedEntry.source_id,
    }

    creation.fields.value = loadedEntry.creation_rules ?? {
      grants: [],
      choices: [],
      hit_point_bonus: 0,
    }
    if (loadedEntry.type === 'background') {
      await loadAbilityOptions(loadedEntry.id)
    }
    if (loadedEntry.type === 'skill') {
      skillAbilityCode.value = loadedEntry.skill_ability_code ?? ''
      await loadSkillAbilityOptions()
    }
    if (loadedEntry.type === 'feat') {
      featCategoryCode.value = loadedEntry.feat_category_code ?? ''
      await loadFeatCategories()
    }
    if (loadedEntry.type === 'item') {
      itemFields.value = {
        item_kind_code: loadedEntry.item_kind_code ?? '',
        tool_group_code: loadedEntry.tool_group_code ?? '',
        tool_ability_code: loadedEntry.tool_ability_code ?? '',
      }
      await loadItemOptions()
    }
    if (loadedEntry.type === 'species' || loadedEntry.type === 'class') {
      basicRuleFields.value = {
        species_speed_feet: loadedEntry.species_speed_feet ?? '',
        class_hit_die_sides: loadedEntry.class_hit_die_sides ?? null,
        class_saving_throw_codes: [...(loadedEntry.class_saving_throw_codes ?? [])],
        class_skill_choice_count: loadedEntry.class_skill_choice_count ?? '',
        class_skill_entry_ids: [...(loadedEntry.class_skill_entry_ids ?? [])],
      }
      await loadBasicRuleOptions()
    }
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить запись'
  } finally {
    changes.markSaved()
    loading.value = false
  }
})

async function submit() {
  const current = entry.value

  if (current === null || loading.value || saving.value || saved.value) return
  if (
    current.type === 'background' &&
    (rulesLoading.value || rulesError.value || abilityRules.value === null)
  )
    return
  if (
    current.type === 'skill' &&
    (skillLoading.value || skillError.value !== '' || skillAbilities.value.length === 0)
  )
    return

  if (
    current.type === 'feat' &&
    (featLoading.value || featError.value !== '' || featCategories.value.length === 0)
  )
    return

  submitError.value = ''
  if (current.type === 'feat' && featCategoryCode.value === '') {
    submitError.value = 'Выберите категорию черты'
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
  if (current.type === 'item') {
    if (itemLoading.value || itemError.value !== '' || itemOptions.value === null) return
    if (itemInput === null) {
      submitError.value = 'Заполните свойства выбранного вида предмета'
      return
    }
  }

  const basicRuleInput =
    basicRuleOptions.value === null
      ? null
      : getBasicRuleInput(current.type, basicRuleFields.value, basicRuleOptions.value)
  if (current.type === 'species' || current.type === 'class') {
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
  if (current.type === 'background') {
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
  saving.value = true

  try {
    entry.value = await updateCatalogEntry(current.id, {
      ...(creation.input.value === undefined ? {} : { creation_rules: creation.input.value }),
      ...form.value,
      ...(current.type === 'background' &&
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
      ...(current.type === 'skill' ? { skill_ability_code: skillAbilityCode.value } : {}),
      ...(current.type === 'feat' ? { feat_category_code: featCategoryCode.value } : {}),
      ...(current.type === 'item' && itemInput !== null ? itemInput : {}),
      ...((current.type === 'species' || current.type === 'class') && basicRuleInput !== null
        ? basicRuleInput
        : {}),
    })
    saved.value = true
    changes.markSaved()

    saving.value = false
    const failure = await router.replace('/admin/archive/entries')

    if (failure) {
      submitError.value = 'Изменения сохранены, но переход отменён. Перейдите к списку записей.'
    }
  } catch (cause) {
    submitError.value = saved.value
      ? 'Изменения сохранены, но не удалось открыть список. Перейдите к списку записей.'
      : cause instanceof Error
        ? cause.message
        : 'Не удалось сохранить запись'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <h1>Редактирование записи архива</h1>

  <RouterLink to="/admin/archive/entries">К списку записей</RouterLink>
  <p v-if="loading">Загружаем запись…</p>
  <p v-else-if="error" role="alert">{{ error }}</p>
  <template v-else-if="entry">
    <h2>{{ entry.title }}</h2>

    <p v-if="entry.is_published">
      Запись опубликована. Сохранённые изменения сразу появятся в публичном архиве.
    </p>

    <form @submit.prevent="submit">
      <fieldset :disabled="saving || saved">
        <label>
          Название
          <input v-model="form.title" required maxlength="255" />
        </label>

        <label>
          Описание
          <textarea v-model="form.body" :required="entry.is_published"></textarea>
        </label>

        <label>
          Источник
          <select v-model="form.source_id">
            <option :value="null">Без источника</option>

            <option v-for="source in sourceOptions" :key="source.id" :value="source.id">
              {{ source.title }}
            </option>
          </select>
        </label>
        <section v-if="entry.type === 'background'">
          <p v-if="rulesLoading" role="status">Загружаем настройки предыстории…</p>
          <p v-if="rulesError" role="alert">
            {{ rulesError }}
            <button
              type="button"
              :disabled="rulesLoading || saving"
              @click="loadAbilityOptions(entry.id)"
            >
              Повторить загрузку
            </button>
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
        <section v-if="entry.type === 'skill'">
          <p v-if="skillLoading" role="status">Загружаем характеристики…</p>
          <p v-if="skillError" role="alert">
            {{ skillError }}
            <button
              type="button"
              :disabled="skillLoading || saving"
              @click="loadSkillAbilityOptions"
            >
              Повторить загрузку
            </button>
          </p>
          <SkillAbilityField
            v-if="skillAbilities.length > 0"
            v-model="skillAbilityCode"
            :options="skillAbilities"
          />
        </section>
        <section v-if="entry.type === 'feat'">
          <p v-if="featLoading" role="status">Загружаем категории черт…</p>
          <p v-if="featError" role="alert">
            {{ featError }}
            <button type="button" :disabled="featLoading || saving" @click="loadFeatCategories">
              Повторить загрузку
            </button>
          </p>
          <FeatCategoryField
            v-if="featCategories.length > 0"
            v-model="featCategoryCode"
            :options="featCategories"
          />
        </section>
        <section v-if="entry.type === 'species' || entry.type === 'class'">
          <p v-if="basicRulesLoading" role="status">Загружаем базовые свойства…</p>
          <p v-if="basicRulesError" role="alert">
            {{ basicRulesError }}
            <button
              type="button"
              :disabled="basicRulesLoading || saving"
              @click="loadBasicRuleOptions"
            >
              Повторить загрузку
            </button>
          </p>
          <BasicRuleDefinitionFields
            v-if="basicRuleOptions"
            v-model="basicRuleFields"
            :options="basicRuleOptions"
            :category="entry.type"
          />
        </section>
        <section v-if="entry.type === 'item'">
          <p v-if="itemLoading" role="status">Загружаем свойства предметов…</p>
          <p v-if="itemError" role="alert">
            {{ itemError }}
            <button type="button" :disabled="itemLoading || saving" @click="loadItemOptions">
              Повторить загрузку
            </button>
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
          v-if="creation.ready.value && creation.supported.value && creation.options.value"
          v-model="creation.fields.value"
          :options="creation.options.value"
        />

        <button
          type="submit"
          :disabled="
            !creation.ready.value ||
            (entry.type === 'background' &&
              (rulesLoading || rulesError !== '' || abilityRules === null)) ||
            (entry.type === 'skill' &&
              (skillLoading || skillError !== '' || skillAbilities.length === 0)) ||
            (entry.type === 'feat' &&
              (featLoading || featError !== '' || featCategories.length === 0)) ||
            (entry.type === 'item' && (itemLoading || itemError !== '' || itemOptions === null)) ||
            ((entry.type === 'species' || entry.type === 'class') &&
              (basicRulesLoading || basicRulesError !== '' || basicRuleOptions === null))
          "
        >
          {{ saving ? 'Сохраняем…' : 'Сохранить изменения' }}
        </button>
      </fieldset>

      <p v-if="submitError" role="alert">{{ submitError }}</p>
      <p v-if="saved" role="status">Изменения сохранены.</p>
    </form>
    <ProfileAvailabilityFields :key="entry.id" :entry-id="entry.id" />
  </template>
</template>

<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}

fieldset {
  gap: 1rem;
}
</style>
