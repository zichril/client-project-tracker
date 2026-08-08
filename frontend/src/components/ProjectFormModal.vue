<template>
  <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ project ? 'Edit Project' : 'New Project' }}</h5>
          <button type="button" class="btn-close" @click="$emit('close')"></button>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Client Name <span class="text-danger">*</span></label>
                <input v-model="form.client_name" type="text"
                  class="form-control" :class="{ 'is-invalid': errors.client_name }" />
                <div v-if="errors.client_name" class="invalid-feedback">{{ errors.client_name[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Project Name <span class="text-danger">*</span></label>
                <input v-model="form.project_name" type="text"
                  class="form-control" :class="{ 'is-invalid': errors.project_name }" />
                <div v-if="errors.project_name" class="invalid-feedback">{{ errors.project_name[0] }}</div>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea v-model="form.description" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select v-model="form.status" class="form-select" :class="{ 'is-invalid': errors.status }">
                  <option value="">Select status</option>
                  <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
                </select>
                <div v-if="errors.status" class="invalid-feedback">{{ errors.status[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Priority <span class="text-danger">*</span></label>
                <select v-model="form.priority" class="form-select" :class="{ 'is-invalid': errors.priority }">
                  <option value="">Select priority</option>
                  <option v-for="p in PRIORITIES" :key="p" :value="p">{{ p }}</option>
                </select>
                <div v-if="errors.priority" class="invalid-feedback">{{ errors.priority[0] }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Start Date</label>
                <input v-model="form.start_date" type="date" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Due Date</label>
                <input v-model="form.due_date" type="date"
                  class="form-control" :class="{ 'is-invalid': errors.due_date }" />
                <div v-if="errors.due_date" class="invalid-feedback">{{ errors.due_date[0] }}</div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="loading">
              {{ loading ? 'Saving...' : 'Save Project' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'

const props = defineProps({ project: { type: Object, default: null } })
const emit = defineEmits(['close', 'saved'])

const STATUSES = ['Planning', 'In Progress', 'On Hold', 'Completed']
const PRIORITIES = ['Low', 'Medium', 'High']

const store = useProjectStore()
const toast = useToast()
const errors = ref({})
const loading = ref(false)

const form = reactive({
  client_name: '',
  project_name: '',
  description: '',
  status: '',
  priority: '',
  start_date: '',
  due_date: '',
})

watch(() => props.project, (p) => {
  if (p) {
    form.client_name  = p.clientName  ?? ''
    form.project_name = p.projectName ?? ''
    form.description  = p.description ?? ''
    form.status       = p.status      ?? ''
    form.priority     = p.priority    ?? ''
    form.start_date   = p.startDate   ?? ''
    form.due_date     = p.dueDate     ?? ''
  }
}, { immediate: true })

async function handleSubmit() {
  errors.value = {}
  loading.value = true
  try {
    if (props.project) {
      await store.updateProject(props.project.id, form)
      toast.success('Project updated')
    } else {
      await store.createProject(form)
      toast.success('Project created')
    }
    emit('saved')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors
    } else {
      toast.error('Something went wrong')
    }
  } finally {
    loading.value = false
  }
}
</script>
