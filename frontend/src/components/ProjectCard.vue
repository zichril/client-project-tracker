<template>
  <div :data-id="project.id" class="card mb-2 shadow-sm" style="cursor: grab;">
    <div class="card-body p-3">
      <div class="d-flex justify-content-between align-items-start mb-1">
        <p class="fw-semibold small mb-0 me-2">{{ project.projectName }}</p>
        <span :class="priorityBadge" class="badge text-nowrap">{{ project.priority }}</span>
      </div>
      <p class="text-muted small mb-1">{{ project.clientName }}</p>
      <p v-if="project.dueDate" class="text-muted mb-2" style="font-size: 0.72rem;">Due {{ project.dueDate }}</p>
      <div class="d-flex gap-2">
        <button @click.stop="openEditModal(project)" class="btn btn-link btn-sm p-0 text-primary">Edit</button>
        <button @click.stop="handleDelete" class="btn btn-link btn-sm p-0 text-danger">Delete</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'

const props = defineProps({ project: { type: Object, required: true } })

const store = useProjectStore()
const toast = useToast()
const openEditModal = inject('openEditModal')

const priorityBadge = computed(() => ({
  'bg-danger':              props.project.priority === 'High',
  'bg-warning text-dark':   props.project.priority === 'Medium',
  'bg-success':             props.project.priority === 'Low',
}))

async function handleDelete() {
  if (!confirm(`Delete "${props.project.projectName}"?`)) return
  try {
    await store.deleteProject(props.project.id)
    toast.success('Project deleted')
  } catch {
    toast.error('Failed to delete project')
  }
}
</script>
