<script setup lang="ts">
defineProps<{
  name: 'home' | 'book' | 'character' | 'plus' | 'quill' | 'news' | 'login' | 'logout' | 'lock'
}>()
</script>
<template>
  <svg
    class="nav-icon"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path v-if="name === 'home'" d="m3 10 9-7 9 7v10H3V10Zm6 10v-7h6v7" />
    <path
      v-else-if="name === 'book'"
      d="M12 5c-3-2-7-2-10-1v15c3-1 7-1 10 1m0-15c3-2 7-2 10-1v15c-3-1-7-1-10 1V5Z"
    />
    <g v-else-if="name === 'character'">
      <circle cx="12" cy="7" r="4" />
      <path d="M4 21v-2a8 8 0 0 1 16 0v2H4Z" />
    </g>
    <path v-else-if="name === 'plus'" d="M12 4v16M4 12h16" />
    <path
      v-else-if="name === 'quill'"
      d="M4 20 15 9m-9 9C2 8 12 2 21 3c0 9-6 18-15 15ZM11 13l6 1"
    />
    <path v-else-if="name === 'login'" d="M15 4h5v16h-5M10 8l4 4-4 4M3 12h11" />
    <path v-else-if="name === 'logout'" d="M9 4H4v16h5m5-12 4 4-4 4m-6-4h13" />
    <g v-else-if="name === 'lock'">
      <rect x="5" y="10" width="14" height="11" rx="2" />
      <path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3" />
    </g>
    <path v-else d="M4 3h16v18H4V3Zm4 5h8m-8 4h8m-8 4h5" />
  </svg>
</template>
