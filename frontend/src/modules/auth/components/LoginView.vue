<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '../store/authStore';

const auth = useAuthStore();
const form = reactive({ email: '', password: '' });
const showPassword = ref(false);
const installPrompt = ref(null);
const installMessage = ref('');

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt.value = event;
});

const installApp = async () => {
    if (!installPrompt.value) {
        installMessage.value = 'Para instalar Nada, abre el menú del navegador y selecciona “Instalar aplicación” o “Añadir a pantalla de inicio”.';
        return;
    }
    await installPrompt.value.prompt();
    installPrompt.value = null;
};

const submit = async () => {
    try {
        await auth.login(form.email, form.password);
    } catch {
        // El mensaje se muestra desde el estado del store.
    }
};
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-[#F8F5F0] px-5 py-10 text-[#282724]">
        <section class="w-full max-w-md rounded-[2rem] bg-white p-7 shadow-[0_20px_70px_rgba(77,81,69,0.12)] sm:p-10">
            <div class="text-center">
                <img src="/icons/nada-logo.png" alt="Nada" class="mx-auto h-20 w-20 rounded-3xl object-cover" />
                <p class="mt-3 text-2xl font-semibold tracking-tight">nada.</p>
                <h1 class="mt-4 text-lg font-semibold tracking-tight">Nada que ponerte.</h1>
                <p class="mt-3 text-base text-[#A87569]">Todo por combinar.</p>
                <p class="mx-auto mt-3 max-w-xs text-xs leading-6 text-[#A87569]">Vamos a encontrar algo entre tanta nada.</p>
            </div>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="text-sm font-medium">Correo electrónico</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" required placeholder="tu@email.com" class="mt-2 w-full rounded-2xl border border-[#E9D9C9] bg-[#FDFCFA] px-4 py-3.5 outline-none transition focus:border-[#4D5145] focus:ring-2 focus:ring-[#E9D9C9]" />
                </div>
                <div>
                        <label for="password" class="text-sm font-medium">Contraseña</label>
                    <div class="relative mt-2">
                        <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Tu contraseña" class="w-full rounded-2xl border border-[#E9D9C9] bg-[#FDFCFA] px-4 py-3.5 pr-16 outline-none transition focus:border-[#4D5145] focus:ring-2 focus:ring-[#E9D9C9]" />
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-[#8E857A]" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="showPassword = !showPassword">
                            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" /><circle cx="12" cy="12" r="2.5" /></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 3 18 18" /><path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a16.7 16.7 0 0 1-3.1 3.7M6.2 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1 0 1.9-.2 2.7-.5" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" /></svg>
                        </button>
                    </div>
                </div>
                <p v-if="auth.error" class="rounded-2xl bg-[#FDEDEC] p-3 text-sm text-[#A23B35]">{{ auth.error }}</p>
                <button type="button" class="block w-full text-right text-xs text-[#A87569]">¿Olvidaste tu contraseña?</button>
                <button type="submit" class="w-full rounded-2xl bg-[#4D5145] px-5 py-3.5 font-semibold text-white transition hover:bg-[#3F4339] disabled:opacity-60" :disabled="auth.loading">{{ auth.loading ? 'Entrando...' : 'Entrar en mi armario →' }}</button>
            </form>

            <p class="mt-4 text-center text-xs text-[#A87569]">¿Aún no tienes cuenta? <button class="font-semibold text-[#4D5145]">Regístrate</button></p>
            <button class="mt-4 w-full rounded-2xl border border-[#E9D9C9] px-5 py-3 text-sm font-medium text-[#4D5145]" @click="installApp">Instalar Nada en el inicio</button>
            <p v-if="installMessage" class="mt-2 text-center text-xs leading-5 text-[#A87569]">{{ installMessage }}</p>

            <div class="mt-5 border-t border-[#E9D9C9] pt-5"><p class="text-center text-[10px] leading-5 text-[#A87569]">Todas esas cosas que supuestamente no tienes.</p></div>
        </section>
    </main>
</template>
