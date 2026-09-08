/**
 * Wrapper simple sobre fetch() para hablar con la API PHP.
 * Todas las rutas son relativas a /api/.
 */
const API_BASE = '/api';

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
        apiRequest('expedientes.php', { method: 'POST', body: JSON.stringify(payload) }),

    actualizarExpediente: (id, payload) =>
        apiRequest(`expedientes.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) }),

    eliminarExpediente: (id) =>
        apiRequest(`expedientes.php?id=${id}`, { method: 'DELETE' }),

    listarNotarias: () => apiRequest('notarias.php'),

    dashboardStats: () => apiRequest('dashboard_stats.php'),
};

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
