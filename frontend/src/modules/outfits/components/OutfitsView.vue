+<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useOutfitStore } from '../store/outfitStore';
import { useWardrobeStore } from '../../wardrobe/store/wardrobeStore';
import { avatarService } from '../../auth/services/avatarService';
import { colorimetryService } from '../../colorimetry/services/colorimetryService';

const outfits = useOutfitStore();
const wardrobe = useWardrobeStore();
const showForm = ref(false);
const editing = ref(null);
const selected = ref(null);
const deleteCandidate = ref(null);
const favoriteOnly = ref(false);
const category = ref('');
const filterOccasion = ref('');
const filterStyle = ref('');
const filterSeason = ref('');
const suggestionOccasion = ref('diario');
const suggestionStyle = ref('casual');
const suggestionSeason = ref('');
const useColorimetry = ref(false);
const hasColorimetry = ref(false);
const message = ref('');
const avatarAvailable = ref(false);
const showTryOn = ref(false);
const tryOnOutfit = ref(null);
const garmentPositions = ref({});
const activeDrag = ref(null);
const selectedTryOnGarmentId = ref(null);
const avatarZones = ref(null);
const autoGarmentPositions = ref({});
const avatarDetectionMessage = ref('');
const avatarFrame = ref(null);
const exportBusy = ref(false);
const exportError = ref('');
const formError = ref('');
const form = reactive({ name: '', occasion: 'diario', style: 'casual', season: '', notes: '', garment_ids: [] });
const categories = [
    { label: 'Todas', value: '' }, { label: 'Superior', value: 'top' }, { label: 'Inferior', value: 'bottom' },
    { label: 'Vestidos', value: 'dress' }, { label: 'Abrigos', value: 'outerwear' }, { label: 'Zapatos', value: 'shoes' },
    { label: 'Accesorios', value: 'accessories' }
];
const visibleGarments = computed(() => category.value ? wardrobe.garments.filter((item) => item.category === category.value) : wardrobe.garments);
const selectedGarments = computed(() => wardrobe.garments.filter((item) => form.garment_ids.includes(item.id)));
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
const requestRemove = (outfit) => {
    deleteCandidate.value = outfit;
};
const confirmRemove = async () => {
    if (!deleteCandidate.value) return;
    await outfits.removeOutfit(deleteCandidate.value.id);
    if (selected.value?.id === deleteCandidate.value.id) selected.value = null;
    deleteCandidate.value = null;
};
const duplicate = async (outfit) => {
    await outfits.duplicateOutfit(outfit);
    message.value = 'Look duplicado. Porque una buena idea merece repetirse.';
    window.setTimeout(() => { message.value = ''; }, 4000);
};
const suggest = async () => {
    await outfits.loadSuggestions(suggestionOccasion.value, suggestionStyle.value, suggestionSeason.value, useColorimetry.value && hasColorimetry.value);
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
const openTryOn = async (outfit) => {
    try {
        const response = await avatarService.get();
        avatarAvailable.value = Boolean(response.data?.exists);
        if (!avatarAvailable.value) {
            message.value = 'Añade primero tu avatar desde Perfil para probar este look.';
            window.setTimeout(() => { message.value = ''; }, 4500);
            return;
        }
        tryOnOutfit.value = outfit;
        garmentPositions.value = Object.fromEntries(outfit.garments.map((garment, index) => [garment.id, defaultGarmentPosition(garment, index)]));
        autoGarmentPositions.value = {};
        selectedTryOnGarmentId.value = outfit.garments[0]?.id || null;
        avatarZones.value = null;
        avatarDetectionMessage.value = 'Ajustando posiciones iniciales al encuadre…';
        showTryOn.value = true;
    } catch (error) {
        message.value = error.message;
    }
};
const detectAvatarZones = (event) => {
    if (!tryOnOutfit.value || !avatarFrame.value) return;
    try {
        const image = event.target;
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 240 / Math.max(image.naturalWidth, image.naturalHeight));
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) return;
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        const { data } = context.getImageData(0, 0, canvas.width, canvas.height);
        const cornerIndices = [0, canvas.width - 1, (canvas.height - 1) * canvas.width, canvas.height * canvas.width - 1];
        const background = [0, 0, 0];
        cornerIndices.forEach((pixel) => {
            background[0] += data[pixel * 4];
            background[1] += data[pixel * 4 + 1];
            background[2] += data[pixel * 4 + 2];
        });
        const backgroundColor = background.map((channel) => channel / cornerIndices.length);
        let minX = canvas.width;
        let maxX = 0;
        let minY = canvas.height;
        let maxY = 0;
        let count = 0;
        const rowBounds = Array.from({ length: canvas.height }, () => ({ min: canvas.width, max: -1, count: 0 }));
        for (let pixel = 0; pixel < canvas.width * canvas.height; pixel += 1) {
            const offset = pixel * 4;
            const distance = Math.sqrt(((data[offset] - backgroundColor[0]) ** 2) + ((data[offset + 1] - backgroundColor[1]) ** 2) + ((data[offset + 2] - backgroundColor[2]) ** 2));
            if (distance < 52) continue;
            const x = pixel % canvas.width;
            const y = Math.floor(pixel / canvas.width);
            minX = Math.min(minX, x);
            maxX = Math.max(maxX, x);
            minY = Math.min(minY, y);
            maxY = Math.max(maxY, y);
            count += 1;
            rowBounds[y].min = Math.min(rowBounds[y].min, x);
            rowBounds[y].max = Math.max(rowBounds[y].max, x);
            rowBounds[y].count += 1;
        }
        if (count < canvas.width * canvas.height * 0.04 || maxY - minY < canvas.height * 0.45) {
            avatarDetectionMessage.value = 'No pude distinguir bien la silueta. Uso posiciones aproximadas; puedes ajustarlas manualmente.';
            return;
        }
        const bounds = avatarFrame.value.getBoundingClientRect();
        const imageScale = Math.min(bounds.width / image.naturalWidth, bounds.height / image.naturalHeight);
        const imageLeft = (bounds.width - image.naturalWidth * imageScale) / 2;
        const imageTop = (bounds.height - image.naturalHeight * imageScale) / 2;
        const originalScale = 1 / scale;
        const silhouetteZoneAt = (fraction) => {
            const targetY = Math.max(minY, Math.min(maxY, Math.round(minY + (maxY - minY) * fraction)));
            const windowRows = rowBounds.slice(Math.max(minY, targetY - 2), Math.min(maxY + 1, targetY + 3))
                .filter((row) => row.count >= 2)
                .sort((first, second) => (first.max - first.min) - (second.max - second.min));
            if (!windowRows.length) return { center: (minX + maxX) / 2, width: maxX - minX + 1 };
            const row = windowRows[Math.floor(windowRows.length / 2)];
            return { center: (row.min + row.max) / 2, width: row.max - row.min + 1 };
        };
        const left = imageLeft + minX * originalScale * imageScale;
        const right = imageLeft + maxX * originalScale * imageScale;
        const top = imageTop + minY * originalScale * imageScale;
        const bottom = imageTop + maxY * originalScale * imageScale;
        const toPercentX = (value) => value / bounds.width * 100;
        const toPercentY = (value) => value / bounds.height * 100;
        const center = (left + right) / 2;
        const height = bottom - top;
        const shouldersProfile = silhouetteZoneAt(0.22);
        const torsoProfile = silhouetteZoneAt(0.34);
        const hipsProfile = silhouetteZoneAt(0.59);
        const toFrameX = (sourceX) => imageLeft + sourceX * originalScale * imageScale;
        const profileWidth = (profile) => profile.width * originalScale * imageScale;
        avatarZones.value = {
            head: { x: center, y: top + height * 0.07 },
            shoulders: { x: toFrameX(shouldersProfile.center), y: top + height * 0.22 },
            torso: { x: toFrameX(torsoProfile.center), y: top + height * 0.34 },
            waist: { x: center, y: top + height * 0.52 },
            hips: { x: toFrameX(hipsProfile.center), y: top + height * 0.59 },
            legs: { x: center, y: top + height * 0.75 },
            feet: { x: center, y: bottom - height * 0.025 },
            width: right - left,
            torsoWidth: profileWidth(torsoProfile),
            hipWidth: profileWidth(hipsProfile),
            shoulderWidth: profileWidth(shouldersProfile),
            height,
            toPercentX,
            toPercentY
        };
        tryOnOutfit.value.garments.forEach((garment) => {
            const zone = avatarZones.value;
            const anchor = garment.category === 'accessories' ? zone.head
                : garment.category === 'shoes' ? zone.feet
                    : garment.category === 'bottom' ? zone.hips
                        : zone.shoulders;
            const widthRatio = garment.category === 'outerwear' ? 0.78 : garment.category === 'dress' ? 0.72 : garment.category === 'bottom' ? 0.66 : garment.category === 'shoes' ? 0.34 : garment.category === 'accessories' ? 0.30 : 0.64;
            const widthAtZone = garment.category === 'bottom' ? zone.hipWidth
                : garment.category === 'outerwear' ? zone.shoulderWidth
                    : garment.category === 'top' ? zone.torsoWidth
                        : zone.width;
            const position = garmentPositions.value[garment.id];
            position.x = Math.max(0, Math.min(90, zone.toPercentX(anchor.x) - position.width / 2));
            const topOffset = garment.category === 'accessories' ? zone.height * 0.025
                : garment.category === 'top' ? zone.height * 0.07
                    : garment.category === 'outerwear' ? zone.height * 0.075
                        : garment.category === 'dress' ? zone.height * 0.045
                            : garment.category === 'bottom' ? zone.height * 0.025
                                : zone.height * 0.015;
            position.y = Math.max(0, Math.min(94, zone.toPercentY(anchor.y - topOffset)));
            position.width = Math.max(14, Math.min(70, widthAtZone / bounds.width * 100 * widthRatio));
        });
        autoGarmentPositions.value = Object.fromEntries(Object.entries(garmentPositions.value).map(([id, position]) => [id, { ...position }]));
        avatarDetectionMessage.value = 'Posiciones iniciales estimadas desde la silueta. Puedes mover y ajustar cada prenda.';
    } catch {
        avatarDetectionMessage.value = 'No pude analizar la silueta. Uso posiciones aproximadas; puedes ajustarlas manualmente.';
    }
};
const openTryOnFromWardrobe = async (garment) => {
    const outfit = { id: `garment-${garment.id}`, name: `Prueba: ${garment.name}`, garments: [garment] };
    await openTryOn(outfit);
};
const defaultGarmentPosition = (garment, index) => {
    const positions = {
        top: { x: 34, y: 23, width: 32 },
        outerwear: { x: 31, y: 20, width: 38 },
        dress: { x: 29, y: 22, width: 42 },
        bottom: { x: 34, y: 46, width: 32 },
        shoes: { x: 39, y: 84, width: 22 },
        accessories: { x: 41, y: 13, width: 18 }
    };
    const categoryItems = tryOnOutfit.value?.garments.filter((item) => item.category === garment.category) || [];
    const categoryIndex = categoryItems.findIndex((item) => item.id === garment.id);
    const base = positions[garment.category] || { x: 34, y: 30, width: 32 };
    const offset = categoryIndex * 5;
    return { x: Math.min(90, base.x + offset), y: Math.min(94, base.y + offset), width: base.width, z: index + 1 };
};
const startGarmentDrag = (event, garment) => {
    if (!avatarFrame.value) return;
    event.preventDefault();
    selectedTryOnGarmentId.value = garment.id;
    event.currentTarget.setPointerCapture(event.pointerId);
    activeDrag.value = { id: garment.id, startX: event.clientX, startY: event.clientY, origin: { ...garmentPositions.value[garment.id] } };
};
const moveGarment = (event) => {
    if (!activeDrag.value || !avatarFrame.value) return;
    const bounds = avatarFrame.value.getBoundingClientRect();
    const position = garmentPositions.value[activeDrag.value.id];
    position.x = Math.min(90, Math.max(0, activeDrag.value.origin.x + ((event.clientX - activeDrag.value.startX) / bounds.width) * 100));
    position.y = Math.min(94, Math.max(0, activeDrag.value.origin.y + ((event.clientY - activeDrag.value.startY) / bounds.height) * 100));
};
const stopGarmentDrag = () => { activeDrag.value = null; };
const resizeGarment = (garment, event) => {
    garmentPositions.value[garment.id].width = Number(event.target.value);
};
const nudgeSelectedGarment = (axis, amount) => {
    const position = garmentPositions.value[selectedTryOnGarmentId.value];
    if (!position) return;
    const key = axis === 'x' ? 'x' : 'y';
    const limit = key === 'x' ? 90 : 94;
    position[key] = Math.min(limit, Math.max(0, position[key] + amount));
};
const resetTryOnPositions = () => {
    garmentPositions.value = Object.fromEntries(tryOnOutfit.value.garments.map((garment, index) => [
        garment.id,
        autoGarmentPositions.value[garment.id] || defaultGarmentPosition(garment, index)
    ]));
};
const saveTryOnImage = async () => {
    if (!avatarFrame.value || !tryOnOutfit.value) return;
    exportBusy.value = true;
    exportError.value = '';
    try {
        const bounds = avatarFrame.value.getBoundingClientRect();
        const scale = 2;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bounds.width * scale);
        canvas.height = Math.round(bounds.height * scale);
        const context = canvas.getContext('2d');
        context.fillStyle = '#FAF8F4';
        context.fillRect(0, 0, canvas.width, canvas.height);
        const avatarResponse = await fetch(avatarService.imageUrl());
        if (!avatarResponse.ok) throw new Error('No se pudo cargar tu avatar.');
        const avatarBlob = await avatarResponse.blob();
        const avatarObjectUrl = URL.createObjectURL(avatarBlob);
        try {
            const avatar = new Image();
            avatar.src = avatarObjectUrl;
            await avatar.decode();
            const avatarRect = getContainedImageRect(avatar, canvas.width, canvas.height);
            context.drawImage(avatar, avatarRect.x, avatarRect.y, avatarRect.width, avatarRect.height);
        } finally {
            URL.revokeObjectURL(avatarObjectUrl);
        }
        for (const garment of tryOnOutfit.value.garments) {
            if (!garment.image_path) continue;
            const position = garmentPositions.value[garment.id];
            const imageResponse = await fetch(wardrobe.imageUrl(garment.id));
            if (!imageResponse.ok) continue;
            const imageBlob = await imageResponse.blob();
            const imageUrl = URL.createObjectURL(imageBlob);
            try {
                const image = new Image();
                image.src = imageUrl;
                await image.decode();
                const overlay = getGarmentOverlayRect(image, position, bounds.width * scale, bounds.height * scale);
                context.drawImage(image, overlay.x, overlay.y, overlay.width, overlay.height);
            } finally {
                URL.revokeObjectURL(imageUrl);
            }
        }
        const link = document.createElement('a');
        link.download = `${tryOnOutfit.value.name.trim().toLowerCase().replace(/[^a-z0-9]+/gi, '-') || 'look'}-prueba.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) {
        exportError.value = error.message || 'No se pudo exportar la composición.';
    } finally {
        exportBusy.value = false;
    }
};
const getContainedImageRect = (image, containerWidth, containerHeight) => {
    const ratio = Math.min(containerWidth / image.naturalWidth, containerHeight / image.naturalHeight);
    const width = image.naturalWidth * ratio;
    const height = image.naturalHeight * ratio;
    return { x: (containerWidth - width) / 2, y: (containerHeight - height) / 2, width, height };
};
const getGarmentOverlayRect = (image, position, containerWidth, containerHeight) => {
    const cssWidth = position.width / 100 * containerWidth;
    const cssHeight = Math.min(176, cssWidth * image.naturalHeight / image.naturalWidth);
    return {
        x: position.x / 100 * containerWidth,
        y: position.y / 100 * containerHeight,
        width: cssWidth,
        height: cssHeight
    };
};
onMounted(async () => {
    outfits.loadOutfits();
    if (!wardrobe.garments.length) wardrobe.loadGarments();
    await outfits.loadPreferences();
    try {
        hasColorimetry.value = Boolean((await colorimetryService.get()).data?.result?.palette);
    } catch {
        hasColorimetry.value = false;
    }
    suggestionStyle.value = outfits.preferences.preferred_style || suggestionStyle.value;
    suggestionSeason.value = outfits.preferences.preferred_season || suggestionSeason.value;
});

defineExpose({ openTryOnFromWardrobe });
</script>

<template>
    <main class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
        <section class="rounded-3xl bg-[var(--color-border)] p-6 sm:p-8">
            <p class="text-sm text-[var(--color-primary)]">Tu armario, pero con ideas</p>
            <h2 class="mt-2 text-3xl font-semibold">Mis looks</h2>
            <p class="mt-2 text-sm text-[var(--color-primary)]">Tantas prendas. Tan pocas decisiones.</p>
            <div class="mt-5 flex gap-2">
                <button class="rounded-full border border-[var(--color-primary)] px-4 py-2 text-sm text-[var(--color-primary)]" @click="create">Crear look</button>
                <button class="rounded-full bg-[var(--color-primary)] px-4 py-2 text-sm text-white" @click="create">Sorpréndeme</button>
            </div>
        </section>
        <p v-if="message" class="mt-5 rounded-2xl bg-[var(--color-danger-border)] p-4 text-sm text-[var(--color-danger-text)]">{{ message }}</p>
        <section class="mt-8 rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-sm text-[var(--color-neutral-text)]">Estilista inteligente</p><h3 class="mt-1 text-xl font-semibold">Que decida alguien. Aunque sea un algoritmo.</h3></div>
                <div class="grid gap-2 sm:grid-cols-4">
                    <select v-model="suggestionOccasion" class="rounded-xl border border-[var(--color-border)] px-3 py-2 pr-8 text-sm"><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option></select>
                    <select v-model="suggestionStyle" class="rounded-xl border border-[var(--color-border)] px-3 py-2 pr-8 text-sm"><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option></select>
                    <select v-model="suggestionSeason" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 pr-8 text-sm"><option value="">Cualquier temporada</option><option>Primavera</option><option>Verano</option><option>Otoño</option><option>Invierno</option></select>
                    <button class="rounded-xl bg-[var(--color-primary)] px-4 py-2 text-sm text-white" @click="suggest">Sugerirme un look</button>
                    <button class="rounded-xl border border-[var(--color-border)] px-4 py-2 text-xs text-[var(--color-primary)]" @click="savePreferences">Guardar preferencias</button>
                    <label class="flex items-center gap-2 rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 text-xs sm:col-span-4" :class="hasColorimetry ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-subtle)]'"><input v-model="useColorimetry" type="checkbox" class="accent-[var(--color-primary)]" :disabled="!hasColorimetry" />Priorizar colores de mi colorimetría <span v-if="!hasColorimetry">(configúrala en Perfil)</span></label>
                </div>
            </div>
            <p v-if="outfits.suggestionMessage" class="mt-4 text-sm text-[var(--color-text-subtle)]">{{ outfits.suggestionMessage }}</p>
            <p v-if="outfits.suggestionError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ outfits.suggestionError }}</p>
            <div v-for="suggestion in outfits.suggestions" :key="suggestion.explanation" class="mt-4 rounded-2xl bg-[var(--color-canvas)] p-4">
                <p class="text-sm">{{ suggestion.explanation }}</p>
                <p v-if="suggestion.reason" class="mt-2 text-xs text-[var(--color-neutral-text)]">{{ suggestion.reason }}</p>
                <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <div v-for="garment in suggestion.garments" :key="garment.id" class="min-w-0">
                        <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-[var(--color-border)]">
                            <img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" />
                            <span v-else class="text-xl text-[var(--color-accent)]" aria-hidden="true">✦</span>
                        </div>
                        <p class="mt-1 truncate text-xs font-medium">{{ garment.name }}</p>
                        <div v-if="suggestion.alternatives?.[garment.id]?.length" class="mt-2 flex flex-wrap gap-2">
                            <button v-for="alternative in suggestion.alternatives[garment.id]" :key="alternative.id" class="rounded-full border border-[var(--color-border)] px-2 py-1 text-[11px] text-[var(--color-primary)]" @click="replaceSuggestionGarment(suggestion, garment.id, alternative)">Cambiar</button>
                        </div>
                    </div>
                </div>
                <button class="mt-4 rounded-xl border border-[var(--color-primary)] px-4 py-2 text-sm text-[var(--color-primary)]" @click="saveSuggestion(suggestion)">Guardar como look</button>
            </div>
        </section>
        <section class="mt-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-xl font-semibold">Looks guardados</h3>
                <div class="flex flex-wrap gap-2">
                    <select v-model="filterOccasion" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 pr-8 text-sm" @change="outfits.loadOutfits(filterOccasion, filterStyle, filterSeason)">
                        <option value="">Todas las ocasiones</option><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option>
                    </select>
                    <select v-model="filterStyle" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 pr-8 text-sm" @change="outfits.loadOutfits(filterOccasion, filterStyle, filterSeason)">
                        <option value="">Todos los estilos</option><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option>
                    </select>
                    <select v-model="filterSeason" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 pr-8 text-sm" @change="outfits.loadOutfits(filterOccasion, filterStyle, filterSeason)"><option value="">Todas las temporadas</option><option>Primavera</option><option>Verano</option><option>Otoño</option><option>Invierno</option></select>
                    <button class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 text-sm" @click="favoriteOnly = !favoriteOnly">♥ Favoritos</button>
                </div>
            </div>
            <p v-if="outfits.error" class="mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-700">{{ outfits.error }}</p>
            <p v-else-if="outfits.loading" class="mt-8 text-center text-sm text-[var(--color-accent)]">Cargando tus looks...</p>
            <div v-else-if="!visibleOutfits.length" class="mt-5 rounded-3xl bg-white p-10 text-center shadow-sm">
                <h3 class="text-lg font-semibold">Tantas prendas. Tan pocas decisiones.</h3>
                <p class="mt-2 text-sm text-[var(--color-text-subtle)]">Vamos a darle algo de sentido a tu armario.</p>
                <button class="mt-5 rounded-full bg-[var(--color-primary)] px-5 py-3 text-sm text-white" @click="create">Crear mi primer look</button>
            </div>
            <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="outfit in visibleOutfits" :key="outfit.id" class="rounded-3xl bg-white p-4 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <button class="w-full text-left" @click="selected = outfit">
                        <div class="grid grid-cols-3 gap-2"><div v-for="garment in outfit.garments" :key="garment.id" class="flex aspect-square items-center justify-center rounded-xl bg-[var(--color-border)]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full rounded-xl object-cover" /><span v-else class="text-xl text-[var(--color-accent)]">✦</span></div></div>
                        <h3 class="mt-4 truncate font-semibold">{{ outfit.name }}</h3>
                        <p class="mt-1 text-xs text-[var(--color-text-subtle)]">{{ outfit.occasion || 'Sin ocasión' }} · {{ outfit.style || 'Sin estilo' }}</p>
                    </button>
                    <div class="mt-3 flex items-center justify-between gap-3 text-sm"><button class="rounded-full px-3 py-1 text-[var(--color-primary)] transition hover:bg-[var(--color-surface-muted)]" @click="outfits.toggleFavorite(outfit)">{{ outfit.is_favorite ? '♥ Favorito' : '♡ Favorito' }}</button><button class="rounded-full px-3 py-1 text-[var(--color-text-subtle)] transition hover:bg-[var(--color-surface-muted)]" @click="duplicate(outfit)">Duplicar</button></div>
                </article>
            </div>
        </section>

        <div v-if="showForm" class="fixed inset-0 z-30 flex items-end justify-center bg-[var(--color-text)]/40 sm:items-center sm:p-6" @click.self="showForm = false">
            <section class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-2xl sm:rounded-3xl">
                <div class="flex justify-between"><h3 class="text-xl font-semibold">{{ editing ? 'Editar look' : 'Crear look' }}</h3><button @click="showForm = false">×</button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <input v-model="form.name" class="rounded-xl border border-[var(--color-border)] px-4 py-3 sm:col-span-2" placeholder="Nombre del look" />
                <select v-model="form.occasion" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8"><option>diario</option><option>oficina</option><option>evento</option><option>viaje</option></select>
                <select v-model="form.style" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8"><option>casual</option><option>elegante</option><option>minimalista</option><option>cómodo</option></select>
                    <select v-model="form.season" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8"><option value="">Temporada (opcional)</option><option>Primavera</option><option>Verano</option><option>Otoño</option><option>Invierno</option></select>
                    <textarea v-model="form.notes" class="rounded-xl border border-[var(--color-border)] px-4 py-3" placeholder="Notas (opcional)" rows="2" />
                </div>
                <div class="mt-5 flex items-center justify-between"><p class="text-sm font-medium">Selecciona las prendas</p><span class="rounded-full bg-[var(--color-surface-muted)] px-3 py-1 text-xs text-[var(--color-text-muted)]">{{ form.garment_ids.length }} seleccionadas</span></div>
                <div class="mt-3 flex gap-2 overflow-x-auto pb-2"><button v-for="item in categories" :key="item.value" class="shrink-0 rounded-full border px-3 py-2 text-xs" :class="category === item.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)]'" @click="category = item.value">{{ item.label }}</button></div>
                <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-5"><button v-for="garment in visibleGarments" :key="garment.id" class="overflow-hidden rounded-2xl border-2 text-left" :class="form.garment_ids.includes(garment.id) ? 'border-[var(--color-primary)]' : 'border-transparent'" @click="toggleGarment(garment.id)"><div class="flex aspect-square items-center justify-center bg-[var(--color-border)]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" /><span v-else class="text-2xl text-[var(--color-accent)]">✦</span></div><p class="truncate p-2 text-xs">{{ garment.name }}</p></button></div>
                <div v-if="selectedGarments.length" class="mt-5 rounded-2xl bg-[var(--color-canvas)] p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-text-subtle)]">Así va quedando tu look</p>
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        <div v-for="garment in selectedGarments" :key="garment.id" class="w-20 shrink-0">
                            <div class="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-[var(--color-border)]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" /><span v-else class="text-xl text-[var(--color-accent)]" aria-hidden="true">✦</span></div>
                            <p class="mt-1 truncate text-xs text-[var(--color-text-muted)]">{{ garment.name }}</p>
                        </div>
                    </div>
                </div>
                <p v-if="formError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ formError }}</p>
                <button class="mt-6 w-full rounded-2xl bg-[var(--color-primary)] px-5 py-3 text-white disabled:opacity-50" :disabled="outfits.loading" @click="save">{{ outfits.loading ? 'Guardando...' : 'Guardar look' }}</button>
            </section>
        </div>

        <div v-if="selected" class="fixed inset-0 z-20 flex items-end justify-center bg-[var(--color-text)]/40 sm:items-center sm:p-6" @click.self="selected = null">
            <section class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-lg sm:rounded-3xl">
                <div class="flex items-start justify-between gap-4"><div><h3 class="text-xl font-semibold">{{ selected.name }}</h3><p class="mt-2 text-sm text-[var(--color-text-subtle)]">{{ selected.occasion || 'Sin ocasión' }} · {{ selected.style || 'Sin estilo' }}</p></div><button class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--color-surface-muted)] text-lg text-[var(--color-primary)]" aria-label="Cerrar detalle" @click="selected = null">×</button></div>
                <p v-if="selected.notes" class="mt-4 text-sm">{{ selected.notes }}</p>
                <div class="mt-5 grid grid-cols-2 gap-3"><div v-for="garment in selected.garments" :key="garment.id"><div class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-[var(--color-border)]"><img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" /><span v-else class="text-2xl text-[var(--color-accent)]">✦</span></div><p class="mt-2 truncate text-xs font-medium">{{ garment.name }}</p><p class="mt-1 truncate text-[11px] text-[var(--color-text-subtle)]">{{ garment.primary_color }}</p></div></div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2"><button class="rounded-xl bg-[var(--color-primary)] px-3 py-3 text-sm font-medium text-white" @click="openTryOn(selected)">Probar con mi avatar</button><button class="rounded-xl border border-[var(--color-border)] px-3 py-3 text-sm" @click="edit(selected)">Editar look</button><button class="rounded-xl border border-[var(--color-border)] px-3 py-3 text-sm" @click="duplicate(selected)">Duplicar look</button><button class="rounded-xl border border-red-200 px-3 py-3 text-sm text-red-700 sm:col-span-2" @click="requestRemove(selected)">Eliminar look</button></div>
            </section>
        </div>
        <div v-if="showTryOn && tryOnOutfit" class="fixed inset-0 z-40 flex items-end justify-center bg-[var(--color-text)]/50 sm:items-center sm:p-6" @click.self="showTryOn = false">
            <section class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 sm:max-w-3xl sm:rounded-3xl sm:p-7">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-[var(--color-primary)]">Prueba visual</p><h3 class="mt-1 text-xl font-semibold">{{ tryOnOutfit.name }}</h3></div><button aria-label="Cerrar prueba visual" class="rounded-full bg-[var(--color-surface-muted)] px-3 py-1 text-lg" @click="showTryOn = false">×</button></div>
                <p class="mt-2 text-sm text-[var(--color-text-muted)]">Selecciona una prenda y muévela con los controles o arrástrala sobre tu foto. La colocación es manual y aproximada.</p>
                <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <div ref="avatarFrame" class="relative mx-auto aspect-[3/5] max-h-[70vh] w-full max-w-sm touch-none overflow-hidden rounded-2xl bg-[var(--color-canvas)]" @pointermove="moveGarment" @pointerup="stopGarmentDrag" @pointercancel="stopGarmentDrag">
                        <img :src="avatarService.imageUrl()" alt="Tu avatar privado" class="absolute inset-0 h-full w-full object-contain" draggable="false" @load="detectAvatarZones" />
                        <div v-for="(garment, index) in [...tryOnOutfit.garments].sort((a, b) => (garmentPositions[a.id]?.z || 0) - (garmentPositions[b.id]?.z || 0))" :key="garment.id" :data-garment-id="garment.id" class="absolute cursor-move select-none touch-none" :class="selectedTryOnGarmentId === garment.id ? 'outline outline-2 outline-offset-2 outline-[var(--color-primary)]' : ''" :style="{ left: `${garmentPositions[garment.id]?.x}%`, top: `${garmentPositions[garment.id]?.y}%`, width: `${garmentPositions[garment.id]?.width}%`, zIndex: garmentPositions[garment.id]?.z }" @pointerdown="startGarmentDrag($event, garment)">
                            <img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="w-full object-contain drop-shadow-lg" :style="{ maxHeight: '11rem' }" draggable="false" />
                            <div v-else class="flex aspect-[4/5] items-center justify-center rounded-xl border-2 border-dashed border-[var(--color-primary)] bg-white/80 p-2 text-center text-xs font-medium">{{ garment.name }}</div>
                            <span class="pointer-events-none absolute inset-0 rounded border border-white/80 shadow-[0_0_0_1px_rgba(0,0,0,0.18)]"></span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <p v-if="avatarDetectionMessage" class="rounded-xl bg-[var(--color-surface-muted)] p-3 text-xs leading-5 text-[var(--color-text-muted)]" role="status">{{ avatarDetectionMessage }}</p>
                        <button class="w-full rounded-xl bg-[var(--color-primary)] px-3 py-3 text-sm font-medium text-white disabled:opacity-50" :disabled="exportBusy" @click="saveTryOnImage">{{ exportBusy ? 'Preparando imagen…' : 'Guardar composición PNG' }}</button>
                        <p v-if="exportError" role="alert" class="rounded-lg bg-[var(--color-danger-surface)] p-2 text-xs text-[var(--color-danger-text)]">{{ exportError }}</p>
                        <article v-for="garment in tryOnOutfit.garments" :key="garment.id" class="rounded-xl border p-3" :class="selectedTryOnGarmentId === garment.id ? 'border-[var(--color-primary)] bg-[var(--color-surface-muted)]' : 'border-[var(--color-border)]'">
                            <button class="min-h-11 w-full truncate text-left text-sm font-semibold" :aria-pressed="selectedTryOnGarmentId === garment.id" @click="selectedTryOnGarmentId = garment.id">{{ garment.name }} <span class="text-xs font-normal text-[var(--color-text-subtle)]">· Tocar para ajustar</span></button>
                            <div class="mt-2 grid grid-cols-3 gap-2" aria-label="Mover prenda">
                                <span></span><button class="min-h-11 rounded-lg border border-[var(--color-border)] bg-white text-lg" aria-label="Mover arriba" @click="selectedTryOnGarmentId = garment.id; nudgeSelectedGarment('y', -2)">↑</button><span></span>
                                <button class="min-h-11 rounded-lg border border-[var(--color-border)] bg-white text-lg" aria-label="Mover a la izquierda" @click="selectedTryOnGarmentId = garment.id; nudgeSelectedGarment('x', -2)">←</button><button class="min-h-11 rounded-lg border border-[var(--color-border)] bg-white text-xs" aria-label="Centrar prenda" @click="garmentPositions[garment.id] = defaultGarmentPosition(garment, tryOnOutfit.garments.indexOf(garment))">Centro</button><button class="min-h-11 rounded-lg border border-[var(--color-border)] bg-white text-lg" aria-label="Mover a la derecha" @click="selectedTryOnGarmentId = garment.id; nudgeSelectedGarment('x', 2)">→</button>
                                <span></span><button class="min-h-11 rounded-lg border border-[var(--color-border)] bg-white text-lg" aria-label="Mover abajo" @click="selectedTryOnGarmentId = garment.id; nudgeSelectedGarment('y', 2)">↓</button><span></span>
                            </div>
                            <label class="mt-3 block text-xs font-medium text-[var(--color-text-muted)]">Tamaño <input type="range" min="12" max="70" :value="garmentPositions[garment.id]?.width" class="mt-2 min-h-11 w-full accent-[var(--color-primary)]" @focus="selectedTryOnGarmentId = garment.id" @input="resizeGarment(garment, $event)" /></label>
                        </article>
                        <button class="w-full rounded-xl border border-[var(--color-border)] px-3 py-2 text-xs text-[var(--color-primary)]" @click="resetTryOnPositions">Restablecer posiciones</button>
                    </div>
                </div>
            </section>
        </div>
        <div v-if="deleteCandidate" class="fixed inset-0 z-40 flex items-center justify-center bg-[var(--color-text)]/40 p-5" @click.self="deleteCandidate = null">
            <section class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold">¿Eliminar este look?</h3>
                <p class="mt-2 text-sm leading-6 text-[var(--color-text-muted)]">“{{ deleteCandidate.name }}” se eliminará de tus looks guardados. Esta acción no se puede deshacer.</p>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button class="rounded-xl border border-[var(--color-border)] px-4 py-3 text-sm font-medium" @click="deleteCandidate = null">Cancelar</button>
                    <button class="rounded-xl bg-red-700 px-4 py-3 text-sm font-medium text-white" @click="confirmRemove">Eliminar</button>
                </div>
            </section>
        </div>
    </main>
</template>
