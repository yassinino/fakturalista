<template>
  <nav v-if="meta.lastPage > 1" class="d-flex align-items-center justify-content-end gap-2 mt-3" aria-label="Pagination">
    <button class="btn btn-sm btn-outline-primary"
            :disabled="meta.currentPage === 1"
            @click="$emit('update:page', meta.currentPage - 1)">
      ‹
    </button>

    <ul class="pagination mb-0">
      <li v-for="n in pageWindow" :key="n" class="page-item" :class="{ active: n === meta.currentPage }">
        <button class="page-link" @click="$emit('update:page', n)">{{ n }}</button>
      </li>
    </ul>

    <button class="btn btn-sm btn-outline-primary"
            :disabled="meta.currentPage === meta.lastPage"
            @click="$emit('update:page', meta.currentPage + 1)">
      ›
    </button>

    <div class="d-flex align-items-center ms-2" v-if="showPerPage">
      <small class="me-2">Por página</small>
      <select class="form-select form-select-sm" style="width:auto"
              :value="meta.perPage"
              @change="$emit('update:perPage', +$event.target.value)">
        <option :value="10">10</option>
        <option :value="25">25</option>
        <option :value="50">50</option>
      </select>
    </div>

    <small class="text-muted ms-2" v-if="meta.total">
      {{ from }}–{{ to }} / {{ meta.total }}
    </small>
  </nav>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: { type: Array, default: () => [] }, // pas utilisé ici, mais dispo si besoin
  meta: {
    type: Object,
    required: true, // { currentPage, lastPage, perPage, total }
  },
  maxLinks: { type: Number, default: 5 },
  showPerPage: { type: Boolean, default: true }
});

const pageWindow = computed(() => {
  const half = Math.floor(props.maxLinks / 2);
  let start = Math.max(1, props.meta.currentPage - half);
  let end = Math.min(props.meta.lastPage, start + props.maxLinks - 1);
  start = Math.max(1, end - props.maxLinks + 1);
  return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

const from = computed(() => (props.meta.total === 0 ? 0 : (props.meta.currentPage - 1) * props.meta.perPage + 1));
const to = computed(() => Math.min(props.meta.currentPage * props.meta.perPage, props.meta.total || 0));
</script>