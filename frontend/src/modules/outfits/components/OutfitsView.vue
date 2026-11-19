+<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useOutfitStore } from '../store/outfitStore';
import { useWardrobeStore } from '../../wardrobe/store/wardrobeStore';

const outfits = useOutfitStore();
const wardrobe = useWardrobeStore();
const showForm = ref(false);
const editing = ref(null);
const selected = ref(null);
const favoriteOnly = ref(false);
const category = ref('');
const filterOccasion = ref('');
const filterStyle = ref('');
const filterSeason = ref('');
const suggestionOccasion = ref('diario');
const suggestionStyle = ref('casual');
const suggestionSeason = ref('');
const message = ref('');
const formError = ref('');
const form = reactive({ name: '', occasion: 'diario', style: 'casual', season: '', notes: '', garment_ids: [] });
const categories = [
    { label: 'Todas', value: '' }, { label: 'Superior', value: 'top' }, { label: 'Inferior', value: 'bottom' },
    { label: 'Vestidos', value: 'dress' }, { label: 'Abrigos', value: 'outerwear' }, { label: 'Zapatos', value: 'shoes' },
    { label: 'Accesorios', value: 'accessories' }
];
const visibleGarments = computed(() => category.value ? wardrobe.garments.filter((item) => item.category === category.value) : wardrobe.garments);
const visibleOutfits = computed(() => favoriteOnly.value ? outfits.outfits.filter((item) => item.is_favorite) : outfits.outfits);

const reset = () => {
    Object.assign(form, { name: '', occasion: 'diario', style: 'casual', season: '', notes: '', garment_ids: [] });
    editing.value = null;
    formError.value = '';
};
const create = () => { reset(); showForm.value = true; };
const edit = (outfit) => {
    Object.assign(form, { name: outfit.name, occasion: outfit.occasion || 'diario', style: outfit.style || 'casual', season: outfit.season || '', notes: outfit.notes || '', garment_ids: outfit.garments.map((item) => item.id) });
    editing.value = outfit;
    selected.value = null;
    showForm.value = true;
};
const toggleGarment = (id) => { form.garment_ids = form.garment_ids.includes(id) ? form.garment_ids.filter((item) => item !== id) : [...form.garment_ids, id]; };
const save = async () => {
    formError.value = '';
    if (!form.name.trim() || !form.garment_ids.length) { formError.value = 'El nombre y al menos una prenda son obligatorios.'; return; }
    try {
        if (editing.value) await outfits.updateOutfit(editing.value.id, { ...form });
        else await outfits.createOutfit({ ...form });
        showForm.value = false;
        reset();
        message.value = 'Resulta que sí tenías algo que ponerte.';
        window.setTimeout(() => { message.value = ''; }, 4000);
    } catch (error) { formError.value = error.message; }
};
const remove = async (outfit) => {
    if (!window.confirm(`¿Eliminar “${outfit.name}”?`)) return;
    await outfits.removeOutfit(outfit.id);
    selected.value = null;
};
const duplicate = async (outfit) => {
    await outfits.duplicateOutfit(outfit);
    message.value = 'Look duplicado. Porque una buena idea merece repetirse.';
    window.setTimeout(() => { message.value = ''; }, 4000);
};
const suggest = async () => {
    await outfits.loadSuggestions(suggestionOccasion.value, suggestionStyle.value, suggestionSeason.value);
};
const saveSuggestion = async (suggestion) => {
    try {
        await outfits.createOutfit({
            name: `Look ${suggestionOccasion.value}`,
            occasion: suggestionOccasion.value,
            style: suggestionStyle.value,
            season: suggestionSeason.value,
            source: 'recommended',
            notes: suggestion.reason,
            garment_ids: suggestion.garments.map((garment) => garment.id)
        });
        message.value = 'Recomendación guardada. Resulta que sí tenías algo que ponerte.';
        window.setTimeout(() => { message.value = ''; }, 4000);
    } catch (error) {
        outfits.suggestionError = error.message;
    }
};
const savePreferences = async () => {
    await outfits.updatePreferences({ preferred_style: suggestionStyle.value, preferred_season: suggestionSeason.value });
    message.value = 'Preferencias guardadas. El estilista ya sabe por dónde empezar.';
    window.setTimeout(() => { message.value = ''; }, 4000);
};
const replaceSuggestionGarment = (suggestion, garmentId, replacement) => {
    const index = suggestion.garments.findIndex((garment) => garment.id === garmentId);
    if (index !== -1) suggestion.garments[index] = replacement;
};
onMounted(async () => {
    outfits.loadOutfits();
    if (!wardrobe.garments.length) wardrobe.loadGarments();
    await outfits.loadPreferences();
    suggestionStyle.value = outfits.preferences.preferred_style || suggestionStyle.value;
    suggestionSeason.value = outfits.preferences.preferred_season || suggestionSeason.value;
});
</script>

<template>
    <main class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
        <section class="rounded-3xl bg-[#E9D9C9] p-6 sm:p-8">
            <p class="text-sm text-[#4D5145]">Tu armario, pero con ideas</p>
            <h2 class="mt-2 text-3xl font-semibold">Mis looks</h2>
            <p class="mt-2 text-sm text-[#4D5145]">Tantas prendas. Tan pocas decisiones.</p>
            <div class="mt-5 flex gap-2">
                <button class="rounded-full border border-[#4D5145] px-4 py-2 text-sm text-[#4D5145]" @click="create">Crear look</button>
                <button class="rounded-full bg-[#4D5145] px-4 py-2 text-sm text-white" @click="create">Sorpréndeme</button>
            </div>
        </section>
        <p v-if="message" class="mt-5 rounded-2xl bg-[#EADBD9] p-4 text-sm text-[#432D32]">{{ message }}</p>
        <section class="mt-8 rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-sm text-[#8E7472]">Estilista inteligente</p><h3 class="mt-1 text-xl font-semibold">Que decida alguien. Aunque sea un algoritmo.</h3></div>
                <div class="grid gap-2 sm:grid-cols-4">
                    <select v-model="suggestionOccasion" class="rounded-xl border border-[#E9D9C9] px-3 py-2 pr-8 text-sm"><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option></select>
                    <select v-model="suggestionStyle" class="rounded-xl border border-[#E9D9C9] px-3 py-2 pr-8 text-sm"><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option></select>
                    <input v-model="suggestionSeason" class="rounded-xl border border-[#E9D9C9] px-3 py-2 text-sm" placeholder="Temporada" />
                    <button class="rounded-xl bg-[#4D5145] px-4 py-2 text-sm text-white" @click="suggest">Sugerirme un look</button>
                    <button class="rounded-xl border border-[#E9D9C9] px-4 py-2 text-xs text-[#4D5145]" @click="savePreferences">Guardar preferencias</button>
                </div>
            </div>
            <p v-if="outfits.suggestionMessage" class="mt-4 text-sm text-[#8E857A]">{{ outfits.suggestionMessage }}</p>
            <p v-if="outfits.suggestionError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ outfits.suggestionError }}</p>
            <div v-for="suggestion in outfits.suggestions" :key="suggestion.explanation" class="mt-4 rounded-2xl bg-[#F8F5F0] p-4">
                <p class="text-sm">{{ suggestion.explanation }}</p>
                <p v-if="suggestion.reason" class="mt-2 text-xs text-[#8E7472]">{{ suggestion.reason }}</p>
                <div class="mt-3 space-y-3">
                    <div v-for="garment in suggestion.garments" :key="garment.id" class="rounded-xl bg-white p-3">
                        <span class="text-xs font-medium">{{ garment.name }}</span>
                        <div v-if="suggestion.alternatives?.[garment.id]?.length" class="mt-2 flex flex-wrap gap-2">
                            <button v-for="alternative in suggestion.alternatives[garment.id]" :key="alternative.id" class="rounded-full border border-[#E9D9C9] px-3 py-1 text-xs text-[#4D5145]" @click="replaceSuggestionGarment(suggestion, garment.id, alternative)">Cambiar por {{ alternative.name }}</button>
                        </div>
                    </div>
                </div>
                <button class="mt-4 rounded-xl border border-[#4D5145] px-4 py-2 text-sm text-[#4D5145]" @click="saveSuggestion(suggestion)">Guardar como look</button>
            </div>
        </section>
        <section class="mt-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-xl font-semibold">Looks guardados</h3>
                <div class="flex flex-wrap gap-2">
                    <select v-model="filterOccasion" class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-2 pr-8 text-sm" @change="outfits.loadOutfits(filterOccasion, filterStyle, filterSeason)">
                        <option value="">Todas las ocasiones</option><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option>
                    </select>
                    <select v-model="filterStyle" class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-2 pr-8 text-sm" @change="outfits.loadOutfits(filterOccasion, filterStyle, filterSeason)">
                        <option value="">Todos los estilos</option><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option>
                    </select>
                    <input v-model="filterSeason" class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-2 text-sm" placeholder="Temporada" @change="outfits.loadOutfits(filterOccasion, filterStyle, filterSeason)" />
                    <button class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-2 text-sm" @click="favoriteOnly = !favoriteOnly">♥ Favoritos</button>
                </div>
            </div>
            <p v-if="outfits.error" class="mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-700">{{ outfits.error }}</p>
            <p v-else-if="outfits.loading" class="mt-8 text-center text-sm text-[#B7A18D]">Cargando tus looks...</p>
            <div v-else-if="!visibleOutfits.length" class="mt-5 rounded-3xl bg-white p-10 text-center shadow-sm">
                <h3 class="text-lg font-semibold">Tantas prendas. Tan pocas decisiones.</h3>
                <p class="mt-2 text-sm text-[#8E857A]">Vamos a darle algo de sentido a tu armario.</p>
                <button class="mt-5 rounded-full bg-[#4D5145] px-5 py-3 text-sm text-white" @click="create">Crear mi primer look</button>
            </div>
            <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="outfit in visibleOutfits" :key="outfit.id" class="rounded-3xl bg-white p-4 shadow-sm">
                    <button class="w-full text-left" @click="selected = outfit">
                        <div class="grid grid-cols-3 gap-2"><div v-for="garment in outfit.garments" :key="garment.id" class="flex aspect-square items-center justify-center rounded-xl bg-[#E9D9C9]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full rounded-xl object-cover" /><span v-else class="text-xl text-[#B7A18D]">✦</span></div></div>
                        <h3 class="mt-4 truncate font-semibold">{{ outfit.name }}</h3>
                        <p class="mt-1 text-xs text-[#8E857A]">{{ outfit.occasion || 'Sin ocasión' }} · {{ outfit.style || 'Sin estilo' }}</p>
                    </button>
                    <div class="mt-3 flex justify-end gap-3 text-sm"><button @click="outfits.toggleFavorite(outfit)">{{ outfit.is_favorite ? '♥' : '♡' }}</button><button class="text-[#8E857A]" @click="duplicate(outfit)">Duplicar</button></div>
                </article>
            </div>
        </section>

        <div v-if="showForm" class="fixed inset-0 z-30 flex items-end justify-center bg-[#282724]/40 sm:items-center sm:p-6" @click.self="showForm = false">
            <section class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-2xl sm:rounded-3xl">
                <div class="flex justify-between"><h3 class="text-xl font-semibold">{{ editing ? 'Editar look' : 'Crear look' }}</h3><button @click="showForm = false">×</button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <input v-model="form.name" class="rounded-xl border border-[#E9D9C9] px-4 py-3 sm:col-span-2" placeholder="Nombre del look" />
                <select v-model="form.occasion" class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-3 pr-8"><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option></select>
                <select v-model="form.style" class="rounded-xl border border-[#E9D9C9] bg-white px-3 py-3 pr-8"><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option></select>
                    <input v-model="form.season" class="rounded-xl border border-[#E9D9C9] px-4 py-3" placeholder="Temporada (opcional)" />
                    <textarea v-model="form.notes" class="rounded-xl border border-[#E9D9C9] px-4 py-3" placeholder="Notas (opcional)" rows="2" />
                </div>
                <p class="mt-5 text-sm font-medium">Selecciona las prendas</p>
                <div class="mt-3 flex gap-2 overflow-x-auto pb-2"><button v-for="item in categories" :key="item.value" class="shrink-0 rounded-full border px-3 py-2 text-xs" :class="category === item.value ? 'border-[#4D5145] bg-[#4D5145] text-white' : 'border-[#E9D9C9]'" @click="category = item.value">{{ item.label }}</button></div>
                <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-5"><button v-for="garment in visibleGarments" :key="garment.id" class="overflow-hidden rounded-2xl border-2 text-left" :class="form.garment_ids.includes(garment.id) ? 'border-[#4D5145]' : 'border-transparent'" @click="toggleGarment(garment.id)"><div class="flex aspect-square items-center justify-center bg-[#E9D9C9]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" /><span v-else class="text-2xl text-[#B7A18D]">✦</span></div><p class="truncate p-2 text-xs">{{ garment.name }}</p></button></div>
                <p v-if="formError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ formError }}</p>
                <button class="mt-6 w-full rounded-2xl bg-[#4D5145] px-5 py-3 text-white disabled:opacity-50" :disabled="outfits.loading" @click="save">{{ outfits.loading ? 'Guardando...' : 'Guardar look' }}</button>
            </section>
        </div>

        <div v-if="selected" class="fixed inset-0 z-20 flex items-end justify-center bg-[#282724]/40 sm:items-center sm:p-6" @click.self="selected = null">
            <section class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-lg sm:rounded-3xl">
                <div class="flex justify-between"><h3 class="text-xl font-semibold">{{ selected.name }}</h3><button @click="selected = null">×</button></div>
                <p class="mt-2 text-sm text-[#8E857A]">{{ selected.occasion || 'Sin ocasión' }} · {{ selected.style || 'Sin estilo' }}</p>
                <p v-if="selected.notes" class="mt-4 text-sm">{{ selected.notes }}</p>
                <div class="mt-5 grid grid-cols-3 gap-3"><div v-for="garment in selected.garments" :key="garment.id"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="aspect-square w-full rounded-xl object-cover" /><p class="mt-1 truncate text-xs">{{ garment.name }}</p></div></div>
                <div class="mt-6 grid grid-cols-2 gap-3"><button class="rounded-xl border border-[#E9D9C9] px-3 py-3 text-sm" @click="edit(selected)">Editar</button><button class="rounded-xl border border-[#E9D9C9] px-3 py-3 text-sm" @click="duplicate(selected)">Duplicar</button><button class="rounded-xl border border-red-200 px-3 py-3 text-sm text-red-700" @click="remove(selected)">Eliminar</button></div>
            </section>
        </div>
    </main>
</template>
