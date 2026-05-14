<script setup>
import DangerButton from "../DangerButton.vue";

/**
 * Generic delete button.
 *
 * Emits `confirm` when clicked — the parent is expected to open a
 * {@see ConfirmModal} and only perform the destructive action after the
 * user confirms there. (Older versions of this component used
 * `window.confirm`; that's now handled by the modal so the UX stays
 * consistent across the app.)
 */
const emit = defineEmits(['confirm']);
</script>

<template>
  <DangerButton
    type="button"
    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded
           bg-red-600 text-white hover:bg-red-700"
    @click="emit('confirm')"
  >
    Delete
  </DangerButton>
</template>
