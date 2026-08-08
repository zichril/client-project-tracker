<template>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th v-for="col in COLUMNS" :key="col.key"
            @click="col.sortable ? toggleSort(col.key) : null"
            :style="col.sortable ? 'cursor: pointer; user-select: none;' : ''"
            class="small text-uppercase text-muted fw-semibold">
            {{ col.label }}
            <span v-if="col.sortable && store.filters.sort_by === col.key">
              {{ store.filters.sort_dir === 'asc' ? '↑' : '↓' }}
            </span>
          </th>
          <th class="small text-uppercase text-muted fw-semibold text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="store.loading">
          <td :colspan="COLUMNS.length + 1" class="text-center text-muted py-5">Loading...</td>
        </tr>
        <tr v-else-if="store.projects.length === 0">
          <td :colspan="COLUMNS.length + 1" class="text-center text-muted py-5">No projects found</td>
        </tr>
        <tr v-else v-for="project in store.projects" :key="project.id">
          <td class="fw-medium">{{ project.clientName }}</td>
          <td>{{ project.projectName }}</td>
          <td><span :class="statusBadge(project.status)" class="badge">{{ project.status }}</span></td>
          <td><span :class="priorityBadge(project.priority)" class="badge">{{ project.priority }}</span></td>
          <td class="text-muted small">{{ project.startDate ?? '—' }}</td>
          <td class="text-muted small">{{ project.dueDate ?? '—' }}</td>
          <td class="text-end">
            <button @click="openEditModal(project)" class="btn btn-link btn-sm p-0 me-2">Edit</button>
            <button @click="handleDelete(project)" class="btn btn-link btn-sm p-0 text-danger">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'

const COLUMNS = [
  { key: 'client_name',  label: 'Client',     sortable: true },
  { key: 'project_name', label: 'Project',    sortable: true },
  { key: 'status',       label: 'Status',     sortable: true },
  { key: 'priority',     label: 'Priority',   sortable: true },
  { key: 'start_date',   label: 'Start Date', sortable: true },
  { key: 'due_date',     label: 'Due Date',   sortable: true },
]

const store = useProjectStore()
const toast = useToast()
const openEditModal = inject('openEditModal')

function toggleSort(key) {
  if (store.filters.sort_by === key) {
    store.filters.sort_dir = store.filters.sort_dir === 'asc' ? 'desc' : 'asc'
  } else {
    store.filters.sort_by = key
    store.filters.sort_dir = 'asc'
  }
  store.fetchProjects()
}

function statusBadge(s) {
  return {
    'Planning':    'bg-secondary',
    'In Progress': 'bg-primary',
    'On Hold':     'bg-warning text-dark',
    'Completed':   'bg-success',
  }[s] ?? 'bg-secondary'
}

function priorityBadge(p) {
  return {
    'High':   'bg-danger',
    'Medium': 'bg-warning text-dark',
    'Low':    'bg-success',
  }[p] ?? 'bg-secondary'
}

async function handleDelete(project) {
  if (!confirm(`Delete "${project.projectName}"?`)) return
  try {
    await store.deleteProject(project.id)
    toast.success('Project deleted')
  } catch {
    toast.error('Failed to delete project')
  }
}
</script>
