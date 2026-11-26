<script setup>
import { computed, onMounted } from 'vue';
import { useWardrobeStore } from '../../wardrobe/store/wardrobeStore';
import { useOutfitStore } from '../../outfits/store/outfitStore';

const wardrobe = useWardrobeStore();
const outfits = useOutfitStore();
const emit = defineEmits(['go-outfits']);
const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});
const recentOutfits = computed(() => outfits.outfits.slice(0, 3));

onMounted(() => {
    wardrobe.loadGarments();
    outfits.loadOutfits();
});
</script>

<template>
    <main class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
        <section class="rounded-3xl bg-[var(--color-border)] p-6 sm:p-8"><p class="text-sm text-[var(--color-primary)]">{{ greeting }}</p><h2 class="mt-2 text-3xl font-semibold leading-tight">¿Qué te apetece ponerte hoy?</h2><p class="mt-3 text-sm text-[var(--color-primary)]">Nada que ponerme. Todo por combinar.</p></section>
        <section class="mt-6 grid grid-cols-2 gap-4"><div class="rounded-3xl bg-white p-5 shadow-sm"><p class="text-sm text-[var(--color-text-subtle)]">Prendas</p><p class="mt-2 text-3xl font-semibold">{{ wardrobe.totalGarments }}</p></div><div class="rounded-3xl bg-white p-5 shadow-sm"><p class="text-sm text-[var(--color-text-subtle)]">Looks guardados</p><p class="mt-2 text-3xl font-semibold">{{ outfits.outfits.length }}</p></div></section>
        <section class="mt-6 rounded-3xl bg-[var(--color-primary)] p-6 text-white"><p class="text-sm text-[var(--color-border)]">Sugerencia rápida</p><h3 class="mt-2 text-2xl font-semibold">Resulta que sí había opciones.</h3><p class="mt-3 text-sm leading-6 text-[var(--color-border)]">Ve a Looks para crear una combinación con lo que ya tienes.</p></section>
        <section v-if="recentOutfits.length" class="mt-6 rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4"><div><p class="text-sm text-[var(--color-text-subtle)]">Tu estilo reciente</p><h3 class="mt-1 text-xl font-semibold">Últimos looks</h3></div><button class="rounded-full border border-[var(--color-border)] px-3 py-2 text-xs font-medium text-[var(--color-primary)]" @click="emit('go-outfits')">Ver todos</button></div>
            <div class="mt-4 grid grid-cols-3 gap-3">
                <button v-for="outfit in recentOutfits" :key="outfit.id" class="text-left" @click="emit('go-outfits')">
                    <div class="grid grid-cols-2 gap-1 overflow-hidden rounded-2xl bg-[var(--color-border)]">
                        <div v-for="garment in outfit.garments.slice(0, 4)" :key="garment.id" class="flex aspect-square items-center justify-center bg-[var(--color-border)]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" /><span v-else class="text-sm text-[var(--color-accent)]">✦</span></div>
                    </div>
                    <p class="mt-2 truncate text-xs font-medium">{{ outfit.name }}</p>
                </button>
            </div>
        </section>
        <section v-if="!wardrobe.totalGarments" class="mt-6 rounded-3xl bg-white p-8 text-center shadow-sm"><h3 class="text-lg font-semibold">Tu armario empieza aquí</h3><p class="mt-2 text-sm text-[var(--color-text-subtle)]">Todas esas cosas que supuestamente no tienes.</p></section>
    </main>
</template>
