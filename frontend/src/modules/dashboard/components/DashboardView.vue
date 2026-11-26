<script setup>
import { computed, onMounted, ref } from 'vue';
import { useWardrobeStore } from '../../wardrobe/store/wardrobeStore';
import { useOutfitStore } from '../../outfits/store/outfitStore';

const wardrobe = useWardrobeStore();
const outfits = useOutfitStore();
const savingSuggestion = ref(false);
const suggestion = computed(() => outfits.suggestions[0] || null);
const emit = defineEmits(['go-outfits', 'go-wardrobe']);
const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});
const recentOutfits = computed(() => outfits.outfits.slice(0, 3));

onMounted(() => {
    wardrobe.loadGarments();
    outfits.loadOutfits();
    loadDailySuggestion();
});

const loadDailySuggestion = async () => {
    try {
        await outfits.loadPreferences();
        await outfits.loadSuggestions('diario', outfits.preferences.preferred_style || 'casual', outfits.preferences.preferred_season || '');
    } catch (requestError) {
        outfits.suggestionError = requestError.message;
    }
};

const refreshSuggestion = loadDailySuggestion;

const saveSuggestion = async () => {
    if (!suggestion.value) return;
    savingSuggestion.value = true;
    try {
        const index = outfits.outfits.length + 1;
        await outfits.createOutfit({
            name: `Look diario ${index}`,
            occasion: 'diario',
            style: outfits.preferences.preferred_style || 'casual',
            season: outfits.preferences.preferred_season || '',
            source: 'recommended',
            notes: suggestion.value.reason || '',
            garment_ids: suggestion.value.garments.map((garment) => garment.id)
        });
        outfits.suggestionMessage = 'Look guardado en tus looks.';
    } catch (requestError) {
        outfits.suggestionError = requestError.message;
    } finally {
        savingSuggestion.value = false;
    }
};

const replaceGarment = (garmentId, replacement) => {
    if (!suggestion.value) return;
    const index = suggestion.value.garments.findIndex((garment) => garment.id === garmentId);
    if (index !== -1) suggestion.value.garments[index] = replacement;
};
</script>

<template>
    <main class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
        <section class="rounded-3xl bg-[var(--color-border)] p-6 sm:p-8"><p class="text-sm text-[var(--color-primary)]">{{ greeting }}</p><h2 class="mt-2 text-3xl font-semibold leading-tight">¿Qué te apetece ponerte hoy?</h2><p class="mt-3 text-sm text-[var(--color-primary)]">Nada que ponerme. Todo por combinar.</p></section>
        <section class="mt-6 grid grid-cols-2 gap-4"><div class="rounded-3xl bg-white p-5 shadow-sm"><p class="text-sm text-[var(--color-text-subtle)]">Prendas</p><p class="mt-2 text-3xl font-semibold">{{ wardrobe.totalGarments }}</p></div><div class="rounded-3xl bg-white p-5 shadow-sm"><p class="text-sm text-[var(--color-text-subtle)]">Looks guardados</p><p class="mt-2 text-3xl font-semibold">{{ outfits.outfits.length }}</p></div></section>
        <section class="mt-6 rounded-3xl bg-[var(--color-primary)] p-6 text-white sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-sm text-[var(--color-border)]">Tu look para hoy</p><h3 class="mt-2 text-2xl font-semibold">Una idea con lo que ya tienes</h3></div><button class="self-start rounded-full border border-white/40 px-4 py-2 text-sm transition hover:bg-white/10 disabled:opacity-60" :disabled="outfits.suggestionLoading || !wardrobe.garments.length" @click="refreshSuggestion">{{ outfits.suggestionLoading ? 'Buscando…' : 'Cambiar look' }}</button></div>
            <p v-if="outfits.suggestionLoading" class="mt-5 text-sm text-[var(--color-border)]" role="status">Buscando una combinación para ti…</p>
            <p v-else-if="outfits.suggestionError" class="mt-5 rounded-xl bg-white/10 p-3 text-sm" role="alert">{{ outfits.suggestionError }}</p>
            <div v-else-if="suggestion" class="mt-5">
                <p class="text-sm leading-6 text-[var(--color-border)]">{{ suggestion.explanation }}</p>
                <p v-if="suggestion.reason" class="mt-1 text-xs text-[var(--color-border)]">{{ suggestion.reason }}</p>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <article v-for="garment in suggestion.garments" :key="garment.id" class="min-w-0 rounded-2xl bg-white p-2 text-[var(--color-text)]">
                        <div class="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-[var(--color-border)]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" /><span v-else class="text-2xl text-[var(--color-accent)]" aria-hidden="true">✦</span></div>
                        <p class="mt-2 truncate text-xs font-medium">{{ garment.name }}</p>
                        <div v-if="suggestion.alternatives?.[garment.id]?.length" class="mt-2 flex flex-wrap gap-1"><button v-for="alternative in suggestion.alternatives[garment.id]" :key="alternative.id" class="rounded-full border border-[var(--color-border)] px-2 py-1 text-[10px] text-[var(--color-primary)]" :aria-label="`Cambiar ${garment.name} por ${alternative.name}`" @click="replaceGarment(garment.id, alternative)">Cambiar</button></div>
                    </article>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3"><button class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-[var(--color-primary)] disabled:opacity-60" :disabled="savingSuggestion" @click="saveSuggestion">{{ savingSuggestion ? 'Guardando…' : 'Guardar look' }}</button><span v-if="outfits.suggestionMessage" class="text-sm text-[var(--color-border)]" role="status">{{ outfits.suggestionMessage }}</span></div>
            </div>
            <div v-else-if="!wardrobe.loading && !wardrobe.totalGarments" class="mt-4"><p class="text-sm leading-6 text-[var(--color-border)]">Añade algunas prendas al armario y prepararé una combinación para ti.</p><button class="mt-3 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-[var(--color-primary)]" @click="emit('go-wardrobe')">Añadir prendas</button></div>
            <p v-else-if="!outfits.suggestionLoading" class="mt-4 text-sm text-[var(--color-border)]">Todavía no encuentro una combinación. Añade prendas de distintas categorías para probar.</p>
        </section>
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
