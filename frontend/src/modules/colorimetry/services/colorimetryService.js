const request = async (path, options = {}) => {
    const token = localStorage.getItem('nada_auth_token');
    const response = await fetch(`/api${path}`, {
        headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}), ...(options.headers || {}) },
        ...options
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'No se ha podido completar la operación.');
    return payload;
};

export const colorimetryService = {
    get() {
        return request('/colorimetry');
    },
    save(image, result) {
        const body = new FormData();
        body.append('image', image);
        body.append('result', JSON.stringify(result));
        return request('/colorimetry', { method: 'POST', body });
    },
    remove() {
        return request('/colorimetry', { method: 'DELETE' });
    },
    imageUrl() {
        const token = localStorage.getItem('nada_auth_token');
        return `/api/colorimetry/image?token=${encodeURIComponent(token || '')}`;
    }
};
