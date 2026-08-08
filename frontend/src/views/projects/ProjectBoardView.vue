<template>
  <div class="d-flex gap-3 p-4 overflow-auto" style="min-height: calc(100vh - 56px);">
    <KanbanColumn
      v-for="status in STATUSES"
      :key="status"
      :status="status"
      :projects="byStatus[status]"
    />
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import KanbanColumn from '@/components/KanbanColumn.vue'

const STATUSES = ['Planning', 'In Progress', 'On Hold', 'Completed']
const store = useProjectStore()

const byStatus = computed(() => {
  const map = {}
  STATUSES.forEach((s) => { map[s] = [] })
  store.projects.forEach((p) => { if (map[p.status]) map[p.status].push(p) })
  return map
})

onMounted(() => store.fetchProjects())
</script>
