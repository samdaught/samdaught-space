<template>
  <h1>Записи архива</h1>
  <nav class="archive-management-tabs" aria-label="Управление архивом">
    <RouterLink to="/admin/archive/entries">Записи</RouterLink>
    <RouterLink to="/admin/archive/entries/sources">Источники</RouterLink>
  </nav>
  <RouterView />
</template>

<style scoped>
.archive-management-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 1.25rem 0 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--line);
}
.archive-management-tabs a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0.6rem 1rem;
  border-radius: 7px;
  text-decoration: none;
}
.archive-management-tabs a.router-link-exact-active {
  color: var(--on-ink);
  background: var(--ink);
}
.archive-management-tabs a:hover:not(.router-link-exact-active) {
  background: var(--lavender);
}
</style>
