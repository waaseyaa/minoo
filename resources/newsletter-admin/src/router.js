import { createRouter, createWebHistory } from 'vue-router'
import EditionList from './pages/EditionList.vue'
import EditionDetail from './pages/EditionDetail.vue'

const routes = [
  { path: '/', name: 'editions', component: EditionList },
  { path: '/:id', name: 'edition', component: EditionDetail, props: true },
]

const router = createRouter({
  history: createWebHistory('/admin/newsletter/'),
  routes,
})

export default router
