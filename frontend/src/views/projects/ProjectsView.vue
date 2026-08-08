<template>
  <ProjectBoardView v-if="store.viewMode === 'board'" />
  <ProjectTableView v-else />
</template>

<script setup>
import { useProjectStore } from '@/stores/projectStore'
import ProjectBoardView from './ProjectBoardView.vue'
import ProjectTableView from './ProjectTableView.vue'

const store = useProjectStore()
</script>
