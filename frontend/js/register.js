// js/register.js

(function() {
    console.log("--> register.js bien chargé et exécuté !");

    const form = document.getElementById('register-form');
    if (!form) {
        console.warn("Formulaire #register-form introuvable dans le DOM.");
        return;
    }

    const lastnameInput = document.getElementById('reg-lastname');
    const firstnameInput = document.getElementById('reg-firstname');
    const emailInput = document.getElementById('reg-email');
    const phoneInput = document.getElementById('reg-phone');
    const experienceInput = document.getElementById('reg-experience');
    const captchaCheck = document.getElementById('captcha-check');
    const consentCheck = document.getElementById('consent-check');
    const submitBtn = form.querySelector('button[type="submit"]');

    // Pré-sélection du niveau si présent dans l'URL (?level=debutant)
    const urlParams = new URLSearchParams(window.location.search);
    const levelFromUrl = urlParams.get('level');
    if (levelFromUrl) {
        const matchingRadio = form.querySelector(`input[name="level"][value="${levelFromUrl}"]`);
        if (matchingRadio) matchingRadio.checked = true;
    }

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const phoneRegex = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;

    // Fonction pour appliquer le style rouge / vert
    function setFieldValidation(input, isValid, errorMessage) {
        let parent = input.parentElement;
        let feedback = parent.querySelector('.invalid-feedback');

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.style.display = 'none';
            parent.appendChild(feedback);
        }

        if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            feedback.style.display = 'none';
            feedback.textContent = '';
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            feedback.style.display = 'block';
            feedback.style.color = '#dc3545';
            feedback.style.fontSize = '0.875rem';
            feedback.style.marginTop = '0.25rem';
            feedback.textContent = errorMessage;
        }
    }

    const validateLastname = () => {
        const val = lastnameInput.value.trim();
        const isValid = val.length >= 2;
        setFieldValidation(lastnameInput, isValid, 'Le nom doit comporter au moins 2 caractères.');
        return isValid;
    };

    const validateFirstname = () => {
        const val = firstnameInput.value.trim();
        const isValid = val.length >= 2;
        setFieldValidation(firstnameInput, isValid, 'Le prénom doit comporter au moins 2 caractères.');
        return isValid;
    };

    const validateEmail = () => {
        const val = emailInput.value.trim();
        const isValid = emailRegex.test(val);
        setFieldValidation(emailInput, isValid, 'Veuillez saisir une adresse email valide (ex: alex@domaine.fr).');
        return isValid;
    };

    const validatePhone = () => {
        const val = phoneInput.value.trim();
        const isValid = phoneRegex.test(val);
        setFieldValidation(phoneInput, isValid, 'Numéro invalide (ex: 06 12 34 56 78 ou +33612345678).');
        return isValid;
    };

    // Écouteurs en direct sur frappe et perte de focus
    lastnameInput.addEventListener('input', validateLastname);
    lastnameInput.addEventListener('blur', validateLastname);

    firstnameInput.addEventListener('input', validateFirstname);
    firstnameInput.addEventListener('blur', validateFirstname);

    emailInput.addEventListener('input', validateEmail);
    emailInput.addEventListener('blur', validateEmail);

    phoneInput.addEventListener('input', validatePhone);
    phoneInput.addEventListener('blur', validatePhone);

    // Validation à la soumission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const isLastnameOk = validateLastname();
        const isFirstnameOk = validateFirstname();
        const isEmailOk = validateEmail();
        const isPhoneOk = validatePhone();

        // Vérif niveau
        const selectedLevel = form.querySelector('input[name="level"]:checked');
        const levelGroup = form.querySelector('input[name="level"]').closest('.mb-3');
        let levelFeedback = levelGroup.querySelector('.text-danger-custom');
        let isLevelOk = !!selectedLevel;

        if (!isLevelOk) {
            if (!levelFeedback) {
                levelFeedback = document.createElement('div');
                levelFeedback.className = 'text-danger text-danger-custom small mt-1';
                levelFeedback.textContent = 'Veuillez sélectionner un niveau de jeu.';
                levelGroup.appendChild(levelFeedback);
            }
        } else if (levelFeedback) {
            levelFeedback.remove();
        }

        // Vérif créneaux
        const selectedSlots = Array.from(form.querySelectorAll('input[id^="slot-"]:checked')).map(cb => cb.value);
        const slotGroup = form.querySelector('#slot-vendredi').closest('.mb-3');
        let slotFeedback = slotGroup.querySelector('.text-danger-custom');
        let isSlotOk = selectedSlots.length > 0;

        if (!isSlotOk) {
            if (!slotFeedback) {
                slotFeedback = document.createElement('div');
                slotFeedback.className = 'text-danger text-danger-custom small mt-1';
                slotFeedback.textContent = 'Veuillez cocher au moins un créneau souhaité.';
                slotGroup.appendChild(slotFeedback);
            }
        } else if (slotFeedback) {
            slotFeedback.remove();
        }

        // Captcha & consentement
        let isCaptchaOk = captchaCheck.checked;
        captchaCheck.classList.toggle('is-invalid', !isCaptchaOk);

        let isConsentOk = consentCheck.checked;
        consentCheck.classList.toggle('is-invalid', !isConsentOk);

        if (!isLastnameOk || !isFirstnameOk || !isEmailOk || !isPhoneOk || !isLevelOk || !isSlotOk || !isCaptchaOk || !isConsentOk) {
            const firstError = form.querySelector('.is-invalid, .text-danger-custom');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Envoi au backend
        const payload = {
            lastname: lastnameInput.value.trim(),
            firstname: firstnameInput.value.trim(),
            email: emailInput.value.trim(),
            phone: phoneInput.value.trim(),
            level: selectedLevel.value,
            slots: selectedSlots,
            experience: experienceInput ? experienceInput.value.trim() : ''
        };

        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Envoi en cours...`;

        try {
            const response = await fetch('/api/membership-request', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok) {
                form.innerHTML = `
                    <div class="alert alert-success p-4 text-center rounded">
                        <h4 class="alert-heading font-title mb-2">🎉 Demande transmise avec succès !</h4>
                        <p class="mb-3">
                            Merci <strong>${payload.firstname}</strong>, votre dossier d'évaluation a bien été envoyé au club.
                        </p>
                        <p class="small text-muted mb-4">
                            Nous prendrons contact avec vous très vite pour planifier votre session d'essai.
                        </p>
                        <a href="/" onclick="route()" class="btn btn-outline-dark btn-sm">Retour à l'accueil</a>
                    </div>
                `;
            } else {
                throw new Error(result.message || "Une erreur est survenue.");
            }
        } catch (error) {
            alert('❌ Erreur : ' + error.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
})();