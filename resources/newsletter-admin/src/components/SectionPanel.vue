<template>
  <div class="section-panel">
    <button class="section-header" @click="open = !open">
      <span class="section-title">{{ label }} <span class="item-count">({{ items.length }})</span></span>
      <span class="chevron" :class="{ 'chevron--open': open }">&#9654;</span>
    </button>
    <div v-if="open" class="section-body">
      <p v-if="!items.length" class="empty">No items in this section.</p>
      <ItemCard
        v-for="(item, idx) in items"
        :key="item.id"
        :item="item"
        :is-first="idx === 0"
        :is-last="idx === items.length - 1"
        @move-up="$emit('reorder', item.id, item.position - 1)"
        @move-down="$emit('reorder', item.id, item.position + 1)"
        @remove="$emit('remove', item.id)"
      />
      <button class="btn btn-small" @click="$emit('add')">+ Add Item</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ItemCard from './ItemCard.vue'

defineProps({
  section: { type: String, required: true },
  label: { type: String, required: true },
  items: { type: Array, default: () => [] },
})

defineEmits(['add', 'remove', 'reorder'])

const open = ref(true)
</script>

<style scoped>
.section-panel {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  margin-bottom: 0.75rem;
  overflow: hidden;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 0.75rem 1rem;
  border: none;
  background: #edf2f7;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 600;
  text-align: left;
}

.section-header:hover {
  background: #e2e8f0;
}

.item-count {
  font-weight: 400;
  color: #718096;
  font-size: 0.85rem;
}

.chevron {
  font-size: 0.7rem;
  transition: transform 0.15s;
}

.chevron--open {
  transform: rotate(90deg);
}

.section-body {
  padding: 0.75rem 1rem;
}

.empty {
  color: #a0aec0;
  font-size: 0.85rem;
  margin-bottom: 0.5rem;
}

.btn {
  padding: 0.35rem 0.75rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 500;
}

.btn-small {
  background: #ebf4ff;
  color: #2c5282;
}

.btn-small:hover {
  background: #bee3f8;
}
</style>
