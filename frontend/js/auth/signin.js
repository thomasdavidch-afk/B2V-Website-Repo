// js/auth/signin.js

// 1. Définition de l'URL API
const API_URL = 'http://localhost:8080/api';

// 2. Fonction utilitaire pour décoder le JWT
function parseJwt(token) {
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
    } catch (e) {
        console.error('Erreur décodage token:', e);
        return null;
    }
}

// 3. Récupération des éléments DOM injectés par le routeur
const formLogin = document.getElementById('form-login');
const inputEmail = document.getElementById('login-email');
const inputPassword = document.getElementById('login-password');

if (formLogin) {
    formLogin.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        const email = inputEmail.value.trim();
        const password = inputPassword.value;
        const submitBtn = formLogin.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;

        // Feedback visuel de chargement
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connexion...';

        // Supprime une éventuelle alerte d'erreur précédente
        const oldAlert = formLogin.querySelector('.alert-login-error');
        if (oldAlert) oldAlert.remove();

        try {
            const response = await fetch(`${API_URL}/login_check`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Identifiants invalides');
            }

            // Enregistrement du token JWT
            localStorage.setItem('jwt_token', data.token);

            // Décodage des rôles
            const payload = parseJwt(data.token);
            const roles = payload?.roles || [];

            // Détermination de la cible et redirection
            const targetPath = roles.includes('ROLE_ADMIN') ? '/accountAdmin' : '/accountUser';
            window.location.href = targetPath;

        } catch (error) {
            console.error('Erreur de connexion :', error.message);

            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger rounded-3 py-2 px-3 small mt-3 mb-0 alert-login-error';
            errorAlert.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${error.message}`;
            formLogin.appendChild(errorAlert);

            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
}