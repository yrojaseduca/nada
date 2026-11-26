const apiRequest = async (path, options = {}) => {
    const token = localStorage.getItem('nada_auth_token');
    const { headers = {}, ...requestOptions } = options;
    const defaultHeaders = options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' };
    const response = await fetch(`/api${path}`, {
        headers: { ...defaultHeaders, ...(token ? { Authorization: `Bearer ${token}` } : {}), ...headers },
        ...requestOptions
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.message || 'No se ha podido completar la operación.');
    }
    return payload;
};

export const garmentService = {
    async list(filters = {}) {
        const params = new URLSearchParams();
        if (filters.category) params.set('category', filters.category);
        if (filters.search) params.set('search', filters.search);
        const query = params.toString();
        return apiRequest(`/garments${query ? `?${query}` : ''}`);
    },
    create(data) {
        const multipart = data instanceof FormData;
        return apiRequest('/garments', {
            method: 'POST',
            headers: multipart ? {} : { 'Content-Type': 'application/json' },
            body: multipart ? data : JSON.stringify(data)
        });
    },
    createMany(category, images, enhanceImage = true, smoothWrinkles = false) {
        const data = new FormData();
        data.append('category', category);
        data.append('enhance_image', enhanceImage ? '1' : '0');
        data.append('smooth_wrinkles', smoothWrinkles ? '1' : '0');
        images.forEach((image) => data.append('images[]', image));
        return apiRequest('/garments/bulk', { method: 'POST', body: data });
    },
    async previewImage(image, enhanceImage = true, smoothWrinkles = false) {
        const data = new FormData();
        data.append('image', image);
        data.append('enhance_image', enhanceImage ? '1' : '0');
        data.append('smooth_wrinkles', smoothWrinkles ? '1' : '0');
        const token = localStorage.getItem('nada_auth_token');
        const response = await fetch('/api/garments/preview-image', {
            method: 'POST',
            headers: token ? { Authorization: `Bearer ${token}` } : {},
            body: data
        });
        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'No se pudo generar la vista previa.');
        }
        return URL.createObjectURL(await response.blob());
    },
    remove(id) {
        return apiRequest(`/garments/${id}`, { method: 'DELETE' });
    },
    update(id, data) {
        return apiRequest(`/garments/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
    },
    toggleFavorite(id) {
        return apiRequest(`/garments/${id}/favorite`, { method: 'POST' });
    },
    imageUrl(id) {
        const token = localStorage.getItem('nada_auth_token');
        return `/api/garments/${id}/image${token ? `?token=${encodeURIComponent(token)}` : ''}`;
    }
};
