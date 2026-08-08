<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 420px;">
      <div class="card-body p-4">
        <h1 class="h4 fw-bold mb-4">Create Account</h1>

        <form @submit.prevent="handleSubmit">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input v-model="form.name" type="text"
              class="form-control" :class="{ 'is-invalid': errors.name }" />
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input v-model="form.email" type="email"
              class="form-control" :class="{ 'is-invalid': errors.email }" />
            <div v-if="errors.email" class="invalid-feedback">{{ errors.email[0] }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input v-model="form.password" type="password"
              class="form-control" :class="{ 'is-invalid': errors.password }" />
            <div v-if="errors.password" class="invalid-feedback">{{ errors.password[0] }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input v-model="form.password_confirmation" type="password" class="form-control" />
          </div>

          <button type="submit" class="btn btn-primary w-100" :disabled="loading">
            {{ loading ? 'Creating account...' : 'Create Account' }}
          </button>
        </form>

        <p class="text-center text-muted small mt-3">
          Have an account?
          <router-link to="/login">Sign In</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref({})
const loading = ref(false)

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  try {
    await authStore.register(form)
    router.push('/')
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors
  } finally {
    loading.value = false
  }
}
</script>
