<script setup lang="ts">
import { publicAsset } from '../utils/publicAsset'
const illustration = publicAsset('404_1.webp')
</script>
<template>
  <section class="illustrated-state">
    <div>
      <span class="state-code">404</span>
      <h1>Извини, магия не сработала.</h1>
      <p>Похоже, портал открылся не туда. Этой страницы нет, но путь домой мы помним.</p>
      <div class="action-row">
        <RouterLink class="button button-primary" to="/">На главную</RouterLink
        ><RouterLink class="button" to="/archive">В архив</RouterLink>
      </div>
    </div>
    <img
      :src="illustration"
      alt="Ведьмочка и её помощники разбираются с неудачным зельем"
      width="1536"
      height="1024"
    />
  </section>
</template>
