<script setup>
import { computed, onMounted } from 'vue';
import { useWardrobeStore } from '../../wardrobe/store/wardrobeStore';
import { useOutfitStore } from '../../outfits/store/outfitStore';

const wardrobe = useWardrobeStore();
const outfits = useOutfitStore();
const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});

onMounted(() => {
    wardrobe.loadGarments();
    outfits.loadOutfits();
});
</script>

<template>
    <main class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
        <section class="rounded-3xl bg-[#E9D9C9] p-6 sm:p-8"><p class="text-sm text-[#4D5145]">{{ greeting }}</p><h2 class="mt-2 text-3xl font-semibold leading-tight">¿Qué te apetece ponerte hoy?</h2><p class="mt-3 text-sm text-[#4D5145]">Nada que ponerme. Todo por combinar.</p></section>
        <section class="mt-6 grid grid-cols-2 gap-4"><div class="rounded-3xl bg-white p-5 shadow-sm"><p class="text-sm text-[#8E857A]">Prendas</p><p class="mt-2 text-3xl font-semibold">{{ wardrobe.totalGarments }}</p></div><div class="rounded-3xl bg-white p-5 shadow-sm"><p class="text-sm text-[#8E857A]">Looks guardados</p><p class="mt-2 text-3xl font-semibold">{{ outfits.outfits.length }}</p></div></section>
        <section class="mt-6 rounded-3xl bg-[#4D5145] p-6 text-white"><p class="text-sm text-[#E9D9C9]">Sugerencia rápida</p><h3 class="mt-2 text-2xl font-semibold">Resulta que sí había opciones.</h3><p class="mt-3 text-sm leading-6 text-[#E9D9C9]">Ve a Looks para crear una combinación con lo que ya tienes.</p></section>
        <section v-if="!wardrobe.totalGarments" class="mt-6 rounded-3xl bg-white p-8 text-center shadow-sm"><h3 class="text-lg font-semibold">Tu armario empieza aquí</h3><p class="mt-2 text-sm text-[#8E857A]">Todas esas cosas que supuestamente no tienes.</p></section>
    </main>
</template>
