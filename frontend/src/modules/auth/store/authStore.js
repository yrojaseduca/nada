import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { authService } from '../services/authService';

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('nada_auth_token') || '');
    const user = ref(null);
    const loading = ref(false);
    const error = ref('');
    const isAuthenticated = computed(() => Boolean(token.value && user.value));

    const login = async (email, password) => {
        loading.value = true;
        error.value = '';
        try {
            const response = await authService.login(email, password);
            token.value = response.token;
            user.value = response.user;
            localStorage.setItem('nada_auth_token', token.value);
        } catch (requestError) {
            error.value = requestError.message;
            throw requestError;
        } finally {
            loading.value = false;
        }
    };

    const restore = async () => {
        if (!token.value) return;
        try {
            const response = await authService.me(token.value);
            user.value = response.user;
        } catch {
            token.value = '';
            localStorage.removeItem('nada_auth_token');
        }
    };

    const logout = async () => {
        if (token.value) await authService.logout(token.value).catch(() => undefined);
        token.value = '';
        user.value = null;
        localStorage.removeItem('nada_auth_token');
    };

    const updateProfile = async (data) => {
        const response = await authService.updateProfile(token.value, data);
        user.value = response.user;
    };

    return { token, user, loading, error, isAuthenticated, login, restore, logout, updateProfile };
});
