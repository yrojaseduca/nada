<script setup>
import { onMounted, ref, watch } from 'vue';
import { useWardrobeStore } from './modules/wardrobe/store/wardrobeStore';
import GarmentForm from './modules/wardrobe/components/GarmentForm.vue';
import LoginView from './modules/auth/components/LoginView.vue';
import { useAuthStore } from './modules/auth/store/authStore';
import UserManagement from './modules/auth/components/UserManagement.vue';
import ProfilePanel from './modules/auth/components/ProfilePanel.vue';
import OutfitsView from './modules/outfits/components/OutfitsView.vue';
import DashboardView from './modules/dashboard/components/DashboardView.vue';

const wardrobe = useWardrobeStore();
const auth = useAuthStore();
const search = ref('');
const showGarmentForm = ref(false);
const selectedGarment = ref(null);
const editingGarment = ref(null);
const showUserManagement = ref(false);
const showProfile = ref(false);
const activeSection = ref('dashboard');
const categories = [
    { label: 'Todas', value: '' },
    { label: 'Partes superiores', value: 'top' },
    { label: 'Pantalones y faldas', value: 'bottom' },
    { label: 'Vestidos y monos', value: 'dress' },
    { label: 'Chaquetas y abrigos', value: 'outerwear' },
    { label: 'Zapatos', value: 'shoes' },
    { label: 'Accesorios', value: 'accessories' }
];

const searchGarments = () => wardrobe.setSearch(search.value);
onMounted(() => wardrobe.loadGarments());
onMounted(() => auth.restore());
watch(() => auth.isAuthenticated, (authenticated) => {
    if (authenticated) wardrobe.loadGarments();
});
</script>

<template>
    <LoginView v-if="!auth.isAuthenticated" />
    <div v-else class="min-h-screen bg-[#F8F5F0] text-[#282724]">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#4D5145]">Nada</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Mi armario</h1>
            </div>
            <button class="rounded-full bg-[#4D5145] px-4 py-2 text-sm font-medium text-white shadow-sm" @click="showGarmentForm = true">+ Añadir prenda</button>
        </header>

        <DashboardView v-if="activeSection === 'dashboard'" />
        <OutfitsView v-else-if="activeSection === 'outfits'" />
        <main v-else class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
            <section class="rounded-3xl bg-[#E9D9C9] p-6 sm:p-8">
                <p class="text-sm text-[#4D5145]">Tu estilo, a tu manera</p>
                <h2 class="mt-2 max-w-xl text-3xl font-semibold leading-tight sm:text-4xl">Cada prenda cuenta una parte de tu historia.</h2>
                <p class="mt-3 max-w-lg text-sm leading-6 text-[#4D5145]">Organiza tus prendas y descubre nuevas combinaciones para cada día.</p>
            </section>

            <section class="mt-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-[#B7A18D]">{{ wardrobe.totalGarments }} prendas</p>
                        <h2 class="text-xl font-semibold">Tu colección</h2>
                    </div>
                    <form class="flex w-full max-w-sm" @submit.prevent="searchGarments">
                        <input v-model="search" type="search" placeholder="Buscar prendas" aria-label="Buscar prendas" class="w-full rounded-2xl border border-[#E9D9C9] bg-white px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-[#4D5145]" />
                    </form>
                </div>

                <div class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Filtrar por categoría">
                    <button v-for="category in categories" :key="category.value" class="shrink-0 rounded-full border px-4 py-2 text-sm transition" :class="wardrobe.filters.category === category.value ? 'border-[#4D5145] bg-[#4D5145] text-white' : 'border-[#E9D9C9] bg-white text-[#4D5145]'" @click="wardrobe.setCategory(category.value)">
                        {{ category.label }}
                    </button>
                </div>

                <p v-if="wardrobe.error" class="mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-700">{{ wardrobe.error }}</p>
                <div v-else-if="wardrobe.loading" class="mt-8 text-center text-sm text-[#B7A18D]">Cargando tu armario...</div>
                <div v-else-if="!wardrobe.garments.length" class="mt-8 rounded-3xl bg-white p-10 text-center shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#E9D9C9] text-2xl">＋</div>
                    <h3 class="mt-4 text-lg font-semibold">Tu armario empieza aquí</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-[#6E695F]">Añade tu primera prenda para empezar a crear looks que encajen contigo.</p>
                    <button class="mt-5 rounded-full bg-[#4D5145] px-5 py-3 text-sm font-medium text-white" @click="showGarmentForm = true">Añadir mi primera prenda</button>
                </div>
                <div v-else class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <article v-for="garment in wardrobe.garments" :key="garment.id" class="overflow-hidden rounded-3xl bg-white shadow-sm" @click="selectedGarment = garment">
                        <div class="relative flex aspect-[4/5] items-center justify-center bg-[#E9D9C9]">
                            <img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" />
                            <span v-else class="text-4xl text-[#B7A18D]">✦</span>
                            <button class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-lg" :aria-label="garment.is_favorite ? 'Quitar de favoritos' : 'Añadir a favoritos'" @click.stop="wardrobe.toggleFavorite(garment)">{{ garment.is_favorite ? '♥' : '♡' }}</button>
                        </div>
                        <div class="p-4">
                            <h3 class="truncate text-sm font-semibold">{{ garment.name }}</h3>
                            <p class="mt-1 text-xs text-[#8E857A]">{{ garment.category }} · {{ garment.primary_color }}</p>
                        </div>
                    </article>
                </div>
            </section>
        </main>

        <div v-if="selectedGarment" class="fixed inset-0 z-20 flex items-end justify-center bg-[#282724]/40 sm:items-center sm:p-6" @click.self="selectedGarment = null">
            <section class="w-full rounded-t-3xl bg-white p-6 sm:max-w-md sm:rounded-3xl">
                <div class="flex items-center justify-between"><h2 class="text-xl font-semibold">{{ selectedGarment.name }}</h2><button aria-label="Cerrar" @click="selectedGarment = null">×</button></div>
                <img v-if="selectedGarment.image_path" :src="wardrobe.imageUrl(selectedGarment.id)" :alt="selectedGarment.name" class="mt-5 aspect-[4/3] w-full rounded-2xl object-cover" />
                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt class="text-[#8E857A]">Categoría</dt><dd class="mt-1 font-medium">{{ selectedGarment.category }}</dd></div><div><dt class="text-[#8E857A]">Color</dt><dd class="mt-1 font-medium">{{ selectedGarment.primary_color }}</dd></div><div><dt class="text-[#8E857A]">Estilo</dt><dd class="mt-1 font-medium">{{ selectedGarment.style || 'Sin definir' }}</dd></div><div><dt class="text-[#8E857A]">Marca</dt><dd class="mt-1 font-medium">{{ selectedGarment.brand || 'Sin marca' }}</dd></div></dl>
                <button class="mt-5 w-full rounded-2xl border border-[#E9D9C9] px-4 py-3 text-sm font-medium" @click="editingGarment = selectedGarment; selectedGarment = null">Editar prenda</button><button class="mt-3 w-full rounded-2xl border border-red-200 px-4 py-3 text-sm font-medium text-red-700" @click="wardrobe.removeGarment(selectedGarment.id); selectedGarment = null">Eliminar prenda</button>
            </section>
        </div>
        <UserManagement v-if="showUserManagement" @close="showUserManagement = false" />
        <ProfilePanel v-if="showProfile" @close="showProfile = false" />

        <div v-if="showGarmentForm" class="fixed inset-0 z-20 flex items-end justify-center bg-[#282724]/40 sm:items-center sm:p-6" @click.self="showGarmentForm = false">
            <section class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-lg sm:rounded-3xl">
                <div class="mb-5 flex items-center justify-between"><h2 class="text-xl font-semibold">Añadir prenda</h2><button aria-label="Cerrar" @click="showGarmentForm = false">×</button></div>
                <GarmentForm @cancel="showGarmentForm = false" @saved="showGarmentForm = false; wardrobe.loadGarments()" />
            </section>
        </div>
        <div v-if="editingGarment" class="fixed inset-0 z-20 flex items-end justify-center bg-[#282724]/40 sm:items-center sm:p-6" @click.self="editingGarment = null"><section class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-lg sm:rounded-3xl"><div class="mb-5 flex items-center justify-between"><h2 class="text-xl font-semibold">Editar prenda</h2><button @click="editingGarment = null">×</button></div><GarmentForm :garment="editingGarment" @cancel="editingGarment = null" @saved="editingGarment = null; wardrobe.loadGarments()" /></section></div>

        <nav class="fixed inset-x-0 bottom-0 border-t border-[#E9D9C9] bg-white/95 px-5 py-3 backdrop-blur sm:hidden" aria-label="Navegación principal">
            <div class="mx-auto flex max-w-md items-center justify-around text-xs text-[#8E857A]"><button :class="activeSection === 'dashboard' ? 'font-semibold text-[#4D5145]' : ''" @click="activeSection = 'dashboard'">⌂ Inicio</button><button :class="activeSection === 'wardrobe' ? 'font-semibold text-[#4D5145]' : ''" @click="activeSection = 'wardrobe'">♧ Armario</button><button :class="activeSection === 'outfits' ? 'font-semibold text-[#4D5145]' : ''" @click="activeSection = 'outfits'">✦ Looks</button><button @click="showProfile = true">◯ Perfil</button><button v-if="auth.user?.is_superadmin" @click="showUserManagement = true">⚙ Configuración</button></div>
        </nav>
    </div>
</template>
