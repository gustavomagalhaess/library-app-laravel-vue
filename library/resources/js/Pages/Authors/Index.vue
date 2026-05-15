<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AuthorList from '@/Components/author/AuthorList.vue';
import AuthorForm from '@/Components/author/AuthorForm.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/shared/ConfirmModal.vue';
import { useToasts } from '@/composables/useToasts.js';

/**
 * Authors index page — mirror image of Pages/Books/Index.vue.
 *
 *   - "+ New author"  → AuthorForm in a modal → POST /api/authors
 *   - "Edit"          → AuthorForm in a modal → PUT  /api/authors/{author}
 *   - "Delete"        → ConfirmModal          → DELETE /api/authors/{author}
 *
 * Feedback for every API call is delivered through the global toast queue
 * (see useToasts + the Toaster mounted in AuthenticatedLayout). The form
 * still shows 422 field errors inline.
 */
defineProps({
  authors: Object,
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

function openEdit(author) {
  formMode.value = 'edit';
  formTarget.value = author;
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

  try {
    if (isEdit) {
      await axios.put(route('api.authors.update', { author: formTarget.value.id }), payload);
    } else {
      await axios.post(route('api.authors.store'), payload);
    }
    formMode.value = null;
    formTarget.value = null;
    refreshList();
    toast.success(isEdit ? 'Author updated.' : 'Author added.');
  } catch (e) {
    if (e?.response?.status === 422) {
      formErrors.value = flattenLaravelErrors(e.response.data?.errors ?? {});
    } else {
      toast.error(e?.response?.data?.message ?? 'Failed to save author. Please try again.');
    }
  } finally {
    formProcessing.value = false;
  }
}

// --- Delete confirmation modal state --------------------------------------
const deleteTarget = ref(null);
const deleteBusy = ref(false);

function askDelete(author) {
  deleteTarget.value = author;
}

function closeDelete() {
  if (deleteBusy.value) return;
  deleteTarget.value = null;
}

async function confirmDelete() {
  if (!deleteTarget.value) return;
  deleteBusy.value = true;
  const name = deleteTarget.value.name ?? 'Author';
  try {
    await axios.delete(route('api.authors.destroy', { author: deleteTarget.value.id }));
    deleteTarget.value = null;
    refreshList();
    toast.success(`"${name}" was deleted.`);
  } catch (e) {
    // 409 = AuthorHasBooks — surface the server message via toast so the
    // user understands why the delete was refused. Close the modal either
    // way; the message is preserved in the toast queue.
    deleteTarget.value = null;
    toast.error(e?.response?.data?.message ?? 'Failed to delete author.');
  } finally {
    deleteBusy.value = false;
  }
}

// --- Helpers ---------------------------------------------------------------
function refreshList() {
  router.reload({ only: ['authors'], preserveScroll: true, preserveState: true });
}

function flattenLaravelErrors(errors) {
  return Object.fromEntries(
    Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
  );
}
</script>

<template>
  <Head title="Authors" />
  <AuthenticatedLayout>

    <div class="py-8">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <AuthorList
          :authors="authors"
          :filters="filters"
          :can="can"
          @new="openCreate"
          @edit="openEdit"
          @delete="askDelete"
        />
      </div>
    </div>

    <!-- Create / edit modal -->
    <Modal :show="formOpen" max-width="lg" @close="closeForm">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          {{ formMode === 'edit' ? 'Edit author' : 'Add a new author' }}
        </h3>
        <AuthorForm
          v-if="formOpen"
          :key="formTarget?.id ?? 'new'"
          :mode="formMode"
          :author="formTarget"
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
      title="Delete author"
      :message="`Delete the author: ${deleteTarget?.name ?? ''}?`"
      confirm-label="Delete"
      :busy="deleteBusy"
      @confirm="confirmDelete"
      @close="closeDelete"
    />
  </AuthenticatedLayout>
</template>
