<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { colorimetryService } from '../services/colorimetryService';

const imageFile = ref(null);
const previewUrl = ref('');
const result = ref(null);
const error = ref('');
const analyzing = ref(false);
const savedImageUrl = ref('');
const skinSamples = ref([]);
const saving = ref(false);
const saved = ref(false);

const palette = {
    warm: ['#A64E3C', '#D58B55', '#D8B65C', '#6D8054', '#2F625A', '#8C514B'],
    cool: ['#6D7896', '#788DA8', '#658A83', '#8B7189', '#A96878', '#525E78'],
    neutral: ['#8A7867', '#B49B7D', '#879078', '#7E8585', '#A08078', '#655E58']
};

const season = computed(() => result.value?.season || '');
const recommendations = computed(() => result.value ? palette[result.value.palette] : []);

const loadSavedAnalysis = async () => {
    try {
        const response = await colorimetryService.get();
        if (response.data) {
            savedImageUrl.value = colorimetryService.imageUrl();
            result.value = response.data.result;
            saved.value = true;
        }
    } catch (loadError) {
        error.value = loadError.message;
    }
};

onMounted(loadSavedAnalysis);

const chooseImage = (event) => {
    const file = event.target.files?.[0];
    error.value = '';
    result.value = null;
    saved.value = false;
    savedImageUrl.value = '';
    imageFile.value = null;
    skinSamples.value = [];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        error.value = 'Elige una imagen JPG, PNG o WEBP de hasta 5 MB.';
        event.target.value = '';
        return;
    }
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    imageFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
};

const recordSample = (event) => {
    if (skinSamples.value.length >= 3) return;
    const image = event.currentTarget;
    const bounds = image.getBoundingClientRect();
    const scale = Math.min(bounds.width / image.naturalWidth, bounds.height / image.naturalHeight);
    const drawnWidth = image.naturalWidth * scale;
    const drawnHeight = image.naturalHeight * scale;
    const offsetX = (bounds.width - drawnWidth) / 2;
    const offsetY = (bounds.height - drawnHeight) / 2;
    const x = (event.clientX - bounds.left - offsetX) / drawnWidth;
    const y = (event.clientY - bounds.top - offsetY) / drawnHeight;
    if (x < 0 || x > 1 || y < 0 || y > 1) return;
    const canvas = document.createElement('canvas');
    canvas.width = image.naturalWidth;
    canvas.height = image.naturalHeight;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(image, 0, 0);
    const centerX = Math.round(x * canvas.width);
    const centerY = Math.round(y * canvas.height);
    const samples = [];
    const region = context.getImageData(Math.max(0, centerX - 4), Math.max(0, centerY - 4), Math.min(9, canvas.width - Math.max(0, centerX - 4)), Math.min(9, canvas.height - Math.max(0, centerY - 4))).data;
    for (let index = 0; index < region.length; index += 4) {
            const pixel = region.slice(index, index + 4);
            if (pixel[0] > 25 && pixel[1] > 15 && pixel[2] > 10 && pixel[0] < 250 && pixel[1] < 245 && pixel[2] < 240) samples.push([pixel[0], pixel[1], pixel[2]]);
    }
    if (samples.length < 10) {
        error.value = 'Ese punto parece demasiado oscuro o claro. Elige una zona de piel sin sombras.';
        return;
    }
    const values = samples[0].map((_, channel) => samples.map((sample) => sample[channel]).sort((a, b) => a - b)[Math.floor(samples.length / 2)]);
    skinSamples.value.push(values);
    error.value = '';
};

const finishAnalysis = async () => {
    if (!imageFile.value || skinSamples.value.length < 2) return;
    saving.value = true;
    error.value = '';
    const samples = skinSamples.value;
    const warmthScores = samples.map(([red, green, blue]) => (red - blue) / Math.max(1, red + green + blue) * 255);
    const warmth = warmthScores.reduce((sum, value) => sum + value, 0) / warmthScores.length;
    const spread = Math.max(...warmthScores) - Math.min(...warmthScores);
    const paletteKey = spread > 24 ? 'neutral' : warmth > 9 ? 'warm' : warmth < -5 ? 'cool' : 'neutral';
    const lightness = samples.reduce((sum, [red, green, blue]) => sum + (red + green + blue) / 3, 0) / samples.length;
    const season = paletteKey === 'warm' ? (lightness > 155 ? 'Primavera cálida' : 'Otoño cálido') : paletteKey === 'cool' ? (lightness > 155 ? 'Verano suave' : 'Invierno frío') : 'Paleta neutra';
    const nextResult = { palette: paletteKey, season, confidence: spread > 24 ? 'baja' : 'orientativa', samples: samples.length };
    try {
        await colorimetryService.save(imageFile.value, nextResult);
        result.value = nextResult;
        saved.value = true;
        savedImageUrl.value = colorimetryService.imageUrl();
    } catch (saveError) {
        error.value = saveError.message;
    } finally {
        saving.value = false;
    }
};

const deleteSaved = async () => {
    try {
        await colorimetryService.remove();
        saved.value = false;
        savedImageUrl.value = '';
        result.value = null;
        imageFile.value = null;
        if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = '';
    } catch (deleteError) {
        error.value = deleteError.message;
    }
};

onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});
</script>

<template>
    <main class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
        <section class="rounded-3xl bg-[var(--color-surface-muted)] p-6 sm:p-9">
            <p class="text-sm font-medium text-[var(--color-primary)]">Colorimetría personal</p>
            <h2 class="mt-2 max-w-2xl text-3xl font-semibold leading-tight sm:text-4xl">Descubre los tonos que acompañan tu belleza natural.</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-[var(--color-text-muted)]">Sube una foto con luz natural y sin filtros. Se guardará en tu cuenta para que puedas consultar o eliminar tu análisis cuando quieras.</p>
        </section>

        <section class="mt-8 grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <div class="rounded-3xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold">Tu fotografía</h3>
                <p class="mt-1 text-sm text-[var(--color-text-muted)]">Frontal, sin maquillaje, con luz natural indirecta y fondo neutro. Marca tres puntos de piel: frente y ambas mejillas.</p>
                <label for="colorimetry-photo" class="mt-5 flex min-h-64 cursor-pointer items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-[var(--color-border)] bg-[var(--color-upload-surface)] text-center transition hover:bg-[var(--color-upload-hover)]">
                    <img v-if="previewUrl" :src="previewUrl" alt="Vista previa de tu fotografía" class="h-80 w-full object-contain" :class="analyzing ? 'cursor-crosshair' : ''" @click.prevent="analyzing && recordSample($event)" />
                    <img v-else-if="savedImageUrl" :src="savedImageUrl" alt="Fotografía guardada de tu análisis" class="h-80 w-full object-contain" />
                    <span v-else class="px-6 text-sm font-medium text-[var(--color-primary)]">＋ Seleccionar una foto</span>
                    <input id="colorimetry-photo" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseImage" />
                </label>
                <p v-if="error" role="alert" class="mt-4 rounded-xl bg-[var(--color-danger-surface)] p-3 text-sm text-[var(--color-danger-text)]">{{ error }}</p>
                <button v-if="previewUrl && !analyzing" class="mt-5 w-full rounded-2xl border border-[var(--color-border)] px-5 py-3 text-sm font-medium text-[var(--color-primary)]" @click="analyzing = true; skinSamples = []">Iniciar análisis guiado</button>
                <template v-if="analyzing">
                    <p class="mt-4 text-sm text-[var(--color-text-muted)]">Pulsa sobre dos o tres zonas de piel iluminadas para tomar muestras. Muestras: {{ skinSamples.length }}/3.</p>
                    <div class="mt-3 flex gap-2"><button v-for="(sample, index) in skinSamples" :key="index" class="h-9 w-9 rounded-full border border-black/10" :style="{ backgroundColor: `rgb(${sample.join(',')})` }" :aria-label="`Muestra ${index + 1}`" @click="skinSamples.splice(index, 1)"></button></div>
                    <button class="mt-4 w-full rounded-2xl bg-[var(--color-primary)] px-5 py-3 font-medium text-white disabled:opacity-50" :disabled="skinSamples.length < 2 || saving" @click="finishAnalysis">{{ saving ? 'Guardando foto y análisis…' : 'Calcular y guardar paleta' }}</button>
                </template>
                <button v-else-if="!previewUrl && saved" class="mt-5 w-full rounded-2xl border border-[var(--color-danger-border)] px-5 py-3 text-sm font-medium text-[var(--color-danger-text)]" @click="deleteSaved">Eliminar foto y análisis</button>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm">
                <template v-if="result">
                    <p class="text-sm font-medium text-[var(--color-accent)]">Tu resultado orientativo</p>
                    <h3 class="mt-2 text-2xl font-semibold">{{ season }}</h3>
                    <p class="mt-2 text-sm leading-6 text-[var(--color-text-muted)]">Estimación {{ result.confidence }} basada en {{ result.samples }} muestras de piel. La luz, el maquillaje y la cámara pueden alterar los tonos.</p>
                    <h4 class="mt-7 text-sm font-semibold">Tonos para explorar</h4>
                    <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-6">
                        <div v-for="color in recommendations" :key="color" class="text-center"><div class="aspect-square rounded-2xl border border-black/5" :style="{ backgroundColor: color }"></div><span class="mt-1 block text-[10px] text-[var(--color-text-subtle)]">{{ color }}</span></div>
                    </div>
                    <p class="mt-6 rounded-2xl bg-[var(--color-surface-muted)] p-4 text-xs leading-5 text-[var(--color-text-muted)]">Es una orientación visual, no un análisis profesional. Para más precisión, toma muestras con luz diurna indirecta, sin filtros ni maquillaje y con la exposición de cámara equilibrada.</p>
                </template>
                <div v-else class="flex min-h-72 flex-col items-center justify-center text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-surface-muted)] text-2xl text-[var(--color-accent)]" aria-hidden="true">✦</span>
                    <h3 class="mt-4 text-lg font-semibold">Tu paleta aparecerá aquí</h3>
                    <p class="mt-2 max-w-sm text-sm leading-6 text-[var(--color-text-muted)]">Después podrás probar estos tonos con las prendas de tu armario.</p>
                </div>
            </div>
        </section>
    </main>
</template>
