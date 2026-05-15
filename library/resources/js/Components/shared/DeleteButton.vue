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
    @click="emit('confirm')"
  >
    Delete
  </DangerButton>
</template>
