<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { Toaster } from '@/components/ui/sonner';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
        <Toaster />
    </AuthLayout>
</template>
