<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AuthenticationError,
  login,
  signup,
  changePassword,
  safeReturnPath,
  type AuthFields,
} from '../../api/auth'
import { publicAsset } from '../../utils/publicAsset'

type Mode = 'login' | 'signup' | 'change' | 'forced'
const props = defineProps<{ mode: Mode }>()
const route = useRoute(),
  router = useRouter()
const username = ref(''),
  password = ref(''),
  passwordRepeat = ref('')
const currentPassword = ref(''),
  newPassword = ref(''),
  newPasswordRepeat = ref('')
const rememberMe = ref(false),
  busy = ref(false),
  error = ref(''),
  notice = ref('')
const fields = ref<AuthFields>({}),
  registered = ref(false)
let generation = 0
const isPassword = computed(() => props.mode === 'change' || props.mode === 'forced')
const title = computed(
  () =>
    ({
      login: 'С возвращением',
      signup: 'Создать аккаунт',
      change: 'Сменить пароль',
      forced: 'Новый пароль',
    })[props.mode],
)
const illustration = computed(() =>
  publicAsset(props.mode === 'login' ? 'Authorization.webp' : 'login.webp'),
)
function clearPasswords(): void {
  password.value = ''
  passwordRepeat.value = ''
  currentPassword.value = ''
  newPassword.value = ''
  newPasswordRepeat.value = ''
}
watch(
  () => props.mode,
  () => {
    ++generation
    busy.value = false
    error.value = ''
    notice.value = ''
    fields.value = {}
    registered.value = false
    clearPasswords()
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  ++generation
  clearPasswords()
})

async function submit(): Promise<void> {
  if (busy.value || registered.value) return
  busy.value = true
  error.value = ''
  notice.value = ''
  fields.value = {}
  const current = generation
  const mode = props.mode
  let committed = false
  try {
    let target: string | { path: string; query: Record<string, string> }
    if (mode === 'login') {
      const result = await login(username.value, password.value, rememberMe.value)
      target = result.must_change_password
        ? '/force-password-change'
        : safeReturnPath(route.query.redirect ?? result.return_url)
    } else if (mode === 'signup') {
      await signup(username.value, password.value, passwordRepeat.value)
      if (current === generation) registered.value = true
      target = { path: '/login', query: { registered: '1' } }
    } else {
      await changePassword(
        newPassword.value,
        newPasswordRepeat.value,
        mode === 'change' ? currentPassword.value : undefined,
      )
      target = { path: '/login', query: { changed: '1' } }
    }
    committed = true
    if (current !== generation) return
    const failure = await router.replace(target)
    if (failure && current === generation)
      notice.value = 'Готово. Перейдите в нужный раздел через меню.'
  } catch (cause) {
    if (current !== generation) return
    if (committed) notice.value = 'Готово. Перейдите в нужный раздел через меню.'
    else if (cause instanceof AuthenticationError && cause.status === 401 && isPassword.value) {
      error.value = 'Сессия истекла. Войдите снова.'
      try {
        await router.replace({ path: '/login', query: { expired: '1' } })
      } catch {}
    } else if (cause instanceof AuthenticationError && Object.keys(cause.fields).length > 0)
      fields.value = cause.fields
    else error.value = cause instanceof Error ? cause.message : 'Не удалось выполнить запрос'
  } finally {
    if (current === generation) {
      busy.value = false
      clearPasswords()
    }
  }
}
</script>

<template>
  <div class="auth-page vue-auth-page">
    <aside class="auth-art">
      <img
        :src="illustration"
        :alt="
          mode === 'login'
            ? 'Ведьмочка проверяет аккаунт в волшебной книге'
            : 'Ведьмочка не пускает кентавра в портал'
        "
        :width="mode === 'login' ? 1217 : 1448"
        :height="mode === 'login' ? 1292 : 1086"
      />
      <h2>
        {{
          mode === 'signup'
            ? 'Кентаврам вход запрещён.'
            : isPassword
              ? 'Портал под защитой.'
              : 'Проверим волшебную запись.'
        }}
      </h2>
      <p>
        {{
          mode === 'forced'
            ? 'Временный пароль нужно заменить своим.'
            : mode === 'change'
              ? 'Задай новый пароль для своего аккаунта.'
              : mode === 'signup'
                ? 'Один аккаунт — все твои персонажи.'
                : 'Твои персонажи уже ждут.'
        }}
      </p>
    </aside>
    <section class="auth-panel" aria-labelledby="auth-title">
      <span class="eyebrow">{{ isPassword ? 'Безопасность аккаунта' : 'Личный кабинет' }}</span>
      <h1 id="auth-title">{{ title }}</h1>
      <p v-if="mode === 'forced'">
        Замени временный пароль, чтобы продолжить. После сохранения войди заново.
      </p>
      <p v-else-if="mode === 'change'">После смены пароля нужно будет войти заново.</p>
      <p v-else-if="mode === 'signup'">Создай аккаунт, чтобы сохранять персонажей.</p>
      <p v-else>Войди, чтобы продолжить работу с персонажами.</p>
      <p v-if="mode === 'login' && route.query.registered === '1'" role="status">
        Аккаунт создан. Теперь можно войти.
      </p>
      <p v-if="mode === 'login' && route.query.changed === '1'" role="status">
        Пароль сохранён. Войди с новым паролем.
      </p>
      <p v-if="mode === 'login' && route.query.expired === '1'" role="alert">
        Сессия истекла. Войди снова.
      </p>
      <p v-if="error" role="alert">{{ error }}</p>
      <p v-if="Object.keys(fields).length" role="alert">Проверьте отмеченные поля.</p>
      <form @submit.prevent="submit" :aria-busy="busy">
        <fieldset class="auth-fields" :disabled="busy || registered">
          <legend class="visually-hidden">{{ title }}</legend>
          <label v-if="!isPassword" for="auth-username"
            >Логин
            <input
              id="auth-username"
              v-model="username"
              autocomplete="username"
              required
              maxlength="100"
              :aria-invalid="!!fields.username"
              :aria-describedby="fields.username ? 'error-username' : undefined"
            />
            <span v-if="fields.username" id="error-username" class="field-error">{{
              fields.username.join(' ')
            }}</span>
          </label>
          <label v-if="!isPassword" for="auth-password"
            >Пароль
            <input
              id="auth-password"
              v-model="password"
              type="password"
              :autocomplete="mode === 'signup' ? 'new-password' : 'current-password'"
              required
              :minlength="mode === 'signup' ? 8 : undefined"
              :maxlength="mode === 'signup' ? 64 : undefined"
              :aria-invalid="!!fields.password"
              :aria-describedby="fields.password ? 'error-password' : undefined"
            />
            <span v-if="fields.password" id="error-password" class="field-error">{{
              fields.password.join(' ')
            }}</span>
            <span v-if="mode === 'signup'" class="field-hint">От 8 до 64 символов.</span>
          </label>
          <label v-if="mode === 'signup'" for="auth-repeat"
            >Повторите пароль
            <input
              id="auth-repeat"
              v-model="passwordRepeat"
              type="password"
              autocomplete="new-password"
              required
              :aria-invalid="!!fields.passwordRepeat"
              :aria-describedby="fields.passwordRepeat ? 'error-repeat' : undefined"
            />
            <span v-if="fields.passwordRepeat" id="error-repeat" class="field-error">{{
              fields.passwordRepeat.join(' ')
            }}</span>
          </label>
          <label v-if="mode === 'change'" for="auth-current"
            >Текущий пароль
            <input
              id="auth-current"
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              required
              :aria-invalid="!!fields.currentPassword"
              :aria-describedby="fields.currentPassword ? 'error-current' : undefined"
            />
            <span v-if="fields.currentPassword" id="error-current" class="field-error">{{
              fields.currentPassword.join(' ')
            }}</span>
          </label>
          <label v-if="isPassword" for="auth-new"
            >Новый пароль
            <input
              id="auth-new"
              v-model="newPassword"
              type="password"
              autocomplete="new-password"
              required
              minlength="8"
              maxlength="64"
              :aria-invalid="!!fields.newPassword"
              :aria-describedby="fields.newPassword ? 'error-new' : 'password-hint'"
            />
            <span id="password-hint" class="field-hint">От 8 до 64 символов.</span>
            <span v-if="fields.newPassword" id="error-new" class="field-error">{{
              fields.newPassword.join(' ')
            }}</span>
          </label>
          <label v-if="isPassword" for="auth-new-repeat"
            >Повторите новый пароль
            <input
              id="auth-new-repeat"
              v-model="newPasswordRepeat"
              type="password"
              autocomplete="new-password"
              required
              :aria-invalid="!!fields.newPasswordRepeat"
              :aria-describedby="fields.newPasswordRepeat ? 'error-new-repeat' : undefined"
            />
            <span v-if="fields.newPasswordRepeat" id="error-new-repeat" class="field-error">{{
              fields.newPasswordRepeat.join(' ')
            }}</span>
          </label>
          <label v-if="mode === 'login'"
            ><input v-model="rememberMe" type="checkbox" />Запомнить меня</label
          >
          <button type="submit">
            {{
              busy
                ? 'Подождите…'
                : isPassword
                  ? 'Сохранить пароль'
                  : mode === 'signup'
                    ? 'Создать аккаунт'
                    : 'Войти'
            }}
          </button>
        </fieldset>
      </form>
      <p v-if="notice" role="status">{{ notice }}</p>
      <p v-if="mode === 'login'" class="auth-switch">
        Впервые здесь? <RouterLink to="/signup">Создать аккаунт</RouterLink>
      </p>
      <p v-if="mode === 'signup'" class="auth-switch">
        Уже есть аккаунт? <RouterLink to="/login">Войти</RouterLink>
      </p>
      <p v-if="mode === 'login'" class="auth-help">
        Если забыл пароль, обратись к администратору. Он выдаст временный.
      </p>
    </section>
  </div>
</template>
