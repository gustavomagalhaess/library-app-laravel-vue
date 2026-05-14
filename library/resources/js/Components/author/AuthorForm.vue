<script setup>
import { reactive, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import CancelButton from '@/Components/shared/CancelButton.vue';
import SuccessButton from '@/Components/SuccessButton.vue';

/**
 * The Author create / edit form, designed to live inside a modal on the
 * Authors index page.
 *
 *   - `@submit` emits the plain payload object for the parent page to POST.
 *   - `@cancel` closes the modal.
 *   - Validation errors come back in via the `:errors` prop.
 */
const props = defineProps({
  mode: { type: String, default: 'create' },
  author: { type: Object, default: null },
  errors: { type: Object, default: () => ({}) },
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'cancel']);

const isEdit = computed(() => props.mode === 'edit');

const form = reactive({ name: props.author?.name ?? '' });

function submit() {
  emit('submit', { name: form.name });
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-5">
    <div>
      <InputLabel value="Name"/>
      <TextInput
        v-model="form.name"
        class="mt-1 block w-full"
      />
      <InputError :message="errors.name" class="mt-1"/>
    </div>

    <div class="flex justify-end gap-2">
      <CancelButton type="button" @click="emit('cancel')">
          Cancel
      </CancelButton>
      <SuccessButton
        type="submit"
        :disabled="processing"
      >
          {{ isEdit ? 'Save changes' : 'Add author' }}
      </SuccessButton>
    </div>
  </form>
</template>
