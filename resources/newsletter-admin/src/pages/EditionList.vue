<template>
  <div class="edition-list">
    <div class="page-header">
      <h2>Editions</h2>
      <button class="btn btn-primary" @click="showForm = !showForm">
        {{ showForm ? 'Cancel' : 'New Edition' }}
      </button>
    </div>

    <form v-if="showForm" class="create-form card" @submit.prevent="onCreate">
      <h3>Create Edition</h3>
      <div class="form-row">
        <label>
          Headline
          <input v-model="form.headline" type="text" required placeholder="Elder Newsletter — May 2026" />
        </label>
      </div>
      <div class="form-row form-row--inline">
        <label>
          Volume
          <input v-model.number="form.volume" type="number" min="1" required />
        </label>
        <label>
          Issue
          <input v-model.number="form.issue_number" type="number" min="1" required />
        </label>
        <label>
          Community ID
          <input v-model="form.community_id" type="text" required />
        </label>
      </div>
      <button class="btn btn-primary" type="submit" :disabled="saving">
        {{ saving ? 'Creating...' : 'Create' }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>

    <p v-if="loading">Loading editions...</p>
    <p v-else-if="!editions.length && !loading">No editions yet.</p>

    <table v-if="editions.length" class="editions-table card">
      <thead>
        <tr>
          <th>Headline</th>
          <th>Vol / Issue</th>
          <th>Community</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="ed in editions" :key="ed.id" class="clickable" @click="goTo(ed.id)">
          <td>{{ ed.headline }}</td>
          <td>Vol. {{ ed.volume }} / #{{ ed.issue_number }}</td>
          <td>{{ ed.community_id }}</td>
          <td><span class="badge" :class="'badge--' + ed.status">{{ ed.status }}</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { listEditions, createEdition } from '../api.js'

const router = useRouter()
const editions = ref([])
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const error = ref('')

const form = ref({
  headline: '',
  volume: 1,
  issue_number: 1,
  community_id: 'manitoulin-regional',
})

onMounted(async () => {
  try {
    const data = await listEditions()
    editions.value = data.editions ?? data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

async function onCreate() {
  saving.value = true
  error.value = ''
  try {
    const result = await createEdition(form.value)
    const id = result.id ?? result.edition?.id
    if (id) {
      router.push({ name: 'edition', params: { id } })
    }
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

function goTo(id) {
  router.push({ name: 'edition', params: { id } })
}
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.card {
  background: #fff;
  border-radius: 8px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.create-form {
  margin-bottom: 1.5rem;
}

.create-form h3 {
  margin-bottom: 0.75rem;
}

.form-row {
  margin-bottom: 0.75rem;
}

.form-row label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.25rem;
}

.form-row input {
  display: block;
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #cbd5e0;
  border-radius: 4px;
  font-size: 0.9rem;
}

.form-row--inline {
  display: flex;
  gap: 1rem;
}

.form-row--inline label {
  flex: 1;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  font-size: 0.875rem;
  cursor: pointer;
  font-weight: 500;
}

.btn-primary {
  background: #2c5282;
  color: #fff;
}

.btn-primary:hover {
  background: #2b4c7e;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.editions-table {
  width: 100%;
  border-collapse: collapse;
}

.editions-table th {
  text-align: left;
  padding: 0.625rem 0.75rem;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #718096;
}

.editions-table td {
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid #e2e8f0;
}

.clickable {
  cursor: pointer;
}

.clickable:hover {
  background: #f7fafc;
}

.badge {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}

.badge--draft { background: #e2e8f0; color: #4a5568; }
.badge--generated { background: #c6f6d5; color: #276749; }
.badge--sent { background: #bee3f8; color: #2b6cb0; }

.error {
  color: #c53030;
  margin-top: 0.5rem;
  font-size: 0.875rem;
}
</style>
