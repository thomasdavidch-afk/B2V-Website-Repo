(() => {
    // Configuration de l'API
    const API_BASE_URL = window.API_BASE_URL || window.API_URL;
    const token = localStorage.getItem('jwt_token');

    // 1. Contrôle de sécurité Frontend (Redirection si non authentifié)
    if (!token) {
        window.location.href = '/signin';
        return;
    }

    // Helper pour requêtes authentifiées
    async function apiFetch(endpoint, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
            ...(options.headers || {})
        };

        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
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

    // Helper pour visualiser un certificat de manière sécurisée (avec Bearer token)
    async function viewCertificat(certificatId) {
        if (!certificatId) {
            alert('Identifiant du certificat introuvable.');
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/admin/certificats/${certificatId}/download`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Impossible de charger le document.');
            }

            const blob = await response.blob();
            const fileUrl = window.URL.createObjectURL(blob);
            window.open(fileUrl, '_blank');
        } catch (error) {
            console.error('Erreur affichage certificat :', error);
            alert('Impossible de charger le certificat médical.');
        }
    }

    // Helper pour fermer proprement une modal Bootstrap
    function closeModal(modalId) {
        const modalEl = document.getElementById(modalId);
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    }

    // Helper formatage de date
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

    // Helper badge certificat médical
    function getCertifBadge(certifStatus, certifUrl) {
        const status = certifStatus || (certifUrl ? 'en_attente' : 'non_fourni');
        switch (status) {
            case 'valide':
            case 'valid':
                return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-patch-check-fill me-1"></i> Validé</span>';
            case 'en_attente':
            case 'pending':
                return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-hourglass-split me-1"></i> En attente</span>';
            case 'refuse':
            case 'rejected':
                return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle-fill me-1"></i> Refusé</span>';
            default:
                return '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-file-earmark-x me-1"></i> Non fourni</span>';
        }
    }

    // Variables d'état
    let usersList = [];
    let sessionsList = [];
    let auditLogsList = [];

    // ==========================================
    // 2. GESTION DES ADHÉRENTS
    // ==========================================
    async function loadUsers() {
        try {
            const res = await apiFetch('/admin/users');
            if (res.ok) {
                usersList = await res.json();
                renderUsers(usersList);

                // Calcul KPI Adhérents actifs
                const activeCount = usersList.filter(u => u.actif ?? u.isActive ?? u.is_active ?? false).length;
                const kpi = document.getElementById('kpi-users-count');
                if (kpi) kpi.textContent = activeCount;
            }
        } catch (err) {
            console.error('Erreur chargement utilisateurs:', err);
        }
    }

    function renderUsers(users) {
        const tbody = document.getElementById('users-table-body');
        if (!tbody) return;

        if (!users || users.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted">Aucun adhérent trouvé.</td></tr>`;
            return;
        }

        tbody.innerHTML = users.map(user => {
            const balance = user.carnetSession ? (user.carnetSession.nbSessionsRestant ?? 0) : (user.balance ?? 0);
            const nom = user.nom || user.lastname || '-';
            const prenom = user.prenom || user.firstname || '-';
            const email = user.email || '-';
            const telephone = user.telephone || user.phone || '<span class="text-muted">-</span>';
            const dateInscription = formatDate(user.createdAt || user.created_at || user.dateInscription);

            // Adresse
            const rue = user.rue || user.adresse || '';
            const cp = user.codePostal || user.code_postal || user.cp || '';
            const ville = user.ville || '';
            const adresseHtml = (rue || cp || ville) 
                ? `<small class="text-dark d-block text-truncate" style="max-width: 150px;" title="${rue} ${cp} ${ville}">${rue ? `${rue}, ` : ''}${cp} ${ville}</small>`
                : '<span class="text-muted small">-</span>';

            // Statut Actif / Inactif
            const isActive = user.actif ?? user.isActive ?? user.is_active ?? false;
            const statusBadge = isActive 
                ? '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i> Actif</span>' 
                : '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-slash-circle me-1"></i> Inactif</span>';

            // Extraction Statut et Objet Certificat
            const certif = user.certificatMedical || user.certificat;
            const certifStatus = (certif && typeof certif === 'object') 
                ? certif.statut 
                : (user.certificatStatus || user.certificat_status);
            const certifBadge = getCertifBadge(certifStatus, certif);

            return `
            <tr>
                <td class="fw-bold text-dark">${nom}</td>
                <td>${prenom}</td>
                <td><small>${email}</small></td>
                <td>${telephone}</td>
                <td><small class="text-muted">${dateInscription}</small></td>
                <td>${adresseHtml}</td>
                <td>${certifBadge}</td>
                <td>${statusBadge}</td>
                <td>
                    <span class="badge ${balance > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} border px-2 py-1">
                        ${balance} restant(s)
                    </span>
                </td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-outline-dark btn-sm rounded-pill btn-edit-user me-1" 
                            data-id="${user.id}" 
                            title="Voir les détails / Modifier">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-outline-primary btn-sm rounded-pill btn-adjust-balance me-1" 
                            data-id="${user.id}" 
                            data-name="${nom} ${prenom}" 
                            data-balance="${balance}"
                            title="Modifier le solde">
                        <i class="bi bi-wallet2"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm rounded-pill btn-delete-user" 
                            data-id="${user.id}" 
                            title="Supprimer">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            `;
        }).join('');

        attachUsersEvents();
    }

    function attachUsersEvents() {
        // Modal Voir / Éditer l'adhérent
        document.querySelectorAll('.btn-edit-user').forEach(btn => {
            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.id, 10);
                const user = usersList.find(u => u.id === userId);
                if (!user) return;

                document.getElementById('edit-user-id').value = user.id;
                document.getElementById('edit-user-lastname').value = user.nom || user.lastname || '';
                document.getElementById('edit-user-firstname').value = user.prenom || user.firstname || '';
                document.getElementById('edit-user-email').value = user.email || '';
                document.getElementById('edit-user-phone').value = user.telephone || user.phone || '';
                document.getElementById('edit-user-rue').value = user.rue || user.adresse || '';
                document.getElementById('edit-user-cp').value = user.codePostal || user.code_postal || user.cp || '';
                document.getElementById('edit-user-ville').value = user.ville || '';

                const isActive = user.actif ?? user.isActive ?? user.is_active ?? false;
                document.getElementById('edit-user-is-active').value = isActive ? 'true' : 'false';

                // Gestion du Certificat Médical
                const certif = user.certificatMedical || user.certificat;
                let certifId = null;
                let certifStatus = 'non_fourni';

                if (certif && typeof certif === 'object') {
                    certifId = certif.id;
                    certifStatus = certif.statut || 'en_attente';
                } else if (typeof certif === 'number') {
                    certifId = certif;
                    certifStatus = user.certificatStatus || user.certificat_status || 'en_attente';
                } else {
                    certifStatus = user.certificatStatus || user.certificat_status || 'non_fourni';
                }

                const certifSelect = document.getElementById('edit-user-certif-status');
                if (certifSelect) {
                    certifSelect.value = certifStatus;
                    // On enregistre l'ID du certificat et son statut initial
                    certifSelect.dataset.certificatId = certifId || '';
                    certifSelect.dataset.initialStatus = certifStatus;
                }

                // Aperçu / Bouton sécurisé vers le certificat
                const certifContainer = document.getElementById('edit-user-certif-preview');
                if (certifContainer) {
                    if (certifId) {
                        certifContainer.innerHTML = `
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="btn-view-certif-modal">
                                <i class="bi bi-file-earmark-medical me-1"></i> Voir le document
                            </button>
                        `;
                        document.getElementById('btn-view-certif-modal').addEventListener('click', (e) => {
                            e.preventDefault();
                            viewCertificat(certifId);
                        });
                    } else {
                        certifContainer.innerHTML = `<span class="text-muted small"><i class="bi bi-info-circle me-1"></i> Aucun document téléversé</span>`;
                    }
                }

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditUser'));
                modal.show();
            });
        });

        // Modal Solde
        document.querySelectorAll('.btn-adjust-balance').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('adjust-user-id').value = btn.dataset.id;
                document.getElementById('adjust-user-name').textContent = btn.dataset.name;
                document.getElementById('adjust-balance-val').value = btn.dataset.balance;

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAdjustBalance'));
                modal.show();
            });
        });

        // Supprimer un adhérent
        document.querySelectorAll('.btn-delete-user').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (confirm('Confirmer la suppression définitive de cet adhérent ?')) {
                    try {
                        const id = btn.dataset.id;
                        const res = await apiFetch(`/admin/users/${id}`, { method: 'DELETE' });
                        if (res.ok) {
                            await loadUsers();
                        } else {
                            const errData = await res.json().catch(() => ({}));
                            alert(errData.message || errData.error || 'Erreur lors de la suppression.');
                        }
                    } catch (err) {
                        console.error('Erreur suppression adhérent:', err);
                    }
                }
            });
        });
    }

    // Filtrage recherche en direct
    const filterInput = document.getElementById('filter-members-input');
    if (filterInput) {
        filterInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const filtered = usersList.filter(u => {
                const nom = (u.nom || u.lastname || '').toLowerCase();
                const prenom = (u.prenom || u.firstname || '').toLowerCase();
                const email = (u.email || '').toLowerCase();
                const ville = (u.ville || '').toLowerCase();
                const cp = (u.codePostal || u.code_postal || u.cp || '').toString();
                return nom.includes(query) || prenom.includes(query) || email.includes(query) || ville.includes(query) || cp.includes(query);
            });
            renderUsers(filtered);
        });
    }

    // Formulaire d'Édition Adhérent (Mise à jour coordonnées + Statut certificat)
    const formEditUser = document.getElementById('form-edit-user');
    if (formEditUser) {
        formEditUser.addEventListener('submit', async (e) => {
            e.preventDefault();
            const userId = document.getElementById('edit-user-id')?.value;
            const certifSelect = document.getElementById('edit-user-certif-status');
            const newCertifStatus = certifSelect?.value;
            const certifId = certifSelect?.dataset.certificatId;

            const payload = {
                nom: document.getElementById('edit-user-lastname')?.value.trim() || '',
                prenom: document.getElementById('edit-user-firstname')?.value.trim() || '',
                email: document.getElementById('edit-user-email')?.value.trim() || '',
                telephone: document.getElementById('edit-user-phone')?.value.trim() || '',
                rue: document.getElementById('edit-user-rue')?.value.trim() || '',
                code_postal: document.getElementById('edit-user-cp')?.value.trim() || '',
                ville: document.getElementById('edit-user-ville')?.value.trim() || '',
                is_active: document.getElementById('edit-user-is-active')?.value === 'true'
            };

            try {
                // 1. Sauvegarde des coordonnées de l'adhérent
                const resUser = await apiFetch(`/admin/users/${userId}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                });

                if (!resUser.ok) {
                    const data = await resUser.json().catch(() => ({}));
                    alert(data.message || data.error || "Erreur lors de la mise à jour de l'adhérent.");
                    return;
                }

                // 2. Sauvegarde du statut du certificat s'il existe et est géré par l'API
                if (certifId && ['valide', 'refuse', 'en_attente'].includes(newCertifStatus)) {
                    const resCertif = await apiFetch(`/admin/certificats/${certifId}/validation`, {
                        method: 'PATCH',
                        body: JSON.stringify({
                            statut: newCertifStatus
                        })
                    });

                    if (!resCertif.ok) {
                        const errCertif = await resCertif.json().catch(() => ({}));
                        alert(errCertif.message || "Erreur lors de la mise à jour du certificat.");
                    }
                }

                closeModal('modalEditUser');
                await loadUsers();

            } catch (err) {
                console.error("Erreur modification utilisateur:", err);
            }
        });
    }

    // Formulaire de Création d'adhérent
    const formCreateUser = document.getElementById('form-create-user');
    if (formCreateUser) {
        formCreateUser.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                nom: document.getElementById('new-user-lastname')?.value.trim() || '',
                prenom: document.getElementById('new-user-firstname')?.value.trim() || '',
                email: document.getElementById('new-user-email')?.value.trim() || '',
                telephone: document.getElementById('new-user-phone')?.value.trim() || '',
                rue: document.getElementById('new-user-rue')?.value.trim() || '',
                code_postal: document.getElementById('new-user-cp')?.value.trim() || '',
                ville: document.getElementById('new-user-ville')?.value.trim() || '',
                solde: parseInt(document.getElementById('new-user-balance')?.value, 10) || 0,
                password: document.getElementById('new-user-password')?.value || ''
            };

            try {
                const res = await apiFetch('/admin/users', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    closeModal('modalCreateUser');
                    e.target.reset();
                    await loadUsers();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || data.error || "Erreur lors de la création de l'adhérent.");
                }
            } catch (err) {
                console.error("Erreur création utilisateur:", err);
            }
        });
    }

    // Formulaire d'Ajustement du Solde
    const formAdjustBalance = document.getElementById('form-adjust-balance');
    if (formAdjustBalance) {
        formAdjustBalance.addEventListener('submit', async (e) => {
            e.preventDefault();
            const userId = document.getElementById('adjust-user-id')?.value;
            const newBalance = parseInt(document.getElementById('adjust-balance-val')?.value, 10);

            try {
                const res = await apiFetch(`/admin/users/${userId}/balance`, {
                    method: 'PATCH',
                    body: JSON.stringify({ balance: newBalance })
                });

                if (res.ok) {
                    closeModal('modalAdjustBalance');
                    await loadUsers();
                } else {
                    alert('Erreur lors de la modification du solde.');
                }
            } catch (err) {
                console.error("Erreur modification solde:", err);
            }
        });
    }

    // ==========================================
    // 3. GESTION DES SÉANCES
    // ==========================================
    async function loadSessions() {
        try {
            const res = await apiFetch('/admin/sessions');
            if (res.ok) {
                sessionsList = await res.json();
                renderSessions(sessionsList);
                populateAttendanceSelect(sessionsList);
                const kpi = document.getElementById('kpi-sessions-count');
                if (kpi) kpi.textContent = sessionsList.length;

                // KPI total présences pointées
                const totalPresents = sessionsList.reduce((sum, s) => sum + (s.nb_presents ?? 0), 0);
                const kpiPresences = document.getElementById('kpi-attendance-count');
                if (kpiPresences) kpiPresences.textContent = totalPresents;
            }
        } catch (err) {
            console.error('Erreur chargement séances:', err);
        }
    }

    function renderSessions(sessions) {
        const tbody = document.getElementById('sessions-table-body');
        if (!tbody) return;

        if (!sessions || sessions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted">Aucune séance programmée.</td></tr>`;
            return;
        }

        tbody.innerHTML = sessions.map(sess => {
            const dateStr = sess.date_session || '-';
            const horaires = (sess.heure_debut && sess.heure_fin) 
                ? `${sess.heure_debut} - ${sess.heure_fin}` 
                : (sess.heure_debut || '-');
            const nbPresents = sess.nb_presents ?? 0;

            return `
            <tr>
                <td class="fw-bold"><i class="bi bi-calendar-check text-primary me-2"></i>Séance du ${dateStr}</td>
                <td><span class="badge bg-light text-dark border">${horaires}</span></td>
                <td><span class="badge bg-success-subtle text-success">${nbPresents} présent(s)</span></td>
                <td class="text-end">
                    <button class="btn btn-outline-danger btn-sm rounded-pill btn-delete-session" data-id="${sess.id}" title="Supprimer la séance">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            `;
        }).join('');

        document.querySelectorAll('.btn-delete-session').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (confirm('Supprimer cette séance ?')) {
                    try {
                        const res = await apiFetch(`/admin/sessions/${btn.dataset.id}`, { method: 'DELETE' });
                        if (res.ok) {
                            await loadSessions();
                        } else {
                            const data = await res.json().catch(() => ({}));
                            alert(data.message || data.error || 'Erreur lors de la suppression.');
                        }
                    } catch (err) {
                        console.error('Erreur suppression séance:', err);
                    }
                }
            });
        });
    }

    // Formulaire de Création de séance
    const formCreateSession = document.getElementById('form-create-session');
    if (formCreateSession) {
        formCreateSession.addEventListener('submit', async (e) => {
            e.preventDefault();

            const dateSession = document.getElementById('new-session-date')?.value;
            const heureDebut = document.getElementById('new-session-time-start')?.value || '18:00';
            const heureFin = document.getElementById('new-session-time-end')?.value || '20:00';

            if (!dateSession) {
                alert('Veuillez renseigner la date de la séance.');
                return;
            }

            const payload = {
                date_session: dateSession,
                heure_debut: heureDebut,
                heure_fin: heureFin
            };

            try {
                const res = await apiFetch('/admin/sessions', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    closeModal('modalCreateSession');
                    e.target.reset();
                    await loadSessions();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || data.error || 'Erreur lors de la création de la séance.');
                }
            } catch (err) {
                console.error('Erreur création séance:', err);
            }
        });
    }

    // ==========================================
    // 4. POINTAGE DES PRÉSENCES
    // ==========================================
    function populateAttendanceSelect(sessions) {
        const select = document.getElementById('select-session-attendance');
        if (!select) return;
        select.innerHTML = '<option value="" selected disabled>-- Choisir une séance --</option>';
        sessions.forEach(sess => {
            const opt = document.createElement('option');
            opt.value = sess.id;
            opt.textContent = `Séance du ${sess.date_session} (${sess.heure_debut} - ${sess.heure_fin})`;
            select.appendChild(opt);
        });
    }

    const selectAttendance = document.getElementById('select-session-attendance');
    if (selectAttendance) {
        selectAttendance.addEventListener('change', (e) => {
            const sessionId = e.target.value;
            const selected = sessionsList.find(s => s.id == sessionId);
            if (selected) {
                const titleElem = document.getElementById('attendance-session-title');
                if (titleElem) {
                    titleElem.textContent = `Séance du ${selected.date_session} (${selected.heure_debut} - ${selected.heure_fin})`;
                }
                renderAttendanceSheet(sessionId);
            }
        });
    }

    function renderAttendanceSheet(sessionId) {
        const tbody = document.getElementById('attendance-table-body');
        if (!tbody) return;

        if (usersList.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-muted">Aucun adhérent à pointer.</td></tr>`;
            return;
        }

        tbody.innerHTML = usersList.map(user => {
            const balance = user.carnetSession ? (user.carnetSession.nbSessionsRestant ?? 0) : (user.balance ?? 0);
            const nom = user.nom || user.lastname || '';
            const prenom = user.prenom || user.firstname || '';

            return `
            <tr>
                <td><strong>${nom} ${prenom}</strong></td>
                <td><span class="badge ${balance > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}">${balance} restant(s)</span></td>
                <td class="text-center">
                    <button class="btn btn-outline-success btn-sm rounded-pill btn-checkin" data-userid="${user.id}" data-sessionid="${sessionId}">
                        <i class="bi bi-check2"></i> Pointer (-1)
                    </button>
                </td>
            </tr>
            `;
        }).join('');

        document.querySelectorAll('.btn-checkin').forEach(btn => {
            btn.addEventListener('click', async () => {
                const userId = parseInt(btn.dataset.userid, 10);
                const sessId = parseInt(btn.dataset.sessionid, 10);

                try {
                    const res = await apiFetch('/admin/presences', {
                        method: 'POST',
                        body: JSON.stringify({ 
                            user_id: userId, 
                            session_id: sessId 
                        })
                    });

                    if (res.ok) {
                        btn.classList.remove('btn-outline-success');
                        btn.classList.add('btn-success', 'disabled');
                        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Pointé';
                        await loadUsers();
                        await loadSessions();
                    } else {
                        const data = await res.json().catch(() => ({}));
                        alert(data.message || data.error || 'Erreur ou solde insuffisant');
                    }
                } catch (err) {
                    console.error('Erreur pointage:', err);
                }
            });
        });
    }

    // ==========================================
    // 5. GESTION DES LOGS D'AUDIT (MONGODB)
    // ==========================================
    function getAuditBadge(eventType) {
        if (!eventType) return '<span class="badge bg-secondary">UNKNOWN</span>';
        if (eventType.includes('CONNEXION') || eventType.includes('AUTH') || eventType.includes('TEST')) {
            return `<span class="badge bg-info-subtle text-info border border-info-subtle">${eventType}</span>`;
        }
        if (eventType.includes('POINTAGE') || eventType.includes('SESSION') || eventType.includes('USER_CREATED')) {
            return `<span class="badge bg-success-subtle text-success border border-success-subtle">${eventType}</span>`;
        }
        if (eventType.includes('DELETE') || eventType.includes('BLOCK') || eventType.includes('SECURITY')) {
            return `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">${eventType}</span>`;
        }
        return `<span class="badge bg-dark-subtle text-dark border border-dark-subtle">${eventType}</span>`;
    }

    async function loadAuditLogs() {
        try {
            const res = await apiFetch('/admin/audit-logs');
            if (res.ok) {
                const data = await res.json();
                auditLogsList = data.logs || (Array.isArray(data) ? data : []);
                renderAuditLogs(auditLogsList);
            } else {
                const tbody = document.getElementById('audit-table-body');
                if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Impossible de charger les logs NoSQL.</td></tr>`;
            }
        } catch (err) {
            console.error('Erreur chargement logs audit:', err);
        }
    }

    function renderAuditLogs(logs) {
        const tbody = document.getElementById('audit-table-body');
        const countBadge = document.getElementById('audit-logs-count');
        if (countBadge) countBadge.textContent = `${logs.length} entrée(s)`;
        if (!tbody) return;

        if (!logs || logs.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">Aucun log d'audit enregistré pour le moment.</td></tr>`;
            return;
        }

        tbody.innerHTML = logs.map(log => {
            const dateStr = log.created_at || '-';
            const email = log.author?.email || '<span class="text-muted">Système / Anonyme</span>';
            const ip = log.ip_address || '-';
            const logId = log.id || log._id || '';

            return `
            <tr>
                <td><small class="text-muted font-monospace">${dateStr}</small></td>
                <td>${getAuditBadge(log.event_type)}</td>
                <td><small class="fw-semibold">${email}</small></td>
                <td><span class="badge bg-light text-secondary border font-monospace">${ip}</span></td>
                <td class="text-center">
                    <button class="btn btn-outline-dark btn-sm rounded-pill btn-view-audit" data-id="${logId}">
                        <i class="bi bi-code-slash me-1"></i> Voir JSON
                    </button>
                </td>
            </tr>
            `;
        }).join('');

        // Attachement de la modal de visualisation JSON
        document.querySelectorAll('.btn-view-audit').forEach(btn => {
            btn.addEventListener('click', () => {
                const log = auditLogsList.find(l => (l.id || l._id) === btn.dataset.id);
                if (!log) return;

                document.getElementById('audit-detail-id').textContent = log.id || log._id || '-';
                document.getElementById('audit-detail-type').innerHTML = getAuditBadge(log.event_type);
                document.getElementById('audit-detail-ua').textContent = log.user_agent || 'N/A';
                document.getElementById('audit-detail-context').textContent = JSON.stringify(log.context || {}, null, 2);

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalViewAuditLog'));
                modal.show();
            });
        });
    }

    // Filtrage des logs en direct
    const filterAuditInput = document.getElementById('filter-audit-input');
    if (filterAuditInput) {
        filterAuditInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const filtered = auditLogsList.filter(log => {
                const event = (log.event_type || '').toLowerCase();
                const email = (log.author?.email || '').toLowerCase();
                const ip = (log.ip_address || '').toLowerCase();
                return event.includes(query) || email.includes(query) || ip.includes(query);
            });
            renderAuditLogs(filtered);
        });
    }

    // Bouton de rafraîchissement des logs
    const btnRefreshAudit = document.getElementById('btn-refresh-audit');
    if (btnRefreshAudit) {
        btnRefreshAudit.addEventListener('click', loadAuditLogs);
    }

    // Bouton de test pour générer un log devant le jury
    const btnTestAudit = document.getElementById('btn-test-audit');
    if (btnTestAudit) {
        btnTestAudit.addEventListener('click', async () => {
            try {
                btnTestAudit.disabled = true;
                btnTestAudit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Émission...';
                
                // On appelle la route GET /api/test-audit
                // (Si apiFetch ajoute déjà /api automatiquement, mets juste '/test-audit', 
                // sinon mets '/api/test-audit' selon comment ton apiFetch est configuré)
                const res = await apiFetch('/test-audit'); // ou '/api/test-audit'

                if (res.ok) {
                    // On recharge la liste pour afficher le nouveau log généré
                    await loadAuditLogs();
                } else {
                    console.error('Erreur lors du test de log, statut :', res.status);
                }
            } catch (err) {
                console.error('Erreur réseau :', err);
            } finally {
                btnTestAudit.disabled = false;
                btnTestAudit.innerHTML = '<i class="bi bi-play-circle me-1"></i> Tester un log';
            }
        });
    }

    // Déconnexion & Rafraîchissement
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', (e) => {
            e.preventDefault();
            localStorage.removeItem('jwt_token');
            window.history.pushState({}, "", "/signin");
            if (typeof window.LoadContentPage === 'function') {
                window.LoadContentPage();
            } else {
                window.location.reload();
            }
        });
    }

    const btnRefresh = document.getElementById('btn-refresh-users');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', loadUsers);
    }

    // Empêche l'erreur de focus aria-hidden à la fermeture des modales
    document.addEventListener('hide.bs.modal', () => {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
    });

    // Initialisation
    loadUsers();
    loadSessions();
    loadAuditLogs();
})();