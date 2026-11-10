<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { getCharacters, type CharactersResponse } from '../../api/characters'
import { deleteCharacter } from '../../api/characterEditor'

const charactersPage = ref<CharactersResponse | null>(null)
const loading = ref(false)
const error = ref('')
const deleting = ref(false)

async function removeCharacter(id: number, revision: number): Promise<void> {
  if (loading.value || deleting.value || !window.confirm('Удалить этого персонажа и его лист?'))
    return
  deleting.value = true
  error.value = ''
  try {
    await deleteCharacter(id, revision)
    await loadPage(1)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось удалить персонажа'
  } finally {
    deleting.value = false
  }
}

async function loadPage(page: number = 1) {
  if (loading.value) return

  loading.value = true
  error.value = ''

  try {
    charactersPage.value = await getCharacters(page)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить персонажей'
  } finally {
    loading.value = false
  }
}

async function changePage(direction: -1 | 1) {
  const current = charactersPage.value

  if (current === null || loading.value) return

  const targetPage = current.meta.page + direction

  if (targetPage < 1 || targetPage > current.meta.page_count) return

  await loadPage(targetPage)
}

onMounted(async () => {
  await loadPage()
})
</script>

<template>
  <header class="page-heading">
    <div>
      <span class="eyebrow">Личный кабинет</span>
      <h1>Мои персонажи</h1>
    </div>

    <RouterLink class="button button-primary" to="/characters/new">Создать персонажа</RouterLink>
  </header>

  <p v-if="loading" role="status">Загружаем персонажей…</p>
  <p v-if="error" role="alert">{{ error }}</p>

  <template v-if="charactersPage">
    <div v-if="charactersPage.data.length === 0" class="empty-state">
      <h2>Первый лист ещё впереди</h2>
      <p>
        Создайте персонажа и заполняйте его в удобном темпе. Незавершённый лист сохранится как
        черновик.
      </p>
    </div>

    <div v-else class="table-scroll" role="region" aria-label="Мои персонажи" tabindex="0">
      <table class="characters-table">
        <thead>
          <tr>
            <th scope="col">Имя</th>
            <th scope="col">Мир и правила</th>
            <th scope="col">Желаемый уровень</th>
            <th scope="col">Статус</th>
            <th scope="col">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="character in charactersPage.data" :key="character.id">
            <td>{{ character.name }}</td>
            <td>{{ character.rule_profile_title }}</td>
            <td>{{ character.target_level }}</td>
            <td>
              <span
                class="status-badge"
                :class="{ 'status-draft': character.status === 'draft' }"
                >{{ character.status === 'draft' ? 'Черновик' : 'Готовый лист' }}</span
              >
            </td>
            <td>
              <div class="table-actions">
                <RouterLink class="table-action" :to="`/characters/${character.id}`"
                  >Открыть лист</RouterLink
                >
                <RouterLink
                  v-if="character.status === 'draft'"
                  class="table-action"
                  :to="`/characters/${character.id}/edit`"
                >
                  Редактировать
                </RouterLink>
                <button
                  type="button"
                  class="button-danger"
                  :disabled="loading || deleting"
                  @click="removeCharacter(character.id, character.revision)"
                >
                  Удалить
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="list-summary">Всего персонажей: {{ charactersPage.meta.total }}</p>

    <nav v-if="charactersPage.meta.page_count > 1" aria-label="Страницы моих персонажей">
      <button
        type="button"
        :disabled="loading || charactersPage.meta.page <= 1"
        @click="changePage(-1)"
      >
        Назад
      </button>
      <span aria-live="polite">
        Страница {{ charactersPage.meta.page }} из {{ charactersPage.meta.page_count }}
      </span>
      <button
        type="button"
        :disabled="loading || charactersPage.meta.page >= charactersPage.meta.page_count"
        @click="changePage(1)"
      >
        Вперёд
      </button>
    </nav>
  </template>
</template>
