(() => {
    const decode = (encoded) => {
        try {
            const padded = encoded.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - encoded.length % 4) % 4);
            return atob(padded).split('').reverse().join('');
        } catch (_) {
            return null;
        }
    };

    const reveal = (element) => {
        if (element.dataset.emailRevealed === '1') return;
        const email = decode(element.dataset.protectedEmail || '');
        if (!email) return;

        element.textContent = element.dataset.emailLabel || email;
        element.dataset.emailRevealed = '1';
        element.setAttribute('aria-label', `E-Mail an ${email}`);

        if (element.dataset.emailLink === '1' && element.tagName === 'A') {
            element.href = `mailto:${email}`;
        }
    };

    document.querySelectorAll('[data-protected-email]').forEach(reveal);
})();
