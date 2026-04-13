<template>
  <div class="app">
    <header class="app-header">
      <h1><router-link to="/">Newsletter Builder</router-link></h1>
    </header>
    <main class="app-main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
</script>

<style>
*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  background: #f5f5f5;
  color: #1a202c;
  line-height: 1.5;
}

.app-header {
  background: #2c5282;
  color: #fff;
  padding: 0.75rem 1.5rem;
}

.app-header h1 {
  font-size: 1.25rem;
  font-weight: 600;
}

.app-header a {
  color: inherit;
  text-decoration: none;
}

.app-main {
  max-width: 1200px;
  margin: 1.5rem auto;
  padding: 0 1rem;
}
</style>
