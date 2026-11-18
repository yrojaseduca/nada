const request = async (path, options = {}) => {
    const token = localStorage.getItem('nada_auth_token');
    const response = await fetch(`/api${path}`, { headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' }, ...options });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'No se ha podido completar la operación.');
    return payload;
};

export const userManagementService = {
    list: () => request('/admin/users'),
    toggleActive: (id) => request(`/admin/users/${id}/toggle-active`, { method: 'POST' })
};
