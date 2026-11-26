import { ref } from 'vue';
import { defineStore } from 'pinia';
import { outfitService } from '../services/outfitService';

export const useOutfitStore = defineStore('outfits', () => {
    const outfits = ref([]);
    const loading = ref(false);
    const error = ref('');
    const suggestions = ref([]);
    const suggestionMessage = ref('');
    const suggestionError = ref('');
    const suggestionLoading = ref(false);
    const preferences = ref({ preferred_style: '', preferred_season: '' });

    const loadOutfits = async (occasion = '', style = '', season = '') => {
        loading.value = true;
        error.value = '';
        try {
            outfits.value = (await outfitService.list(occasion, style, season)).data || [];
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

    const loadSuggestions = async (occasion, style, season, useColorimetry = false) => {
        suggestionLoading.value = true;
        suggestionError.value = '';
        suggestionMessage.value = '';
        try {
            const response = await outfitService.suggestions(occasion, style, season, useColorimetry);
            suggestions.value = response.data || [];
            suggestionMessage.value = response.message || '';
        } catch (requestError) {
            suggestions.value = [];
            suggestionError.value = requestError.message;
        } finally {
            suggestionLoading.value = false;
        }
    };

    const loadPreferences = async () => {
        const response = await outfitService.preferences();
        preferences.value = response.data || preferences.value;
    };

    const updatePreferences = async (data) => {
        const response = await outfitService.updatePreferences(data);
        preferences.value = response.data || preferences.value;
    };

    return { outfits, loading, error, suggestions, suggestionMessage, suggestionError, suggestionLoading, preferences, loadOutfits, createOutfit, updateOutfit, removeOutfit, duplicateOutfit, toggleFavorite, loadSuggestions, loadPreferences, updatePreferences };
});
