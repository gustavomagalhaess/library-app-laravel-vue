<script setup>
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

/**
 * Generic yes / no confirmation modal.
 *
 * Replaces the browser's `window.confirm` for destructive actions (deletes,
 * etc.). The parent controls visibility via :show and listens for @confirm /
 * @close to decide what to do.
 *
 *   <ConfirmModal
 *     :show="!!toDelete"
 *     title="Delete book"
 *     :message="`Delete the book: ${toDelete?.media?.title}?`"
 *     @confirm="performDelete"
 *     @close="toDelete = null"
 *   />
 */
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Are you sure?' },
  message: { type: String, default: 'This action cannot be undone.' },
  confirmLabel: { type: String, default: 'Delete' },
  cancelLabel: { type: String, default: 'Cancel' },
  busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'close']);
</script>

<template>
  <Modal :show="show" max-width="md" @close="emit('close')">
    <div class="p-6">
      <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
      <p class="mt-2 text-sm text-gray-600">{{ message }}</p>

      <div class="mt-6 flex justify-end gap-2">
        <SecondaryButton type="button" :disabled="busy" @click="emit('close')">
          {{ cancelLabel }}
        </SecondaryButton>
        <DangerButton type="button" dusk="confirm-modal-confirm" :disabled="busy" @click="emit('confirm')">
          {{ confirmLabel }}
        </DangerButton>
      </div>
    </div>
  </Modal>
</template>
