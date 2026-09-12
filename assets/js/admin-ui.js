/**
 * UI compartida del área de administración:
 *  - Menú de usuario en la topbar (nombre + "Editar mi perfil" + "Cerrar sesión")
 *  - Modal para editar el perfil propio (nombre, correo, usuario, contraseña)
 *
 * Requiere: api.js cargado antes. Se auto-inicializa al cargar el DOM.
 */
(function () {
    'use strict';

    const LOGIN_URL = '/admin/login.html';

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function build() {
        const host = document.querySelector('.topbar .topbar-user');
        if (!host) return;

        // ----- Menú de usuario -----
        const menu = document.createElement('div');
        menu.className = 'user-menu';
        menu.innerHTML = `
            <button class="user-menu-trigger" id="userMenuBtn" type="button" aria-haspopup="true" aria-expanded="false">
                <span class="avatar" id="umAvatar">👤</span>
                <span id="nombreUsuario">${esc(document.getElementById('nombreUsuario')?.textContent || 'Cuenta')}</span>
                <span class="caret">▼</span>
            </button>
            <div class="user-menu-dropdown" id="userMenuDropdown" hidden>
                <div class="u-head">
                    <div class="u-name" id="umName">—</div>
                    <div class="u-role" id="umRole"></div>
                </div>
                <button type="button" id="umEditProfile">👤&nbsp; Editar mi perfil</button>
                <button type="button" class="danger" id="umLogout">↪&nbsp; Cerrar sesión</button>
            </div>`;
        host.replaceWith(menu);

        // ----- Modal de perfil -----
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.id = 'perfilModal';
        overlay.hidden = true;
        overlay.innerHTML = `
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="perfilModalTitle">
                <div class="modal-head">
                    <h3 id="perfilModalTitle">Editar mi perfil</h3>
                    <button class="modal-close" type="button" data-cerrar>&times;</button>
                </div>
                <div class="modal-body">
                    <div id="perfilAlert"></div>
                    <div class="form-group">
                        <label for="pf_nombre">Nombre</label>
                        <input type="text" id="pf_nombre" autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="pf_email">Correo</label>
                        <input type="email" id="pf_email" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="pf_username">Usuario de acceso</label>
                        <input type="text" id="pf_username" autocomplete="username" placeholder="opcional">
                    </div>

                    <p class="modal-section-label">Cambiar contraseña (opcional)</p>
                    <div class="form-group">
                        <label for="pf_pass_actual">Contraseña actual</label>
                        <input type="password" id="pf_pass_actual" autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="pf_pass_nueva">Nueva contraseña</label>
                        <input type="password" id="pf_pass_nueva" autocomplete="new-password" placeholder="mín. 6 caracteres">
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-outline" type="button" data-cerrar>Cancelar</button>
                    <button class="btn btn-primary" type="button" id="pf_guardar">Guardar cambios</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);

        wire();
        cargarSesion();
    }

    function wire() {
        const btn = document.getElementById('userMenuBtn');
        const dd = document.getElementById('userMenuDropdown');
        const overlay = document.getElementById('perfilModal');

        const cerrarMenu = () => { dd.hidden = true; btn.setAttribute('aria-expanded', 'false'); };
        const toggleMenu = () => {
            dd.hidden = !dd.hidden;
            btn.setAttribute('aria-expanded', String(!dd.hidden));
        };

        btn.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
        document.addEventListener('click', (e) => { if (!dd.hidden && !dd.contains(e.target)) cerrarMenu(); });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            cerrarMenu();
            if (!overlay.hidden) cerrarModal();
        });

        document.getElementById('umLogout').addEventListener('click', logout);
        document.getElementById('umEditProfile').addEventListener('click', () => { cerrarMenu(); abrirModal(); });

        overlay.querySelectorAll('[data-cerrar]').forEach(el => el.addEventListener('click', cerrarModal));
        overlay.addEventListener('click', (e) => { if (e.target === overlay) cerrarModal(); });
        document.getElementById('pf_guardar').addEventListener('click', guardarPerfil);
    }

    async function logout() {
        try { await Api.logout(); } catch (e) { /* ignore */ }
        try { ApiCache.clear(); } catch (e) { /* ignore */ }
        window.location.href = LOGIN_URL;
    }

    async function cargarSesion() {
        try {
            const s = await Api.session();
            if (!s.autenticado) return;
            const nombre = s.usuario.nombre || 'Cuenta';
            document.getElementById('nombreUsuario').textContent = nombre;
            document.getElementById('umName').textContent = nombre;
            document.getElementById('umRole').textContent = s.usuario.rol || '';
            document.getElementById('umAvatar').textContent = (nombre.trim()[0] || '👤').toUpperCase();
        } catch (e) { /* la propia página ya maneja la sesión */ }
    }

    function pfAlert(msg, tipo) {
        document.getElementById('perfilAlert').innerHTML =
            msg ? `<div class="alert alert-${tipo}">${esc(msg)}</div>` : '';
    }

    async function abrirModal() {
        const overlay = document.getElementById('perfilModal');
        pfAlert('');
        ['pf_pass_actual', 'pf_pass_nueva'].forEach(id => document.getElementById(id).value = '');
        overlay.hidden = false;
        try {
            const { data } = await Api.perfil();
            document.getElementById('pf_nombre').value = data.nombre || '';
            document.getElementById('pf_email').value = data.email || '';
            document.getElementById('pf_username').value = data.username || '';
            overlay._orig = data;
        } catch (e) {
            pfAlert('No se pudo cargar tu información.', 'error');
        }
    }

    function cerrarModal() {
        document.getElementById('perfilModal').hidden = true;
    }

    async function guardarPerfil() {
        const overlay = document.getElementById('perfilModal');
        const orig = overlay._orig || {};
        const nombre = document.getElementById('pf_nombre').value.trim();
        const email = document.getElementById('pf_email').value.trim();
        const username = document.getElementById('pf_username').value.trim();
        const passActual = document.getElementById('pf_pass_actual').value;
        const passNueva = document.getElementById('pf_pass_nueva').value;

        if (!nombre) { pfAlert('El nombre es obligatorio.', 'error'); return; }

        const payload = {};
        if (nombre !== (orig.nombre || '')) payload.nombre = nombre;
        if (email !== (orig.email || '')) payload.email = email;
        if (username !== (orig.username || '')) payload.username = username;
        if (passNueva) {
            if (!passActual) { pfAlert('Escribe tu contraseña actual para cambiarla.', 'error'); return; }
            payload.password = passNueva;
            payload.password_actual = passActual;
        }

        if (!Object.keys(payload).length) { pfAlert('No hay cambios que guardar.', 'error'); return; }

        const btn = document.getElementById('pf_guardar');
        btn.disabled = true;
        try {
            await Api.actualizarPerfil(payload);
            if (payload.nombre) {
                document.getElementById('nombreUsuario').textContent = payload.nombre;
                document.getElementById('umName').textContent = payload.nombre;
                document.getElementById('umAvatar').textContent = payload.nombre.trim()[0].toUpperCase();
            }
            pfAlert('Perfil actualizado.', 'success');
            setTimeout(cerrarModal, 800);
        } catch (e) {
            pfAlert(e && e.error ? e.error : 'No se pudo actualizar el perfil.', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }
})();
