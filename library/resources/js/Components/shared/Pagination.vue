<script setup>
/**
 * Generic pagination control. Works with Laravel paginators + Inertia.
 *
 * Props:
 *   meta:  the paginator object (current_page, last_page, links, ...).
 *   only:  Inertia partial reload `only` keys, so the Index page can refresh
 *          just the list section instead of the whole page.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  meta: { type: Object, required: true },
  only: { type: Array, default: () => [] },
});

const links = computed(() => props.meta.links ?? []);

function go(url) {
  if (!url) return;
  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
    only: props.only.length ? props.only : undefined,
  });
}
</script>

<template>
  <nav v-if="meta.last_page > 1" class="flex items-center justify-between mt-4">
    <p class="text-sm text-gray-600">
      Showing <strong>{{ meta.from ?? 0 }}</strong>–<strong>{{ meta.to ?? 0 }}</strong>
      of <strong>{{ meta.total }}</strong>
    </p>
    <div class="flex gap-1">
      <button
        v-for="(link, idx) in links"
        :key="idx"
        :disabled="!link.url || link.active"
        @click="go(link.url)"
        v-html="link.label"
        class="px-3 py-1 text-sm rounded border"
        :class="[
          link.active
            ? 'bg-indigo-600 text-white border-indigo-600'
            : 'bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed',
        ]"
      />
    </div>
  </nav>
</template>
