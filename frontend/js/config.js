// js/config.js
const isLocalhost = Boolean(
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1'
);

const currentApiUrl = isLocalhost
    ? 'http://localhost:8080/api'
    : 'https://b2v-backend.onrender.com/api';

// On expose les deux noms pour être compatible avec tous vos fichiers JS
window.API_URL = currentApiUrl;
window.API_BASE_URL = currentApiUrl;