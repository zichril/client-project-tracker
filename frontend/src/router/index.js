import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/auth/LoginView.vue'
import RegisterView from '@/views/auth/RegisterView.vue'
import ProjectsView from '@/views/projects/ProjectsView.vue'

const routes = [
  { path: '/login', component: LoginView },
  { path: '/register', component: RegisterView },
  { path: '/', component: ProjectsView, meta: { requiresAuth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to) => {
  const token = localStorage.getItem('auth_token')
  if (to.meta.requiresAuth && !token) return '/login'
  if ((to.path === '/login' || to.path === '/register') && token) return '/'
})

export default router
