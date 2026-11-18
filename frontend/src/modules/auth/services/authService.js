const request = async (path, options = {}) => {
    const response = await fetch(`/api${path}`, {
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'No se ha podido completar la operación.');
    return payload;
};

export const authService = {
    login(email, password) {
        return request('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) });
    },
    me(token) {
        return request('/auth/me', { headers: { Authorization: `Bearer ${token}` } });
    },
    logout(token) {
        return request('/auth/logout', { method: 'POST', headers: { Authorization: `Bearer ${token}` } });
    },
    updateProfile(token, data) {
        return request('/auth/me', { method: 'PATCH', headers: { Authorization: `Bearer ${token}` }, body: JSON.stringify(data) });
    }
};
