// js/landing.js

document.addEventListener("DOMContentLoaded", function () {
    // Initialize Feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Change Navigation styles on scroll
    const header = document.querySelector('.landing-header');
    if (header) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const landingNavMenu = document.getElementById('landingNavMenu');
    
    if (mobileMenuBtn && landingNavMenu) {
        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            landingNavMenu.classList.toggle('active');
            
            // Toggle between menu and x icon
            const icon = mobileMenuBtn.querySelector('i');
            if (landingNavMenu.classList.contains('active')) {
                icon.setAttribute('data-feather', 'x');
            } else {
                icon.setAttribute('data-feather', 'menu');
            }
            if (typeof feather !== 'undefined') feather.replace();
        });

        // Close menu if user clicks outside
        document.addEventListener('click', () => {
            if (landingNavMenu.classList.contains('active')) {
                landingNavMenu.classList.remove('active');
                mobileMenuBtn.querySelector('i').setAttribute('data-feather', 'menu');
                if (typeof feather !== 'undefined') feather.replace();
            }
        });
    }
});
