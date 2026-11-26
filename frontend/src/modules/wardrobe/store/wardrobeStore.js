import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { garmentService } from '../services/garmentService';

export const useWardrobeStore = defineStore('wardrobe', () => {
    const garments = ref([]);
    const filters = ref({ category: '', search: '', favorites: false, color: '', season: '', style: '' });
    const favoriteOnly = computed(() => filters.value.favorites);
    const loading = ref(false);
    const error = ref('');
    const visibleGarments = computed(() => garments.value.filter((garment) => {
        const search = filters.value.search.trim().toLocaleLowerCase();
        if (filters.value.category && garment.category !== filters.value.category) return false;
        if (search && !`${garment.name || ''} ${garment.brand || ''}`.toLocaleLowerCase().includes(search)) return false;
        if (favoriteOnly.value && !garment.is_favorite) return false;
        if (filters.value.color && garment.primary_color !== filters.value.color) return false;
        if (filters.value.season && garment.season !== filters.value.season) return false;
        if (filters.value.style && garment.style !== filters.value.style) return false;
        return true;
    }));
    const totalGarments = computed(() => visibleGarments.value.length);

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
    };

    const setSearch = async (search) => {
        filters.value.search = search;
    };

    const clearFilters = async () => {
        filters.value = { category: '', search: '', favorites: false, color: '', season: '', style: '' };
        await loadGarments();
    };

    const toggleFavorites = () => {
        filters.value.favorites = !filters.value.favorites;
    };

    const setFilter = (name, value) => {
        filters.value[name] = value;
    };

    const setCategoryFilter = (category) => {
        filters.value.category = category;
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

    return { garments, visibleGarments, filters, favoriteOnly, loading, error, totalGarments, loadGarments, setCategory, setSearch, clearFilters, toggleFavorites, setFilter, setCategoryFilter, toggleFavorite, imageUrl, removeGarment };
});
