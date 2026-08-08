import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token'))
  const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'))
  const isAuthenticated = computed(() => !!token.value)

  async function login(credentials) {
    const { data } = await authApi.login(credentials)
    _persist(data.token, data.user)
  }

  async function register(credentials) {
    const { data } = await authApi.register(credentials)
    _persist(data.token, data.user)
  }

  async function logout() {
    try { await authApi.logout() } catch {}
    _clear()
  }

  function _persist(t, u) {
    token.value = t
    user.value = u
    localStorage.setItem('auth_token', t)
    localStorage.setItem('auth_user', JSON.stringify(u))
  }

  function _clear() {
    token.value = null
    user.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  return { token, user, isAuthenticated, login, register, logout }
})
