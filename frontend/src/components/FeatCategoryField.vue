<script setup lang="ts">
import type { FeatCategory } from '../api/featCategories'

defineProps<{ options: FeatCategory[] }>()
const code = defineModel<string>({ required: true })
</script>

<template>
  <label>
    Категория черты
    <select v-model="code" required>
      <option disabled value="">Выберите категорию черты</option>
      <option v-for="category in options" :key="category.code" :value="category.code">
        {{ category.title }}
      </option>
    </select>
  </label>
  <p>Условия получения и эффекты черты пока указываются в описании.</p>
</template>
