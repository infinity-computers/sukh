// MOBILE MENU TOGGLE
const menuToggle = document.querySelector('.menu-toggle');
const navLinks = document.querySelector('nav ul');
if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('open');
    });
}

// GENERIC SLIDER INITIALIZATION
function initSlider(containerSelector, delay = 4000) {
    const container = document.querySelector(containerSelector);
    if (!container) return;
    const slides = Array.from(container.children);
    let idx = 0;
    function show(index) {
        slides.forEach((s, i) => {
            s.classList.toggle('active', i === index);
        });
        idx = index;
    }
    function next() {
        show((idx + 1) % slides.length);
    }
    let timer = setInterval(next, delay);
    container.addEventListener('mouseenter', () => clearInterval(timer));
    container.addEventListener('mouseleave', () => {
        timer = setInterval(next, delay);
    });
}

// fade‑in on scroll
const faders = document.querySelectorAll('.fade-in');
const appearOptions = {
    threshold: 0.2,
};
const appearOnScroll = new IntersectionObserver(function (entries, appearOnScroll) {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('visible');
        appearOnScroll.unobserve(entry.target);
    });
}, appearOptions);

faders.forEach(fader => {
    appearOnScroll.observe(fader);
});

// initialize sliders
initSlider('.hero-slider', 5000);

// ANIMATED COUNTER for Stats Bar
const counters = document.querySelectorAll('.stat-number[data-target]');
if (counters.length) {
    const counterObserver = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const el = entry.target;
            const target = +el.getAttribute('data-target');
            const duration = 2000;
            const start = performance.now();
            function update(now) {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                // ease-out curve
                const ease = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(ease * target);
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    el.textContent = target;
                }
            }
            requestAnimationFrame(update);
            counterObserver.unobserve(el);
        });
    }, { threshold: 0.5 });

    counters.forEach(c => counterObserver.observe(c));
}

// 3D CARD TILT effect on mouse move (desktop only)
if (!('ontouchstart' in window)) {
    const tiltCards = document.querySelectorAll(
        '.property-card, .service-card, .why-card, .team-card, .ptype-card, .google-review-card'
    );

    tiltCards.forEach(card => {
        card.addEventListener('mousemove', function (e) {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const rotateX = ((y - centerY) / centerY) * -6;  // max 6deg
            const rotateY = ((x - centerX) / centerX) * 6;

            requestAnimationFrame(() => {
                card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-5px) scale(1.02)`;
            });
        });

        card.addEventListener('mouseleave', function () {
            requestAnimationFrame(() => {
                card.style.transform = '';
            });
        });
    });
}
