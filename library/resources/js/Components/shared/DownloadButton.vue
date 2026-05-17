<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToasts } from '@/composables/useToasts.js';
import { trackJob } from '@/composables/useJobTracker.js';

/**
 * Triggers the queued-download workflow and surfaces feedback via a toast.
 *
 * The page passes `type` + `id` (e.g. type=book, id=<media uuid>). On click:
 *   1. POST /api/{type}/{id}/download                       — queues a job
 *   2. Show a persistent "Preparing download…" loading toast
 *   3. Poll /api/jobs/{uuid} until terminal
 *   4. On success, replace the toast with a "Download" action button that
 *      points at the signed URL returned by the job. The toast stays up
 *      until the user clicks the link or dismisses it.
 *
 * For backwards compatibility the component still supports being rendered
 * as a plain link by passing an `href` prop (used in places that haven't
 * been ported to the queued flow yet).
 */
const props = defineProps({
  type: { type: String, default: null },
  id:   { type: String, default: null },
  title: { type: String, default: null },
  // Optional fallback: render as a plain link to this href if both
  // type+id are missing. Lets the component double as a sync link too.
  href: { type: String, default: null },
});

const toast = useToasts();
const busy = ref(false);

async function requestDownload() {
  if (busy.value) return;
  if (!props.type || !props.id) {
    // No queued flow available — let the parent anchor take over.
    return;
  }
  busy.value = true;
  const t = toast.loading(`Preparing download${props.title ? ` of "${props.title}"` : ''}…`);
  try {
    const { data } = await axios.post(
      route('api.media.download.request', { type: props.type, id: props.id }),
    );
    const job = data?.job;
    if (!job) {
      t.resolve('error', { message: 'Failed to start the download.' });
      return;
    }
    const finalJob = await trackJob(job);
    if (finalJob.status === 'completed' && finalJob.result?.url) {
      t.resolve('success', {
        message: 'Your download is ready.',
        action: { label: 'Download now', href: finalJob.result.url },
      });
    } else {
      t.resolve('error', { message: finalJob.message ?? 'Failed to prepare download.' });
    }
  } catch (e) {
    t.resolve('error', { message: e?.response?.data?.message ?? e?.message ?? 'Download failed.' });
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <!-- Render as a button if we have a queued flow available, otherwise as
       a plain link so the legacy sync download still works. -->
  <button
    v-if="type && id"
    type="button"
    :disabled="busy"
    :aria-busy="busy"
    class="inline-flex items-center rounded-md border
        border-gray-300
        bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest
        text-gray-700 shadow-sm transition duration-150 ease-in-out
        hover:bg-gray-50 focus:outline-none focus:ring-2
        focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
    @click="requestDownload"
  >
    <svg
      v-if="busy"
      class="size-4 mr-1 animate-spin"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
    <slot v-else />
  </button>
  <a
    v-else-if="href"
    :href="href"
    class="inline-flex items-center rounded-md border
        border-gray-300
        bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest
        text-gray-700 shadow-sm transition duration-150 ease-in-out
        hover:bg-gray-50 focus:outline-none focus:ring-2
        focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
  >
    <slot />
  </a>
</template>
