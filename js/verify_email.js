// js/verify_email.js
// Halaman verifikasi OTP email setelah registrasi.
feather.replace();

const verifyForm = document.getElementById('verifyForm');
const emailInput = document.getElementById('verifyEmail');
const otpInput = document.getElementById('otpCode');
const resendBtn = document.getElementById('resendBtn');
const alertBox = document.getElementById('alertBox');
const submitButton = verifyForm.querySelector('.btn-login');

const RESEND_COOLDOWN = 60; // detik, harus selaras dengan KSMEDU_OTP_RESEND_COOLDOWN
let cooldownTimer = null;

function showAlert(message, type = 'error') {
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.style.display = 'block';
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}

// Email dibawa lewat query string dari halaman registrasi (opsional).
(function prefillEmail() {
    const params = new URLSearchParams(window.location.search);
    const email = (params.get('email') || '').trim();
    if (email) {
        emailInput.value = email;
        otpInput.focus();
    }
})();

// Hanya digit yang diterima pada field OTP.
otpInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});

function startCooldown(seconds) {
    let remaining = seconds;
    resendBtn.disabled = true;
    resendBtn.textContent = `Kirim Ulang OTP (${remaining}s)`;

    clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
            clearInterval(cooldownTimer);
            resendBtn.disabled = false;
            resendBtn.textContent = 'Kirim Ulang OTP';
            return;
        }
        resendBtn.textContent = `Kirim Ulang OTP (${remaining}s)`;
    }, 1000);
}

verifyForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const email = emailInput.value.trim();
    const otp = otpInput.value.trim();

    if (!/^\d{6}$/.test(otp)) {
        showAlert('Kode OTP harus 6 digit angka.');
        return;
    }

    submitButton.classList.add('loading-state');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Memverifikasi...';

    try {
        const response = await fetch(`${window.APP_CONFIG.SERVICES}/verify_email_otp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, otp })
        });

        const result = await response.json();

        if (result.ok && result.already_verified) {
            showAlert(result.message || 'Email sudah terverifikasi.', 'success');
            setTimeout(() => {
                window.location.href = 'login';
            }, 1500);
            return;
        }

        if (result.ok) {
            showAlert('Verifikasi berhasil! Mengalihkan...', 'success');

            // Verifikasi sukses = login baru: bersihkan jejak sesi lama lalu
            // simpan token akun yang baru diaktifkan.
            if (window.AuthStorage) {
                window.AuthStorage.clearAll();
                window.AuthStorage.setTokens(result);
                window.AuthStorage.setSession({
                    email: (result.user && result.user.email) || email,
                    name: (result.user && result.user.name) || '',
                    role: (result.user && result.user.role) || 'user'
                });
            } else {
                if (window.TokenManager) window.TokenManager.clearTokens();
                sessionStorage.setItem('userLoggedIn', 'true');
                sessionStorage.setItem('userEmail', email);
                sessionStorage.setItem('userName', (result.user && result.user.name) || '');
                sessionStorage.setItem('userType', 'user');
            }

            setTimeout(() => {
                window.location.href = 'dashboard';
            }, 1500);
            return;
        }

        showAlert(result.message || 'Verifikasi gagal.');
        submitButton.classList.remove('loading-state');
        submitButton.textContent = originalText;
    } catch (error) {
        console.error('Verify OTP error:', error);
        showAlert('Terjadi kesalahan server.');
        submitButton.classList.remove('loading-state');
        submitButton.textContent = originalText;
    }
});

resendBtn.addEventListener('click', async function () {
    const email = emailInput.value.trim();
    if (!email) {
        showAlert('Masukkan alamat email terlebih dahulu.');
        return;
    }

    resendBtn.disabled = true;
    resendBtn.textContent = 'Mengirim...';

    try {
        const response = await fetch(`${window.APP_CONFIG.SERVICES}/resend_email_otp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });

        const result = await response.json();

        if (result.ok && result.already_verified) {
            showAlert(result.message || 'Email sudah terverifikasi.', 'success');
            resendBtn.disabled = false;
            resendBtn.textContent = 'Kirim Ulang OTP';
            return;
        }

        if (result.ok) {
            showAlert(result.message || 'Kode OTP baru telah dikirim.', 'success');
            otpInput.value = '';
            startCooldown(result.cooldown || RESEND_COOLDOWN);
            return;
        }

        showAlert(result.message || 'Gagal mengirim ulang OTP.');
        if (result.retry_after) {
            startCooldown(result.retry_after);
        } else {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Kirim Ulang OTP';
        }
    } catch (error) {
        console.error('Resend OTP error:', error);
        showAlert('Terjadi kesalahan server.');
        resendBtn.disabled = false;
        resendBtn.textContent = 'Kirim Ulang OTP';
    }
});
