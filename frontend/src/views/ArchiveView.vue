<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getArchiveEntries, getArchiveTypes, type ArchivePage } from '../api/archive'
import { publicAsset } from '../utils/publicAsset'
const route = useRoute(),
  router = useRouter()
const categories = ref<Array<{ code: string; title: string }>>([])
const result = ref<ArchivePage | null>(null)
const loading = ref(false),
  error = ref(''),
  search = ref(''),
  category = ref('')
let request = 0
function queryValue(key: string): string {
  return typeof route.query[key] === 'string' ? (route.query[key] as string) : ''
}
async function load(): Promise<void> {
  const current = ++request
  loading.value = true
  error.value = ''
  result.value = null
  search.value = queryValue('q')
  category.value = queryValue('type')
  const query = new URLSearchParams({ page: queryValue('page') || '1', per_page: '20' })
  if (search.value) query.set('q', search.value)
  if (category.value) query.set('type', category.value)
  try {
    const [types, page] = await Promise.all([getArchiveTypes(), getArchiveEntries(query)])
    if (current === request) {
      categories.value = types
      result.value = page
    }
  } catch (cause) {
    if (current === request)
      error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить архив'
  } finally {
    if (current === request) loading.value = false
  }
}
function navigate(page = 1): void {
  void router.push({
    path: '/archive',
    query: {
      ...(category.value ? { type: category.value } : {}),
      ...(search.value.trim() ? { q: search.value.trim() } : {}),
      ...(page > 1 ? { page: String(page) } : {}),
    },
  })
}
watch(() => route.fullPath, load, { immediate: true })
</script>
<template>
  <section class="archive-banner archive-banner-compact" aria-labelledby="archive-title">
    <div class="archive-banner-copy">
      <h1 id="archive-title">Архив</h1>
      <p>
        Правила, существа и истории наших миров. Полки ещё наполняются — опубликованные записи уже
        можно читать.
      </p>
    </div>
    <img
      class="archive-banner-art"
      :src="publicAsset('archive.webp')"
      alt="Ведьмочка с пушистыми помощниками у шкафа с книгами и волшебными предметами"
      width="1448"
      height="1086"
    />
  </section>
  <form class="archive-filters" @submit.prevent="navigate()">
    <label
      >Категория<select v-model="category">
        <option value="">Все категории</option>
        <option v-for="type in categories" :key="type.code" :value="type.code">
          {{ type.title }}
        </option>
      </select></label
    >
    <label
      >Поиск<input
        v-model="search"
        type="search"
        maxlength="100"
        placeholder="Название или текст записи"
    /></label>
    <button type="submit">Найти</button>
  </form>
  <p v-if="loading" role="status">Загружаем записи…</p>
  <p v-else-if="error" role="alert">
    {{ error }} <button type="button" @click="load">Повторить</button>
  </p>
  <template v-else-if="result">
    <p class="count-label">Записей: {{ result.meta.total }}</p>
    <p v-if="!result.data.length" class="empty-state">По этим условиям ничего не найдено.</p>
    <ul v-else class="archive-entries">
      <li v-for="entry in result.data" :key="entry.id">
        <span class="eyebrow">{{ entry.type_title }} · {{ entry.profile_title }}</span>
        <h2>
          <RouterLink :to="{ path: `/archive/${entry.id}`, query: route.query }">{{
            entry.title
          }}</RouterLink>
        </h2>
        <p class="preserve-lines">{{ entry.excerpt }}</p>
        <small v-if="entry.source_title">Источник: {{ entry.source_title }}</small>
      </li>
    </ul>
    <nav v-if="result.meta.page_count > 1" class="pagination" aria-label="Страницы архива">
      <button :disabled="result.meta.page <= 1" @click="navigate(result.meta.page - 1)">
        Назад
      </button>
      <span>{{ result.meta.page }} / {{ result.meta.page_count }}</span>
      <button
        :disabled="result.meta.page >= result.meta.page_count"
        @click="navigate(result.meta.page + 1)"
      >
        Далее
      </button>
    </nav>
  </template>
</template>
