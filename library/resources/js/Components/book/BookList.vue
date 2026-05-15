<script setup>
import { router } from '@inertiajs/vue3';
import DownloadButton from '@/Components/shared/DownloadButton.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import SearchBar from '@/Components/shared/SearchBar.vue';
import SuccessButton from "@/Components/SuccessButton.vue";
import WarningButton from "@/Components/WarningButton.vue";
import DeleteButton from "@/Components/shared/DeleteButton.vue";

/**
 * Renders the books table.
 *
 * The list is presentational — it doesn't talk to the API itself.
 * Create / edit / delete bubble up to the parent page (Pages/Books/Index.vue),
 * which owns the modals and the axios calls.
 */
defineProps({
  books: { type: Object, required: true },
  filters: { type: Object, default: () => ({ q: '' }) },
  can: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['new', 'edit', 'delete']);

function search(term) {
  router.get(
    route('media.index', { type: 'book' }),
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
      >+ New book</SuccessButton>
    </div>

    <SearchBar
      :model-value="filters.q ?? ''"
      placeholder="Search by title or author…"
      @search="search"
    />

    <div class="bg-white shadow rounded overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Title</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Author(s)</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Pages</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Year</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
          <tr v-for="book in books.data" :key="book.uuid">
            <td class="px-4 py-2 text-sm text-gray-800 font-medium">{{ book.media?.title }}</td>
            <td class="px-4 py-2 text-sm text-gray-600">
              <span v-if="book.media?.authors?.length">
                {{ book.media.authors.map(a => a.name).join(', ') }}
              </span>
              <span v-else class="italic text-gray-400">—</span>
            </td>
              <td class="px-4 py-2 text-sm text-gray-600">{{ book.pages }}</td>
              <td class="px-4 py-2 text-sm text-gray-600">{{ book.media?.publication_year }}</td>
            <td class="px-4 py-2 text-right space-x-1">
              <DownloadButton
                v-if="can.download"
                :href="route('media.download', { type: 'book', id: book.uuid })"
              />
              <WarningButton
                v-if="can.update"
                @click="emit('edit', book)"
              >Edit</WarningButton>
              <DeleteButton
                v-if="can.delete"
                @confirm="emit('delete', book)"
              />
            </td>
          </tr>
          <tr v-if="!books.data.length">
            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
              No books found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :meta="books" :only="['books']" />
  </div>
</template>
