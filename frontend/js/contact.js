// =============================================================================
// INITIALISATION DE LA PAGE CONTACT
// =============================================================================
window.initContact = function() {
    const API_BASE_URL = window.API_URL;

    const form = document.getElementById('contact-form');
    if (!form) return;

    const lastNameInput = document.getElementById('lastName');
    const firstNameInput = document.getElementById('firstName');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const subjectInput = document.getElementById('subject');
    const messageInput = document.getElementById('message');
    const alertBox = document.getElementById('contact-alert');
    const submitBtn = form.querySelector('button[type="submit"]');

    // Expressions régulières
    const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    // Formats acceptés : 0612345678, 06 12 34 56 78, 06.12.34.56.78, +33 6 12 34 56 78
    const PHONE_REGEX = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;

    // -------------------------------------------------------------
    // HELPERS D'AFFICHAGE DES ERREURS
    // -------------------------------------------------------------
    function setInvalid(input, message) {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');

        // Récupère ou crée la div de feedback d'erreur
        let feedback = input.parentElement.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentElement.appendChild(feedback);
        }
        feedback.textContent = message;
    }

    function setValid(input) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');

        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) feedback.remove();
    }

    function resetInput(input) {
        input.classList.remove('is-invalid', 'is-valid');
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) feedback.remove();
    }

    function showAlert(type, message) {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} mb-4`;
        alertBox.innerHTML = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideAlert() {
        if (!alertBox) return;
        alertBox.classList.add('d-none');
    }

    // -------------------------------------------------------------
    // RÈGLES DE VALIDATION UNITAIRE
    // -------------------------------------------------------------
    function validateLastName() {
        const val = lastNameInput.value.trim();
        if (!val) {
            setInvalid(lastNameInput, 'Le nom de famille est obligatoire.');
            return false;
        }
        setValid(lastNameInput);
        return true;
    }

    function validateFirstName() {
        const val = firstNameInput.value.trim();
        if (!val) {
            setInvalid(firstNameInput, 'Le prénom est obligatoire.');
            return false;
        }
        setValid(firstNameInput);
        return true;
    }

    function validateEmail() {
        const val = emailInput.value.trim();
        if (!val) {
            setInvalid(emailInput, 'L\'adresse e-mail est obligatoire.');
            return false;
        }
        if (!EMAIL_REGEX.test(val)) {
            setInvalid(emailInput, 'Format d\'adresse e-mail invalide (ex: exemple@domaine.com).');
            return false;
        }
        setValid(emailInput);
        return true;
    }

    function validatePhone() {
        const val = phoneInput.value.trim();
        // Téléphone optionnel : valide si vide
        if (!val) {
            resetInput(phoneInput);
            return true;
        }
        if (!PHONE_REGEX.test(val)) {
            setInvalid(phoneInput, 'Format invalide (ex: 06 12 34 56 78 ou +33 6 12 34 56 78).');
            return false;
        }
        setValid(phoneInput);
        return true;
    }

    function validateSubject() {
        const val = subjectInput.value.trim();
        if (!val) {
            setInvalid(subjectInput, 'Le sujet du message est obligatoire.');
            return false;
        }
        setValid(subjectInput);
        return true;
    }

    function validateMessage() {
        const val = messageInput.value.trim();
        if (!val) {
            setInvalid(messageInput, 'Veuillez saisir votre message.');
            return false;
        }
        if (val.length < 10) {
            setInvalid(messageInput, 'Votre message doit contenir au moins 10 caractères.');
            return false;
        }
        setValid(messageInput);
        return true;
    }

    // -------------------------------------------------------------
    // ÉCOUTEURS D'ÉVÉNEMENTS EN TEMPS RÉEL (au blur / saisie)
    // -------------------------------------------------------------
    lastNameInput.addEventListener('blur', validateLastName);
    firstNameInput.addEventListener('blur', validateFirstName);
    emailInput.addEventListener('blur', validateEmail);
    phoneInput.addEventListener('blur', validatePhone);
    subjectInput.addEventListener('blur', validateSubject);
    messageInput.addEventListener('blur', validateMessage);

    // -------------------------------------------------------------
    // SOUMISSION DU FORMULAIRE
    // -------------------------------------------------------------
    form.onsubmit = async (e) => {
        e.preventDefault();
        hideAlert();

        const isLastNameOk = validateLastName();
        const isFirstNameOk = validateFirstName();
        const isEmailOk = validateEmail();
        const isPhoneOk = validatePhone();
        const isSubjectOk = validateSubject();
        const isMessageOk = validateMessage();

        // Bloque si au moins une vérification échoue
        if (!isLastNameOk || !isFirstNameOk || !isEmailOk || !isPhoneOk || !isSubjectOk || !isMessageOk) {
            showAlert('danger', '<i class="bi bi-exclamation-triangle-fill me-2"></i>Veuillez corriger les champs surlignés en rouge avant d\'envoyer.');
            return;
        }

        // Préparation du payload
        const payload = {
            nom: lastNameInput.value.trim(),
            prenom: firstNameInput.value.trim(),
            email: emailInput.value.trim(),
            telephone: phoneInput.value.trim() || null,
            sujet: subjectInput.value.trim(),
            message: messageInput.value.trim()
        };

        // État de chargement du bouton
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Envoi en cours...';

        try {
            const response = await fetch(`${API_BASE_URL}/contact`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                showAlert('success', '<i class="bi bi-check-circle-fill me-2"></i>Votre message a bien été envoyé à l\'équipe B2V ! Nous vous répondrons dans les plus brefs délais.');
                form.reset();
                [lastNameInput, firstNameInput, emailInput, phoneInput, subjectInput, messageInput].forEach(resetInput);
            } else {
                showAlert('danger', `<i class="bi bi-exclamation-circle-fill me-2"></i>${data.message || data.error || 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer.'}`);
            }
        } catch (error) {
            console.error('Erreur contact:', error);
            showAlert('danger', '<i class="bi bi-wifi-off me-2"></i>Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    };
};

// Exécution au chargement
window.initContact();