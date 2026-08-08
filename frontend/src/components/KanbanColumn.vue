<template>
  <div class="rounded-3 p-3 flex-shrink-0" style="background: #f1f3f5; min-width: 260px; min-height: 200px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="text-uppercase text-muted fw-semibold mb-0 small">{{ status }}</h6>
      <span class="badge bg-secondary rounded-pill">{{ items.length }}</span>
    </div>

    <VueDraggable
      v-model="items"
      group="projects"
      item-key="id"
      @add="onAdd"
    >
      <template #item="{ element }">
        <ProjectCard :project="element" />
      </template>
    </VueDraggable>

    <p v-if="items.length === 0" class="text-center text-muted small py-4 mb-0">No projects</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { useProjectStore } from '@/stores/projectStore'
import { useToast } from 'vue-toastification'
import ProjectCard from './ProjectCard.vue'

const props = defineProps({
  status: { type: String, required: true },
  projects: { type: Array, required: true },
})

const store = useProjectStore()
const toast = useToast()

const items = computed({
  get: () => props.projects,
  set: () => {},
})

async function onAdd(event) {
  const id = Number(event.item.dataset.id)
  try {
    await store.updateProjectStatus(id, props.status)
  } catch {
    toast.error('Failed to update status')
    await store.fetchProjects()
  }
}
</script>
