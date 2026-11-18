import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { garmentService } from '../services/garmentService';

export const useWardrobeStore = defineStore('wardrobe', () => {
    const garments = ref([]);
    const filters = ref({ category: '', search: '' });
    const loading = ref(false);
    const error = ref('');
    const totalGarments = computed(() => garments.value.length);

    const loadGarments = async () => {
        loading.value = true;
        error.value = '';
        try {
            const response = await garmentService.list(filters.value);
            garments.value = response.data || [];
        } catch (requestError) {
            error.value = requestError.message;
        } finally {
            loading.value = false;
        }
    };

    const setCategory = async (category) => {
        filters.value.category = category;
        await loadGarments();
    };

    const setSearch = async (search) => {
        filters.value.search = search;
        await loadGarments();
    };

    const toggleFavorite = async (garment) => {
        const response = await garmentService.toggleFavorite(garment.id);
        const index = garments.value.findIndex((item) => item.id === garment.id);
        if (index !== -1) garments.value[index] = response.data;
    };

    const imageUrl = (id) => garmentService.imageUrl(id);

    const removeGarment = async (id) => {
        await garmentService.remove(id);
        garments.value = garments.value.filter((garment) => garment.id !== id);
    };

    return { garments, filters, loading, error, totalGarments, loadGarments, setCategory, setSearch, toggleFavorite, imageUrl, removeGarment };
});
