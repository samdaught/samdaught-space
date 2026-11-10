<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  getAdminNews,
  setNewsPublished,
  deleteNews,
  formatNewsDate,
  type NewsAdmin,
  type NewsPage,
} from '../../api/news'
const newsPage = ref<NewsPage<NewsAdmin> | null>(null)
const loading = ref(false)
const actionId = ref<number | null>(null)
const error = ref('')
const notice = ref('')

async function loadPage(page = 1): Promise<void> {
  if (loading.value || actionId.value !== null) return
  loading.value = true
  error.value = ''
  try {
    newsPage.value = await getAdminNews(page)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить новости'
  } finally {
    loading.value = false
  }
}
async function togglePublication(news: NewsAdmin): Promise<void> {
  if (loading.value || actionId.value !== null) return
  actionId.value = news.id
  error.value = ''
  notice.value = ''
  try {
    Object.assign(news, await setNewsPublished(news.id, news.revision, !news.is_published))
    notice.value = news.is_published
      ? 'Новость опубликована на главной.'
      : 'Новость снята с публикации.'
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось изменить публикацию'
  } finally {
    actionId.value = null
  }
}
async function removeNews(news: NewsAdmin): Promise<void> {
  if (
    loading.value ||
    actionId.value !== null ||
    news.is_published ||
    !window.confirm('Удалить черновик новости «' + news.title + '»?')
  )
    return
  actionId.value = news.id
  error.value = ''
  notice.value = ''
  try {
    await deleteNews(news.id, news.revision)
    notice.value = 'Черновик удалён.'
    newsPage.value = null
    actionId.value = null
    await loadPage(1)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось удалить новость'
  } finally {
    actionId.value = null
  }
}
onMounted(() => {
  void loadPage()
})
</script>
<template>
  <header class="page-heading">
    <h1>Новости сайта</h1>
    <RouterLink class="button button-primary" to="/admin/news/new">Написать новость</RouterLink>
  </header>
  <p>
    Сначала сохраните черновик, затем опубликуйте его здесь. Опубликованные новости появятся на
    главной.
  </p>
  <p v-if="loading" role="status">Загружаем новости…</p>
  <p v-if="error" role="alert" class="message">{{ error }}</p>
  <p v-if="notice" role="status">{{ notice }}</p>
  <button
    type="button"
    :disabled="loading || actionId !== null"
    @click="loadPage(newsPage?.meta.page ?? 1)"
  >
    Обновить список
  </button>
  <template v-if="newsPage">
    <p v-if="newsPage.data.length === 0">Новостей пока нет.</p>
    <div v-else class="table-scroll" role="region" aria-label="Новости сайта" tabindex="0">
      <table>
        <thead>
          <tr>
            <th scope="col">Заголовок</th>
            <th scope="col">Статус</th>
            <th scope="col">Дата публикации</th>
            <th scope="col">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="news in newsPage.data" :key="news.id">
            <th scope="row">{{ news.title }}</th>
            <td>{{ news.is_published ? 'Опубликована' : 'Черновик' }}</td>
            <td>{{ formatNewsDate(news.published_at) }}</td>
            <td>
              <RouterLink :to="`/admin/news/${news.id}/edit`">Редактировать</RouterLink>
              <RouterLink v-if="news.is_published" :to="`/news/${news.id}`"
                >Открыть на сайте</RouterLink
              >
              <button
                type="button"
                :disabled="loading || actionId !== null"
                @click="togglePublication(news)"
              >
                {{ news.is_published ? 'Снять с публикации' : 'Опубликовать' }}
              </button>
              <button
                type="button"
                :disabled="loading || actionId !== null || news.is_published"
                @click="removeNews(news)"
              >
                Удалить черновик
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <nav aria-label="Страницы новостей в админке">
      <button
        type="button"
        :disabled="loading || actionId !== null || newsPage.meta.page <= 1"
        @click="loadPage(newsPage.meta.page - 1)"
      >
        Назад</button
      ><span>Страница {{ newsPage.meta.page }} из {{ newsPage.meta.page_count }}</span
      ><button
        type="button"
        :disabled="loading || actionId !== null || newsPage.meta.page >= newsPage.meta.page_count"
        @click="loadPage(newsPage.meta.page + 1)"
      >
        Далее
      </button>
    </nav>
  </template>
</template>
<style scoped>
th,
td {
  text-align: left;
  padding: 0.5rem;
}
nav {
  display: flex;
  gap: 1rem;
  margin-block: 1rem;
}
.message {
  white-space: pre-wrap;
}
</style>
