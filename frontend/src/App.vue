<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { refreshSession, session } from './api/session'
import { logout } from './api/auth'
import NavIcon from './components/NavIcon.vue'
import { withConfirmedLeave } from './composables/useUnsavedChanges'
const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)
const loggingOut = ref(false)
const logoutError = ref('')
async function signOut(): Promise<void> {
  if (loggingOut.value) return
  loggingOut.value = true
  logoutError.value = ''
  try {
    await withConfirmedLeave(async () => {
      await logout()
      await router.replace('/login')
    })
  } catch (cause) {
    logoutError.value = cause instanceof Error ? cause.message : 'Не удалось выйти'
  } finally {
    loggingOut.value = false
  }
}
watch(
  () => route.path,
  () => {
    menuOpen.value = false
  },
)

onMounted(async () => {
  try {
    await refreshSession()
  } catch {}
})
</script>

<template>
  <a class="skip-link" href="#page-content">К содержимому</a>
  <div class="app-shell">
    <aside class="app-sidebar">
      <div class="sidebar-brand-row">
        <RouterLink class="brand" to="/" aria-label="Samdaught Space — на главную">
          <svg class="brand-mark" viewBox="0 0 40 44" fill="none" aria-hidden="true">
            <path
              d="M28 4a16 16 0 1 0 7 27A17 17 0 0 1 28 4Z"
              stroke="currentColor"
              stroke-width="1.5"
            />
            <path d="m12 12 2 5 5 2-5 2-2 5-2-5-5-2 5-2Z" fill="currentColor" />
          </svg>
          <span
            ><span class="brand-name">Samdaught<br />Space</span
            ><span class="brand-caption">Место для приключений</span></span
          >
        </RouterLink>
        <button
          class="mobile-menu-toggle"
          type="button"
          :aria-expanded="menuOpen"
          aria-controls="main-navigation"
          @click="menuOpen = !menuOpen"
        >
          {{ menuOpen ? 'Закрыть' : 'Меню' }}
        </button>
      </div>
      <nav
        id="main-navigation"
        class="sidebar-navigation"
        :class="{ 'is-open': menuOpen }"
        aria-label="Главная навигация"
      >
        <RouterLink to="/"><NavIcon name="home" />Главная</RouterLink>
        <RouterLink to="/archive"><NavIcon name="book" />Архив</RouterLink>
        <RouterLink to="/help"><NavIcon name="book" />Справка</RouterLink>
        <template v-if="session?.authenticated && !session.must_change_password">
          <p class="nav-heading">Мои приключения</p>
          <RouterLink to="/cabinet"><NavIcon name="character" />Персонажи</RouterLink>
          <RouterLink to="/characters/new"><NavIcon name="plus" />Создать персонажа</RouterLink>
        </template>
        <p v-if="session?.can_manage_catalog || session?.can_manage_news" class="nav-heading">
          Мастерская
        </p>
        <template v-if="session?.can_manage_catalog">
          <RouterLink to="/admin/archive/entries"><NavIcon name="quill" />Записи архива</RouterLink>
          <RouterLink to="/admin/content-sources"><NavIcon name="book" />Источники</RouterLink>
        </template>
        <RouterLink v-if="session?.can_manage_news" to="/admin/news"
          ><NavIcon name="news" />Новости сайта</RouterLink
        >
        <p class="nav-heading">Аккаунт</p>
        <template v-if="session?.authenticated">
          <p class="sidebar-account">{{ session.username }}</p>
          <RouterLink v-if="!session.must_change_password" to="/change-password"
            ><NavIcon name="lock" />Сменить пароль</RouterLink
          >
          <button class="sidebar-logout" type="button" :disabled="loggingOut" @click="signOut">
            <NavIcon name="logout" />{{ loggingOut ? 'Выходим…' : 'Выйти' }}
          </button>
        </template>
        <RouterLink v-else to="/login"><NavIcon name="login" />Войти</RouterLink>
        <p v-if="logoutError" class="sidebar-error" role="alert">{{ logoutError }}</p>
      </nav>
    </aside>
    <div class="app-main">
      <main id="page-content" class="app-content page-content" tabindex="-1"><RouterView /></main>
      <footer class="app-footer">
        <span
          >© {{ new Date().getFullYear() }}
          <RouterLink to="/help#contact">Связь с администратором</RouterLink></span
        >
      </footer>
    </div>
  </div>
</template>
