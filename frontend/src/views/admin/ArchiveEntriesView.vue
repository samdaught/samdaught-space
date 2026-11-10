<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  getCatalogEntries,
  type CatalogEntriesResponse,
  setCatalogEntryPublished,
  type CatalogEntryListItem,
} from '../../api/catalogEntries'

const audit = ref<{ id: number; title: string; complete: boolean; issues: string[] }[] | null>(
    null,
  ),
  auditing = ref(false)
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
const entriesPage = ref<CatalogEntriesResponse | null>(null)
const loading = ref(false)
const error = ref('')
const publishingId = ref<number | null>(null)

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

async function changePage(direction: -1 | 1) {
  const current = entriesPage.value

  if (current === null || loading.value) return

  const targetPage = current.meta.page + direction

  if (targetPage < 1 || targetPage > current.meta.page_count) return

  await loadPage(targetPage)
}

async function togglePublication(entry: CatalogEntryListItem) {
  if (loading.value || publishingId.value !== null) return

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

onMounted(async () => {
  await loadPage()
})
</script>

<template>
  <header class="page-heading">
    <h2>Записи</h2>

    <RouterLink class="button button-primary" to="/admin/archive/entries/new"
      >Создать запись</RouterLink
    >
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
            <th scope="col">Статус</th>
            <th scope="col">Механика</th>
            <th scope="col">Действия</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="entry in entriesPage.data" :key="entry.id">
            <td>{{ entry.title }}</td>
            <td>{{ entry.type_title }}</td>
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
            <td>
              <RouterLink :to="'/admin/archive/entries/' + entry.id + '/edit'">
                Редактировать
              </RouterLink>

              <button
                type="button"
                :disabled="loading || publishingId !== null"
                @click="togglePublication(entry)"
              >
                {{ entry.is_published ? 'Снять с публикации' : 'Опубликовать' }}
              </button>

              <span v-if="publishingId === entry.id" role="status"> Меняем статус… </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p>Всего записей: {{ entriesPage.meta.total }}</p>

    <nav aria-label="Страницы записей архива">
      <button
        type="button"
        :disabled="loading || publishingId !== null || entriesPage.meta.page <= 1"
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
          loading || publishingId !== null || entriesPage.meta.page >= entriesPage.meta.page_count
        "
        @click="changePage(1)"
      >
        Вперёд
      </button>
    </nav>
  </template>
</template>
