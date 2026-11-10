import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import ArchiveView from '../views/ArchiveView.vue'
import ArchiveEntryView from '../views/ArchiveEntryView.vue'
import HelpView from '../views/HelpView.vue'
import ContentSourcesView from '../views/admin/ContentSourcesView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import ArchiveEntryCreateView from '../views/admin/ArchiveEntryCreateView.vue'
import ArchiveEntriesView from '../views/admin/ArchiveEntriesView.vue'
import ArchiveEntryEditView from '../views/admin/ArchiveEntryEditView.vue'
import CharacterEditorView from '../views/characters/CharacterEditorView.vue'
import CharacterSheetView from '../views/characters/CharacterSheetView.vue'
import CabinetView from '../views/characters/CabinetView.vue'
import AccessDeniedView from '../views/AccessDeniedView.vue'
import { refreshSession, session } from '../api/session'
import AuthenticationView from '../views/users/AuthenticationView.vue'
import NewsView from '../views/NewsView.vue'
import NewsListView from '../views/admin/NewsListView.vue'
import NewsEditView from '../views/admin/NewsEditView.vue'
import { confirmLeave, finishNavigation } from '../composables/useUnsavedChanges'

export const router = createRouter({
  history: createWebHistory('/'),
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash }
    return { top: 0 }
  },
  routes: [
    { path: '/', component: HomeView },
    {
      path: '/login',
      component: AuthenticationView,
      props: { mode: 'login' },
      meta: { guestOnly: true },
    },
    {
      path: '/signup',
      component: AuthenticationView,
      props: { mode: 'signup' },
      meta: { guestOnly: true },
    },
    {
      path: '/change-password',
      component: AuthenticationView,
      props: { mode: 'change' },
      meta: { requiresAuth: true },
    },
    {
      path: '/force-password-change',
      component: AuthenticationView,
      props: { mode: 'forced' },
      meta: { requiresAuth: true, forcedPassword: true },
    },
    { path: '/archive', component: ArchiveView },
    { path: '/archive/:id', component: ArchiveEntryView },
    { path: '/help', component: HelpView },
    { path: '/news/:id', component: NewsView },
    {
      path: '/admin/news',
      component: NewsListView,
      meta: { requiresAuth: true, manageNews: true },
    },
    {
      path: '/admin/news/new',
      component: NewsEditView,
      meta: { requiresAuth: true, manageNews: true },
    },
    {
      path: '/admin/news/:id/edit',
      component: NewsEditView,
      meta: { requiresAuth: true, manageNews: true },
    },
    { path: '/cabinet', component: CabinetView, meta: { requiresAuth: true } },
    { path: '/characters/new', component: CharacterEditorView, meta: { requiresAuth: true } },
    { path: '/characters/:id/edit', component: CharacterEditorView, meta: { requiresAuth: true } },
    { path: '/characters/:id', component: CharacterSheetView, meta: { requiresAuth: true } },
    { path: '/access-denied', component: AccessDeniedView },
    { path: '/:pathMatch(.*)*', component: NotFoundView },
    {
      path: '/admin/content-sources',
      component: ContentSourcesView,
      meta: { requiresAuth: true, manageCatalog: true },
    },
    {
      path: '/admin/archive/entries',
      component: ArchiveEntriesView,
      meta: { requiresAuth: true, manageCatalog: true },
    },
    {
      path: '/admin/archive/entries/new',
      component: ArchiveEntryCreateView,
      meta: { requiresAuth: true, manageCatalog: true },
    },
    {
      path: '/admin/archive/entries/:id/edit',
      component: ArchiveEntryEditView,
      meta: { requiresAuth: true, manageCatalog: true },
    },
  ],
})

router.beforeEach(async (to, from) => {
  if (to.path !== from.path && !confirmLeave()) return false
  const adminPage = to.path === '/admin' || to.path.startsWith('/admin/')
  const newsPage =
    to.path === '/admin/news' || to.path.startsWith('/admin/news/') || to.meta.manageNews
  if (session.value?.must_change_password && !to.meta.forcedPassword)
    return '/force-password-change'
  if (!to.meta.requiresAuth && !adminPage && !to.meta.guestOnly) return true
  try {
    const current = await refreshSession()
    if (current.must_change_password && !to.meta.forcedPassword) return '/force-password-change'
    if (!current.authenticated && !to.meta.guestOnly)
      return {
        path: '/login',
        query: to.meta.forcedPassword ? { expired: '1' } : { redirect: to.fullPath },
      }
    if (current.authenticated && to.meta.guestOnly) return '/cabinet'
    if (to.meta.forcedPassword && !current.must_change_password) return '/cabinet'
    if (newsPage && !current.can_manage_news)
      return { path: '/access-denied', query: { reason: 'forbidden' } }
    if (!newsPage && (adminPage || to.meta.manageCatalog) && !current.can_manage_catalog)
      return { path: '/access-denied', query: { reason: 'forbidden' } }
    return true
  } catch {
    if (to.meta.guestOnly) return true
    return { path: '/access-denied', query: { reason: 'unavailable' } }
  }
})

router.afterEach((to, _from, failure) => {
  finishNavigation()
  if (failure) return
  const titles: Record<string, string> = {
    '/': 'Главная',
    '/archive': 'Архив',
    '/help': 'Справка',
    '/login': 'Вход',
    '/signup': 'Регистрация',
    '/cabinet': 'Мои персонажи',
    '/characters/new': 'Создание персонажа',
    '/change-password': 'Смена пароля',
    '/force-password-change': 'Новый пароль',
    '/admin/news': 'Новости сайта',
    '/admin/news/new': 'Новая новость',
    '/admin/archive/entries': 'Записи архива',
    '/admin/archive/entries/new': 'Новая запись',
    '/admin/content-sources': 'Источники',
    '/access-denied': 'Нет доступа',
  }
  document.title =
    titles[to.path] ??
    (to.path.startsWith('/characters/')
      ? to.path.endsWith('/edit')
        ? 'Редактор персонажа'
        : 'Лист персонажа'
      : to.path.startsWith('/archive/')
        ? 'Запись архива'
        : to.path.endsWith('/edit')
          ? 'Редактирование'
          : to.path.startsWith('/news/')
            ? 'Новость'
            : 'Страница не найдена')
})
router.onError(finishNavigation)
