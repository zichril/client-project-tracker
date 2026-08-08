<template>
  <div>
    <ProjectFilters />
    <ProjectTable />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useProjectStore } from '@/stores/projectStore'
import ProjectFilters from '@/components/ProjectFilters.vue'
import ProjectTable from '@/components/ProjectTable.vue'

const store = useProjectStore()
onMounted(() => store.fetchProjects())
</script>
