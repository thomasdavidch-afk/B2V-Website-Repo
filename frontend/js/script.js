// ==========================================================================
// 1. DÉCODAGE DU TOKEN JWT
// ==========================================================================
function parseJwt(token) {
    if (!token) return null;
    try {
        const base64Url = token.split('.')[1];
        const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        const jsonPayload = decodeURIComponent(
            atob(base64)
                .split('')
                .map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
                .join('')
        );
        return JSON.parse(jsonPayload);
    } catch {
        return null;
    }
}

// ==========================================================================
// 2. MISE À JOUR DYNAMIQUE DE LA NAVBAR
// ==========================================================================
export function updateNavbar() {
    const token = localStorage.getItem('jwt_token');
    const navSessions = document.getElementById('nav-sessions');
    const authContainer = document.getElementById('nav-auth-container');

    if (!authContainer) return;

    if (token) {
        const payload = parseJwt(token);
        const roles = payload?.roles || [];
        const isAdmin = roles.includes('ROLE_ADMIN');

        // Afficher l'onglet "Achat de sessions" si connecté
        if (navSessions) {
            navSessions.classList.remove('d-none');
        }

        // Définir la cible du bouton selon le rôle
        const accountUrl = isAdmin ? '/accountAdmin' : '/accountUser';
        const accountLabel = isAdmin ? 'Admin' : 'Mon Compte';
        const iconClass = isAdmin ? 'bi-shield-lock-fill' : 'bi-person-circle';

        // Injecter Mon Compte / Admin + Bouton Déconnexion
        authContainer.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <a href="${accountUrl}" onclick="route()" class="btn btn-outline-b2v btn-sm px-3">
                    <i class="bi ${iconClass} me-1"></i>${accountLabel}
                </a>
                <button id="btn-navbar-logout" class="btn btn-sm btn-outline-danger px-2" title="Déconnexion">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </div>
        `;
    } else {
        // Cacher l'onglet "Achat de sessions"
        if (navSessions) {
            navSessions.classList.add('d-none');
        }

        // Remettre le bouton standard Connexion
        authContainer.innerHTML = `
            <a href="/signin" onclick="route()" class="btn btn-outline-b2v btn-sm px-3" id="btn-login">Connexion</a>
        `;
    }
}

// =========================================================================
// 3. GESTION DU CLIC SUR LA DÉCONNEXION
// =========================================================================
document.addEventListener('click', (event) => {
    // Clic sur l'icône ou le bouton de déconnexion
    const logoutBtn = event.target.closest('#btn-navbar-logout') || event.target.closest('#btn-logout');
    const isLogoutText = event.target.textContent && event.target.textContent.trim().toLowerCase() === 'déconnexion';

    if (logoutBtn || (event.target.tagName === 'BUTTON' && isLogoutText)) {
        event.preventDefault();

        // 1. Suppression du token
        localStorage.removeItem('jwt_token');

        // 2. Mise à jour visuelle de la barre
        updateNavbar();

        // 3. Redirection vers la page de connexion
        window.history.pushState({}, "", "/signin");
        
        // Charger le contenu de la nouvelle URL
        if (typeof window.LoadContentPage === 'function') {
            window.LoadContentPage();
        } else {
            window.dispatchEvent(new PopStateEvent('popstate'));
        }
    }
});