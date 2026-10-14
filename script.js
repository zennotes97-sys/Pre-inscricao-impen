document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');

    if (!form) {
        return;
    }

    const progressWrapper = document.getElementById('progress-wrapper');
    const progressFill = document.getElementById('progress-fill');
    const progressPercent = document.getElementById('progress-percent');
    const stepItems = Array.from(document.querySelectorAll('.step'));
    const progressFields = Array.from(form.querySelectorAll('input, select, textarea')).filter((field) => field.type !== 'hidden');
    const submitButton = form.querySelector('button[type="submit"]');
    const phoneInput = form.elements.contacto;
    const optionalPhoneInput = form.elements.telefone_opcional;
    const emailInput = form.elements.email;

    function updateProgress() {
        const total = progressFields.length;
        const filled = progressFields.filter((field) => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                return field.checked;
            }

            return field.value.trim() !== '';
        }).length;

        const percentage = total > 0 ? Math.round((filled / total) * 100) : 0;

        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }

        if (progressPercent) {
            progressPercent.textContent = `${percentage}%`;
        }

        if (stepItems.length > 0) {
            const activeIndex = Math.min(Math.floor(percentage / 25), stepItems.length - 1);
            stepItems.forEach((step, index) => {
                step.classList.toggle('active', index <= activeIndex);
            });
        }
    }

    function updateProgressVisibility() {
        if (!progressWrapper) {
            return;
        }

        const scrollY = window.scrollY || window.pageYOffset;

        if (scrollY > 180) {
            progressWrapper.classList.add('visible');
        } else {
            progressWrapper.classList.remove('visible');
        }
    }

    progressFields.forEach((field) => {
        field.addEventListener('input', updateProgress);
        field.addEventListener('change', updateProgress);
    });

    window.addEventListener('scroll', updateProgressVisibility, { passive: true });
    updateProgress();
    updateProgressVisibility();

    function createToastContainer() {
        let container = document.querySelector('.toast-container');

        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        return container;
    }

    function showToast(message, type = 'info') {
        const container = createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const title = document.createElement('div');
        title.className = 'toast-title';
        title.innerHTML = `
            <span>${type === 'success' ? 'Sucesso' : type === 'error' ? 'Erro' : 'Aviso'}</span>
            <span>✦</span>
        `;

        const text = document.createElement('div');
        text.className = 'toast-message';
        text.textContent = message;

        toast.appendChild(title);
        toast.appendChild(text);
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    function isValidMozambiquePhone(value) {
        const normalized = value.replace(/[\s().-]/g, '');
        return /^(?:(?:\+258|00258|258))?8[2-7]\d{7}$/.test(normalized);
    }

    function showSuccessMessage(emailStatus) {
        const formCard = document.querySelector('.card');

        if (!formCard) {
            return;
        }

        const success = document.createElement('section');
        success.className = 'success-message';
        success.setAttribute('role', 'status');
        success.setAttribute('aria-live', 'polite');

        const icon = document.createElement('div');
        icon.className = 'success-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '✓';

        const title = document.createElement('h2');
        title.textContent = 'Pré-inscrição enviada com sucesso!';

        const message = document.createElement('p');
        message.textContent = 'Os seus dados foram registados. Obrigado pelo seu interesse no IMPEN. Aguarde o contacto ou a confirmação do Instituto.';

        const emailMessage = document.createElement('p');
        emailMessage.className = 'email-status';
        emailMessage.textContent = emailStatus;

        const backLink = document.createElement('a');
        backLink.className = 'success-link';
        backLink.href = 'index.html';
        backLink.textContent = 'Voltar ao formulário';

        success.append(icon, title, message, emailMessage, backLink);
        formCard.replaceChildren(success);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            showToast('Preencha todos os campos obrigatórios.', 'error');
            return;
        }

        if (!isValidMozambiquePhone(phoneInput.value.trim())) {
            phoneInput.setCustomValidity('Introduza um número moçambicano válido (82 a 87, com 9 dígitos).');
            phoneInput.reportValidity();
            showToast('O contacto deve ser um número moçambicano válido, começando por 82, 83, 84, 85, 86 ou 87.', 'error');
            return;
        }

        if (optionalPhoneInput.value.trim() !== '' && !isValidMozambiquePhone(optionalPhoneInput.value.trim())) {
            optionalPhoneInput.setCustomValidity('Introduza um número moçambicano válido (82 a 87, com 9 dígitos).');
            optionalPhoneInput.reportValidity();
            showToast('Verifique o telefone opcional.', 'error');
            return;
        }

        phoneInput.setCustomValidity('');
        optionalPhoneInput.setCustomValidity('');
        emailInput.value = emailInput.value.trim().toLowerCase();
        form.elements.nome.value = form.elements.outros_nomes.value.trim();

        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'A enviar...';
        showToast('A enviar a sua pré-inscrição...', 'info');

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json' }
            });

            let result = null;
            try {
                result = await response.json();
            } catch (error) {
                result = null;
            }

            if (response.ok && result && result.success === true) {
                showSuccessMessage(result.emailMessage);
                showToast(
                    result.emailAdmin && result.emailApplicant
                        ? 'Pré-inscrição registada e emails enviados.'
                        : result.emailMessage,
                    result.emailAdmin && result.emailApplicant ? 'success' : 'info'
                );
                return;
            }

            const message = result && result.message
                ? result.message
                : 'Ocorreu um erro ao processar a inscrição.';

            showToast(message, 'error');
        } catch (error) {
            showToast('Não foi possível enviar a pré-inscrição. Tente novamente.', 'error');
        } finally {
            if (submitButton.isConnected) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }
    });

    phoneInput.addEventListener('input', () => {
        phoneInput.setCustomValidity('');
    });

    optionalPhoneInput.addEventListener('input', () => {
        optionalPhoneInput.setCustomValidity('');
    });
});
