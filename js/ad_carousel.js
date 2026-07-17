// =========================================================
// AD CAROUSEL — autoplay tiap 5 detik, panah, dots, pause on hover
// =========================================================

(function () {
  function initAdCarousel() {
    const root = document.getElementById("ksmAdCarousel");
    if (!root) return;

    const track = document.getElementById("ksmAdTrack");
    const slides = Array.from(track.children);
    const prevBtn = document.getElementById("ksmAdPrev");
    const nextBtn = document.getElementById("ksmAdNext");
    const dots = Array.from(document.querySelectorAll("#ksmAdDots .ksm-ad-dot"));

    const AUTOPLAY_MS = 5000;
    let current = 0;
    let timer = null;

    function goTo(index) {
      current = (index + slides.length) % slides.length;
      track.style.transform = `translateX(-${current * 100}%)`;
      dots.forEach((dot, i) => dot.classList.toggle("active", i === current));
    }

    function next() {
      goTo(current + 1);
    }

    function prev() {
      goTo(current - 1);
    }

    function startAutoplay() {
      stopAutoplay();
      timer = setInterval(next, AUTOPLAY_MS);
    }

    function stopAutoplay() {
      if (timer) clearInterval(timer);
      timer = null;
    }

    nextBtn.addEventListener("click", () => {
      next();
      startAutoplay(); // reset timer supaya tidak langsung geser lagi
    });

    prevBtn.addEventListener("click", () => {
      prev();
      startAutoplay();
    });

    dots.forEach((dot) => {
      dot.addEventListener("click", () => {
        goTo(parseInt(dot.dataset.index, 10));
        startAutoplay();
      });
    });

    root.addEventListener("mouseenter", stopAutoplay);
    root.addEventListener("mouseleave", startAutoplay);

    // Support swipe di mobile (dasar, tanpa library tambahan)
    let touchStartX = 0;
    track.addEventListener(
      "touchstart",
      (e) => {
        touchStartX = e.touches[0].clientX;
        stopAutoplay();
      },
      { passive: true },
    );
    track.addEventListener(
      "touchend",
      (e) => {
        const deltaX = e.changedTouches[0].clientX - touchStartX;
        if (deltaX > 40) prev();
        else if (deltaX < -40) next();
        startAutoplay();
      },
      { passive: true },
    );

    goTo(0);
    startAutoplay();
  }

  document.addEventListener("DOMContentLoaded", initAdCarousel);
})();