// =============================================================================
// INITIALISATION DE LA PAGE MON COMPTE (USER)
// =============================================================================
window.initAccountUser = function() {
    // Configuration de l'API
    const API_BASE_URL = window.API_URL;
    const token = localStorage.getItem('jwt_token');

    // 1. Contrôle de sécurité Frontend
    if (!token) {
        window.location.href = '/signin';
        return;
    }

    // Helper pour requêtes authentifiées
    async function apiFetch(endpoint, options = {}) {
        const headers = {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
            ...(options.headers || {})
        };

        if (!(options.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(`${API_URL}${endpoint}`, {
            ...options,
            headers
        });

        if (response.status === 401) {
            localStorage.removeItem('jwt_token');
            window.location.href = '/signin';
            throw new Error('Session expirée');
        }

        return response;
    }

    // Formatage des dates
    function formatDate(dateString) {
        if (!dateString) return '-';
        try {
            const d = new Date(dateString);
            if (isNaN(d.getTime())) return dateString;
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch {
            return dateString;
        }
    }

    let currentUserData = null;

    // ==========================================
    // 2. CHARGEMENT DU PROFIL & DU SOLDE
    // ==========================================
    async function loadUserProfile() {
        try {
            // Profil utilisateur
            const resMe = await apiFetch('/me');
            if (resMe.ok) {
                currentUserData = await resMe.json();
                renderUserProfile(currentUserData);
            }

            // Solde du carnet (/api/carnets/me)
            loadUserCarnet();

            // Certificat médical (/api/adherent/certificat)
            loadUserCertificat();

            // Historique des transactions (/api/transactions/me)
            loadUserHistory();

        } catch (err) {
            console.error("Erreur chargement profil:", err);
        }
    }

    function renderUserProfile(user) {
        const prenom = user.prenom || 'Adhérent';
        const nom = user.nom || '';
        const initial = prenom.charAt(0).toUpperCase() || 'A';

        // Avatar et Titre
        const avatarEl = document.getElementById('user-avatar-initial');
        if (avatarEl) avatarEl.textContent = initial;

        const welcomeEl = document.getElementById('user-welcome-name');
        if (welcomeEl) welcomeEl.textContent = `Bonjour, ${prenom}`;

        // Statut Actif / Inactif
        const isActive = user.actif ?? user.isActive ?? user.is_active ?? true;
        const statusEl = document.getElementById('user-status-badge');
        if (statusEl) {
            if (isActive) {
                statusEl.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold me-2';
                statusEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Adhérent Actif';
            } else {
                statusEl.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill fw-semibold me-2';
                statusEl.innerHTML = '<i class="bi bi-slash-circle me-1"></i> Compte Inactif';
            }
        }

        // Remplissage du formulaire de profil
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val || '';
        };

        setVal('profile-lastname', nom);
        setVal('profile-firstname', prenom);
        setVal('profile-email', user.email);
        setVal('profile-phone', user.telephone || user.phone);
        setVal('profile-rue', user.rue || user.adresse);
        setVal('profile-cp', user.codePostal || user.code_postal || user.cp);
        setVal('profile-ville', user.ville);
    }

    // ==========================================
    // 3. CHARGEMENT DU CARNET / SOLDE
    // ==========================================
    async function loadUserCarnet() {
        const balanceEl = document.getElementById('user-balance');
        try {
            const res = await apiFetch('/carnets/me');
            if (res.ok) {
                const carnet = await res.json();
                const nb = carnet.nbSessionsRestant ?? carnet.nb_sessions_restant ?? carnet.solde ?? carnet.nbSessions ?? 0;
                if (balanceEl) balanceEl.textContent = nb;
            }
        } catch (err) {
            console.error("Erreur carnet:", err);
            if (balanceEl) balanceEl.textContent = '0';
        }
    }

    // ==========================================
    // 4. GESTION DU CERTIFICAT MÉDICAL
    // ==========================================
    async function loadUserCertificat() {
        const statusTextEl = document.getElementById('certificat-status-text');
        const viewBtn = document.getElementById('btn-view-certif');
        const uploadBtn = document.getElementById('btn-upload-certif');

        try {
            const res = await apiFetch('/adherent/certificat');
            if (res.ok) {
                const data = await res.json();
                const statut = data.statut || data.status || 'non_fourni';
                const hasFile = data.hasCertificat || data.id || data.url || (statut !== 'non_fourni');

                let badgeHtml = '<span class="badge bg-secondary-subtle text-secondary">Non fourni</span>';
                if (statut === 'valide' || statut === 'valid') {
                    badgeHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-patch-check-fill me-1"></i> Validé par le bureau</span>';
                } else if (statut === 'en_attente' || statut === 'pending') {
                    badgeHtml = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-hourglass-split me-1"></i> En attente de validation</span>';
                } else if (statut === 'refuse' || statut === 'rejected') {
                    badgeHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle-fill me-1"></i> Refusé</span>';
                }

                if (statusTextEl) statusTextEl.innerHTML = `Statut : ${badgeHtml}`;

                if (hasFile && viewBtn) {
                    viewBtn.classList.remove('d-none');
                    if (uploadBtn) uploadBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Remplacer le document';
                } else if (viewBtn) {
                    viewBtn.classList.add('d-none');
                    if (uploadBtn) uploadBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Déposer un document';
                }
            }
        } catch (err) {
            console.error("Erreur statut certificat:", err);
        }
    }

    // Clic sur "Voir mon document" avec authentification
    const btnViewCertif = document.getElementById('btn-view-certif');
    if (btnViewCertif) {
        btnViewCertif.onclick = async (e) => {
            e.preventDefault();
            try {
                const originalText = btnViewCertif.innerHTML;
                btnViewCertif.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Chargement...';

                // Requête sécurisée avec header Authorization
                const res = await apiFetch('/adherent/certificat/download');
                if (!res.ok) throw new Error('Impossible de charger le document');

                // Téléchargement et ouverture via Blob
                const blob = await res.blob();
                const fileURL = URL.createObjectURL(blob);
                window.open(fileURL, '_blank');

                btnViewCertif.innerHTML = originalText;
            } catch (err) {
                console.error("Erreur lors de l'ouverture du document :", err);
                alert("Erreur lors de la récupération du certificat.");
                btnViewCertif.innerHTML = '<i class="bi bi-eye me-1"></i> Voir mon document';
            }
        };
    }

    // Upload du certificat médical
    const btnUploadCertif = document.getElementById('btn-upload-certif');
    const inputCertif = document.getElementById('input-certificat-file');

    if (btnUploadCertif && inputCertif) {
        btnUploadCertif.onclick = () => inputCertif.click();

        inputCertif.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                alert('Le fichier est trop volumineux (5 Mo maximum).');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('certificat', file); // Double clé par compatibilité

            try {
                btnUploadCertif.disabled = true;
                btnUploadCertif.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Envoi...';

                const res = await apiFetch('/adherent/certificat', {
                    method: 'POST',
                    body: formData
                });

                if (res.ok) {
                    alert('Votre certificat a été envoyé avec succès !');
                    await loadUserCertificat();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || data.error || "Erreur lors du dépôt du certificat.");
                }
            } catch (err) {
                console.error("Erreur upload:", err);
            } finally {
                btnUploadCertif.disabled = false;
                inputCertif.value = '';
            }
        };
    }

    // ==========================================
    // 5. HISTORIQUE DES TRANSACTIONS
    // ==========================================
    async function loadUserHistory() {
        const historyList = document.getElementById('user-history-list');
        if (!historyList) return;

        try {
            const res = await apiFetch('/transactions/me');
            if (res.ok) {
                const history = await res.json();
                renderUserHistory(Array.isArray(history) ? history : (history.transactions || []));
            } else {
                historyList.innerHTML = `<div class="list-group-item text-center py-4 text-muted">Aucune transaction enregistrée.</div>`;
            }
        } catch (err) {
            console.error("Erreur historique:", err);
            historyList.innerHTML = `<div class="list-group-item text-center py-4 text-muted">Historique indisponible.</div>`;
        }
    }

    function renderUserHistory(items) {
        const historyList = document.getElementById('user-history-list');
        if (!historyList) return;

        if (!items || items.length === 0) {
            historyList.innerHTML = `<div class="list-group-item text-center py-4 text-muted">Aucun mouvement pour le moment.</div>`;
            return;
        }

        historyList.innerHTML = items.map(item => {
            const dateStr = formatDate(item.date || item.createdAt || item.created_at || item.dateTransaction);
            const type = item.type || (item.montant > 0 ? 'ACHAT' : 'PRESENCE');
            const qty = item.quantiteSessions ?? item.nbSessions ?? item.quantite ?? item.amount ?? 1;

            let iconHtml = '<div class="bg-light text-secondary p-2 rounded-3 me-3"><i class="bi bi-clock-history fs-5"></i></div>';
            let badgeHtml = `<span class="badge bg-secondary-subtle text-secondary fs-6 fw-bold">-${qty} session(s)</span>`;
            let titre = item.label || item.libelle || 'Mouvement solde';

            if (type.includes('ACHAT') || type.includes('HELLOASSO') || qty > 0) {
                iconHtml = '<div class="bg-success-subtle text-success p-2 rounded-3 me-3"><i class="bi bi-cart-check-fill fs-5"></i></div>';
                badgeHtml = `<span class="badge bg-success-subtle text-success fs-6 fw-bold">+${qty} sessions</span>`;
                if (!item.label) titre = 'Achat HelloAsso';
            } else if (type.includes('ADMIN')) {
                iconHtml = '<div class="bg-warning-subtle text-warning-emphasis p-2 rounded-3 me-3"><i class="bi bi-tools fs-5"></i></div>';
                badgeHtml = `<span class="badge bg-warning-subtle text-warning-emphasis fs-6 fw-bold">${qty} session(s)</span>`;
            }

            return `
            <div class="list-group-item d-flex align-items-center justify-content-between p-3 border-start-0 border-end-0">
                <div class="d-flex align-items-center">
                    ${iconHtml}
                    <div>
                        <h6 class="mb-0 fw-bold">${titre}</h6>
                        <small class="text-muted">${dateStr} ${item.reference ? `&bull; Réf: ${item.reference}` : ''}</small>
                    </div>
                </div>
                <div class="text-end">
                    ${badgeHtml}
                    <div class="small text-muted">${item.commentaire || item.description || ''}</div>
                </div>
            </div>
            `;
        }).join('');
    }

    const btnRefreshHistory = document.getElementById('btn-refresh-history');
    if (btnRefreshHistory) {
        btnRefreshHistory.onclick = loadUserHistory;
    }

    // ==========================================
    // 6. DÉCONNEXION
    // ==========================================
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
        btnLogout.onclick = (e) => {
            e.preventDefault();
            localStorage.removeItem('jwt_token');
            window.location.href = '/signin';
        };
    }

    // ==========================================
    // 7. GESTION DU SCROLL VERS LES ANCRES INTERNES
    // ==========================================
    const navAnchors = document.querySelectorAll('a[href^="#"]');
    navAnchors.forEach(anchor => {
        anchor.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();

            const targetId = anchor.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        };
    });

    // Lancement du chargement
    loadUserProfile();
};

// Exécution automatique à l'import
window.initAccountUser();