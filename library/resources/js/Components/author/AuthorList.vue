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
      <h2 class="text-xl font-semibold text-gray-800">Authors</h2>
      <SuccessButton
        v-if="can.create"
        @click="emit('new')"
      >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
          </svg>
          New
      </SuccessButton>
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
              >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                      <path d="m2.695 14.762-1.262 3.155a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.886L17.5 5.501a2.121 2.121 0 0 0-3-3L3.58 13.419a4 4 0 0 0-.885 1.343Z" />
                  </svg>
              </WarningButton>
              <DeleteButton
                v-if="can.delete"
                @confirm="emit('delete', author)"
              >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                      <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                  </svg>
              </DeleteButton>
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
