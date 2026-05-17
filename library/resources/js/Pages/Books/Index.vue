<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookList from '@/Components/book/BookList.vue';
import BookForm from '@/Components/book/BookForm.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/shared/ConfirmModal.vue';
import { useToasts } from '@/composables/useToasts.js';
import { trackJob } from '@/composables/useJobTracker.js';

/**
 * Books index page.
 *
 * Mutations now go through the queued-job pipeline:
 *
 *   1. Submit closes the form *immediately* — perceived latency is what
 *      kills good UX, so we don't keep the modal open while the worker runs.
 *   2. A persistent "Saving…" toast appears with a spinner.
 *   3. trackJob() polls /api/jobs/{id} until completion.
 *   4. The toast resolves into success/error, and the table partial is
 *      reloaded so the new/updated/deleted row reflects the worker's state.
 *
 * Downloads are also queued — the row's DownloadButton opens its own toast
 * and gets a signed URL action when the job resolves.
 */
defineProps({
  books: Object,
  filters: Object,
  can: Object,
});

const toast = useToasts();

// --- Form modal state ------------------------------------------------------
const formMode = ref(null);
const formTarget = ref(null);
const formErrors = ref({});
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
  const isEdit = formMode.value === 'edit';
  const recordTitle = payload.get('title') || formTarget.value?.media?.title || 'book';

  const url = isEdit
    ? route('api.media.update', { type: 'book', id: formTarget.value.uuid })
    : route('api.media.store',  { type: 'book' });

  let response;
  try {
    response = await axios.post(url, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  } catch (e) {
    if (e?.response?.status === 422) {
      // Validation lives synchronously in the FormRequest — no job dispatched.
      // Show errors inline; keep the form open for the user to fix them.
      formErrors.value = flattenLaravelErrors(e.response.data?.errors ?? {});
    } else {
      toast.error(e?.response?.data?.message ?? 'Failed to queue book save. Please try again.');
    }
    formProcessing.value = false;
    return;
  }

  // The validation has passed and the job is queued. Close the form
  // immediately so the user can keep working; the toast carries the
  // outcome.
  formMode.value = null;
  formTarget.value = null;
  formProcessing.value = false;

  const job = response.data?.job;
  if (!job) {
    // Defensive: if the API is misbehaving and didn't return a job, fall
    // back to a list reload so the UI doesn't pretend success.
    refreshList();
    return;
  }

  awaitJob(job, {
    pending: isEdit ? `Updating "${recordTitle}"…` : `Saving "${recordTitle}"…`,
    success: isEdit ? `"${recordTitle}" updated.` : `"${recordTitle}" added.`,
    fallbackError: isEdit ? 'Failed to update book.' : 'Failed to save book.',
  }).finally(refreshList);
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
  const title = deleteTarget.value.media?.title ?? 'Book';

  let response;
  try {
    response = await axios.delete(
      route('api.media.destroy', { type: 'book', id: deleteTarget.value.uuid }),
    );
  } catch (e) {
    toast.error(e?.response?.data?.message ?? 'Failed to queue delete.');
    deleteTarget.value = null;
    deleteBusy.value = false;
    return;
  }

  deleteTarget.value = null;
  deleteBusy.value = false;

  const job = response.data?.job;
  if (!job) {
    refreshList();
    return;
  }

  awaitJob(job, {
    pending: `Deleting "${title}"…`,
    success: `"${title}" was deleted.`,
    fallbackError: 'Failed to delete book.',
  }).finally(refreshList);
}

/**
 * Shared "dispatch + poll + toast" plumbing.
 *
 * Opens a persistent loading toast for the duration of the queued job and
 * resolves it to success/error based on the worker's TrackedJob status.
 * The optimistic UI (closed modal, removed row, etc.) is the caller's
 * responsibility; we only handle feedback + reconciliation.
 */
async function awaitJob(job, { pending, success, fallbackError }) {
  const t = toast.loading(pending);
  try {
    const finalJob = await trackJob(job);
    if (finalJob.status === 'completed') {
      t.resolve('success', { message: success });
    } else {
      t.resolve('error', { message: finalJob.message ?? fallbackError });
    }
  } catch (e) {
    t.resolve('error', { message: e?.message ?? fallbackError });
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

    <div class="py-8">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
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
