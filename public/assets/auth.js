
document.addEventListener('DOMContentLoaded', () => {
    const forms = ['signupForm', 'loginForm'];

    forms.forEach((formId) => {
        const form = document.getElementById(formId);

        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            clearErrors(form);

            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const button = form.querySelector(
                'button[type="submit"]'
            );

            const originalText = button.textContent;
            const message = document.getElementById('formMessage');

            button.disabled = true;
            button.textContent = 'Please wait...';
            message.replaceChildren();

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.errors) {
                    showFieldErrors(form, result.errors);
                }

                showMessage(
                    message,
                    result.message || 'Request failed.',
                    result.success ? 'success' : 'danger'
                );

                if (result.success) {
                    if (result.redirect) {
                        window.location.assign(result.redirect);
                        return;
                    }

                    if (formId === 'signupForm') {
                        form.reset();
                        form.classList.remove('was-validated');
                    }
                }
            } catch (error) {
                showMessage(
                    message,
                    'Unable to process your request. Please try again.',
                    'danger'
                );
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    });

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach((input) => {
            input.classList.remove('is-invalid');
        });

        form.querySelectorAll('[data-error]').forEach((element) => {
            element.textContent = '';
        });
    }

    function showFieldErrors(form, errors) {
        Object.entries(errors).forEach(([field, error]) => {
            const input = form.elements.namedItem(field);

            if (!input || !('classList' in input)) return;

            input.classList.add('is-invalid');

            const feedback = form.querySelector(
                `[data-error="${field}"]`
            );

            if (feedback) {
                feedback.textContent = error;
            }
        });
    }

    function showMessage(container, text, type) {
        const alert = document.createElement('div');

        alert.className = `alert alert-${type}`;
        alert.setAttribute('role', 'alert');
        alert.textContent = text;

        container.replaceChildren(alert);
    }
});
