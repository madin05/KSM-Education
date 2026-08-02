// js/register_user.js
feather.replace();

const registerForm = document.getElementById('registerForm');
const alertBox = document.getElementById('alertBox');
const submitButton = registerForm.querySelector('.btn-login');

function showAlert(message, type = 'error') {
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.style.display = 'block';
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

registerForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    const name = document.getElementById('regName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;

    // Client-side validation
    if (password.length < 6) {
        showAlert('Password minimal 6 karakter!');
        return;
    }

    if (password !== confirmPassword) {
        showAlert('Konfirmasi password tidak cocok!');
        return;
    }

    // Show loading state
    submitButton.classList.add('loading-state');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Mendaftar...';

    try {
        const response = await fetch(`${window.APP_CONFIG.SERVICES}/auth_register.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password })
        });

        const result = await response.json();

        if (result.ok) {
            showAlert(result.message || 'Registrasi berhasil! Mengalihkan...', 'success');

            // Akun belum aktif sampai OTP diverifikasi, jadi tidak ada token
            // yang disimpan di sini. Jejak sesi lama tetap dibersihkan.
            if (window.AuthStorage) {
                window.AuthStorage.clearAll();
            } else if (window.TokenManager) {
                window.TokenManager.clearTokens();
            }

            setTimeout(() => {
                window.location.href = 'verify_email?email=' + encodeURIComponent(email);
            }, 1500);
        } else {

            showAlert(result.message || 'Registrasi gagal.');
            submitButton.classList.remove('loading-state');
            submitButton.textContent = originalText;
        }
    } catch (error) {
        console.error('Registration error:', error);
        showAlert('Terjadi kesalahan server.');
        submitButton.classList.remove('loading-state');
        submitButton.textContent = originalText;
    }
});
