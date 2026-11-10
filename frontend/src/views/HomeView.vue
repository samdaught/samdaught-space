<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { getNews, formatNewsDate, type NewsPage, type NewsSummary } from '../api/news'
import { session } from '../api/session'
import { publicAsset } from '../utils/publicAsset'
const mascotUrl = publicAsset('home_picture.webp')
const newsPage = ref<NewsPage<NewsSummary> | null>(null)
const loading = ref(false)
const error = ref('')
async function loadPage(page = 1): Promise<void> {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    newsPage.value = await getNews(page)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить новости'
  } finally {
    loading.value = false
  }
}
onMounted(() => {
  void loadPage()
})
</script>
<template>
  <section class="home-intro" aria-labelledby="home-title">
    <div class="home-intro-copy">
      <h1 id="home-title">Магия ещё пишется.</h1>
      <p>
        Собираем здесь правила, лор и персонажей для наших игр в D&amp;D. Архив ещё наполняется,
        редактор персонажа уже работает.
      </p>
      <div class="action-row">
        <RouterLink v-if="session?.authenticated" class="button button-primary" to="/characters/new"
          >Создать персонажа</RouterLink
        >
        <RouterLink v-else class="button button-primary" to="/login"
          >Войти и создать персонажа</RouterLink
        >
        <RouterLink class="button button-quiet" to="/archive">Открыть архив</RouterLink>
      </div>
    </div>
    <img
      class="hero-art"
      :src="mascotUrl"
      alt="Ведьмочка с двумя пушистыми помощниками заполняет книгу персонажей"
      width="1920"
      height="819"
      fetchpriority="high"
    />
  </section>
  <div class="home-body">
    <section aria-labelledby="news-heading">
      <div class="section-heading">
        <h2 id="news-heading">Новости сайта</h2>
        <RouterLink v-if="session?.can_manage_news" to="/admin/news/new"
          >Написать новость</RouterLink
        >
      </div>
      <p v-if="loading" role="status">Загружаем новости…</p>
      <p v-if="error" role="alert">
        {{ error }}
        <button type="button" :disabled="loading" @click="loadPage(newsPage?.meta.page ?? 1)">
          Повторить
        </button>
      </p>
      <template v-if="newsPage && !loading && !error">
        <div v-if="newsPage.data.length === 0" class="empty-state">
          <h3>Пока тихо</h3>
          <p>Здесь появятся новости сайта и заметки о новых возможностях.</p>
        </div>
        <article v-for="news in newsPage.data" :key="news.id" class="news-entry">
          <time :datetime="news.published_at">{{ formatNewsDate(news.published_at) }}</time>
          <h2>
            <RouterLink :to="`/news/${news.id}`">{{ news.title }}</RouterLink>
          </h2>
          <p>{{ news.excerpt }}</p>
          <RouterLink :to="`/news/${news.id}`">Читать полностью</RouterLink>
        </article>
        <nav v-if="newsPage.meta.page_count > 1" aria-label="Страницы новостей">
          <button
            type="button"
            :disabled="newsPage.meta.page <= 1"
            @click="loadPage(newsPage.meta.page - 1)"
          >
            Назад</button
          ><span>Страница {{ newsPage.meta.page }} из {{ newsPage.meta.page_count }}</span
          ><button
            type="button"
            :disabled="newsPage.meta.page >= newsPage.meta.page_count"
            @click="loadPage(newsPage.meta.page + 1)"
          >
            Далее
          </button>
        </nav>
      </template>
    </section>
  </div>
</template>
<style scoped>
.news-entry p {
  white-space: pre-wrap;
}
</style>
