<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookList from '@/Components/book/BookList.vue';
import BookForm from '@/Components/book/BookForm.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/shared/ConfirmModal.vue';

/**
 * Books index page.
 *
 * Inertia still renders the list, but every mutation now goes through the
 * /api/* JSON endpoints from this page:
 *
 *   - "+ New book"   → opens the BookForm in a modal → POST /api/book
 *   - "Edit"         → opens the BookForm in a modal → POST /api/book/{id}
 *   - "Delete"       → opens a ConfirmModal         → DELETE /api/book/{id}
 *
 * After a successful call we ask Inertia to reload only the `books` partial
 * so the table refreshes without a full navigation.
 */
const props = defineProps({
  books: Object,
  filters: Object,
  can: Object,
});

// --- Form modal state ------------------------------------------------------
const formMode = ref(null);              // null | 'create' | 'edit'
const formTarget = ref(null);            // book being edited (or null on create)
const formErrors = ref({});              // { field: 'error message' }
const formProcessing = ref(false);
const formOpen = computed(() => formMode.value !== null);

function openCreate() {
  formMode.value = 'create';
  formTarget.value = null;
  formErrors.value = {};
}

function openEdit(book) {
  formMode.value = 'edit';
  formTarget.value = book;
  formErrors.value = {};
}

function closeForm() {
  if (formProcessing.value) return;
  formMode.value = null;
  formTarget.value = null;
  formErrors.value = {};
}

async function submitForm(payload) {
  formProcessing.value = true;
  formErrors.value = {};

  const url = formMode.value === 'edit'
    ? route('api.media.update', { type: 'book', id: formTarget.value.uuid })
    : route('api.media.store',  { type: 'book' });

  try {
    await axios.post(url, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    formMode.value = null;
    formTarget.value = null;
    refreshList();
  } catch (e) {
    if (e?.response?.status === 422) {
      formErrors.value = flattenLaravelErrors(e.response.data?.errors ?? {});
    } else {
      // Surface a single banner-style error in the form area.
      formErrors.value = { _form: e?.response?.data?.message ?? 'Something went wrong.' };
    }
  } finally {
    formProcessing.value = false;
  }
}

// --- Delete confirmation modal state --------------------------------------
const deleteTarget = ref(null);
const deleteBusy = ref(false);

function askDelete(book) {
  deleteTarget.value = book;
}

function closeDelete() {
  if (deleteBusy.value) return;
  deleteTarget.value = null;
}

async function confirmDelete() {
  if (!deleteTarget.value) return;
  deleteBusy.value = true;
  try {
    await axios.delete(route('api.media.destroy', { type: 'book', id: deleteTarget.value.uuid }));
    deleteTarget.value = null;
    refreshList();
  } catch (e) {
    // For now we just close the modal — a future improvement is to surface
    // the server message inline. The error is still logged by the server.
    deleteTarget.value = null;
  } finally {
    deleteBusy.value = false;
  }
}

// --- Helpers ---------------------------------------------------------------

/** Ask Inertia to refresh only the `books` prop in-place. */
function refreshList() {
  router.reload({ only: ['books'], preserveScroll: true, preserveState: true });
}

/**
 * Laravel returns validation errors as { field: ['message', …] }. Most of
 * our inputs only show one message, so flatten the arrays to their first
 * element — matches the shape Inertia's useForm gave us before.
 */
function flattenLaravelErrors(errors) {
  return Object.fromEntries(
    Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
  );
}
</script>

<template>
  <Head title="Books" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Books</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <BookList
          :books="books"
          :filters="filters"
          :can="can"
          @new="openCreate"
          @edit="openEdit"
          @delete="askDelete"
        />
      </div>
    </div>

    <!-- Create / edit modal -->
    <Modal :show="formOpen" max-width="2xl" @close="closeForm">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          {{ formMode === 'edit' ? 'Edit book' : 'Add a new book' }}
        </h3>
        <p v-if="formErrors._form" class="mb-3 text-sm text-red-600">{{ formErrors._form }}</p>
        <BookForm
          v-if="formOpen"
          :key="formTarget?.uuid ?? 'new'"
          :mode="formMode"
          :book="formTarget"
          :errors="formErrors"
          :processing="formProcessing"
          @submit="submitForm"
          @cancel="closeForm"
        />
      </div>
    </Modal>

    <!-- Delete confirmation modal -->
    <ConfirmModal
      :show="!!deleteTarget"
      title="Delete book"
      :message="`Delete the book: ${deleteTarget?.media?.title ?? ''}?`"
      confirm-label="Delete"
      :busy="deleteBusy"
      @confirm="confirmDelete"
      @close="closeDelete"
    />
  </AuthenticatedLayout>
</template>
