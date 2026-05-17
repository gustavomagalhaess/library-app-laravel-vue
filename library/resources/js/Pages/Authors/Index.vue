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
import { trackJob } from '@/composables/useJobTracker.js';

/**
 * Authors index page — mirror image of Pages/Books/Index.vue. Mutations are
 * queued: validation runs synchronously (FormRequest), then the controller
 * dispatches a job and returns 202. The page closes its modal immediately
 * and uses the loading toast to surface the queued job's eventual outcome.
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
  const name = payload?.name ?? formTarget.value?.name ?? 'author';

  let response;
  try {
    if (isEdit) {
      response = await axios.put(
        route('api.authors.update', { author: formTarget.value.id }),
        payload,
      );
    } else {
      response = await axios.post(route('api.authors.store'), payload);
    }
  } catch (e) {
    if (e?.response?.status === 422) {
      formErrors.value = flattenLaravelErrors(e.response.data?.errors ?? {});
    } else {
      toast.error(e?.response?.data?.message ?? 'Failed to queue author save. Please try again.');
    }
    formProcessing.value = false;
    return;
  }

  formMode.value = null;
  formTarget.value = null;
  formProcessing.value = false;

  const job = response.data?.job;
  if (!job) {
    refreshList();
    return;
  }

  awaitJob(job, {
    pending: isEdit ? `Updating "${name}"…` : `Saving "${name}"…`,
    success: isEdit ? `"${name}" updated.` : `"${name}" added.`,
    fallbackError: isEdit ? 'Failed to update author.' : 'Failed to save author.',
  }).finally(refreshList);
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

  let response;
  try {
    response = await axios.delete(
      route('api.authors.destroy', { author: deleteTarget.value.id }),
    );
  } catch (e) {
    // The "has books" precondition is enforced inside the job; if the
    // controller itself fails synchronously something deeper went wrong.
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
    pending: `Deleting "${name}"…`,
    success: `"${name}" was deleted.`,
    fallbackError: 'Failed to delete author.',
  }).finally(refreshList);
}

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
