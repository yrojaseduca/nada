<script setup>
import { reactive, ref } from 'vue';
import { garmentService } from '../services/garmentService';

const props = defineProps({ garment: { type: Object, default: null } });
const emit = defineEmits(['saved', 'cancel']);
const loading = ref(false);
const error = ref('');
const image = ref(null);
const preview = ref('');
const seasons = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
const styles = ['Casual', 'Elegante', 'Minimalista', 'Deportivo', 'Bohemio', 'Formal'];
const form = reactive({ name: props.garment?.name || '', category: props.garment?.category || 'top', primary_color: props.garment?.primary_color || '', season: props.garment?.season || '', style: props.garment?.style || '', brand: props.garment?.brand || '', notes: props.garment?.notes || '' });

const submit = async () => {
    error.value = '';
    if (!form.name.trim() || !form.primary_color.trim()) {
        error.value = 'El nombre y el color principal son obligatorios.';
        return;
    }
    loading.value = true;
    try {
        const data = new FormData();
        Object.entries(form).forEach(([key, value]) => data.append(key, value));
        if (image.value) data.append('image', image.value);
        if (props.garment) {
            await garmentService.update(props.garment.id, form);
        } else {
            await garmentService.create(data);
        }
        emit('saved');
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
};

const selectImage = (event) => {
    const selected = event.target.files?.[0];
    if (!selected) return;
    image.value = selected;
    preview.value = URL.createObjectURL(selected);
};
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
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
                <label class="text-sm font-medium" for="garment-color">Color principal</label>
                <select id="garment-color" v-model="form.primary_color" class="mt-1 w-full rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                    <option value="">Selecciona un color</option>
                    <option v-for="color in colors" :key="color">{{ color }}</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <select v-model="form.season" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                <option value="">Temporada</option>
                <option v-for="season in seasons" :key="season" :value="season">{{ season }}</option>
            </select>
            <select v-model="form.style" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 pr-8">
                <option value="">Estilo</option>
                <option v-for="style in styles" :key="style" :value="style">{{ style }}</option>
            </select>
        </div>
        <input v-model="form.brand" class="w-full rounded-xl border border-[var(--color-border)] px-4 py-3" placeholder="Marca (opcional)" />
        <textarea v-model="form.notes" class="w-full rounded-xl border border-[var(--color-border)] px-4 py-3" placeholder="Notas (opcional)" rows="3" />
        <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" class="rounded-full px-4 py-2 text-sm" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="rounded-full bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white" :disabled="loading">{{ loading ? 'Guardando...' : props.garment ? 'Guardar cambios' : 'Guardar prenda' }}</button>
        </div>
    </form>
</template>
const colors = ['Negro', 'Blanco', 'Gris', 'Azul', 'Rojo', 'Verde', 'Marrón', 'Beige', 'Rosa', 'Amarillo'];
