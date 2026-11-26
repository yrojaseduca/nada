<script setup>
import { useAuthStore } from '../store/authStore';
import { onMounted, reactive, ref } from 'vue';
import { avatarService } from '../services/avatarService';

const auth = useAuthStore();
const editing = ref(false);
const error = ref('');
const form = reactive({ name: auth.user?.name || '', email: auth.user?.email || '' });
const avatarExists = ref(false);
const avatarUrl = ref('');
const avatarFile = ref(null);
const avatarPreview = ref('');
const avatarConsent = ref(false);
const avatarBusy = ref(false);
const avatarError = ref('');
const avatarMessage = ref('');
const save = async () => { try { await auth.updateProfile(form); editing.value = false; } catch (requestError) { error.value = requestError.message; } };
const loadAvatar = async () => {
    try {
        const response = await avatarService.get();
        avatarExists.value = response.data.exists;
        avatarUrl.value = avatarExists.value ? avatarService.imageUrl() : '';
    } catch (requestError) {
        avatarError.value = requestError.message;
    }
};
onMounted(loadAvatar);
const chooseAvatar = (event) => {
    const file = event.target.files?.[0];
    avatarError.value = '';
    avatarMessage.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
        avatarError.value = 'Elige una imagen JPG, PNG o WEBP de hasta 8 MB.';
        event.target.value = '';
        return;
    }
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
};
const uploadAvatar = async () => {
    if (!avatarFile.value || !avatarConsent.value) return;
    avatarBusy.value = true;
    avatarError.value = '';
    try {
        await avatarService.save(avatarFile.value);
        avatarExists.value = true;
        avatarUrl.value = avatarService.imageUrl();
        avatarFile.value = null;
        if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = '';
        avatarMessage.value = 'Tu avatar se ha guardado en tu espacio privado.';
    } catch (requestError) {
        avatarError.value = requestError.message;
    } finally {
        avatarBusy.value = false;
    }
};
const deleteAvatar = async () => {
    avatarBusy.value = true;
    avatarError.value = '';
    try {
        await avatarService.remove();
        avatarExists.value = false;
        avatarUrl.value = '';
        avatarMessage.value = 'Avatar eliminado.';
    } catch (requestError) {
        avatarError.value = requestError.message;
    } finally {
        avatarBusy.value = false;
    }
};
const cleanupAvatarPreview = () => {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
};
import { onBeforeUnmount } from 'vue';
onBeforeUnmount(cleanupAvatarPreview);
defineEmits(['close', 'open-colorimetry']);
</script>

<template>
    <section class="fixed inset-0 z-30 flex items-end justify-center bg-[var(--color-text)]/40 sm:items-center sm:p-6" @click.self="$emit('close')">
        <div class="w-full rounded-t-3xl bg-white p-6 sm:max-w-md sm:rounded-3xl">
            <div class="flex items-center justify-between"><div><p class="text-xs uppercase tracking-[0.2em] text-[var(--color-primary)]">Perfil</p><h2 class="mt-1 text-xl font-semibold">{{ auth.user?.name }}</h2></div><button aria-label="Cerrar" @click="$emit('close')">×</button></div>
            <p class="mt-2 text-sm text-[var(--color-text-subtle)]">{{ auth.user?.email }}</p>
            <section class="mt-6 rounded-2xl bg-[var(--color-canvas)] p-4">
                <h3 class="font-semibold">Avatar para probar looks</h3>
                <p class="mt-2 text-xs leading-5 text-[var(--color-text-muted)]">Tómate una foto de cuerpo entero, de frente, con ropa ajustada y de un solo tono. Usa luz uniforme y un fondo sencillo para que las prendas se distingan mejor.</p>
                <div v-if="avatarPreview || avatarUrl" class="mt-3 flex justify-center rounded-xl bg-white p-2"><img :src="avatarPreview || avatarUrl" alt="Vista previa de tu avatar privado" class="max-h-64 rounded-lg object-contain" /></div>
                <label for="avatar-photo" class="mt-3 block cursor-pointer rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 text-center text-sm font-medium text-[var(--color-primary)]">{{ avatarPreview ? 'Cambiar foto' : avatarExists ? 'Elegir otra foto' : 'Elegir foto de cuerpo entero' }}<input id="avatar-photo" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseAvatar" /></label>
                <label class="mt-3 flex gap-2 text-xs leading-5 text-[var(--color-text-muted)]"><input v-model="avatarConsent" type="checkbox" class="mt-1 accent-[var(--color-primary)]" />Acepto guardar esta foto en mi espacio privado para usarla como avatar en las pruebas de looks. Puedo eliminarla cuando quiera.</label>
                <button v-if="avatarPreview" class="mt-3 w-full rounded-xl bg-[var(--color-primary)] px-4 py-3 text-sm font-medium text-white disabled:opacity-50" :disabled="!avatarConsent || avatarBusy" @click="uploadAvatar">{{ avatarBusy ? 'Guardando…' : 'Guardar avatar privado' }}</button>
                <button v-else-if="avatarExists" class="mt-3 w-full rounded-xl border border-[var(--color-danger-border)] px-4 py-3 text-sm font-medium text-[var(--color-danger-text)] disabled:opacity-50" :disabled="avatarBusy" @click="deleteAvatar">Eliminar avatar y foto</button>
                <p v-if="avatarError" role="alert" class="mt-3 text-xs text-[var(--color-danger-text)]">{{ avatarError }}</p>
                <p v-if="avatarMessage" class="mt-3 text-xs text-[var(--color-primary)]">{{ avatarMessage }}</p>
            </section>
            <button class="mt-3 w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-left" @click="$emit('open-colorimetry')"><span class="block text-sm font-semibold">Mi colorimetría</span><span class="mt-1 block text-xs text-[var(--color-text-muted)]">Consulta o actualiza los colores que te favorecen.</span></button>
            <button v-if="!editing" class="mt-7 w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-left text-sm font-medium" @click="editing = true">Administrar mi cuenta</button>
            <form v-else class="mt-7 space-y-3" @submit.prevent="save"><input v-model="form.name" class="w-full rounded-xl border border-[var(--color-border)] px-3 py-3" placeholder="Nombre" /><input v-model="form.email" type="email" class="w-full rounded-xl border border-[var(--color-border)] px-3 py-3" placeholder="Email" /><p v-if="error" class="text-sm text-red-700">{{ error }}</p><button class="w-full rounded-xl bg-[var(--color-primary)] px-4 py-3 text-sm text-white">Guardar cambios</button></form>
            <button class="mt-3 w-full rounded-2xl bg-[var(--color-primary)] px-4 py-3 text-sm font-medium text-white" @click="auth.logout(); $emit('close')">Cerrar sesión</button>
        </div>
    </section>
</template>
