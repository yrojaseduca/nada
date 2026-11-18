<script setup>
import { onMounted, ref } from 'vue';
import { useOutfitStore } from '../store/outfitStore';
import { useWardrobeStore } from '../../wardrobe/store/wardrobeStore';

const outfits = useOutfitStore();
const wardrobe = useWardrobeStore();
const selected = ref([]);
const name = ref('');
const showForm = ref(false);
const occasion = ref('diario');
const style = ref('casual');
const filterOccasion = ref('');
const filterStyle = ref('');

const toggleGarment = (garment) => {
    selected.value = selected.value.includes(garment.id)
        ? selected.value.filter((id) => id !== garment.id)
        : [...selected.value, garment.id];
};

const save = async () => {
    if (!name.value.trim() || !selected.value.length) return;
    await outfits.createOutfit({ name: name.value, garment_ids: selected.value });
    name.value = '';
    selected.value = [];
    showForm.value = false;
};

onMounted(() => {
    outfits.loadOutfits();
    if (!wardrobe.garments.length) wardrobe.loadGarments();
});

const suggest = () => outfits.loadSuggestions(occasion.value, style.value);
const filterOutfits = () => outfits.loadOutfits(filterOccasion.value, filterStyle.value);
</script>

<template>
    <main class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
        <section class="rounded-3xl bg-[#E9D9C9] p-6 sm:p-8">
            <p class="text-sm text-[#4D5145]">Tu armario, pero con ideas</p>
            <h2 class="mt-2 text-3xl font-semibold leading-tight">Looks para esos días de “no tengo nada”.</h2>
            <button class="mt-5 rounded-full bg-[#4D5145] px-5 py-3 text-sm font-medium text-white" @click="showForm = true">Crear un look</button>
        </section>

        <section v-if="showForm" class="mt-6 rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h3 class="text-xl font-semibold">Crear look</h3><button @click="showForm = false">×</button></div>
            <input v-model="name" class="mt-5 w-full rounded-2xl border border-[#E9D9C9] px-4 py-3" placeholder="Nombre del look" />
            <p class="mt-5 text-sm font-medium">Elige las prendas</p>
            <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-5">
                <button v-for="garment in wardrobe.garments" :key="garment.id" class="overflow-hidden rounded-2xl border-2 text-left" :class="selected.includes(garment.id) ? 'border-[#4D5145]' : 'border-transparent'" @click="toggleGarment(garment)">
                    <div class="flex aspect-square items-center justify-center bg-[#E9D9C9]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" /><span v-else class="text-2xl text-[#B7A18D]">✦</span></div>
                    <p class="truncate p-2 text-xs">{{ garment.name }}</p>
                </button>
            </div>
            <button class="mt-6 w-full rounded-2xl bg-[#4D5145] px-5 py-3 font-medium text-white disabled:opacity-50" :disabled="!name.trim() || !selected.length" @click="save">Guardar look</button>
        </section>

        <section class="mt-8">
            <h2 class="text-xl font-semibold">Mis looks</h2>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row"><select v-model="filterOccasion" class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-2 text-sm" @change="filterOutfits"><option value="">Todas las ocasiones</option><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option></select><select v-model="filterStyle" class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-2 text-sm" @change="filterOutfits"><option value="">Todos los estilos</option><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option></select></div>
            <div class="mt-4 rounded-3xl bg-white p-5 shadow-sm"><div class="flex flex-col gap-3 sm:flex-row sm:items-end"><label class="flex-1 text-sm">Ocasión<select v-model="occasion" class="mt-2 w-full rounded-xl border border-[#E9D9C9] bg-white px-3 py-2"><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option></select></label><label class="flex-1 text-sm">Estilo<select v-model="style" class="mt-2 w-full rounded-xl border border-[#E9D9C9] bg-white px-3 py-2"><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option></select></label><button class="rounded-xl bg-[#4D5145] px-4 py-2 text-sm font-medium text-white" @click="suggest">Sugerirme un look</button></div><p v-if="outfits.suggestionMessage" class="mt-4 text-sm text-[#8E857A]">{{ outfits.suggestionMessage }}</p><div v-for="suggestion in outfits.suggestions" :key="suggestion.explanation" class="mt-4 rounded-2xl bg-[#F8F5F0] p-4"><p class="text-sm">{{ suggestion.explanation }}</p><div class="mt-3 flex gap-2"><span v-for="garment in suggestion.garments" :key="garment.id" class="rounded-full bg-white px-3 py-1 text-xs">{{ garment.name }}</span></div></div></div>
            <p v-if="outfits.error" class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-700">{{ outfits.error }}</p>
            <div v-else-if="!outfits.outfits.length" class="mt-5 rounded-3xl bg-white p-8 text-center shadow-sm"><p class="text-lg font-semibold">Aún no hay looks guardados.</p><p class="mt-2 text-sm text-[#8E857A]">Resulta que sí había opciones.</p></div>
            <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="outfit in outfits.outfits" :key="outfit.id" class="rounded-3xl bg-white p-4 shadow-sm"><div class="grid grid-cols-3 gap-2"><div v-for="garment in outfit.garments" :key="garment.id" class="flex aspect-square items-center justify-center rounded-xl bg-[#E9D9C9]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full rounded-xl object-cover" /><span v-else class="text-xl text-[#B7A18D]">✦</span></div></div><div class="mt-4 flex items-center justify-between"><h3 class="font-semibold">{{ outfit.name }}</h3><button :aria-label="outfit.is_favorite ? 'Quitar favorito' : 'Añadir favorito'" @click="outfits.toggleFavorite(outfit)">{{ outfit.is_favorite ? '♥' : '♡' }}</button></div></article>
            </div>
        </section>
    </main>
</template>
