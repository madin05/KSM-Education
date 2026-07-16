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

    // Guidelines Mobile Autoplay with Pause-on-Touch/Scroll Interaction
    const guidelinesGrid = document.querySelector('.guidelines-grid');
    if (guidelinesGrid) {
        let isUserInteracting = false;
        let autoplayInterval = null;
        let activeIndex = 0;
        let interactionTimeout = null;

        const startAutoplay = () => {
            if (autoplayInterval) clearInterval(autoplayInterval);
            autoplayInterval = setInterval(() => {
                if (isUserInteracting) return;
                
                const cards = guidelinesGrid.querySelectorAll('.guidelines-card');
                if (cards.length === 0) return;
                
                activeIndex = (activeIndex + 1) % cards.length;
                const targetCard = cards[activeIndex];
                
                guidelinesGrid.scrollTo({
                    left: targetCard.offsetLeft - guidelinesGrid.offsetLeft - 20, // offset for visual padding alignment
                    behavior: 'smooth'
                });
            }, 3000);
        };

        const stopAutoplay = () => {
            if (autoplayInterval) {
                clearInterval(autoplayInterval);
                autoplayInterval = null;
            }
        };

        const handleUserInteraction = () => {
            isUserInteracting = true;
            stopAutoplay();
            
            // Resume autoplay after 5 seconds of no interactions
            clearTimeout(interactionTimeout);
            interactionTimeout = setTimeout(() => {
                isUserInteracting = false;
                
                // Recalculate closest index based on current manual scroll location
                const cards = guidelinesGrid.querySelectorAll('.guidelines-card');
                if (cards.length > 0) {
                    const scrollLeft = guidelinesGrid.scrollLeft;
                    let closestIndex = 0;
                    let minDiff = Infinity;
                    cards.forEach((card, index) => {
                        const diff = Math.abs(card.offsetLeft - guidelinesGrid.offsetLeft - scrollLeft - 20);
                        if (diff < minDiff) {
                            minDiff = diff;
                            closestIndex = index;
                        }
                    });
                    activeIndex = closestIndex;
                }
                
                startAutoplay();
            }, 5000);
        };

        // Bind events for touch screens and scroll detection
        guidelinesGrid.addEventListener('touchstart', handleUserInteraction, { passive: true });
        guidelinesGrid.addEventListener('touchmove', handleUserInteraction, { passive: true });
        guidelinesGrid.addEventListener('scroll', function() {
            if (!isUserInteracting) {
                handleUserInteraction();
            }
        }, { passive: true });

        // Start horizontal autoplay initially if on mobile device
        if (window.innerWidth <= 768) {
            startAutoplay();
        }

        // React to viewport resize
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                startAutoplay();
            } else {
                stopAutoplay();
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
