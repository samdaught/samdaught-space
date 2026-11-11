<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getArchiveEntry, type ArchiveDetails } from '../api/archive'
import SpeciesCardContent from '../components/SpeciesCardContent.vue'
import ClassCardContent from '../components/ClassCardContent.vue'
import SpellCardContent from '../components/SpellCardContent.vue'
const route = useRoute(),
  entry = ref<ArchiveDetails | null>(null),
  error = ref(''),
  loading = ref(false)
let request = 0
onBeforeUnmount(() => {
  ++request
})
const sourceUrl = computed(() => {
  try {
    const url = new URL(entry.value?.source_url ?? '')
    return ['https:', 'http:'].includes(url.protocol) ? url.href : null
  } catch {
    return null
  }
})
async function load(): Promise<void> {
  const current = ++request
  loading.value = true
  error.value = ''
  entry.value = null
  try {
    const id = Number(route.params.id)
    if (!Number.isSafeInteger(id) || id < 1) throw new Error('Некорректный адрес записи')
    const data = await getArchiveEntry(id)
    if (current === request) {
      entry.value = data
      document.title = data.title + ' — Архив'
    }
  } catch (cause) {
    if (current === request)
      error.value = cause instanceof Error ? cause.message : 'Не удалось открыть запись'
  } finally {
    if (current === request) loading.value = false
  }
}
watch(() => route.params.id, load, { immediate: true })
</script>
<template>
  <RouterLink :to="{ path: '/archive', query: route.query }">К архиву</RouterLink>
  <p v-if="loading" role="status">Открываем запись…</p>
  <p v-else-if="error" role="alert">{{ error }} <button @click="load">Повторить</button></p>
  <article v-else-if="entry" class="reading-page">
    <p class="eyebrow">{{ entry.type_title }} · {{ entry.profile_title }}</p>
    <h1>{{ entry.title }}</h1>
    <SpeciesCardContent
      v-if="entry.species_card"
      :card="entry.species_card"
      :body="entry.body ?? ''"
      :title="entry.title"
    />
    <ClassCardContent
      v-else-if="entry.class_card && entry.class_details"
      :card="entry.class_card"
      :details="entry.class_details"
      :body="entry.body ?? ''"
      :title="entry.title"
    />
    <SpellCardContent v-else-if="entry.spell" :spell="entry.spell" :body="entry.body ?? ''" />
    <p v-else class="preserve-lines">{{ entry.body }}</p>
    <footer v-if="entry.source_title" class="entry-source">
      Источник:
      <a v-if="sourceUrl" :href="sourceUrl" target="_blank" rel="noopener noreferrer">{{
        entry.source_title
      }}</a
      ><span v-else>{{ entry.source_title }}</span>
      <p v-if="entry.attribution_text">{{ entry.attribution_text }}</p>
    </footer>
  </article>
</template>
