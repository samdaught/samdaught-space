<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getNewsArticle, formatNewsDate, type NewsArticle } from '../api/news'
const route = useRoute()
const news = ref<NewsArticle | null>(null)
const loading = ref(false)
const error = ref('')
let request = 0
async function load(): Promise<void> {
  const current = ++request,
    id = Number(route.params.id)
  news.value = null
  error.value = ''
  if (!Number.isSafeInteger(id) || id < 1) {
    loading.value = false
    error.value = 'Новость не найдена'
    return
  }
  loading.value = true
  try {
    const result = await getNewsArticle(id)
    if (current === request) news.value = result
  } catch (cause) {
    if (current === request)
      error.value = cause instanceof Error ? cause.message : 'Не удалось открыть новость'
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
onBeforeUnmount(() => {
  ++request
})
</script>
<template>
  <div class="reading-layout">
    <RouterLink to="/">К новостям</RouterLink>
    <p v-if="loading" role="status">Загружаем новость…</p>
    <p v-if="error" role="alert">
      {{ error }} <button type="button" :disabled="loading" @click="load">Повторить</button>
    </p>
    <article v-if="news" class="reading-page">
      <h1>{{ news.title }}</h1>
      <time :datetime="news.published_at">{{ formatNewsDate(news.published_at) }}</time>
      <div class="news-body">{{ news.body }}</div>
    </article>
  </div>
</template>
<style scoped>
.news-body {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  margin-block: 1rem;
}
</style>
