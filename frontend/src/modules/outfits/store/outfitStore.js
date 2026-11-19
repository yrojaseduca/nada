import { ref } from 'vue';
import { defineStore } from 'pinia';
import { outfitService } from '../services/outfitService';

export const useOutfitStore = defineStore('outfits', () => {
    const outfits = ref([]);
    const loading = ref(false);
    const error = ref('');
    const suggestions = ref([]);
    const suggestionMessage = ref('');

    const loadOutfits = async (occasion = '', style = '') => {
        loading.value = true;
        error.value = '';
        try {
            outfits.value = (await outfitService.list(occasion, style)).data || [];
        } catch (requestError) {
            error.value = requestError.message;
        } finally {
            loading.value = false;
        }
    };

    const createOutfit = async (data) => {
        const response = await outfitService.create(data);
        outfits.value.unshift(response.data);
    };

    const updateOutfit = async (id, data) => {
        const response = await outfitService.update(id, data);
        const index = outfits.value.findIndex((item) => item.id === id);
        if (index !== -1) outfits.value[index] = response.data;
    };

    const removeOutfit = async (id) => {
        await outfitService.remove(id);
        outfits.value = outfits.value.filter((item) => item.id !== id);
    };

    const duplicateOutfit = async (outfit) => {
        const response = await outfitService.create({
            name: `${outfit.name} (copia)`,
            occasion: outfit.occasion,
            style: outfit.style,
            season: outfit.season,
            notes: outfit.notes,
            source: outfit.source || 'manual',
            garment_ids: outfit.garments.map((garment) => garment.id)
        });
        outfits.value.unshift(response.data);
    };

    const toggleFavorite = async (outfit) => {
        const response = await outfitService.toggleFavorite(outfit.id);
        const index = outfits.value.findIndex((item) => item.id === outfit.id);
        if (index !== -1) outfits.value[index] = response.data;
    };

    const loadSuggestions = async (occasion, style, season) => {
        const response = await outfitService.suggestions(occasion, style, season);
        suggestions.value = response.data || [];
        suggestionMessage.value = response.message || '';
    };

    return { outfits, loading, error, suggestions, suggestionMessage, loadOutfits, createOutfit, updateOutfit, removeOutfit, duplicateOutfit, toggleFavorite, loadSuggestions };
});
