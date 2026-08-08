<template>
  <div class="d-flex flex-wrap gap-2 p-3 bg-white border-bottom">
    <input
      v-model="store.filters.search"
      @input="onFilterChange"
      type="text"
      placeholder="Search projects..."
      class="form-control form-control-sm"
      style="max-width: 220px;"
    />
    <select v-model="store.filters.status" @change="onFilterChange" class="form-select form-select-sm" style="max-width: 160px;">
      <option value="">All Statuses</option>
      <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
    </select>
    <select v-model="store.filters.priority" @change="onFilterChange" class="form-select form-select-sm" style="max-width: 160px;">
      <option value="">All Priorities</option>
      <option v-for="p in PRIORITIES" :key="p" :value="p">{{ p }}</option>
    </select>
    <button v-if="hasActiveFilters" @click="clearFilters" class="btn btn-link btn-sm text-muted p-0">
      Clear filters
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useProjectStore } from '@/stores/projectStore'

const STATUSES = ['Planning', 'In Progress', 'On Hold', 'Completed']
const PRIORITIES = ['Low', 'Medium', 'High']

const store = useProjectStore()

const hasActiveFilters = computed(() =>
  store.filters.search || store.filters.status || store.filters.priority
)

function onFilterChange() { store.fetchProjects() }

function clearFilters() {
  store.filters.search = ''
  store.filters.status = ''
  store.filters.priority = ''
  store.fetchProjects()
}
</script>
