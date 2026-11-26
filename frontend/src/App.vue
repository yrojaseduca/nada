<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useWardrobeStore } from './modules/wardrobe/store/wardrobeStore';
import GarmentForm from './modules/wardrobe/components/GarmentForm.vue';
import LoginView from './modules/auth/components/LoginView.vue';
import { useAuthStore } from './modules/auth/store/authStore';
import UserManagement from './modules/auth/components/UserManagement.vue';
import ProfilePanel from './modules/auth/components/ProfilePanel.vue';
import OutfitsView from './modules/outfits/components/OutfitsView.vue';
import DashboardView from './modules/dashboard/components/DashboardView.vue';
import ColorimetryView from './modules/colorimetry/components/ColorimetryView.vue';

const wardrobe = useWardrobeStore();
const auth = useAuthStore();
const search = ref('');
const showGarmentForm = ref(false);
const selectedGarment = ref(null);
const editingGarment = ref(null);
const showUserManagement = ref(false);
const showProfile = ref(false);
const showWardrobeFilters = ref(false);
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
const categoryLabels = Object.fromEntries(categories.filter((category) => category.value).map((category) => [category.value, category.label]));
const seasons = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
const styles = ['Casual', 'Elegante', 'Minimalista', 'Deportivo', 'Bohemio', 'Formal'];
const colors = ['Negro', 'Blanco', 'Gris', 'Azul', 'Rojo', 'Verde', 'Marrón', 'Beige', 'Rosa', 'Amarillo'];
const colorHex = { Negro: '#282724', Blanco: '#FFFFFF', Gris: '#8E857A', Azul: '#426A8C', Rojo: '#B95750', Verde: '#668267', Marrón: '#8B654D', Beige: '#DCCBB9', Rosa: '#D99BA4', Amarillo: '#D7B84B' };
const activeFilterCount = computed(() => [wardrobe.filters.category, wardrobe.filters.search, wardrobe.filters.favorites, wardrobe.filters.color, wardrobe.filters.season, wardrobe.filters.style].filter(Boolean).length);
let searchTimer;

const searchGarments = () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => wardrobe.setSearch(search.value), 300);
};
const clearGarmentFilters = () => {
    search.value = '';
    wardrobe.clearFilters();
};
const setWardrobeFilter = (name, value) => wardrobe.setFilter(name, value);
onMounted(() => wardrobe.loadGarments());
onMounted(() => auth.restore());
watch(() => auth.isAuthenticated, (authenticated) => {
    if (authenticated) wardrobe.loadGarments();
});
onBeforeUnmount(() => window.clearTimeout(searchTimer));
</script>

<template>
    <LoginView v-if="!auth.isAuthenticated" />
    <div v-else class="min-h-screen bg-[var(--color-canvas)] text-[var(--color-text)]">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[var(--color-primary)]">Nada</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Mi armario</h1>
            </div>
            <button class="rounded-full bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-white shadow-sm" @click="showGarmentForm = true">+ Añadir prenda</button>
        </header>

        <DashboardView v-if="activeSection === 'dashboard'" @go-outfits="activeSection = 'outfits'" @go-wardrobe="activeSection = 'wardrobe'" />
        <OutfitsView v-else-if="activeSection === 'outfits'" />
        <ColorimetryView v-else-if="activeSection === 'colorimetry'" />
        <main v-else class="mx-auto max-w-6xl px-5 pb-28 sm:px-8">
            <section class="rounded-3xl bg-[var(--color-border)] p-6 sm:p-8">
                <p class="text-sm text-[var(--color-primary)]">Tu estilo, a tu manera</p>
                <h2 class="mt-2 max-w-xl text-3xl font-semibold leading-tight sm:text-4xl">Cada prenda cuenta una parte de tu historia.</h2>
                <p class="mt-3 max-w-lg text-sm leading-6 text-[var(--color-primary)]">Organiza tus prendas y descubre nuevas combinaciones para cada día.</p>
            </section>

            <section class="mt-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-[var(--color-accent)]">{{ wardrobe.totalGarments }} {{ wardrobe.totalGarments === 1 ? 'prenda encontrada' : 'prendas encontradas' }}</p>
                        <h2 class="text-xl font-semibold">Tu colección</h2>
                    </div>
                    <form class="flex w-full max-w-sm" @submit.prevent="searchGarments">
                        <input v-model="search" type="search" placeholder="Buscar prendas" aria-label="Buscar prendas" class="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-[var(--color-primary)]" @input="searchGarments" />
                    </form>
                </div>

                <div class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Filtrar por categoría y favoritos">
                    <button v-for="category in categories" :key="category.value" class="shrink-0 rounded-full border px-4 py-2 text-sm transition" :class="wardrobe.filters.category === category.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)] bg-white text-[var(--color-primary)]'" @click="wardrobe.setCategoryFilter(category.value)">
                        {{ category.label }}
                    </button>
                    <button class="shrink-0 rounded-full border px-4 py-2 text-sm transition" :class="wardrobe.favoriteOnly ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)] bg-white text-[var(--color-primary)]'" @click="wardrobe.toggleFavorites">♥ Favoritas</button>
                    <button class="shrink-0 rounded-full border border-[var(--color-border)] bg-white px-4 py-2 text-sm text-[var(--color-primary)]" :aria-expanded="showWardrobeFilters" @click="showWardrobeFilters = !showWardrobeFilters">Más filtros <span aria-hidden="true">▾</span><span v-if="activeFilterCount" class="ml-1 rounded-full bg-[var(--color-primary)] px-1.5 py-0.5 text-[10px] text-white">{{ activeFilterCount }}</span></button>
                </div>
                <div v-if="showWardrobeFilters" class="mt-3 grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-3">
                    <div class="rounded-xl border border-[var(--color-border)] p-3 sm:col-span-3"><p class="text-xs font-medium text-[var(--color-text-muted)]">Color</p><div class="mt-2 flex flex-wrap gap-2"><button class="rounded-full border px-3 py-1.5 text-xs" :class="!wardrobe.filters.color ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)] text-[var(--color-primary)]'" @click="setWardrobeFilter('color', '')">Todos</button><button v-for="color in colors" :key="color" class="flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-xs" :class="wardrobe.filters.color === color ? 'border-[var(--color-primary)] bg-[var(--color-surface-muted)] text-[var(--color-primary)]' : 'border-[var(--color-border)] text-[var(--color-text-muted)]'" @click="setWardrobeFilter('color', color)"><span class="h-3 w-3 rounded-full border border-black/10" :style="{ backgroundColor: colorHex[color] }" aria-hidden="true"></span>{{ color }}</button></div></div>
                    <select :value="wardrobe.filters.season" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 pr-8 text-sm" aria-label="Filtrar por temporada" @change="setWardrobeFilter('season', $event.target.value)"><option value="">Todas las temporadas</option><option v-for="season in seasons" :key="season">{{ season }}</option></select>
                    <select :value="wardrobe.filters.style" class="rounded-xl border border-[var(--color-border)] bg-white px-3 py-2 pr-8 text-sm" aria-label="Filtrar por estilo" @change="setWardrobeFilter('style', $event.target.value)"><option value="">Todos los estilos</option><option v-for="style in styles" :key="style">{{ style }}</option></select>
                    <button v-if="activeFilterCount" class="text-left text-xs font-medium text-[var(--color-primary)] underline underline-offset-2 sm:col-span-3" @click="clearGarmentFilters">Limpiar todos los filtros</button>
                </div>

                <p v-if="wardrobe.error" class="mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-700">{{ wardrobe.error }}</p>
                <div v-else-if="wardrobe.loading" class="mt-8 text-center text-sm text-[var(--color-accent)]">Cargando tu armario...</div>
                <div v-else-if="!wardrobe.visibleGarments.length && (wardrobe.filters.search || wardrobe.filters.category || wardrobe.favoriteOnly || wardrobe.filters.color || wardrobe.filters.season || wardrobe.filters.style)" class="mt-8 rounded-3xl bg-white p-10 text-center shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[var(--color-surface-muted)] text-2xl text-[var(--color-accent)]">⌕</div>
                    <h3 class="mt-4 text-lg font-semibold">No encontramos prendas</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-[var(--color-text-muted)]">Prueba con otra búsqueda o elimina los filtros para ver todo tu armario.</p>
                    <button class="mt-5 rounded-full border border-[var(--color-border)] px-5 py-3 text-sm font-medium text-[var(--color-primary)]" @click="clearGarmentFilters">Limpiar filtros</button>
                </div>
                <div v-else-if="!wardrobe.visibleGarments.length" class="mt-8 rounded-3xl bg-white p-10 text-center shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[var(--color-border)] text-2xl">＋</div>
                    <h3 class="mt-4 text-lg font-semibold">Tu armario empieza aquí</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-[var(--color-text-muted)]">Añade tu primera prenda para empezar a crear looks que encajen contigo.</p>
                    <button class="mt-5 rounded-full bg-[var(--color-primary)] px-5 py-3 text-sm font-medium text-white" @click="showGarmentForm = true">Añadir mi primera prenda</button>
                </div>
                <div v-else class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <article v-for="garment in wardrobe.visibleGarments" :key="garment.id" class="group cursor-pointer overflow-hidden rounded-3xl bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md" @click="selectedGarment = garment">
                        <div class="relative flex aspect-[4/5] items-center justify-center bg-[var(--color-border)]">
                            <img v-if="garment.image_path" :src="wardrobe.imageUrl(garment.id)" :alt="garment.name" class="h-full w-full object-cover" />
                            <span v-else class="text-4xl text-[var(--color-accent)]" aria-hidden="true">✦</span>
                            <span class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[11px] font-medium text-[var(--color-primary)]">{{ categoryLabels[garment.category] || garment.category }}</span>
                            <button class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-lg transition hover:scale-110" :aria-label="garment.is_favorite ? 'Quitar de favoritos' : 'Añadir a favoritos'" @click.stop="wardrobe.toggleFavorite(garment)">{{ garment.is_favorite ? '♥' : '♡' }}</button>
                        </div>
                        <div class="p-4">
                            <h3 class="truncate text-sm font-semibold">{{ garment.name }}</h3>
                            <p class="mt-1 text-xs text-[var(--color-text-subtle)]">{{ garment.primary_color }}<span v-if="garment.brand"> · {{ garment.brand }}</span></p>
                            <div v-if="garment.season || garment.style" class="mt-3 flex flex-wrap gap-1.5">
                                <span v-if="garment.season" class="rounded-full bg-[var(--color-surface-muted)] px-2.5 py-1 text-[11px] text-[var(--color-text-muted)]">{{ garment.season }}</span>
                                <span v-if="garment.style" class="rounded-full bg-[var(--color-surface-muted)] px-2.5 py-1 text-[11px] text-[var(--color-text-muted)]">{{ garment.style }}</span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </main>

        <div v-if="selectedGarment" class="fixed inset-0 z-20 flex items-end justify-center bg-[var(--color-text)]/40 sm:items-center sm:p-6" @click.self="selectedGarment = null">
            <section class="w-full rounded-t-3xl bg-white p-6 sm:max-w-md sm:rounded-3xl">
                <div class="flex items-center justify-between"><h2 class="text-xl font-semibold">{{ selectedGarment.name }}</h2><button aria-label="Cerrar" @click="selectedGarment = null">×</button></div>
                <img v-if="selectedGarment.image_path" :src="wardrobe.imageUrl(selectedGarment.id)" :alt="selectedGarment.name" class="mt-5 aspect-[4/3] w-full rounded-2xl object-cover" />
                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt class="text-[var(--color-text-subtle)]">Categoría</dt><dd class="mt-1 font-medium">{{ selectedGarment.category }}</dd></div><div><dt class="text-[var(--color-text-subtle)]">Color</dt><dd class="mt-1 font-medium">{{ selectedGarment.primary_color }}</dd></div><div><dt class="text-[var(--color-text-subtle)]">Estilo</dt><dd class="mt-1 font-medium">{{ selectedGarment.style || 'Sin definir' }}</dd></div><div><dt class="text-[var(--color-text-subtle)]">Marca</dt><dd class="mt-1 font-medium">{{ selectedGarment.brand || 'Sin marca' }}</dd></div></dl>
                <button class="mt-5 w-full rounded-2xl border border-[var(--color-border)] px-4 py-3 text-sm font-medium" @click="editingGarment = selectedGarment; selectedGarment = null">Editar prenda</button><button class="mt-3 w-full rounded-2xl border border-red-200 px-4 py-3 text-sm font-medium text-red-700" @click="wardrobe.removeGarment(selectedGarment.id); selectedGarment = null">Eliminar prenda</button>
            </section>
        </div>
        <UserManagement v-if="showUserManagement" @close="showUserManagement = false" />
        <ProfilePanel v-if="showProfile" @close="showProfile = false" @open-colorimetry="showProfile = false; activeSection = 'colorimetry'" />

        <div v-if="showGarmentForm" class="fixed inset-0 z-20 flex items-end justify-center bg-[var(--color-text)]/40 sm:items-center sm:p-6" @click.self="showGarmentForm = false">
            <section class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-lg sm:rounded-3xl">
                <div class="mb-5 flex items-center justify-between"><h2 class="text-xl font-semibold">Añadir prenda</h2><button aria-label="Cerrar" @click="showGarmentForm = false">×</button></div>
                <GarmentForm @cancel="showGarmentForm = false" @saved="wardrobe.loadGarments()" />
            </section>
        </div>
        <div v-if="editingGarment" class="fixed inset-0 z-20 flex items-end justify-center bg-[var(--color-text)]/40 sm:items-center sm:p-6" @click.self="editingGarment = null"><section class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-lg sm:rounded-3xl"><div class="mb-5 flex items-center justify-between"><h2 class="text-xl font-semibold">Editar prenda</h2><button @click="editingGarment = null">×</button></div><GarmentForm :garment="editingGarment" @cancel="editingGarment = null" @saved="editingGarment = null; wardrobe.loadGarments()" /></section></div>

        <nav class="fixed inset-x-0 bottom-0 border-t border-[var(--color-border)] bg-white/95 px-5 py-3 backdrop-blur sm:hidden" aria-label="Navegación principal">
            <div class="mx-auto flex max-w-md items-center gap-1 text-[13px] text-[var(--color-text-subtle)]"><button class="flex min-h-11 flex-1 items-center justify-center rounded-xl px-1 py-2 transition active:bg-[var(--color-surface-muted)]" :class="activeSection === 'dashboard' ? 'font-semibold text-[var(--color-primary)]' : ''" @click="activeSection = 'dashboard'">⌂ Inicio</button><button class="flex min-h-11 flex-1 items-center justify-center rounded-xl px-1 py-2 transition active:bg-[var(--color-surface-muted)]" :class="activeSection === 'wardrobe' ? 'font-semibold text-[var(--color-primary)]' : ''" @click="activeSection = 'wardrobe'">♧ Armario</button><button class="flex min-h-11 flex-1 items-center justify-center rounded-xl px-1 py-2 transition active:bg-[var(--color-surface-muted)]" :class="activeSection === 'outfits' ? 'font-semibold text-[var(--color-primary)]' : ''" @click="activeSection = 'outfits'">✦ Looks</button><button class="flex min-h-11 flex-1 items-center justify-center rounded-xl px-1 py-2 transition active:bg-[var(--color-surface-muted)]" @click="showProfile = true">⚙ Perfil</button><button v-if="auth.user?.is_superadmin" class="flex min-h-11 flex-1 items-center justify-center rounded-xl px-1 py-2 transition active:bg-[var(--color-surface-muted)]" @click="showUserManagement = true">⚙ Admin</button></div>
        </nav>
    </div>
</template>
