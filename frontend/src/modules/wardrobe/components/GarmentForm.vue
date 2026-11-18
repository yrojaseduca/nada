<script setup>
import { reactive, ref } from 'vue';
import { garmentService } from '../services/garmentService';

const props = defineProps({ garment: { type: Object, default: null } });
const emit = defineEmits(['saved', 'cancel']);
const loading = ref(false);
const error = ref('');
const image = ref(null);
const preview = ref('');
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
            <input id="garment-image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm" @change="selectImage" />
            <img v-if="preview" :src="preview" alt="Previsualización de la prenda" class="mt-3 h-32 w-full rounded-2xl object-cover" />
        </div>
        <div>
            <label class="text-sm font-medium" for="garment-name">Nombre</label>
            <input id="garment-name" v-model="form.name" class="mt-1 w-full rounded-xl border border-[#E9D9C9] px-4 py-3" placeholder="Camisa blanca" />
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium" for="garment-category">Categoría</label>
                <select id="garment-category" v-model="form.category" class="mt-1 w-full rounded-xl border border-[#E9D9C9] bg-white px-3 py-3">
                    <option value="top">Parte superior</option><option value="bottom">Pantalón o falda</option><option value="dress">Vestido o mono</option><option value="outerwear">Chaqueta o abrigo</option><option value="shoes">Zapatos</option><option value="accessories">Accesorio</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium" for="garment-color">Color principal</label>
                <input id="garment-color" v-model="form.primary_color" class="mt-1 w-full rounded-xl border border-[#E9D9C9] px-3 py-3" placeholder="Blanco" />
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <input v-model="form.season" class="rounded-xl border border-[#E9D9C9] px-3 py-3" placeholder="Temporada" />
            <input v-model="form.style" class="rounded-xl border border-[#E9D9C9] px-3 py-3" placeholder="Estilo" />
        </div>
        <input v-model="form.brand" class="w-full rounded-xl border border-[#E9D9C9] px-4 py-3" placeholder="Marca (opcional)" />
        <textarea v-model="form.notes" class="w-full rounded-xl border border-[#E9D9C9] px-4 py-3" placeholder="Notas (opcional)" rows="3" />
        <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" class="rounded-full px-4 py-2 text-sm" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="rounded-full bg-[#4D5145] px-5 py-2 text-sm font-medium text-white" :disabled="loading">{{ loading ? 'Guardando...' : props.garment ? 'Guardar cambios' : 'Guardar prenda' }}</button>
        </div>
    </form>
</template>
