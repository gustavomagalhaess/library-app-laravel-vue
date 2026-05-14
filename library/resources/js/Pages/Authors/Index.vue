<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AuthorList from '@/Components/author/AuthorList.vue';
import AuthorForm from '@/Components/author/AuthorForm.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/shared/ConfirmModal.vue';

/**
 * Authors index page — mirror image of Pages/Books/Index.vue.
 *
 *   - "+ New author"  → AuthorForm in a modal → POST /api/authors
 *   - "Edit"          → AuthorForm in a modal → PUT  /api/authors/{author}
 *   - "Delete"        → ConfirmModal          → DELETE /api/authors/{author}
 */
defineProps({
  authors: Object,
  filters: Object,
  can: Object,
});

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

  try {
    if (formMode.value === 'edit') {
      await axios.put(route('api.authors.update', { author: formTarget.value.id }), payload);
    } else {
      await axios.post(route('api.authors.store'), payload);
    }
    formMode.value = null;
    formTarget.value = null;
    refreshList();
  } catch (e) {
    if (e?.response?.status === 422) {
      formErrors.value = flattenLaravelErrors(e.response.data?.errors ?? {});
    } else {
      formErrors.value = { _form: e?.response?.data?.message ?? 'Something went wrong.' };
    }
  } finally {
    formProcessing.value = false;
  }
}

// --- Delete confirmation modal state --------------------------------------
const deleteTarget = ref(null);
const deleteBusy = ref(false);
const deleteError = ref(null);

function askDelete(author) {
  deleteTarget.value = author;
  deleteError.value = null;
}

function closeDelete() {
  if (deleteBusy.value) return;
  deleteTarget.value = null;
  deleteError.value = null;
}

async function confirmDelete() {
  if (!deleteTarget.value) return;
  deleteBusy.value = true;
  deleteError.value = null;
  try {
    await axios.delete(route('api.authors.destroy', { author: deleteTarget.value.id }));
    deleteTarget.value = null;
    refreshList();
  } catch (e) {
    // 409 = AuthorHasBooks — surface the server message in the modal so the
    // user understands why the delete was refused.
    deleteError.value = e?.response?.data?.message ?? 'Failed to delete author.';
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
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Authors</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
        <p v-if="formErrors._form" class="mb-3 text-sm text-red-600">{{ formErrors._form }}</p>
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
      :message="deleteError ?? `Delete the author: ${deleteTarget?.name ?? ''}?`"
      :confirm-label="deleteError ? 'OK' : 'Delete'"
      :busy="deleteBusy"
      @confirm="deleteError ? closeDelete() : confirmDelete()"
      @close="closeDelete"
    />
  </AuthenticatedLayout>
</template>
