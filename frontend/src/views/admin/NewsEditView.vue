<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getAdminNewsArticle, saveNews, type NewsAdmin, type NewsContent } from '../../api/news'
import { useFormChanges } from '../../composables/useFormChanges'
import { afterSuccessfulSave } from '../../composables/useUnsavedChanges'
const route = useRoute(),
  router = useRouter()
const newsId = ref<number | null>(null)
const basis = ref<NewsAdmin | null>(null)
const form = ref<NewsContent>({ title: '', summary: '', body: '' })
const loading = ref(false),
  saving = ref(false)
const pageError = ref(''),
  submitError = ref(''),
  notice = ref('')
const changes = useFormChanges(
  () => form.value,
  () => saving.value,
)
let generation = 0
function apply(news: NewsAdmin): void {
  basis.value = news
  newsId.value = news.id
  form.value = { title: news.title, summary: news.summary, body: news.body }
  changes.markSaved()
}
async function load(id: number | null, preserveInput = false): Promise<void> {
  const current = ++generation
  loading.value = true
  saving.value = false
  pageError.value = ''
  submitError.value = ''
  notice.value = ''
  if (!preserveInput) {
    newsId.value = id
    basis.value = null
    form.value = { title: '', summary: '', body: '' }
  }
  try {
    if (id === null) {
      newsId.value = null
      basis.value = null
      form.value = { title: '', summary: '', body: '' }
      changes.markSaved()
      return
    }
    const news = await getAdminNewsArticle(id)
    if (current === generation) apply(news)
  } catch (cause) {
    if (current === generation) {
      const message = cause instanceof Error ? cause.message : 'Не удалось открыть новость'
      if (preserveInput) submitError.value = message + '. Введённый текст сохранён в форме.'
      else pageError.value = message
    }
  } finally {
    if (current === generation) loading.value = false
  }
}
watch(
  () => route.params.id,
  (raw) => {
    const id = raw === undefined ? null : Number(raw)
    if (id !== null && (!Number.isSafeInteger(id) || id < 1)) {
      ++generation
      loading.value = false
      saving.value = false
      newsId.value = null
      basis.value = null
      pageError.value = 'Некорректный ID новости'
      return
    }
    void load(id)
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  ++generation
})
async function reload(): Promise<void> {
  if (changes.dirty.value && !window.confirm('Заменить введённый текст сохранённой версией?'))
    return
  if (!saving.value && !loading.value && newsId.value !== null) await load(newsId.value, true)
}
async function submit(): Promise<void> {
  if (loading.value || saving.value || pageError.value !== '') return
  submitError.value = ''
  notice.value = ''
  if (form.value.title.trim() === '') {
    submitError.value = 'Введите заголовок новости'
    return
  }
  saving.value = true
  const current = generation
  let committed = false
  try {
    const result = await saveNews(newsId.value, { ...form.value }, basis.value?.revision)
    if (current !== generation) return
    apply(result)
    committed = true
    notice.value = 'Новость сохранена.'
    const failure = await afterSuccessfulSave(() => router.replace('/admin/news'))
    if (failure && current === generation)
      notice.value = 'Новость сохранена. Откройте список по ссылке выше.'
  } catch (cause) {
    if (current === generation) {
      if (committed) notice.value = 'Новость сохранена. Откройте список по ссылке выше.'
      else
        submitError.value = cause instanceof Error ? cause.message : 'Не удалось сохранить новость'
    }
  } finally {
    if (current === generation) saving.value = false
  }
}
</script>
<template>
  <section class="document-editor">
    <h1>{{ newsId === null ? 'Новая новость' : 'Редактирование новости' }}</h1>
    <RouterLink to="/admin/news">К списку новостей</RouterLink>
    <p v-if="loading" role="status">Загружаем новость…</p>
    <p v-if="pageError" role="alert">{{ pageError }}</p>
    <button v-if="newsId !== null" type="button" :disabled="loading || saving" @click="reload">
      Загрузить сохранённую версию вместо введённой
    </button>
    <form v-if="!loading && !pageError" @submit.prevent="submit" :aria-busy="saving">
      <fieldset :disabled="saving">
        <legend>Текст новости</legend>
        <label>Заголовок<input v-model="form.title" maxlength="255" required /></label>
        <label>Короткий анонс<textarea v-model="form.summary" maxlength="500"></textarea></label>
        <p>
          Анонс показывается на главной. Если оставить его пустым, появится начало текста новости.
        </p>
        <label>Текст<textarea v-model="form.body" maxlength="100000" rows="16"></textarea></label>
        <p>
          Обычный текст с абзацами. Черновик можно сохранить без текста; перед публикацией его нужно
          заполнить.
        </p>
        <p v-if="basis?.is_published">
          Новость опубликована: сохранённые изменения сразу появятся на сайте.
        </p>
        <p v-else>После сохранения опубликуйте новость в списке новостей.</p>
        <button type="submit">{{ saving ? 'Сохраняем…' : 'Сохранить новость' }}</button>
      </fieldset>
      <p v-if="submitError" role="alert" class="message">{{ submitError }}</p>
      <p v-if="notice" role="status">{{ notice }}</p>
    </form>
  </section>
</template>
<style scoped>
fieldset,
label {
  display: grid;
  gap: 0.5rem;
}
fieldset {
  margin-block: 1rem;
}
.message {
  white-space: pre-wrap;
}
</style>
