const request = async (path, options = {}) => {
    const token = localStorage.getItem('nada_auth_token');
    const response = await fetch(`/api${path}`, { headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }, ...options });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'No se ha podido completar la operación.');
    return payload;
};

export const outfitService = {
    list: (occasion = '', style = '') => request(`/outfits?occasion=${encodeURIComponent(occasion)}&style=${encodeURIComponent(style)}`),
    create: (data) => request('/outfits', { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data) => request(`/outfits/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),
    remove: (id) => request(`/outfits/${id}`, { method: 'DELETE' }),
    toggleFavorite: (id) => request(`/outfits/${id}/favorite`, { method: 'POST' })
    ,
    suggestions: (occasion, style) => request(`/outfits/suggestions?occasion=${encodeURIComponent(occasion || '')}&style=${encodeURIComponent(style || '')}`)
};
