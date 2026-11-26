<script setup>
import { reactive, ref } from 'vue';
import { garmentService } from '../services/garmentService';
import { useWardrobeStore } from '../store/wardrobeStore';

const props = defineProps({ garment: { type: Object, default: null } });
const emit = defineEmits(['saved', 'cancel']);
const wardrobe = useWardrobeStore();
const wardrobeImageUrl = (id) => wardrobe.imageUrl(id);
const keepAdding = ref(false);
const bulkMode = ref(false);
const bulkCategory = ref('top');
const bulkImages = ref([]);
const bulkInput = ref(null);
const bulkCreated = ref([]);
const bulkReviewForms = ref({});
const bulkSavedIds = ref([]);
const bulkSaveErrors = ref({});
const savingBulkDetails = ref(false);
const selectedBulkIds = ref([]);
const bulkDefaults = reactive({ season: '', style: '', brand: '' });
const loading = ref(false);
const error = ref('');
const success = ref('');
const image = ref(null);
const preview = ref('');
const seasons = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
const styles = ['Casual', 'Elegante', 'Minimalista', 'Deportivo', 'Bohemio', 'Formal'];
const form = reactive({ name: props.garment?.name || '', category: props.garment?.category || 'top', primary_color: props.garment?.primary_color || '', season: props.garment?.season || '', style: props.garment?.style || '', brand: props.garment?.brand || '', notes: props.garment?.notes || '' });

const resetForm = ({ category = 'top', style = '' } = {}) => {
    Object.assign(form, { name: '', category, primary_color: '', season: '', style, brand: '', notes: '' });
    image.value = null;
    preview.value = '';
    const imageInput = document.getElementById('garment-image');
    if (imageInput) imageInput.value = '';
};

const submit = async () => {
    error.value = '';
    success.value = '';
    if (!form.name.trim()) {
        error.value = 'El nombre de la prenda es obligatorio.';
        return;
    }
    const addAnother = keepAdding.value;
    keepAdding.value = false;
    loading.value = true;
    try {
        const data = new FormData();
        Object.entries(form).forEach(([key, value]) => data.append(key, typeof value === 'string' ? value.trim() : value));
        if (image.value) data.append('image', image.value);
        if (props.garment) {
            await garmentService.update(props.garment.id, form);
        } else {
            await garmentService.create(data);
        }
        emit('saved');
        if (!props.garment) {
            if (addAnother) {
                resetForm({ category: form.category, style: form.style });
                success.value = 'Prenda añadida. Puedes añadir otra.';
            } else {
                emit('cancel');
            }
        }
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
};

const submitBulk = async () => {
    error.value = '';
    success.value = '';
    if (!bulkImages.value.length) {
        error.value = 'Selecciona al menos una fotografía.';
        return;
    }
    loading.value = true;
    try {
        const response = await garmentService.createMany(bulkCategory.value, bulkImages.value);
        bulkCreated.value = response.data || [];
        bulkReviewForms.value = Object.fromEntries(bulkCreated.value.map((garment) => [garment.id, {
            name: garment.name,
            primary_color: garment.primary_color || '',
            season: garment.season || '',
            style: garment.style || '',
            brand: garment.brand || ''
        }]));
        bulkSavedIds.value = [];
        selectedBulkIds.value = [];
        bulkDefaults.season = '';
        bulkDefaults.style = '';
        bulkDefaults.brand = '';
        emit('saved');
        success.value = '';
        bulkImages.value = [];
        if (bulkInput.value) bulkInput.value.value = '';
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
};

const saveBulkDetails = async (garment) => {
    if (!bulkReviewForms.value[garment.id].name.trim()) {
        bulkSaveErrors.value[garment.id] = 'Escribe un nombre para esta prenda.';
        return;
    }
    try {
        const updated = await garmentService.update(garment.id, { ...bulkReviewForms.value[garment.id], category: garment.category, notes: garment.notes || '' });
        bulkCreated.value = bulkCreated.value.map((item) => item.id === garment.id ? updated.data : item);
        bulkSavedIds.value = [...bulkSavedIds.value, garment.id];
        delete bulkSaveErrors.value[garment.id];
    } catch (requestError) {
        bulkSaveErrors.value[garment.id] = requestError.message;
    }
};

const saveAllBulkDetails = async () => {
    savingBulkDetails.value = true;
    await Promise.all(bulkCreated.value.filter((garment) => !bulkSavedIds.value.includes(garment.id)).map(saveBulkDetails));
    savingBulkDetails.value = false;
};

const applyBulkDefaults = () => {
    bulkCreated.value.forEach((garment) => {
        if (!selectedBulkIds.value.includes(garment.id)) return;
        const details = bulkReviewForms.value[garment.id];
        if (bulkDefaults.season) details.season = bulkDefaults.season;
        if (bulkDefaults.style) details.style = bulkDefaults.style;
        if (bulkDefaults.brand.trim()) details.brand = bulkDefaults.brand.trim();
        bulkSavedIds.value = bulkSavedIds.value.filter((id) => id !== garment.id);
        delete bulkSaveErrors.value[garment.id];
    });
};

const toggleBulkSelection = (id) => {
    selectedBulkIds.value = selectedBulkIds.value.includes(id)
        ? selectedBulkIds.value.filter((selectedId) => selectedId !== id)
        : [...selectedBulkIds.value, id];
};

const toggleAllBulkSelection = () => {
    selectedBulkIds.value = selectedBulkIds.value.length === bulkCreated.value.length
        ? []
        : bulkCreated.value.map((garment) => garment.id);
};

const selectBulkImages = (event) => {
    bulkImages.value = Array.from(event.target.files || []);
    error.value = '';
};

const selectImage = (event) => {
    const selected = event.target.files?.[0];
    if (!selected) return;
    image.value = selected;
    preview.value = URL.createObjectURL(selected);
};
</script>

<template>
    <div class="mb-4 flex rounded-full bg-[var(--color-surface-muted)] p-1">
        <button type="button" class="flex-1 rounded-full px-3 py-2 text-sm" :class="!bulkMode ? 'bg-white font-medium shadow-sm' : ''" @click="bulkMode = false; error = ''; success = ''">Una prenda</button>
        <button type="button" class="flex-1 rounded-full px-3 py-2 text-sm" :class="bulkMode ? 'bg-white font-medium shadow-sm' : ''" @click="bulkMode = true; error = ''; success = ''">Varias fotos</button>
    </div>
    <section v-if="bulkMode && !props.garment && bulkCreated.length" class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h3 class="text-lg font-semibold">Revisa tus prendas</h3>
                <p class="mt-1 text-sm text-[var(--color-text-subtle)]">Completa los datos que quieras ahora; también puedes hacerlo después desde el armario.</p></div>
            <button type="button" class="shrink-0 rounded-full bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-white disabled:opacity-60" :disabled="savingBulkDetails || bulkSavedIds.length === bulkCreated.length" @click="saveAllBulkDetails">{{ savingBulkDetails ? 'Guardando…' : 'Guardar todas' }}</button>
        </div>
        <section class="space-y-3 rounded-2xl bg-[var(--color-surface-muted)] p-4">
            <div class="flex items-center justify-between gap-3"><p class="text-sm font-medium">Aplicar a {{ selectedBulkIds.length }} seleccionadas</p><button type="button" class="text-xs font-medium text-[var(--color-primary)] underline" @click="toggleAllBulkSelection">{{ selectedBulkIds.length === bulkCreated.length ? 'Deseleccionar todas' : 'Seleccionar todas' }}</button></div>
            <div class="grid gap-2 sm:grid-cols-3">
                <select v-model="bulkDefaults.season" aria-label="Temporada común" class="min-w-0 rounded-lg border border-[var(--color-border)] bg-white px-2 py-2 text-xs"><option value="">No cambiar temporada</option><option v-for="season in seasons" :key="season">{{ season }}</option></select>
                <select v-model="bulkDefaults.style" aria-label="Estilo común" class="min-w-0 rounded-lg border border-[var(--color-border)] bg-white px-2 py-2 text-xs"><option value="">No cambiar estilo</option><option v-for="style in styles" :key="style">{{ style }}</option></select>
                <input v-model="bulkDefaults.brand" aria-label="Marca común" class="min-w-0 rounded-lg border border-[var(--color-border)] bg-white px-2 py-2 text-xs" placeholder="Marca común" />
            </div>
            <button type="button" class="rounded-full border border-[var(--color-primary)] px-4 py-2 text-xs font-medium text-[var(--color-primary)] disabled:opacity-50" :disabled="!selectedBulkIds.length || (!bulkDefaults.season && !bulkDefaults.style && !bulkDefaults.brand.trim())" @click="applyBulkDefaults">Aplicar datos seleccionados</button>
        </section>
        <article v-for="garment in bulkCreated" :key="garment.id" class="grid grid-cols-[5rem_1fr] gap-3 rounded-2xl border border-[var(--color-border)] p-3">
            <div class="flex flex-col items-center gap-2"><input type="checkbox" :checked="selectedBulkIds.includes(garment.id)" :aria-label="`Seleccionar ${bulkReviewForms[garment.id].name}`" class="h-4 w-4 accent-[var(--color-primary)]" @change="toggleBulkSelection(garment.id)" /><img :src="wardrobeImageUrl(garment.id)" :alt="bulkReviewForms[garment.id].name" class="h-20 w-20 rounded-xl bg-[var(--color-surface-muted)] object-cover" /></div>
            <div class="min-w-0 space-y-2">
                <input v-model="bulkReviewForms[garment.id].name" :aria-label="`Nombre para ${garment.name}`" class="w-full rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm" placeholder="Nombre de la prenda" />
                <div class="grid grid-cols-2 gap-2">
                    <select v-model="bulkReviewForms[garment.id].primary_color" :aria-label="`Color para ${garment.name}`" class="min-w-0 rounded-lg border border-[var(--color-border)] bg-white px-2 py-2 text-xs"><option value="">Color</option><option v-for="color in colors" :key="color">{{ color }}</option></select>
                    <select v-model="bulkReviewForms[garment.id].season" :aria-label="`Temporada para ${garment.name}`" class="min-w-0 rounded-lg border border-[var(--color-border)] bg-white px-2 py-2 text-xs"><option value="">Temporada</option><option v-for="season in seasons" :key="season">{{ season }}</option></select>
                    <select v-model="bulkReviewForms[garment.id].style" :aria-label="`Estilo para ${garment.name}`" class="min-w-0 rounded-lg border border-[var(--color-border)] bg-white px-2 py-2 text-xs"><option value="">Estilo</option><option v-for="style in styles" :key="style">{{ style }}</option></select>
                    <input v-model="bulkReviewForms[garment.id].brand" :aria-label="`Marca para ${garment.name}`" class="min-w-0 rounded-lg border border-[var(--color-border)] px-2 py-2 text-xs" placeholder="Marca" />
                </div>
                <button v-if="!bulkSavedIds.includes(garment.id)" type="button" class="rounded-full bg-[var(--color-primary)] px-3 py-1.5 text-xs font-medium text-white disabled:opacity-60" :disabled="savingBulkDetails" @click="saveBulkDetails(garment)">Guardar datos</button>
                <p v-else class="text-xs font-medium text-green-700" role="status">Datos guardados</p>
                <p v-if="bulkSaveErrors[garment.id]" class="text-xs text-red-700" role="alert">{{ bulkSaveErrors[garment.id] }}</p>
            </div>
        </article>
        <div class="flex items-center justify-between"><p class="text-xs text-[var(--color-text-subtle)]">{{ bulkSavedIds.length }} de {{ bulkCreated.length }} guardadas</p><button type="button" class="rounded-full bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white" @click="bulkCreated = []; bulkReviewForms = {}; bulkSavedIds = []; bulkSaveErrors = {}; emit('cancel')">Listo</button></div>
    </section>
    <form v-else-if="bulkMode && !props.garment" class="space-y-4" @submit.prevent="submitBulk">
        <div>
            <label class="text-sm font-medium" for="bulk-category">Categoría común</label>
            <select id="bulk-category" v-model="bulkCategory" class="mt-1 w-full rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                <option value="top">Parte superior</option><option value="bottom">Pantalón o falda</option><option value="dress">Vestido o mono</option><option value="outerwear">Chaqueta o abrigo</option><option value="shoes">Zapatos</option><option value="accessories">Accesorio</option>
            </select>
        </div>
        <div>
            <label class="text-sm font-medium" for="bulk-images">Fotografías</label>
            <input id="bulk-images" ref="bulkInput" type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-2 block w-full rounded-xl border border-[var(--color-border)] p-3 text-sm" @change="selectBulkImages" />
            <p class="mt-1 text-xs text-[var(--color-text-subtle)]">JPG, PNG o WEBP · Máximo 5 MB por imagen</p>
        </div>
        <ul v-if="bulkImages.length" class="max-h-32 space-y-1 overflow-y-auto rounded-xl bg-[var(--color-surface-muted)] p-3 text-sm">
            <li v-for="file in bulkImages" :key="`${file.name}-${file.lastModified}`" class="truncate">{{ file.name }}</li>
        </ul>
        <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>
        <p v-if="success" class="rounded-xl bg-green-50 p-3 text-sm text-green-800" role="status">{{ success }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" class="rounded-full px-4 py-2 text-sm" @click="emit('cancel')">Cerrar</button>
            <button type="submit" class="rounded-full bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white disabled:opacity-60" :disabled="loading">{{ loading ? 'Añadiendo…' : `Añadir ${bulkImages.length || ''} prendas` }}</button>
        </div>
    </form>
    <form class="space-y-4" @submit.prevent="submit">
        <template v-if="!bulkMode || props.garment">
        <div>
            <label class="text-sm font-medium" for="garment-image">Fotografía</label>
            <label for="garment-image" class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[var(--color-border)] bg-[var(--color-upload-surface)] px-6 py-7 text-center transition hover:border-[var(--color-upload-border-hover)] hover:bg-[var(--color-upload-hover)] focus-within:ring-2 focus-within:ring-[var(--color-primary)] focus-within:ring-offset-2">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-[var(--color-primary)] shadow-sm" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0L7.5 9m4.5-4.5L16.5 9M5.25 15v2.25A2.25 2.25 0 007.5 19.5h9a2.25 2.25 0 002.25-2.25V15" />
                    </svg>
                </span>
                <span class="mt-3 text-sm font-semibold text-[var(--color-primary)]">Haz clic para seleccionar una imagen</span>
                <span class="mt-1 text-xs text-[var(--color-text-subtle)]">JPG, PNG o WEBP · Máximo 5 MB</span>
                <span v-if="image" class="mt-3 rounded-full bg-[var(--color-border)] px-3 py-1 text-xs font-medium text-[var(--color-primary)]">{{ image.name }}</span>
                <input id="garment-image" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="selectImage" />
            </label>
            <img v-if="preview" :src="preview" alt="Previsualización de la prenda" class="mt-3 h-32 w-full rounded-2xl object-cover" />
        </div>
        <div>
            <label class="text-sm font-medium" for="garment-name">Nombre</label>
            <input id="garment-name" v-model="form.name" class="mt-1 w-full rounded-xl border border-[var(--color-border)] px-4 py-3" placeholder="Camisa blanca" />
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium" for="garment-category">Categoría</label>
                <select id="garment-category" v-model="form.category" class="mt-1 w-full rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                    <option value="top">Parte superior</option><option value="bottom">Pantalón o falda</option><option value="dress">Vestido o mono</option><option value="outerwear">Chaqueta o abrigo</option><option value="shoes">Zapatos</option><option value="accessories">Accesorio</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium" for="garment-color">Color principal <span class="font-normal text-[var(--color-text-subtle)]">(opcional)</span></label>
                <select id="garment-color" v-model="form.primary_color" class="mt-1 w-full rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                    <option value="">Selecciona un color</option>
                    <option v-for="color in colors" :key="color">{{ color }}</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <select v-model="form.season" aria-label="Temporada (opcional)" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                <option value="">Temporada</option>
                <option v-for="season in seasons" :key="season" :value="season">{{ season }}</option>
            </select>
            <select v-model="form.style" aria-label="Estilo (opcional)" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                <option value="">Estilo</option>
                <option v-for="style in styles" :key="style" :value="style">{{ style }}</option>
            </select>
        </div>
        <input v-model="form.brand" aria-label="Marca (opcional)" class="w-full rounded-xl border border-[var(--color-border)] px-4 py-3" placeholder="Marca (opcional)" />
        <textarea v-model="form.notes" class="w-full rounded-xl border border-[var(--color-border)] px-4 py-3" placeholder="Notas (opcional)" rows="3" />
        <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <p v-if="success" class="rounded-xl bg-green-50 p-3 text-sm text-green-800" role="status">{{ success }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" class="rounded-full px-4 py-2 text-sm" @click="emit('cancel')">Cancelar</button>
            <button v-if="!props.garment" type="submit" class="rounded-full border border-[var(--color-border)] px-4 py-2 text-sm font-medium text-[var(--color-primary)]" :disabled="loading" @click="keepAdding = true">{{ loading ? 'Guardando...' : 'Guardar y añadir otra' }}</button>
            <button type="submit" class="rounded-full bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white" :disabled="loading">{{ loading ? 'Guardando...' : props.garment ? 'Guardar cambios' : 'Guardar prenda' }}</button>
        </div>
        </template>
    </form>
</template>
const colors = ['Negro', 'Blanco', 'Gris', 'Azul', 'Rojo', 'Verde', 'Marrón', 'Beige', 'Rosa', 'Amarillo'];
