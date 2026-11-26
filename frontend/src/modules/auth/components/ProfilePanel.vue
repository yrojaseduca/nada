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
const avatarGuide = ref(null);
const avatarConfirmed = ref(false);
const avatarAnalyzing = ref(false);
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
    avatarConfirmed.value = false;
    avatarAnalyzing.value = true;
    avatarPreview.value = URL.createObjectURL(file);
    analyzeAvatar(file);
};
const analyzeAvatar = (file) => {
    const image = new Image();
    const analysisUrl = URL.createObjectURL(file);
    image.onload = () => {
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 160 / Math.max(image.naturalWidth, image.naturalHeight));
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const context = canvas.getContext('2d');
        if (!context) {
            avatarAnalyzing.value = false;
            URL.revokeObjectURL(analysisUrl);
            return;
        }
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        const { data } = context.getImageData(0, 0, canvas.width, canvas.height);
        const corners = [0, canvas.width - 1, (canvas.height - 1) * canvas.width, canvas.height * canvas.width - 1];
        const background = [0, 0, 0];
        corners.forEach((pixel) => {
            background[0] += data[pixel * 4];
            background[1] += data[pixel * 4 + 1];
            background[2] += data[pixel * 4 + 2];
        });
        const backgroundColor = background.map((channel) => channel / corners.length);
        let totalBrightness = 0;
        let darkPixels = 0;
        let minX = canvas.width;
        let maxX = 0;
        let minY = canvas.height;
        let maxY = 0;
        let foregroundPixels = 0;
        for (let index = 0; index < data.length; index += 4) {
            const brightness = (data[index] * 0.299 + data[index + 1] * 0.587 + data[index + 2] * 0.114) / 255;
            const pixel = index / 4;
            const x = pixel % canvas.width;
            const y = Math.floor(pixel / canvas.width);
            const colorDistance = Math.sqrt(((data[index] - backgroundColor[0]) ** 2) + ((data[index + 1] - backgroundColor[1]) ** 2) + ((data[index + 2] - backgroundColor[2]) ** 2));
            if (colorDistance > 52) {
                minX = Math.min(minX, x);
                maxX = Math.max(maxX, x);
                minY = Math.min(minY, y);
                maxY = Math.max(maxY, y);
                foregroundPixels += 1;
            }
            totalBrightness += brightness;
            if (brightness < 0.12) darkPixels += 1;
        }
        const average = totalBrightness / (data.length / 4);
        const ratio = image.naturalWidth / image.naturalHeight;
        const foregroundHeight = maxY - minY + 1;
        const foregroundCenter = (minX + maxX) / 2;
        const centerOffset = Math.abs(foregroundCenter - canvas.width / 2) / canvas.width;
        const foregroundDetected = foregroundPixels > canvas.width * canvas.height * 0.03;
        const fullBodyLikely = ratio <= 0.8 && foregroundDetected && foregroundHeight / canvas.height >= 0.72 && minY / canvas.height < 0.16 && maxY / canvas.height > 0.84;
        const centeredLikely = foregroundDetected && centerOffset <= 0.12;
        avatarGuide.value = {
            fullBody: { ok: fullBodyLikely, text: fullBodyLikely ? 'La figura ocupa casi toda la altura; confirma que se ven cabeza y pies.' : 'No puedo confirmar el cuerpo entero. El encuadre debe mostrarte desde la cabeza hasta los pies.' },
            centered: { ok: centeredLikely, text: centeredLikely ? 'La figura parece centrada; confirma la posición en la vista previa.' : 'La figura parece desplazada o no se distingue bien del fondo; revisa que estés centrado.' },
            lighting: { ok: average >= 0.3 && average <= 0.82 && darkPixels / (data.length / 4) < 0.45, text: average < 0.3 ? 'La foto parece oscura; busca más luz uniforme.' : average > 0.82 ? 'La foto parece sobreexpuesta; evita la luz directa.' : darkPixels / (data.length / 4) >= 0.45 ? 'Hay muchas zonas oscuras; prueba con luz más uniforme.' : 'La luz parece suficiente.' }
        };
        avatarAnalyzing.value = false;
        URL.revokeObjectURL(analysisUrl);
    };
    image.onerror = () => {
        avatarAnalyzing.value = false;
        URL.revokeObjectURL(analysisUrl);
    };
    image.src = analysisUrl;
};
const uploadAvatar = async () => {
    if (!avatarFile.value || !avatarConsent.value || !avatarConfirmed.value || avatarAnalyzing.value) return;
    avatarBusy.value = true;
    avatarError.value = '';
    try {
        await avatarService.save(avatarFile.value);
        avatarExists.value = true;
        avatarUrl.value = avatarService.imageUrl();
        avatarFile.value = null;
        if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = '';
        avatarGuide.value = null;
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
        <div class="max-h-[calc(100dvh-1rem)] w-full overflow-y-auto rounded-t-3xl bg-white p-6 pb-[calc(6rem+env(safe-area-inset-bottom))] sm:max-h-[90dvh] sm:max-w-md sm:rounded-3xl sm:p-6">
            <div class="flex items-center justify-between"><div><p class="text-xs uppercase tracking-[0.2em] text-[var(--color-primary)]">Perfil</p><h2 class="mt-1 text-xl font-semibold">{{ auth.user?.name }}</h2></div><button aria-label="Cerrar" @click="$emit('close')">×</button></div>
            <p class="mt-2 text-sm text-[var(--color-text-subtle)]">{{ auth.user?.email }}</p>
            <section class="mt-6 rounded-2xl bg-[var(--color-canvas)] p-4">
                <h3 class="font-semibold">Avatar para probar looks</h3>
                <p class="mt-2 text-xs leading-5 text-[var(--color-text-muted)]">Tómate una foto de cuerpo entero, de frente, con ropa ajustada y de un solo tono. Usa luz uniforme y un fondo sencillo para que las prendas se distingan mejor.</p>
                <div v-if="avatarPreview || avatarUrl" class="mt-3 flex justify-center rounded-xl bg-white p-2"><img :src="avatarPreview || avatarUrl" alt="Vista previa de tu avatar privado" class="max-h-64 rounded-lg object-contain" /></div>
                <p v-if="avatarAnalyzing" class="mt-3 text-xs text-[var(--color-text-subtle)]" role="status">Revisando encuadre e iluminación en este dispositivo…</p>
                <div v-if="avatarGuide" class="mt-3 rounded-xl border border-[var(--color-border)] bg-white p-3" aria-live="polite">
                    <p class="text-xs font-semibold">Guía de encuadre (orientativa)</p>
                    <ul class="mt-2 space-y-1.5 text-xs leading-5">
                        <li v-for="(check, key) in avatarGuide" :key="key" class="flex gap-2"><span :class="check.ok ? 'text-green-700' : 'text-amber-700'" aria-hidden="true">{{ check.ok ? '✓' : '!' }}</span><span>{{ check.text }}</span></li>
                    </ul>
                    <p class="mt-2 text-[10px] leading-4 text-[var(--color-text-subtle)]">La comprobación estima luz y formato; confirma visualmente cuerpo entero y centrado antes de guardar.</p>
                </div>
                <label for="avatar-photo" class="mt-3 block cursor-pointer rounded-xl border border-[var(--color-border)] bg-white px-3 py-3 text-center text-sm font-medium text-[var(--color-primary)]">{{ avatarPreview ? 'Cambiar foto' : avatarExists ? 'Elegir otra foto' : 'Elegir foto de cuerpo entero' }}<input id="avatar-photo" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseAvatar" /></label>
                <label v-if="avatarPreview && avatarGuide && !avatarAnalyzing" class="mt-3 flex gap-2 text-xs leading-5 text-[var(--color-text-muted)]"><input v-model="avatarConfirmed" type="checkbox" class="mt-1 accent-[var(--color-primary)]" />He comprobado la vista previa: aparezco centrado y de cuerpo entero.</label>
                <label class="mt-3 flex gap-2 text-xs leading-5 text-[var(--color-text-muted)]"><input v-model="avatarConsent" type="checkbox" class="mt-1 accent-[var(--color-primary)]" />Acepto guardar esta foto en mi espacio privado para usarla como avatar en las pruebas de looks. Puedo eliminarla cuando quiera.</label>
                <button v-if="avatarPreview" class="mt-3 w-full rounded-xl bg-[var(--color-primary)] px-4 py-3 text-sm font-medium text-white disabled:opacity-50" :disabled="!avatarConsent || !avatarConfirmed || avatarAnalyzing || avatarBusy" @click="uploadAvatar">{{ avatarBusy ? 'Guardando…' : 'Guardar avatar privado' }}</button>
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
