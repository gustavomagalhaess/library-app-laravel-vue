<script setup>
import { router } from '@inertiajs/vue3';
import Pagination from '@/Components/shared/Pagination.vue';
import SearchBar from '@/Components/shared/SearchBar.vue';
import SuccessButton from "@/Components/SuccessButton.vue";
import WarningButton from "@/Components/WarningButton.vue";
import DeleteButton from "@/Components/shared/DeleteButton.vue";

/**
 * Renders the authors table. Like BookList, this component is purely
 * presentational — create / edit / delete bubble up to the parent page,
 * which owns the modals and the axios calls.
 */
defineProps({
  authors: { type: Object, required: true },
  filters: { type: Object, default: () => ({ q: '' }) },
  can: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['new', 'edit', 'delete']);

function search(term) {
  router.get(
    route('authors.index'),
    term ? { q: term } : {},
    { preserveScroll: true, preserveState: true, replace: true },
  );
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
      <h2 class="text-xl font-semibold text-gray-800"></h2>
      <SuccessButton
        v-if="can.create"
        @click="emit('new')"
      >+ New author</SuccessButton>
    </div>

    <SearchBar
      :model-value="filters.q ?? ''"
      placeholder="Search authors by name…"
      @search="search"
    />

    <div class="bg-white shadow rounded overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Books</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
          <tr v-for="author in authors.data" :key="author.id">
            <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ author.name }}</td>
            <td class="px-4 py-2 text-sm text-gray-600">{{ author.books_count ?? 0 }}</td>
            <td class="px-4 py-2 text-right space-x-1">
              <WarningButton
                v-if="can.update"
                @click="emit('edit', author)"
              >Edit</WarningButton>
              <DeleteButton
                v-if="can.delete"
                @confirm="emit('delete', author)"
              />
            </td>
          </tr>
          <tr v-if="!authors.data.length">
            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
              No authors found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :meta="authors" :only="['authors']" />
  </div>
</template>
