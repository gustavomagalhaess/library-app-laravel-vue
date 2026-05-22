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
        dusk="author-new"
        @click="emit('new')"
      >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
              <path d="M8.75 3.75a.75.75 0 0 0-1.5 0v3.5h-3.5a.75.75 0 0 0 0 1.5h3.5v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5v-3.5Z" />
          </svg>
          New
      </SuccessButton>
    </div>

    <SearchBar
      :model-value="filters.q ?? ''"
      placeholder="Search authors by name…"
      @search="search"
    />

    <div class="bg-white opacity-95 shadow rounded overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="text-xs font-semibold uppercase bg-gray-50 dark:bg-gray-500 text-gray-600 dark:text-gray-300">
          <tr>
            <th class="px-4 py-2 text-left">Name</th>
            <th class="px-4 py-2 text-left">Books</th>
            <th class="px-4 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-300 divide-y divide-gray-100 dark:divide-gray-500 text-gray-600 dark:text-gray-800">
          <tr v-for="author in authors.data" :key="author.id">
            <td class="px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-950">{{ author.name }}</td>
            <td class="px-4 py-2 text-sm">{{ author.books_count ?? 0 }}</td>
            <td class="px-4 py-2 text-right space-x-1">
              <WarningButton
                v-if="can.update"
                :dusk="'author-edit-' + author.id"
                @click="emit('edit', author)"
              >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                      <path fill-rule="evenodd" d="M11.013 2.513a1.75 1.75 0 0 1 2.475 2.474L6.226 12.25a2.751 2.751 0 0 1-.892.596l-2.047.848a.75.75 0 0 1-.98-.98l.848-2.047a2.75 2.75 0 0 1 .596-.892l7.262-7.261Z" clip-rule="evenodd" />
                  </svg>
              </WarningButton>
              <DeleteButton
                v-if="can.delete"
                :dusk="'author-delete-' + author.id"
                @confirm="emit('delete', author)"
              >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                      <path fill-rule="evenodd" d="M5 3.25V4H2.75a.75.75 0 0 0 0 1.5h.3l.815 8.15A1.5 1.5 0 0 0 5.357 15h5.285a1.5 1.5 0 0 0 1.493-1.35l.815-8.15h.3a.75.75 0 0 0 0-1.5H11v-.75A2.25 2.25 0 0 0 8.75 1h-1.5A2.25 2.25 0 0 0 5 3.25Zm2.25-.75a.75.75 0 0 0-.75.75V4h3v-.75a.75.75 0 0 0-.75-.75h-1.5ZM6.05 6a.75.75 0 0 1 .787.713l.275 5.5a.75.75 0 0 1-1.498.075l-.275-5.5A.75.75 0 0 1 6.05 6Zm3.9 0a.75.75 0 0 1 .712.787l-.275 5.5a.75.75 0 0 1-1.498-.075l.275-5.5a.75.75 0 0 1 .786-.711Z" clip-rule="evenodd" />
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
