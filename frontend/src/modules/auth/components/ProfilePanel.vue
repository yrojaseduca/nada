<script setup>
import { useAuthStore } from '../store/authStore';
import { reactive, ref } from 'vue';

const auth = useAuthStore();
const editing = ref(false);
const error = ref('');
const form = reactive({ name: auth.user?.name || '', email: auth.user?.email || '' });
const save = async () => { try { await auth.updateProfile(form); editing.value = false; } catch (requestError) { error.value = requestError.message; } };
defineEmits(['close']);
</script>

<template>
    <section class="fixed inset-0 z-30 flex items-end justify-center bg-[#282724]/40 sm:items-center sm:p-6" @click.self="$emit('close')">
        <div class="w-full rounded-t-3xl bg-white p-6 sm:max-w-md sm:rounded-3xl">
            <div class="flex items-center justify-between"><div><p class="text-xs uppercase tracking-[0.2em] text-[#4D5145]">Perfil</p><h2 class="mt-1 text-xl font-semibold">{{ auth.user?.name }}</h2></div><button aria-label="Cerrar" @click="$emit('close')">×</button></div>
            <p class="mt-2 text-sm text-[#8E857A]">{{ auth.user?.email }}</p>
            <button v-if="!editing" class="mt-7 w-full rounded-2xl border border-[#E9D9C9] px-4 py-3 text-left text-sm font-medium" @click="editing = true">Administrar mi cuenta</button>
            <form v-else class="mt-7 space-y-3" @submit.prevent="save"><input v-model="form.name" class="w-full rounded-xl border border-[#E9D9C9] px-3 py-3" placeholder="Nombre" /><input v-model="form.email" type="email" class="w-full rounded-xl border border-[#E9D9C9] px-3 py-3" placeholder="Email" /><p v-if="error" class="text-sm text-red-700">{{ error }}</p><button class="w-full rounded-xl bg-[#4D5145] px-4 py-3 text-sm text-white">Guardar cambios</button></form>
            <button class="mt-3 w-full rounded-2xl bg-[#4D5145] px-4 py-3 text-sm font-medium text-white" @click="auth.logout(); $emit('close')">Cerrar sesión</button>
        </div>
    </section>
</template>
