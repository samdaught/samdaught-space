<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  copyCatalogEntryToWorld,
  getCatalogEntries,
  type CatalogEntriesResponse,
  type CatalogEntriesQuery,
  setCatalogEntryPublished,
  type CatalogEntryListItem,
} from '../../api/catalogEntries'
import { getRuleProfiles, type RuleProfile } from '../../api/ruleProfiles'

type AuditRow = {
  id: number
  title: string
  complete: boolean
  issues: string[]
  content_complete: boolean
  content_issues: string[]
  warnings: string[]
}
type SortField = NonNullable<CatalogEntriesQuery['sort']>
type SortDirection = NonNullable<CatalogEntriesQuery['direction']>

const router = useRouter()
const audit = ref<AuditRow[] | null>(null)
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
const search = ref('')
const sort = ref<SortField>('title')
const direction = ref<SortDirection>('asc')
let searchTimer: ReturnType<typeof setTimeout> | null = null
let latestListRequest = 0

const incompleteAudit = computed(() => audit.value?.filter((row) => !row.complete) ?? [])
const incompleteContentAudit = computed(
  () => audit.value?.filter((row) => !row.content_complete) ?? [],
)
const draftDependencyAudit = computed(
  () => audit.value?.filter((row) => row.warnings.length > 0) ?? [],
)

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
  if (publishingId.value !== null) return

  const requestId = ++latestListRequest
  loading.value = true
  error.value = ''

  try {
    const result = await getCatalogEntries({
      page,
      q: search.value,
      sort: sort.value,
      direction: direction.value,
    })
    if (requestId === latestListRequest) entriesPage.value = result
  } catch (cause) {
    if (requestId === latestListRequest) {
      error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить записи'
    }
  } finally {
    if (requestId === latestListRequest) loading.value = false
  }
}

function sortLabel(column: SortField): string {
  if (sort.value !== column) return ''
  return direction.value === 'asc' ? 'по возрастанию' : 'по убыванию'
}

async function changeSort(column: SortField) {
  if (sort.value === column) {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = column
    direction.value = 'asc'
  }
  await loadPage()
}

function scheduleSearch() {
  if (searchTimer !== null) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    searchTimer = null
    void loadPage()
  }, 250)
}

function clearSearch() {
  if (search.value === '') return
  search.value = ''
  if (searchTimer !== null) clearTimeout(searchTimer)
  searchTimer = null
  void loadPage()
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

onBeforeUnmount(() => {
  if (searchTimer !== null) clearTimeout(searchTimer)
})

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

  <section class="archive-tools" aria-label="Поиск и сортировка записей">
    <label class="archive-search">
      Найти запись
      <input
        v-model="search"
        type="search"
        maxlength="100"
        placeholder="Название, категория, мир или код"
        @input="scheduleSearch"
      />
    </label>
    <button v-if="search" type="button" :disabled="loading" @click="clearSearch">
      Очистить поиск
    </button>
    <p class="field-hint">Нажмите на заголовок столбца, чтобы изменить порядок сортировки.</p>
  </section>

  <button type="button" :disabled="auditing" @click="auditArchive">
    {{ auditing ? 'Проверяем…' : 'Проверить готовность архива' }}
  </button>

  <section v-if="audit" class="audit-results">
    <h3>Проверка механики</h3>
    <p>
      Проверено: {{ audit.length }}. Требуют настройки механики: {{ incompleteAudit.length }}.
      Требуют описания для публикации: {{ incompleteContentAudit.length }}. Связи с черновиками:
      {{ draftDependencyAudit.length }}.
    </p>
    <p class="field-hint">
      Черновик в связанной записи не означает ошибку механики: он лишь не опубликован. Такие связи
      показаны отдельно, чтобы не смешивать их с тем, что действительно нужно заполнить.
    </p>
    <details v-for="row in incompleteAudit" :key="row.id">
      <summary>{{ row.title }}</summary>
      <ul>
        <li v-for="issue in row.issues" :key="issue">{{ issue }}</li>
      </ul>
      <RouterLink :to="'/admin/archive/entries/' + row.id + '/edit'">Исправить запись</RouterLink>
    </details>
    <details v-if="incompleteContentAudit.length > 0" class="content-audit">
      <summary>Записи без описания для публикации: {{ incompleteContentAudit.length }}</summary>
      <ul>
        <li v-for="row in incompleteContentAudit" :key="row.id">
          <RouterLink :to="'/admin/archive/entries/' + row.id + '/edit'">{{
            row.title
          }}</RouterLink>
        </li>
      </ul>
    </details>
  </section>

  <section v-if="copyingEntry" class="copy-panel" aria-labelledby="copy-entry-title">
    <h3 id="copy-entry-title">Перенести запись в другой мир</h3>
    <p>
      Будет создан неопубликованный черновик «{{ copyingEntry.title }}». Связи с записями исходного
      мира не переносятся: в новом мире мастер настраивает их заново. У классов также заново
      задаются выборы навыков.
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
            <th scope="col">
              <button
                class="sort-button"
                type="button"
                :aria-label="`Сортировать по названию: ${sortLabel('title') || 'не выбрано'}`"
                @click="changeSort('title')"
              >
                Название
                <span v-if="sort === 'title'" aria-hidden="true">{{
                  direction === 'asc' ? '↑' : '↓'
                }}</span>
              </button>
            </th>
            <th scope="col">
              <button
                class="sort-button"
                type="button"
                :aria-label="`Сортировать по категории: ${sortLabel('type') || 'не выбрано'}`"
                @click="changeSort('type')"
              >
                Категория
                <span v-if="sort === 'type'" aria-hidden="true">{{
                  direction === 'asc' ? '↑' : '↓'
                }}</span>
              </button>
            </th>
            <th scope="col">
              <button
                class="sort-button"
                type="button"
                :aria-label="`Сортировать по миру: ${sortLabel('profile') || 'не выбрано'}`"
                @click="changeSort('profile')"
              >
                Мир
                <span v-if="sort === 'profile'" aria-hidden="true">{{
                  direction === 'asc' ? '↑' : '↓'
                }}</span>
              </button>
            </th>
            <th scope="col">
              <button
                class="sort-button"
                type="button"
                :aria-label="`Сортировать по статусу: ${sortLabel('status') || 'не выбрано'}`"
                @click="changeSort('status')"
              >
                Статус
                <span v-if="sort === 'status'" aria-hidden="true">{{
                  direction === 'asc' ? '↑' : '↓'
                }}</span>
              </button>
            </th>
            <th scope="col">Действия</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="entry in entriesPage.data" :key="entry.id">
            <td>{{ entry.title }}</td>
            <td>{{ entry.type_title }}</td>
            <td>{{ entry.rule_profile_title }}</td>
            <td>{{ entry.is_published ? 'Опубликовано' : 'Черновик' }}</td>
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
              <span v-else class="copy-unavailable"> Сначала перенесите основу </span>

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
        :disabled="
          loading || publishingId !== null || copyingId !== null || entriesPage.meta.page <= 1
        "
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
.archive-tools {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  gap: 0.75rem 1rem;
  margin: 1.25rem 0;
}

.archive-search {
  display: grid;
  gap: 0.35rem;
  width: min(100%, 30rem);
}

.field-hint {
  width: 100%;
  margin: 0;
  color: var(--muted);
}

.audit-results {
  margin: 1.25rem 0;
}

.audit-results details {
  margin-block: 0.5rem;
}

.content-audit ul {
  max-height: 18rem;
  overflow: auto;
}

.sort-button {
  display: inline-flex;
  gap: 0.25rem;
  align-items: baseline;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  font-weight: inherit;
  text-decoration: underline;
  text-underline-offset: 0.16em;
}

.sort-button:hover {
  color: var(--accent);
}

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
