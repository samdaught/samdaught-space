<script setup lang="ts">
import { useRoute } from 'vue-router'
import { session } from '../api/session'
import { publicAsset } from '../utils/publicAsset'
const route = useRoute()
const illustration = publicAsset('login.webp')
</script>

<template>
  <section class="illustrated-state">
    <div>
      <span class="eyebrow">На страже портала</span>
      <h1>Доступ закрыт</h1>
      <p v-if="route.query.reason === 'signin'">Войдите в аккаунт, чтобы открыть эту страницу.</p>
      <p v-else-if="route.query.reason === 'unavailable'">
        Не удалось проверить права. Попробуйте открыть нужную страницу позже.
      </p>
      <p v-else>У вашего аккаунта нет права открывать эту страницу.</p>
      <div class="action-row">
        <RouterLink v-if="!session?.authenticated" class="button button-primary" to="/login"
          >Войти</RouterLink
        >
        <RouterLink class="button" to="/">На главную</RouterLink>
      </div>
    </div>
    <img :src="illustration" alt="Ведьмочка охраняет вход в портал" width="1448" height="1086" />
  </section>
</template>
