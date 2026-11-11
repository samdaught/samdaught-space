<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  copyCatalogEntryToWorld,
  getCatalogEntries,
  type CatalogEntriesResponse,
  setCatalogEntryPublished,
  type CatalogEntryListItem,
} from '../../api/catalogEntries'
import { getRuleProfiles, type RuleProfile } from '../../api/ruleProfiles'

const router = useRouter()
const audit = ref<{ id: number; title: string; complete: boolean; issues: string[] }[] | null>(
  null,
)
const auditing = ref(false)
const entriesPage = ref<CatalogEntriesResponse | null>(null)
const profiles = ref<RuleProfile[]>([])
const loading = ref(false)
const loadingProfiles = ref(false)
const error = ref('')
const publishingId = ref<number | null>(null)
const copyingId = ref<number | null>(null)
const copyingEntry = ref<CatalogEntryListItem | null>(null)
const copyTargetId = ref<number | null>(null)

const copyTargets = computed(() =>
  profiles.value.filter((profile) => profile.id !== copyingEntry.value?.rule_profile_id),
)

function canCopyToWorld(entry: CatalogEntryListItem): boolean {
  return !['background', 'subclass', 'species_variant'].includes(entry.type)
}

async function auditArchive() {
  auditing.value = true
  error.value = ''

  try {
    const response = await fetch('/api/v1/catalog-audit', { credentials: 'same-origin' })
    if (!response.ok) throw new Error('Не удалось проверить архив')
    audit.value = (await response.json()).data
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Ошибка проверки'
  } finally {
    auditing.value = false
  }
}

async function loadPage(page: number = 1) {
  if (loading.value || publishingId.value !== null) return

  loading.value = true
  error.value = ''

  try {
    entriesPage.value = await getCatalogEntries(page)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить записи'
  } finally {
    loading.value = false
  }
}

async function loadProfiles() {
  if (loadingProfiles.value) return

  loadingProfiles.value = true

  try {
    profiles.value = await getRuleProfiles()
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить миры'
  } finally {
    loadingProfiles.value = false
  }
}

async function changePage(direction: -1 | 1) {
  const current = entriesPage.value

  if (current === null || loading.value) return

  const targetPage = current.meta.page + direction

  if (targetPage < 1 || targetPage > current.meta.page_count) return

  await loadPage(targetPage)
}

async function togglePublication(entry: CatalogEntryListItem) {
  if (loading.value || publishingId.value !== null || copyingId.value !== null) return

  error.value = ''
  publishingId.value = entry.id

  try {
    const result = await setCatalogEntryPublished(entry.id, !entry.is_published)
    entry.is_published = result.is_published
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось изменить статус публикации'
  } finally {
    publishingId.value = null
  }
}

function openCopyDialog(entry: CatalogEntryListItem) {
  error.value = ''
  copyingEntry.value = entry
  copyTargetId.value = copyTargets.value[0]?.id ?? null

  if (copyTargetId.value === null && !loadingProfiles.value) {
    error.value = 'Нет другого мира, в который можно перенести запись.'
  }
}

function closeCopyDialog() {
  if (copyingId.value !== null) return
  error.value = ''
  copyingEntry.value = null
  copyTargetId.value = null
}

async function copyToWorld() {
  const entry = copyingEntry.value
  const targetId = copyTargetId.value

  if (entry === null || targetId === null || copyingId.value !== null) return

  error.value = ''
  copyingId.value = entry.id

  try {
    const copy = await copyCatalogEntryToWorld(entry.id, targetId)
    copyingEntry.value = null
    copyTargetId.value = null
    await router.push(`/admin/archive/entries/${copy.id}/edit`)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось перенести запись'
  } finally {
    copyingId.value = null
  }
}

onMounted(async () => {
  await Promise.all([loadPage(), loadProfiles()])
})
</script>

<template>
  <header class="page-heading">
    <h2>Записи</h2>

    <RouterLink class="button button-primary" to="/admin/archive/entries/new">
      Создать запись
    </RouterLink>
  </header>

  <button type="button" :disabled="auditing" @click="auditArchive">
    {{ auditing ? 'Проверяем…' : 'Проверить готовность архива' }}
  </button>

  <section v-if="audit">
    <h3>Проверка механики</h3>
    <p>
      Проверено: {{ audit.length }}. Требуют заполнения:
      {{ audit.filter((row) => !row.complete).length }}.
    </p>
    <details v-for="row in audit.filter((row) => !row.complete)" :key="row.id">
      <summary>{{ row.title }}</summary>
      <ul>
        <li v-for="issue in row.issues" :key="issue">{{ issue }}</li>
      </ul>
      <RouterLink :to="'/admin/archive/entries/' + row.id + '/edit'">Исправить запись</RouterLink>
    </details>
  </section>

  <section v-if="copyingEntry" class="copy-panel" aria-labelledby="copy-entry-title">
    <h3 id="copy-entry-title">Перенести запись в другой мир</h3>
    <p>
      Будет создан неопубликованный черновик «{{ copyingEntry.title }}». Связи с записями
      исходного мира не переносятся: в новом мире мастер настраивает их заново. У классов также
      заново задаются выборы навыков.
    </p>
    <label>
      Целевой мир
      <select v-model="copyTargetId" :disabled="copyingId !== null || loadingProfiles">
        <option :value="null">Выберите мир</option>
        <option v-for="profile in copyTargets" :key="profile.id" :value="profile.id">
          {{ profile.title }}
        </option>
      </select>
    </label>
    <div class="copy-actions">
      <button
        type="button"
        :disabled="copyTargetId === null || copyingId !== null || loadingProfiles"
        @click="copyToWorld"
      >
        {{ copyingId === copyingEntry.id ? 'Создаём черновик…' : 'Создать черновик в этом мире' }}
      </button>
      <button type="button" :disabled="copyingId !== null" @click="closeCopyDialog">Отмена</button>
    </div>
  </section>

  <p v-if="loading">Загружаем записи…</p>
  <p v-if="error" role="alert">{{ error }}</p>

  <template v-if="entriesPage">
    <p v-if="entriesPage.data.length === 0">Записей пока нет.</p>

    <div v-else class="table-scroll" role="region" aria-label="Записи архива" tabindex="0">
      <table>
        <thead>
          <tr>
            <th scope="col">Название</th>
            <th scope="col">Категория</th>
            <th scope="col">Мир</th>
            <th scope="col">Статус</th>
            <th scope="col">Механика</th>
            <th scope="col">Действия</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="entry in entriesPage.data" :key="entry.id">
            <td>{{ entry.title }}</td>
            <td>{{ entry.type_title }}</td>
            <td>{{ entry.rule_profile_title }}</td>
            <td>{{ entry.is_published ? 'Опубликовано' : 'Черновик' }}</td>
            <td>
              {{
                entry.mechanics_status === 'ready'
                  ? 'Заполнена'
                  : entry.mechanics_status === 'partial'
                    ? 'Частично'
                    : 'Только текст'
              }}
            </td>
            <td class="entry-actions">
              <RouterLink :to="'/admin/archive/entries/' + entry.id + '/edit'">
                Редактировать
              </RouterLink>

              <button
                type="button"
                :disabled="loading || publishingId !== null || copyingId !== null"
                @click="togglePublication(entry)"
              >
                {{ entry.is_published ? 'Снять с публикации' : 'Опубликовать' }}
              </button>

              <button
                v-if="canCopyToWorld(entry)"
                type="button"
                :disabled="loadingProfiles || copyingId !== null"
                @click="openCopyDialog(entry)"
              >
                В другой мир
              </button>
              <span v-else class="copy-unavailable">
                Сначала перенесите основу
              </span>

              <span v-if="publishingId === entry.id" role="status">Меняем статус…</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p>Всего записей: {{ entriesPage.meta.total }}</p>

    <nav aria-label="Страницы записей архива">
      <button
        type="button"
        :disabled="loading || publishingId !== null || copyingId !== null || entriesPage.meta.page <= 1"
        @click="changePage(-1)"
      >
        Назад
      </button>

      <span aria-live="polite">
        Страница {{ entriesPage.meta.page }} из {{ entriesPage.meta.page_count }}
      </span>

      <button
        type="button"
        :disabled="
          loading ||
          publishingId !== null ||
          copyingId !== null ||
          entriesPage.meta.page >= entriesPage.meta.page_count
        "
        @click="changePage(1)"
      >
        Вперёд
      </button>
    </nav>
  </template>
</template>

<style scoped>
.copy-panel {
  display: grid;
  gap: 0.85rem;
  max-width: 48rem;
  margin: 1.25rem 0;
  padding: 1rem;
  border: 1px solid var(--line);
  border-left: 4px solid var(--accent);
}

.copy-panel h3,
.copy-panel p {
  margin: 0;
}

.copy-panel label {
  display: grid;
  gap: 0.35rem;
  max-width: 24rem;
}

.copy-actions,
.entry-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.copy-unavailable {
  color: var(--muted);
  font-size: 0.9em;
}
</style>
