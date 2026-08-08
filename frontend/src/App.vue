<template>
  <div>
    <nav v-if="authStore.isAuthenticated" class="navbar navbar-expand-lg bg-white border-bottom shadow-sm sticky-top">
      <div class="container-fluid px-4">
        <span class="navbar-brand fw-semibold">Client Project Tracker</span>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <div class="btn-group btn-group-sm" role="group">
            <button @click="setView('board')"
              :class="['btn', store.viewMode === 'board' ? 'btn-primary' : 'btn-outline-secondary']">
              Board
            </button>
            <button @click="setView('table')"
              :class="['btn', store.viewMode === 'table' ? 'btn-primary' : 'btn-outline-secondary']">
              Table
            </button>
          </div>
          <button @click="openCreateModal" class="btn btn-primary btn-sm">+ New Project</button>
          <span class="text-muted small">{{ authStore.user?.name }}</span>
          <button @click="handleLogout" class="btn btn-link btn-sm text-muted p-0">Logout</button>
        </div>
      </div>
    </nav>

    <router-view />

    <ProjectFormModal
      v-if="showModal"
      :project="editingProject"
      @close="closeModal"
      @saved="onSaved"
    />
  </div>
</template>

<script setup>
import { ref, provide } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'
import ProjectFormModal from '@/components/ProjectFormModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const store = useProjectStore()
const toast = useToast()

const showModal = ref(false)
const editingProject = ref(null)

function openCreateModal() { editingProject.value = null; showModal.value = true }
function openEditModal(project) { editingProject.value = project; showModal.value = true }
function closeModal() { showModal.value = false; editingProject.value = null }
function onSaved() { closeModal(); store.fetchProjects() }

provide('openEditModal', openEditModal)

function setView(mode) { store.setViewMode(mode) }

async function handleLogout() {
  await authStore.logout()
  toast.success('Logged out')
  router.push('/login')
}
</script>
