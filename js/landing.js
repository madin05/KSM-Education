// js/landing.js

document.addEventListener("DOMContentLoaded", function () {
    // Initialize Feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Change Navigation styles on scroll
    const header = document.querySelector('.landing-header');
    if (header) {
        const handleScroll = () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        };
        window.addEventListener('scroll', handleScroll);
        handleScroll();
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

    // Language Switcher Dropdowns (Header & Footer)
    const langBtn = document.getElementById('langBtn');
    const langDropdown = document.getElementById('langDropdown');
    
    if (langBtn && langDropdown) {
        langBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            langDropdown.classList.toggle('show');
        });
        document.addEventListener('click', () => {
            langDropdown.classList.remove('show');
        });
    }

    const langBtnFooter = document.getElementById('langBtnFooter');
    const langDropdownFooter = document.getElementById('langDropdownFooter');
    
    if (langBtnFooter && langDropdownFooter) {
        langBtnFooter.addEventListener('click', (e) => {
            e.stopPropagation();
            langDropdownFooter.classList.toggle('show');
        });
        document.addEventListener('click', () => {
            langDropdownFooter.classList.remove('show');
        });
    }

    // Custom Search Filter Dropdown
    const filterTrigger = document.getElementById('filterTrigger');
    const filterDropdownMenu = document.getElementById('filterDropdownMenu');
    const searchFilter = document.getElementById('searchFilter');
    const filterCurrent = document.getElementById('filterCurrent');
    
    if (filterTrigger && filterDropdownMenu && searchFilter && filterCurrent) {
        filterTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            filterDropdownMenu.classList.toggle('show');
        });

        filterDropdownMenu.querySelectorAll('li').forEach(item => {
            item.addEventListener('click', function () {
                const val = this.getAttribute('data-value');
                const txt = this.textContent;
                
                // Update hidden input value
                searchFilter.value = val;
                
                // Update visible text
                filterCurrent.textContent = txt;
                
                // Toggle active class
                filterDropdownMenu.querySelectorAll('li').forEach(el => el.classList.remove('active'));
                this.classList.add('active');
                
                // Close dropdown
                filterDropdownMenu.classList.remove('show');
            });
        });

        document.addEventListener('click', () => {
            filterDropdownMenu.classList.remove('show');
        });
    }

    // Search Form Action Router
    const searchForm = document.getElementById('searchForm');
    
    if (searchForm && searchFilter) {
        searchForm.addEventListener('submit', function (e) {
            const filterValue = searchFilter.value;
            if (filterValue === 'articles') {
                searchForm.action = 'user/opinions_user.php';
            } else {
                searchForm.action = 'user/journals_user.php';
            }
        });
    }
});

// Helper function to switch languages via URL parameter
function changeLanguage(lang) {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.location.href = url.toString();
}
