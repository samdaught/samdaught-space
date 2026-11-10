<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { getContentSources, type ContentSource } from '../../api/contentSources'
import { createContentSource } from '../../api/contentSources'

import { useFormChanges } from '../../composables/useFormChanges'

const sources = ref<ContentSource[]>([])
const error = ref('')

onMounted(async () => {
  try {
    sources.value = await getContentSources()
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить источники'
  }
})

const form = ref({ code: '', title: '', rights_note: '' })
const saving = ref(false)
const submitError = ref('')
const changes = useFormChanges(
  () => form.value,
  () => saving.value,
)

async function submit() {
  if (saving.value) return

  saving.value = true
  submitError.value = ''

  try {
    const created = await createContentSource(form.value)
    sources.value.push(created)
    form.value = { code: '', title: '', rights_note: '' }
    changes.markSaved()
  } catch (cause) {
    submitError.value = cause instanceof Error ? cause.message : 'Не удалось создать источник'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <h1>Источники</h1>
  <p v-if="error">{{ error }}</p>
  <ul v-else>
    <li v-for="source in sources" :key="source.id">
      {{ source.title }}
    </li>
  </ul>
  <form @submit.prevent="submit">
    <fieldset :disabled="saving">
      <legend>Новый источник</legend>
      <label>Код <input v-model="form.code" required maxlength="64" /></label>
      <label>Название <input v-model="form.title" required maxlength="255" /></label>
      <label>Условия использования <textarea v-model="form.rights_note" required></textarea></label>
      <button type="submit" :disabled="saving">Добавить источник</button>
    </fieldset>
    <p v-if="submitError">{{ submitError }}</p>
  </form>
</template>
