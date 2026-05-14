<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { chat } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const nodes = ref<Array<{ x: number; y: number; delay: number; size: number }>>([]);

onMounted(() => {
    const arr: Array<{ x: number; y: number; delay: number; size: number }> = [];
    for (let i = 0; i < 18; i++) {
        arr.push({
            x: Math.random() * 100,
            y: Math.random() * 100,
            delay: Math.random() * 4,
            size: 2 + Math.random() * 3,
        });
    }
    nodes.value = arr;
});
</script>

<template>
    <div class="relative grid h-dvh flex-col items-center justify-center px-4 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="relative hidden h-full flex-col overflow-hidden bg-zinc-950 p-10 lg:flex">
            <div class="absolute inset-0 bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950" />

            <svg class="absolute inset-0 h-full w-full opacity-[0.03]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="currentColor" class="text-zinc-500" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>

            <div
                v-for="node in nodes"
                :key="node.x + node.y"
                class="absolute animate-pulse rounded-full"
                :style="{
                    left: node.x + '%',
                    top: node.y + '%',
                    width: node.size + 'px',
                    height: node.size + 'px',
                    animationDelay: node.delay + 's',
                    animationDuration: 3 + node.delay + 's',
                }"
                :class="node.size > 3.5 ? 'bg-[#155974]/40' : 'bg-[#0a2d51]/30'"
            />

            <svg
                class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.06]"
                xmlns="http://www.w3.org/2000/svg"
            >
                <line
                    v-for="(node, i) in nodes.filter((_, k) => k % 3 === 0).slice(0, 5)"
                    :key="'l' + i"
                    :x1="node.x + '%'"
                    :y1="node.y + '%'"
                    :x2="(nodes[i + 1]?.x ?? 50) + '%'"
                    :y2="(nodes[i + 1]?.y ?? 50) + '%'"
                    stroke="currentColor"
                    class="text-[#155974]"
                    stroke-width="0.5"
                />
            </svg>

            <Link :href="chat()" class="relative z-20">
                <img src="/Logo-blanco.png" alt="MonitorSQL" class="h-12 w-auto" />
            </Link>

            <div class="relative z-20 mt-auto flex flex-col gap-6 pb-8">
                <h2 class="max-w-md text-3xl font-bold leading-tight text-white">
                    Consulta tus bases de datos con
                    <span class="text-[#3bafd4]">
                        lenguaje natural
                    </span>
                </h2>

                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-[#155974]/20">
                            <span class="size-1.5 rounded-full bg-[#3bafd4]" />
                        </div>
                        <p class="text-sm text-zinc-400">
                            <span class="font-medium text-zinc-300">Conexiones remotas</span> — MySQL, MariaDB, PostgreSQL con credenciales encriptadas y SSL.
                        </p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-[#155974]/20">
                            <span class="size-1.5 rounded-full bg-[#3bafd4]" />
                        </div>
                        <p class="text-sm text-zinc-400">
                            <span class="font-medium text-zinc-300">IA que genera SQL</span> — Escribe en español lo que necesitas y obtén consultas seguras y optimizadas.
                        </p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-[#155974]/20">
                            <span class="size-1.5 rounded-full bg-[#3bafd4]" />
                        </div>
                        <p class="text-sm text-zinc-400">
                            <span class="font-medium text-zinc-300">Gráficos automáticos</span> — Visualiza resultados en barras, líneas o donut sin configurar nada.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full lg:p-8">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[380px]">
                <div class="flex flex-col gap-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
