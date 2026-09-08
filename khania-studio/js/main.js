/**
 * KHANIA STUDIO — OFFICIAL WEBSITE INTERACTIVE SCRIPT
 * Document Code: KHANIA-SPEC-v1.3.1
 * Phase 3.1 — Navigation Simplification & Page Architecture Refinement
 * Features: Mobile Nav Drawer, Smooth Scroll, FAQ Accordion, Pricing Drawers, Portofolio Filter & Preview Modal, Blog Reader Modal, Contact Form Routing, Keyboard Accessibility
 */

document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initFaqAccordion();
  initPricingDrawers();
  initPortfolioFilter();
  initPortfolioModal();
  initBlogReaderModal();
  initContactForm();
  initSmoothScroll();
  initHeaderScroll();
  initKeyboardNav();
  auditWhatsAppRouting();
});

/**
 * 1. Mobile Navigation Toggle & Body Scroll Locking
 */
function initMobileNav() {
  const toggleBtn = document.querySelector('.mobile-toggle');
  const navMenu = document.querySelector('.nav-menu');
  const navLinks = document.querySelectorAll('.nav-menu a');

  if (!toggleBtn || !navMenu) return;

  function closeDrawer() {
    navMenu.classList.remove('active');
    toggleBtn.setAttribute('aria-expanded', 'false');
    toggleBtn.innerHTML = '&#9776;';
    document.body.style.overflow = '';
  }

  function openDrawer() {
    navMenu.classList.add('active');
    toggleBtn.setAttribute('aria-expanded', 'true');
    toggleBtn.innerHTML = '&#215;';
    document.body.style.overflow = 'hidden';
  }

  toggleBtn.addEventListener('click', () => {
    const isActive = navMenu.classList.contains('active');
    if (isActive) {
      closeDrawer();
    } else {
      openDrawer();
    }
  });

  // Close mobile nav when any menu link or CTA is clicked
  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      closeDrawer();
    });
  });

  // Store reference to close function globally for keyboard escape handling
  window._closeMobileNav = closeDrawer;
}

/**
 * 2. FAQ Accordion Logic & Keyboard Accessibility
 */
function initFaqAccordion() {
  const faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(item => {
    const questionBtn = item.querySelector('.faq-question');
    if (!questionBtn) return;

    questionBtn.addEventListener('click', () => {
      const isCurrentlyActive = item.classList.contains('active');

      // Close all other FAQ items for clean accordion UX
      faqItems.forEach(otherItem => {
        otherItem.classList.remove('active');
        const otherBtn = otherItem.querySelector('.faq-question');
        if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
      });

      // Toggle clicked item
      if (!isCurrentlyActive) {
        item.classList.add('active');
        questionBtn.setAttribute('aria-expanded', 'true');
      }
    });

    // Keyboard Enter / Space support
    questionBtn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        questionBtn.click();
      }
    });
  });
}

/**
 * 3. Pricing Package [Info Detail] Drawers
 */
function initPricingDrawers() {
  const detailBtns = document.querySelectorAll('.info-detail-btn');

  detailBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      if (!targetId) return;

      const drawer = document.getElementById(targetId);
      if (!drawer) return;

      const isOpen = drawer.classList.toggle('open');
      btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      
      const labelText = isOpen ? 'Sembunyikan Detail &#9650;' : '[Info Detail] &#9660;';
      btn.innerHTML = labelText;
    });

    // Keyboard Enter / Space support
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        btn.click();
      }
    });
  });
}

/**
 * 4. Portofolio Category Filtering
 */
function initPortfolioFilter() {
  const filterBtns = document.querySelectorAll('.filter-btn');
  const portfolioCards = document.querySelectorAll('.portfolio-card');

  if (!filterBtns.length || !portfolioCards.length) return;

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const filterCategory = btn.getAttribute('data-filter');

      // Update active button state
      filterBtns.forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-pressed', 'true');

      // Filter portfolio items
      portfolioCards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');

        if (filterCategory === 'all' || cardCategory === filterCategory) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });

    // Keyboard Enter / Space support
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        btn.click();
      }
    });
  });
}

/**
 * 5. Portofolio Detail Preview Modal Controller
 */
function initPortfolioModal() {
  const modal = document.getElementById('portfolio-modal');
  const portfolioCards = document.querySelectorAll('.portfolio-card');

  if (!modal || !portfolioCards.length) return;

  const modalImg = document.getElementById('modal-img');
  const modalTag = document.getElementById('modal-tag');
  const modalTitle = document.getElementById('modal-title');
  const modalCategory = document.getElementById('modal-category');
  const modalDescription = document.getElementById('modal-description');
  const modalCloseBtn = modal.querySelector('.modal-close');
  let lastFocusedElement = null;

  function openModal(card) {
    const imgEl = card.querySelector('.portfolio-thumb img');
    const tagEl = card.querySelector('.portfolio-tag');
    const titleEl = card.querySelector('.portfolio-info h3');
    const categoryEl = card.querySelector('.portfolio-category');
    const descEl = card.querySelector('.portfolio-info p');

    if (!imgEl || !titleEl || !categoryEl || !descEl) return;

    lastFocusedElement = document.activeElement;

    modalImg.src = imgEl.src;
    modalImg.alt = imgEl.alt;
    
    if (tagEl) {
      modalTag.textContent = tagEl.textContent;
      modalTag.className = tagEl.className;
      modalTag.style.display = 'inline-block';
    } else {
      modalTag.style.display = 'none';
    }

    modalTitle.textContent = titleEl.textContent;
    modalCategory.textContent = categoryEl.textContent;
    modalDescription.textContent = descEl.textContent;

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    if (modalCloseBtn) modalCloseBtn.focus();
  }

  function closeModal() {
    if (!modal.classList.contains('open')) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';

    if (lastFocusedElement) {
      lastFocusedElement.focus();
    }
  }

  portfolioCards.forEach(card => {
    card.addEventListener('click', () => openModal(card));
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openModal(card);
      }
    });
  });

  if (modalCloseBtn) {
    modalCloseBtn.addEventListener('click', closeModal);
  }

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeModal();
    }
  });

  window._closePortfolioModal = closeModal;
}

/**
 * 6. Blog Article Reader Modal Controller
 */
function initBlogReaderModal() {
  const modal = document.getElementById('article-reader-modal');
  const readBtns = document.querySelectorAll('.read-article-btn');
  if (!modal || !readBtns.length) return;

  const articleTitle = document.getElementById('article-title');
  const articleCategory = document.getElementById('article-category');
  const articleBody = document.getElementById('article-body');
  const closeBtns = modal.querySelectorAll('.close-article-modal');

  const articlesData = {
    '1': {
      title: 'Mengapa UMKM Membutuhkan Website Profesional di Era Digital?',
      category: 'Strategi Digital UMKM',
      body: '<p style="margin-bottom: 1rem;">Di era serba digital saat ini, kehadiran online bukan lagi sekadar pilihan pelengkap, melainkan aset strategis utama bagi perkembangan usaha Mikro, Kecil, dan Menengah (UMKM) di Indonesia.</p><p style="margin-bottom: 1rem;">Memiliki website resmi berbasis nama domain bisnis memberikan kredibilitas instan di mata calon pelanggan. Calon pelanggan dapat mempelajari profil produk, jam operasional, serta portofolio secara mandiri sebelum melanjutkan transaksi via WhatsApp.</p><p>Dengan website berbasis sistem referensi dari Khania Studio, Anda dapat memiliki landing page profesional dalam waktu produksi awal 24–72 jam kerja tanpa proses yang rumit.</p>'
    },
    '2': {
      title: 'Berapa Biaya Membuat Website untuk Bisnis? Panduan Memilih Paket yang Tepat',
      category: 'Panduan & Transparansi Biaya',
      body: '<p style="margin-bottom: 1rem;">Salah satu kekhawatiran terbesar pelaku usaha saat ingin membuat website adalah ketakutan akan adanya biaya tersembunyi atau invoice yang membengkak.</p><p style="margin-bottom: 1rem;">Khania Studio menghadirkan transparansi biaya penuh sejak awal. Paket pembuatan website dimulai dari Paket Starter (Rp400.000/tahun), Bronze (Rp580.000/tahun), Silver (Rp1.100.000/tahun), hingga Gold (Rp1.750.000/tahun). Seluruh paket sudah mencakup pendaftaran domain &amp; hosting selama 1 tahun.</p><p>Formula perpanjangan tahun berikutnya pun dijelaskan secara transparan tanpa syarat tersembunyi.</p>'
    },
    '3': {
      title: 'Punya Website Referensi yang Anda Suka? Begini Cara Kami Mengadaptasinya',
      category: 'Workflow & Desain Referensi',
      body: '<p style="margin-bottom: 1rem;">Banyak pemilik bisnis merasa kesulitan menyampaikan konsep tata letak atau inspirasi visual impian mereka kepada tim pembuat website.</p><p style="margin-bottom: 1rem;">Metode <em>Reference-Based Design Customization</em> Khania Studio mempermudah proses ini. Anda hanya perlu mengirimkan link atau foto contoh website yang Anda sukai. Tim kami akan menganalisis tata letak dan menyesuaikannya dengan logo, warna brand, serta materi usaha Anda.</p><p>Proses ini memangkas waktu diskusi desain yang berlarut-larut sehingga website Anda siap online secara terukur.</p>'
    }
  };

  function openArticleModal(id) {
    const data = articlesData[id];
    if (!data) return;

    articleTitle.textContent = data.title;
    articleCategory.textContent = data.category;
    articleBody.innerHTML = data.body;

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeArticleModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  readBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const articleId = btn.getAttribute('data-article');
      openArticleModal(articleId);
    });
  });

  closeBtns.forEach(btn => {
    btn.addEventListener('click', closeArticleModal);
  });

  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeArticleModal();
  });

  window._closeArticleModal = closeArticleModal;
}

/**
 * 7. Contact Form Dual Submission Handler (WhatsApp & Email)
 */
function initContactForm() {
  const form = document.getElementById('consultation-form');
  const btnWa = document.getElementById('btn-submit-wa');
  const btnEmail = document.getElementById('btn-submit-email');

  if (!form) return;

  function validateAndGetData() {
    if (!form.checkValidity()) {
      form.reportValidity();
      return null;
    }

    const nameEl = document.getElementById('contact-name');
    const phoneEl = document.getElementById('contact-phone');
    const emailEl = document.getElementById('contact-email');
    const cityEl = document.getElementById('contact-city');
    const msgEl = document.getElementById('contact-message');

    return {
      name: nameEl ? nameEl.value.trim() : '',
      phone: phoneEl ? phoneEl.value.trim() : '',
      email: emailEl ? emailEl.value.trim() : '',
      city: cityEl ? cityEl.value.trim() : '',
      message: msgEl ? msgEl.value.trim() : 'Saya mau konsultasi'
    };
  }

  if (btnWa) {
    btnWa.addEventListener('click', (e) => {
      e.preventDefault();
      const data = validateAndGetData();
      if (!data) return;

      const text = `Halo Khania Studio,\n\nSaya ingin konsultasi pembuatan website.\n\nNama: ${data.name}\nNo HP/WA: ${data.phone}\nEmail: ${data.email}\nKota Domisili: ${data.city}\nPesan / Kebutuhan: ${data.message}`;
      const waUrl = `https://wa.me/6285286939234?text=${encodeURIComponent(text)}`;
      
      window.open(waUrl, '_blank', 'noopener');
    });
  }

  if (btnEmail) {
    btnEmail.addEventListener('click', (e) => {
      e.preventDefault();
      const data = validateAndGetData();
      if (!data) return;

      const subject = `Konsultasi Website — ${data.name}`;
      const body = `Nama: ${data.name}\n\nNomor HP / WhatsApp: ${data.phone}\n\nAlamat Email: ${data.email}\n\nKota Domisili: ${data.city}\n\nPesan / Kebutuhan Website:\n\n${data.message}`;
      
      const mailtoUrl = `mailto:admin@khania-studio.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      window.location.href = mailtoUrl;
    });
  }
}

/**
 * 8. Smooth Scrolling & Anchor Offset
 */
function initSmoothScroll() {
  const anchorLinks = document.querySelectorAll('a[href^="#"]:not([href="#"])');

  anchorLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const targetId = link.getAttribute('href').substring(1);
      const targetElement = document.getElementById(targetId);

      if (targetElement) {
        e.preventDefault();
        targetElement.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });

        // Close mobile drawer if active
        if (typeof window._closeMobileNav === 'function') {
          window._closeMobileNav();
        }
      }
    });
  });
}

/**
 * 9. Sticky Header Scroll Shadow
 */
function initHeaderScroll() {
  const header = document.querySelector('.header');
  if (!header) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 20) {
      header.style.boxShadow = '0 4px 20px rgba(15, 23, 42, 0.4)';
    } else {
      header.style.boxShadow = 'none';
    }
  });
}

/**
 * 10. Global Keyboard Accessibility (Escape Key Listener)
 */
function initKeyboardNav() {
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (typeof window._closePortfolioModal === 'function') {
        window._closePortfolioModal();
      }
      if (typeof window._closeArticleModal === 'function') {
        window._closeArticleModal();
      }
      if (typeof window._closeMobileNav === 'function') {
        window._closeMobileNav();
      }
    }
  });
}

/**
 * 11. WhatsApp Link & Route Audit
 */
function auditWhatsAppRouting() {
  const waLinks = document.querySelectorAll('a[href*="wa.me"]');
  waLinks.forEach(link => {
    const href = link.getAttribute('href');
    if (href && !href.includes('6285286939234')) {
      console.warn('Non-official WhatsApp number detected in CTA link:', href);
    }
    if (!link.getAttribute('target')) {
      link.setAttribute('target', '_blank');
    }
    if (!link.getAttribute('rel')) {
      link.setAttribute('rel', 'noopener');
    }
  });
}
