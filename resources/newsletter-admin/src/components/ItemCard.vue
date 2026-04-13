<template>
  <div class="item-card">
    <span class="position">#{{ item.position }}</span>
    <span class="source-badge" :class="'source--' + item.source_type">{{ item.source_type }}</span>
    <span class="title">{{ item.inline_title || item.editor_blurb || '(untitled)' }}</span>
    <span class="actions">
      <button :disabled="isFirst" title="Move up" @click="$emit('move-up')">&#9650;</button>
      <button :disabled="isLast" title="Move down" @click="$emit('move-down')">&#9660;</button>
      <button class="remove" title="Remove" @click="$emit('remove')">&#10005;</button>
    </span>
  </div>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
  isFirst: { type: Boolean, default: false },
  isLast: { type: Boolean, default: false },
})

defineEmits(['move-up', 'move-down', 'remove'])
</script>

<style scoped>
.item-card {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #edf2f7;
}

.item-card:last-of-type {
  border-bottom: none;
}

.position {
  font-size: 0.75rem;
  color: #a0aec0;
  min-width: 1.5rem;
}

.source-badge {
  display: inline-block;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
}

.source--inline { background: #fefcbf; color: #975a16; }
.source--entity { background: #c6f6d5; color: #276749; }

.title {
  flex: 1;
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.actions {
  display: flex;
  gap: 0.25rem;
}

.actions button {
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  width: 1.5rem;
  height: 1.5rem;
  font-size: 0.65rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.actions button:hover:not(:disabled) {
  background: #edf2f7;
}

.actions button:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.actions .remove:hover:not(:disabled) {
  background: #fed7d7;
  border-color: #fc8181;
}
</style>
