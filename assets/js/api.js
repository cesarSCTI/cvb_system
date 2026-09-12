/**
 * Wrapper simple sobre fetch() para hablar con la API PHP.
 * Todas las rutas son relativas a /api/.
 *
 * API_BASE se calcula a partir de la URL real de este script (no se asume
 * que el proyecto vive en la raíz del dominio), para que funcione igual
 * si se instala en midominio.com/, en un subdominio, o en una subcarpeta
 * tipo midominio.com/cvb/ (caso típico de cPanel).
 */
const API_BASE = (() => {
    try {
        // api.js vive en <raíz-app>/assets/js/api.js -> subir 2 niveles = <raíz-app>/api
        return new URL('../../api', document.currentScript.src).pathname.replace(/\/$/, '');
    } catch (e) {
        return '/api';
    }
})();

/**
 * Caché ligero en sessionStorage para pintar al instante datos ya vistos
 * (patrón stale-while-revalidate). Se limpia solo al cerrar la pestaña
 * o tras cualquier operación de escritura (ver Api.*).
 */
const ApiCache = {
    _k: (k) => 'cvb:cache:' + k,
    get(k) {
        try { const v = sessionStorage.getItem(this._k(k)); return v ? JSON.parse(v) : null; }
        catch (e) { return null; }
    },
    set(k, v) {
        try { sessionStorage.setItem(this._k(k), JSON.stringify(v)); } catch (e) { /* cuota / modo privado */ }
    },
    clear() {
        try {
            Object.keys(sessionStorage)
                .filter(k => k.startsWith('cvb:cache:'))
                .forEach(k => sessionStorage.removeItem(k));
        } catch (e) { /* noop */ }
    },
};

async function apiRequest(path, options = {}) {
    const res = await fetch(`${API_BASE}/${path}`, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        ...options,
    });

    let data = null;
    try {
        data = await res.json();
    } catch (e) {
        data = null;
    }

    if (res.status === 401) {
        // Sesión expirada o inexistente: mandar al login correspondiente
        const esAdmin = window.location.pathname.includes('/admin/');
        window.location.href = esAdmin ? '/admin/login.html' : '/cliente/login.html';
        return Promise.reject(data);
    }

    if (!res.ok) {
        return Promise.reject(data || { error: 'Error inesperado.' });
    }

    return data;
}

const Api = {
    login: (tipo, identificador, password) =>
        apiRequest('login.php', { method: 'POST', body: JSON.stringify({ tipo, identificador, password }) }),

    logout: () => apiRequest('logout.php', { method: 'POST' }),

    session: () => apiRequest('session.php'),

    listarExpedientes: (params = {}) => {
        const qs = new URLSearchParams(params).toString();
        return apiRequest(`expedientes.php?${qs}`);
    },

    obtenerExpediente: (id) => apiRequest(`expedientes.php?id=${id}`),

    crearExpediente: (payload) =>
        mutar(apiRequest('expedientes.php', { method: 'POST', body: JSON.stringify(payload) })),

    actualizarExpediente: (id, payload) =>
        mutar(apiRequest(`expedientes.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) })),

    eliminarExpediente: (id) =>
        mutar(apiRequest(`expedientes.php?id=${id}`, { method: 'DELETE' })),

    listarNotarias: () => apiRequest('notarias.php'),

    listarNotariasAdmin: () => apiRequest('notarias.php?todas=1'),

    crearNotaria: (payload) =>
        mutar(apiRequest('notarias.php', {
            method: 'POST',
            body: JSON.stringify(typeof payload === 'string' ? { nombre: payload } : payload),
        })),

    actualizarNotaria: (id, payload) =>
        mutar(apiRequest(`notarias.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) })),

    eliminarNotaria: (id) =>
        mutar(apiRequest(`notarias.php?id=${id}`, { method: 'DELETE' })),

    dashboardStats: () => apiRequest('dashboard_stats.php'),

    perfil: () => apiRequest('perfil.php'),

    actualizarPerfil: (payload) =>
        mutar(apiRequest('perfil.php', { method: 'PUT', body: JSON.stringify(payload) })),
};

/** Invalida el caché cuando una escritura tiene éxito. */
function mutar(promesa) {
    return promesa.then((r) => { ApiCache.clear(); return r; });
}

/** Formatea un número como moneda MXN. */
function formatCurrency(valor) {
    if (valor === null || valor === undefined || valor === '') return 'N/A';
    return '$' + Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Formatea "3" como "3 días" y null como "-". */
function formatDias(v) {
    if (v === null || v === undefined) return '-';
    return v + (v === 1 ? ' día' : ' días');
}

/** Formatea fecha ISO (yyyy-mm-dd) a dd/mm/yyyy. */
function formatFecha(v) {
    if (!v) return '-';
    const [y, m, d] = v.split('-');
    return `${parseInt(d)}/${parseInt(m)}/${y}`;
}
