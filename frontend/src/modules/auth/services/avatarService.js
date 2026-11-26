const token = () => localStorage.getItem('nada_auth_token') || '';

const request = async (path, options = {}) => {
    const response = await fetch(`/api${path}`, {
        headers: { Authorization: `Bearer ${token()}`, ...(options.headers || {}) },
        ...options
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'No se pudo completar la operación.');
    return payload;
};

export const avatarService = {
    get() {
        return request('/avatar');
    },
    save(image) {
        const body = new FormData();
        body.append('image', image);
        body.append('consent', '1');
        return request('/avatar', { method: 'POST', body });
    },
    remove() {
        return request('/avatar', { method: 'DELETE' });
    },
    imageUrl() {
        return `/api/avatar/image?token=${encodeURIComponent(token())}`;
    }
};
