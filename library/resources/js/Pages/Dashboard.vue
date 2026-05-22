<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    bookCount: { type: Number, required: true },
});
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>

        <div class="py-8">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-2xl font-semibold text-gray-800">Dashboard</h2>
                    </div>
                    <div class="max-w-64 rounded overflow-hidden shadow-lg">
                        <div class="px-6 py-4 text-white bg-gray-700 opacity-85">
                            <div class="font-bold text-xl mb-2 text-center">Total Books</div>
                            <p class="text-base text-center">
                                {{ bookCount }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
