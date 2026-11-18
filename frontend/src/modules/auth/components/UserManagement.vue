<script setup>
import { onMounted, ref } from 'vue';
import { userManagementService } from '../services/userManagementService';

defineEmits(['close']);

const users = ref([]);
const error = ref('');
const loading = ref(true);

const load = async () => {
    try {
        users.value = (await userManagementService.list()).data || [];
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
};

const toggle = async (user) => {
    await userManagementService.toggleActive(user.id);
    user.active = !user.active;
};

onMounted(load);
</script>

<template>
    <section class="fixed inset-0 z-30 overflow-y-auto bg-[#F8F5F0] p-5 sm:p-10">
        <div class="mx-auto max-w-3xl">
            <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#4D5145]">Nada</p><h2 class="mt-1 text-2xl font-semibold">Gestión de usuarios</h2></div><button class="rounded-full bg-white px-4 py-2 text-sm" @click="$emit('close')">Cerrar</button></div>
            <p v-if="error" class="mt-6 rounded-2xl bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
            <div v-else-if="loading" class="mt-8 text-sm text-[#8E857A]">Cargando usuarios...</div>
            <div v-else class="mt-8 overflow-hidden rounded-3xl bg-white shadow-sm">
                <div v-for="user in users" :key="user.id" class="flex items-center justify-between gap-4 border-b border-[#F0EBE5] p-5 last:border-0">
                    <div><p class="font-medium">{{ user.name }}</p><p class="text-sm text-[#8E857A]">{{ user.email }}</p><span v-if="user.is_superadmin" class="text-xs text-[#4D5145]">Superadmin</span></div>
                    <button class="rounded-full px-3 py-2 text-xs" :class="user.active ? 'bg-[#E9D9C9] text-[#4D5145]' : 'bg-red-50 text-red-700'" @click="toggle(user)">{{ user.active ? 'Activo' : 'Inactivo' }}</button>
                </div>
            </div>
        </div>
    </section>
</template>
