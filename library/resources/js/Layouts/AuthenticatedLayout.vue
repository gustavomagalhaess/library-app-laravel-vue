<script setup>
/**
 * Minimal authenticated layout for the Library app.
 * Auth/account flows are split between Fortify (profile info, password,
 * email verification, 2FA) and a tiny app-owned delete-account endpoint
 * (DELETE /user → DeleteAccountController). The "Profile" link below hosts
 * the Vue forms that talk to those endpoints.
 */
import { ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Toaster from '@/Components/shared/Toaster.vue';
import { useToasts } from '@/composables/useToasts.js';

const showMobile = ref(false);
const page = usePage();
const toast = useToasts();

const user = computed(() => page.props.auth?.user ?? null);
const permissions = computed(() => page.props.auth?.permissions ?? []);
const roles = computed(() => page.props.auth?.roles ?? []);

const can = (perm) => Array.isArray(permissions.value)
  ? permissions.value.includes(perm)
  : !!permissions.value?.includes?.(perm);

// Role check — mirrors the Spatie role names seeded in
// RolesAndPermissionsSeeder (admin, librarian, reader). Used to hide the
// developer-only Telescope / Horizon links from non-admins. The backend
// Gate (viewTelescope / viewHorizon in their respective ServiceProviders)
// is what actually enforces access; this just prevents the link from
// rendering for users who can't open it anyway.
const hasRole = (name) => Array.isArray(roles.value)
  ? roles.value.includes(name)
  : !!roles.value?.includes?.(name);

// Fortify flashes machine-readable status slugs (e.g. 'password-updated').
// Translate the ones we surface to humans here so the toast doesn't read like
// a kebab-case identifier. Anything not in this table falls through verbatim,
// which works fine for our own controllers that flash full sentences.
const FLASH_LABELS = {
  'password-updated': 'Password updated.',
  'profile-information-updated': 'Profile updated.',
  'verification-link-sent': 'Verification link sent.',
  'two-factor-authentication-enabled': 'Two-factor authentication enabled.',
  'two-factor-authentication-disabled': 'Two-factor authentication disabled.',
  'recovery-codes-generated': 'Recovery codes regenerated.',
};

// Server-side flash messages still work for traditional Inertia navigations
// (e.g. profile/logout). They're forwarded into the toast queue so we have a
// single notification mechanism — no more competing banners.
watch(
  () => page.props.flash?.status,
  (msg) => { if (msg) toast.success(FLASH_LABELS[msg] ?? msg); },
  { immediate: true },
);
watch(
  () => page.props.flash?.error,
  (msg) => { if (msg) toast.error(FLASH_LABELS[msg] ?? msg); },
  { immediate: true },
);

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <div class="background-image min-h-screen bg-gray-100 dark:bg-gray-800">
    <nav class="bg-white border-b border-gray-200 dark:bg-black dark:text-white/50 dark:border-gray-500">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <Link :href="route('dashboard')" class="flex items-center font-semibold text-gray-600 dark:text-white/40">
              📚 Library
            </Link>

            <div class="hidden sm:flex sm:items-center sm:ml-8 space-x-4">
              <Link
                :href="route('dashboard')"
                class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-white/50 dark:hover:text-indigo-400"
              >Dashboard</Link>

              <Link
                v-if="can('books.view')"
                :href="route('media.index', { type: 'book' })"
                class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-white/50 dark:hover:text-indigo-400"
              >Books</Link>

              <Link
                v-if="can('authors.view')"
                :href="route('authors.index')"
                class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-white/50 dark:hover:text-indigo-400"
              >Authors</Link>
            </div>
          </div>

          <div class="hidden sm:flex sm:items-center gap-4">
            <!-- Telescope + Horizon are NOT Inertia pages — they're standalone
                 Laravel UIs mounted at /telescope and /horizon. Wrapping them
                 in <Link> makes Inertia intercept the click and try to render
                 the response as an Inertia page (which it isn't), which also
                 swallows target="_blank". Use plain <a> tags so the browser
                 handles the navigation natively. -->
            <a
              v-if="user && hasRole('admin')"
              href="/telescope"
              target="_blank"
              rel="noopener noreferrer"
              class="text-sm text-gray-600 hover:text-indigo-600 dark:text-white/50 dark:hover:text-indigo-400"
            >Telescope</a>
            <a
              v-if="user && hasRole('admin')"
              href="/horizon"
              target="_blank"
              rel="noopener noreferrer"
              class="text-sm text-gray-600 hover:text-indigo-600 dark:text-white/50 dark:hover:text-indigo-400"
            >Horizon</a>
            <Link
              v-if="user"
              :href="route('profile.edit')"
              class="text-sm text-gray-600 hover:text-indigo-600 dark:text-white/50 dark:hover:text-indigo-400"
            >{{ user.name }}</Link>
            <button
              type="button"
              class="text-sm text-gray-600 hover:text-red-600 dark:text-white/50 dark:hover:text-red-400"
              @click="logout"
            >Log out</button>
          </div>

          <div class="flex items-center sm:hidden">
            <button @click="showMobile = !showMobile" class="p-2 text-gray-500 hover:text-gray-700">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <div v-show="showMobile" class="sm:hidden border-t border-gray-200 bg-white">
        <div class="py-2 space-y-1 px-4">
          <Link :href="route('dashboard')" class="block py-2 text-sm text-gray-700">Dashboard</Link>
          <Link v-if="can('books.view')" :href="route('media.index', { type: 'book' })" class="block py-2 text-sm text-gray-700">Books</Link>
          <Link v-if="can('authors.view')" :href="route('authors.index')" class="block py-2 text-sm text-gray-700">Authors</Link>
          <a v-if="user && hasRole('admin')" href="/telescope" target="_blank" rel="noopener noreferrer" class="block py-2 text-sm text-gray-700">Telescope</a>
          <a v-if="user && hasRole('admin')" href="/horizon" target="_blank" rel="noopener noreferrer" class="block py-2 text-sm text-gray-700">Horizon</a>
          <Link v-if="user" :href="route('profile.edit')" class="block py-2 text-sm text-gray-700">Profile</Link>
          <button type="button" class="block py-2 text-sm text-red-600" @click="logout">Log out</button>
        </div>
      </div>
    </nav>

    <header v-if="$slots.header" class="bg-white dark:bg-gray-800 shadow-lg">
      <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <slot name="header" />
      </div>
    </header>

    <main>
      <slot />
    </main>

    <!-- Single global toast queue for both API responses (pushed from pages
         via useToasts) and Inertia flash messages (forwarded by the watchers
         above). -->
    <Toaster />
  </div>
</template>
<style scoped>
.background-image {
    background-image: url('/assets/images/library-bg-2.jpg');
    background-size: cover;
    background-position: center;
}
</style>
