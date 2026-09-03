(() => {
    // Configuration de l'API
    const API_BASE_URL = 'http://localhost:8080/api';
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

    // Helper pour fermer une modal Bootstrap
    function closeModal(modalId) {
        const modalEl = document.getElementById(modalId);
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modalInstance.hide();
        }
    }

    // Variables d'état
    let usersList = [];
    let sessionsList = [];

    // ==========================================
    // 2. GESTION DES ADHÉRENTS
    // ==========================================
    async function loadUsers() {
        try {
            const res = await apiFetch('/admin/users');
            if (res.ok) {
                usersList = await res.json();
                renderUsers(usersList);
                const kpi = document.getElementById('kpi-users-count');
                if (kpi) kpi.textContent = usersList.length;
            }
        } catch (err) {
            console.error('Erreur chargement utilisateurs:', err);
        }
    }

    function renderUsers(users) {
        const tbody = document.getElementById('users-table-body');
        if (!tbody) return;

        if (!users || users.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">Aucun adhérent trouvé.</td></tr>`;
            return;
        }

        tbody.innerHTML = users.map(user => {
            const balance = user.carnetSession ? (user.carnetSession.nbSessionsRestant ?? 0) : (user.balance ?? 0);
            const prenom = user.prenom || user.firstname || '';
            const nom = user.nom || user.lastname || '';
            const telephone = user.telephone || user.phone || '<span class="text-muted">-</span>';
            const isAdmin = user.roles && user.roles.includes('ROLE_ADMIN');

            return `
            <tr>
                <td>
                    <div class="fw-bold text-dark">${prenom} ${nom}</div>
                </td>
                <td>${user.email}</td>
                <td>${telephone}</td>
                <td>
                    <span class="badge ${balance > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} border px-2 py-1">
                        ${balance} session(s)
                    </span>
                </td>
                <td>
                    <span class="badge ${isAdmin ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'}">
                        ${isAdmin ? 'Admin' : 'Adhérent'}
                    </span>
                </td>
                <td class="text-end">
                    <button class="btn btn-outline-primary btn-sm rounded-pill btn-adjust-balance" 
                            data-id="${user.id}" 
                            data-name="${prenom} ${nom}" 
                            data-balance="${balance}"
                            title="Modifier le solde">
                        <i class="bi bi-wallet2 me-1"></i> Solde
                    </button>
                    <button class="btn btn-outline-danger btn-sm rounded-pill btn-delete-user" data-id="${user.id}" title="Supprimer">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            `;
        }).join('');

        attachUsersEvents();
    }

    function attachUsersEvents() {
        // Déclencher modal d'ajustement de solde
        document.querySelectorAll('.btn-adjust-balance').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('adjust-user-id').value = btn.dataset.id;
                document.getElementById('adjust-user-name').textContent = btn.dataset.name;
                document.getElementById('adjust-balance-val').value = btn.dataset.balance;

                const modal = new bootstrap.Modal(document.getElementById('modalAdjustBalance'));
                modal.show();
            });
        });

        // Supprimer un membre
        document.querySelectorAll('.btn-delete-user').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (confirm('Confirmer la suppression de cet adhérent ?')) {
                    try {
                        const id = btn.dataset.id;
                        const res = await apiFetch(`/admin/users/${id}`, { method: 'DELETE' });
                        if (res.ok) {
                            loadUsers();
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

    // Filtrage recherche
    const filterInput = document.getElementById('filter-members-input');
    if (filterInput) {
        filterInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const filtered = usersList.filter(u => {
                const fullName = `${u.prenom || u.firstname || ''} ${u.nom || u.lastname || ''}`.toLowerCase();
                const email = (u.email || '').toLowerCase();
                return fullName.includes(query) || email.includes(query);
            });
            renderUsers(filtered);
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
                    loadUsers();
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
                    loadUsers();
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
            }
        } catch (err) {
            console.error('Erreur chargement séances:', err);
        }
    }

    function renderSessions(sessions) {
        const tbody = document.getElementById('sessions-table-body');
        if (!tbody) return;

        if (!sessions || sessions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">Aucune séance programmée.</td></tr>`;
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
                <td class="fw-bold">Séance d'entraînement</td>
                <td>${dateStr}</td>
                <td>${horaires}</td>
                <td><span class="badge bg-light text-dark border">${nbPresents} présent(s)</span></td>
                <td class="text-end">
                    <button class="btn btn-outline-danger btn-sm rounded-pill btn-delete-session" data-id="${sess.id}" title="Supprimer">
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
                            loadSessions();
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

            // Récupération du champ datetime (format "YYYY-MM-DDTHH:mm")
            const rawDateTime = document.getElementById('new-session-datetime')?.value;

            if (!rawDateTime) {
                alert('Veuillez renseigner la date et l\'heure de la séance.');
                return;
            }

            // Découpage en date_session, heure_debut, et calcul automatique de heure_fin (+2h)
            const [datePart, timePart] = rawDateTime.split('T');
            const heureDebut = timePart || '18:00';

            // Calcul de l'heure de fin par défaut (+ 2 heures)
            const [h, m] = heureDebut.split(':').map(Number);
            const endH = String((h + 2) % 24).padStart(2, '0');
            const endM = String(m).padStart(2, '0');
            const heureFin = `${endH}:${endM}`;

            const payload = {
                date_session: datePart,
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
            const prenom = user.prenom || user.firstname || '';
            const nom = user.nom || user.lastname || '';

            return `
            <tr>
                <td><strong>${prenom} ${nom}</strong></td>
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

    // Déconnexion
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', (e) => {
            e.preventDefault();
            localStorage.removeItem('jwt_token');
            window.location.href = '/signin';
        });
    }

    const btnRefresh = document.getElementById('btn-refresh-users');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', loadUsers);
    }

    // Initialisation
    loadUsers();
    loadSessions();
})();