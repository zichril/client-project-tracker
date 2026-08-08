import { defineStore } from 'pinia'
import { ref } from 'vue'
import { projectsApi } from '@/api/projects'

export const useProjectStore = defineStore('projects', () => {
  const projects = ref([])
  const loading = ref(false)
  const viewMode = ref(localStorage.getItem('view_mode') || 'board')

  const filters = ref({
    search: '',
    status: '',
    priority: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
  })

  function setViewMode(mode) {
    viewMode.value = mode
    localStorage.setItem('view_mode', mode)
  }

  async function fetchProjects() {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters.value).filter(([, v]) => v !== '')
      )
      const { data } = await projectsApi.index(params)
      projects.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function createProject(payload) {
    const { data } = await projectsApi.store(payload)
    projects.value.unshift(data.data)
    return data.data
  }

  async function updateProject(id, payload) {
    const { data } = await projectsApi.update(id, payload)
    const idx = projects.value.findIndex((p) => p.id === id)
    if (idx !== -1) projects.value[idx] = data.data
    return data.data
  }

  async function deleteProject(id) {
    await projectsApi.destroy(id)
    projects.value = projects.value.filter((p) => p.id !== id)
  }

  async function updateProjectStatus(id, status) {
    const project = projects.value.find((p) => p.id === id)
    if (!project) return
    const prev = project.status
    project.status = status
    try {
      await projectsApi.update(id, {
        client_name: project.clientName,
        project_name: project.projectName,
        description: project.description,
        status,
        priority: project.priority,
        start_date: project.startDate,
        due_date: project.dueDate,
      })
    } catch {
      project.status = prev
      throw new Error('Status update failed')
    }
  }

  return {
    projects, loading, viewMode, filters,
    setViewMode, fetchProjects, createProject, updateProject, deleteProject, updateProjectStatus,
  }
})
