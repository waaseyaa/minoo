<template>
  <div class="edition-detail">
    <div class="top-bar">
      <router-link to="/" class="back-link">&#8592; All Editions</router-link>
      <h2 v-if="edition">{{ edition.headline }}</h2>
      <span v-if="edition" class="badge" :class="'badge--' + edition.status">{{ edition.status }}</span>
    </div>

    <p v-if="loading">Loading edition...</p>
    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="edition">
      <div class="builder-layout">
        <!-- Left: Section editor -->
        <div class="editor-panel">
          <SectionPanel
            v-for="sec in sectionOrder"
            :key="sec"
            :section="sec"
            :label="sectionLabels[sec]"
            :items="itemsBySection(sec)"
            @add="openAddModal(sec)"
            @remove="onRemove"
            @reorder="onReorder"
          />
        </div>

        <!-- Right: Preview -->
        <div class="preview-panel">
          <div class="preview-header">
            <strong>Print Preview</strong>
            <button class="btn btn-small" @click="refreshPreview">Refresh Preview</button>
          </div>
          <iframe
            v-if="previewUrl"
            :src="previewUrl"
            class="preview-iframe"
          ></iframe>
          <p v-else class="preview-placeholder">Click "Refresh Preview" to load.</p>
        </div>
      </div>

      <!-- Action bar -->
      <div class="action-bar card">
        <button class="btn btn-primary" :disabled="generating" @click="onGenerate">
          {{ generating ? 'Generating...' : 'Generate PDF' }}
        </button>
        <span v-if="pdfInfo" class="pdf-info">PDF ready ({{ pdfInfo }})</span>
        <a v-if="edition.pdf_path" :href="pdfDownloadUrl" class="btn btn-outline" target="_blank">Download PDF</a>
        <button
          v-if="edition.pdf_path"
          class="btn btn-send"
          :disabled="sending"
          @click="onSend"
        >
          {{ sending ? 'Sending...' : 'Send to Printer' }}
        </button>
      </div>
    </template>

    <!-- Add Item Modal -->
    <div v-if="modal.open" class="modal-overlay" @click.self="modal.open = false">
      <div class="modal card">
        <h3>Add Item to {{ sectionLabels[modal.section] }}</h3>
        <div class="tabs">
          <button :class="{ active: modal.tab === 'inline' }" @click="modal.tab = 'inline'">Inline Content</button>
          <button :class="{ active: modal.tab === 'entity' }" @click="modal.tab = 'entity'">From Entity</button>
        </div>

        <!-- Inline tab -->
        <form v-if="modal.tab === 'inline'" @submit.prevent="addInlineItem">
          <label>Title <input v-model="modal.inline.title" type="text" /></label>
          <label>Body <textarea v-model="modal.inline.body" rows="4"></textarea></label>
          <label>Blurb <input v-model="modal.inline.blurb" type="text" /></label>
          <button class="btn btn-primary" type="submit">Add</button>
        </form>

        <!-- Entity tab -->
        <div v-if="modal.tab === 'entity'">
          <input
            v-model="modal.entityQuery"
            type="text"
            placeholder="Search entities..."
            @input="onEntitySearch"
          />
          <p v-if="modal.searching" class="searching">Searching...</p>
          <ul v-if="modal.results.length" class="entity-results">
            <li v-for="r in modal.results" :key="r.id" @click="addEntityItem(r)">
              <span class="entity-type-badge">{{ r.entity_type }}</span>
              {{ r.label }}
            </li>
          </ul>
        </div>

        <button class="btn btn-cancel" @click="modal.open = false">Cancel</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import SectionPanel from '../components/SectionPanel.vue'
import {
  getEdition, addItem, removeItem, reorderItem,
  entitySearch, getPreviewToken, generate, downloadUrl, send
} from '../api.js'

const props = defineProps({
  id: { type: String, required: true },
})

const sectionLabels = {
  cover: 'Cover',
  editors_note: "Editor's Note",
  news: 'News',
  events: 'Events',
  teachings: 'Teachings',
  language: 'Language',
  community: 'Community',
  language_corner: 'Language Corner',
  jokes: 'Jokes',
  puzzles: 'Puzzles',
  horoscope: 'Horoscope',
  elder_spotlight: 'Elder Spotlight',
  back_page: 'Back Page',
}

const sectionOrder = Object.keys(sectionLabels)

const edition = ref(null)
const itemsBySectionData = ref({})
const loading = ref(true)
const error = ref('')
const generating = ref(false)
const sending = ref(false)
const pdfInfo = ref('')
const previewUrl = ref('')

const modal = reactive({
  open: false,
  section: '',
  tab: 'inline',
  inline: { title: '', body: '', blurb: '' },
  entityQuery: '',
  results: [],
  searching: false,
})

let searchTimer = null

const pdfDownloadUrl = computed(() => edition.value ? downloadUrl(edition.value.id) : '')

onMounted(async () => {
  await loadEdition()
})

async function loadEdition() {
  loading.value = true
  error.value = ''
  try {
    const data = await getEdition(props.id)
    edition.value = data.edition ?? data
    itemsBySectionData.value = data.items_by_section ?? {}
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function itemsBySection(section) {
  const list = itemsBySectionData.value?.[section] ?? []
  return [...list].sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
}

function openAddModal(section) {
  modal.open = true
  modal.section = section
  modal.tab = 'inline'
  modal.inline = { title: '', body: '', blurb: '' }
  modal.entityQuery = ''
  modal.results = []
}

async function addInlineItem() {
  try {
    await addItem(edition.value.id, {
      section: modal.section,
      source_type: 'inline',
      title: modal.inline.title,
      body: modal.inline.body,
      blurb: modal.inline.blurb,
    })
    modal.open = false
    await loadEdition()
  } catch (e) {
    error.value = e.message
  }
}

async function addEntityItem(entity) {
  try {
    await addItem(edition.value.id, {
      section: modal.section,
      source_type: 'entity',
      source_entity_type: entity.entity_type,
      source_entity_id: entity.id,
    })
    modal.open = false
    await loadEdition()
  } catch (e) {
    error.value = e.message
  }
}

function onEntitySearch() {
  clearTimeout(searchTimer)
  if (!modal.entityQuery.trim()) {
    modal.results = []
    return
  }
  searchTimer = setTimeout(async () => {
    modal.searching = true
    try {
      const data = await entitySearch(modal.entityQuery)
      modal.results = data.results ?? data ?? []
    } catch (e) {
      modal.results = []
    } finally {
      modal.searching = false
    }
  }, 300)
}

async function onRemove(itemId) {
  try {
    await removeItem(edition.value.id, itemId)
    await loadEdition()
  } catch (e) {
    error.value = e.message
  }
}

async function onReorder(itemId, newPosition) {
  try {
    await reorderItem(edition.value.id, itemId, newPosition)
    await loadEdition()
  } catch (e) {
    error.value = e.message
  }
}

async function refreshPreview() {
  try {
    const data = await getPreviewToken(props.id)
    previewUrl.value = data.preview_url
  } catch (e) {
    error.value = e.message
  }
}

async function onGenerate() {
  generating.value = true
  pdfInfo.value = ''
  try {
    const data = await generate(props.id)
    pdfInfo.value = data.file_size ?? 'done'
    await loadEdition()
  } catch (e) {
    error.value = e.message
  } finally {
    generating.value = false
  }
}

async function onSend() {
  if (!confirm('Send this edition to the printer? This cannot be undone.')) return
  sending.value = true
  try {
    await send(props.id)
    await loadEdition()
  } catch (e) {
    error.value = e.message
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.top-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.back-link {
  color: #2c5282;
  text-decoration: none;
  font-size: 0.875rem;
}

.back-link:hover {
  text-decoration: underline;
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

.builder-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.editor-panel {
  min-width: 0;
}

.preview-panel {
  min-width: 0;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.preview-iframe {
  width: 100%;
  height: 600px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}

.preview-placeholder {
  color: #a0aec0;
  font-size: 0.875rem;
  padding: 2rem;
  text-align: center;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.card {
  background: #fff;
  border-radius: 8px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.action-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.pdf-info {
  font-size: 0.85rem;
  color: #276749;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  font-size: 0.875rem;
  cursor: pointer;
  font-weight: 500;
  text-decoration: none;
}

.btn-primary {
  background: #2c5282;
  color: #fff;
}

.btn-primary:hover { background: #2b4c7e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-outline {
  background: #fff;
  color: #2c5282;
  border: 1px solid #2c5282;
}

.btn-outline:hover { background: #ebf4ff; }

.btn-send {
  background: #276749;
  color: #fff;
}

.btn-send:hover { background: #22543d; }
.btn-send:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-small {
  padding: 0.35rem 0.75rem;
  font-size: 0.8rem;
  background: #ebf4ff;
  color: #2c5282;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-small:hover { background: #bee3f8; }

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.modal {
  width: 500px;
  max-height: 80vh;
  overflow-y: auto;
}

.modal h3 {
  margin-bottom: 0.75rem;
}

.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.tabs button {
  padding: 0.4rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  background: #f7fafc;
  cursor: pointer;
  font-size: 0.85rem;
}

.tabs button.active {
  background: #2c5282;
  color: #fff;
  border-color: #2c5282;
}

.modal form label,
.modal > div > label {
  display: block;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.modal input,
.modal textarea {
  display: block;
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #cbd5e0;
  border-radius: 4px;
  font-size: 0.875rem;
  margin-top: 0.15rem;
  margin-bottom: 0.5rem;
  font-family: inherit;
}

.entity-results {
  list-style: none;
  max-height: 200px;
  overflow-y: auto;
  margin: 0.5rem 0;
}

.entity-results li {
  padding: 0.5rem;
  cursor: pointer;
  border-bottom: 1px solid #edf2f7;
  font-size: 0.875rem;
}

.entity-results li:hover {
  background: #f7fafc;
}

.entity-type-badge {
  display: inline-block;
  padding: 0.1rem 0.35rem;
  border-radius: 3px;
  font-size: 0.7rem;
  font-weight: 600;
  background: #e2e8f0;
  color: #4a5568;
  margin-right: 0.35rem;
  text-transform: uppercase;
}

.searching {
  color: #a0aec0;
  font-size: 0.85rem;
}

.btn-cancel {
  background: #e2e8f0;
  color: #4a5568;
  margin-top: 0.75rem;
}

.btn-cancel:hover { background: #cbd5e0; }

.error {
  color: #c53030;
  font-size: 0.875rem;
  margin-top: 0.5rem;
}
</style>
