<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NHRE — National Healthcare Record Exchange</title>
  <meta name="description"
    content="One Nation. One Health Record. The National Healthcare Record Exchange securely connects hospitals, clinics, labs, pharmacies, and patients.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260811-16">
  <script>
    (function () {
      try {
        var t = localStorage.getItem("nhre-theme");
        if (t !== "light" && t !== "dark") {
          t = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }
        document.documentElement.dataset.theme = t;
        document.documentElement.style.colorScheme = t;
      } catch (e) {}
    })();
  </script>
</head>

<body>

  <a href="#main" class="skip-link">Skip to main content</a>

  <!-- ================= NAVBAR ================= -->
  <nav class="navbar navbar-expand-lg navbar-nhre" id="mainNav">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#home">
        <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img">
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"
        aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars navbar-toggle-icon"></i>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav mx-auto my-3 my-lg-0 gap-1">
          <li class="nav-item"><a class="nav-link nav-link-nhre" href="#home">Home</a></li>
          <li class="nav-item"><a class="nav-link nav-link-nhre" href="#features">Features</a></li>
          <li class="nav-item"><a class="nav-link nav-link-nhre" href="#how-it-works">How It Works</a></li>
          <li class="nav-item"><a class="nav-link nav-link-nhre" href="#security">Security</a></li>
          <li class="nav-item"><a class="nav-link nav-link-nhre" href="#benefits">Benefits</a></li>
          <li class="nav-item"><a class="nav-link nav-link-nhre" href="site_page.php?page=contact">Contact</a></li>
        </ul>
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <a href="login.php" class="btn btn-outline-nhre ripple">Login</a>
          <a href="register.php" class="btn btn-solid-nhre ripple">Register</a>
        </div>
      </div>
    </div>
  </nav>

  <main id="main">
    <!-- ================= HERO ================= -->
    <header class="hero" id="home">
      <div class="hero-bg-anim" aria-hidden="true"></div>
      <canvas id="nhre-particles" aria-hidden="true"></canvas>
      <div class="floating-icon floating-icon-hospital" aria-hidden="true"><i
          class="fa-solid fa-house-chimney-medical"></i></div>
      <div class="floating-icon floating-icon-doctor" aria-hidden="true"><i class="fa-solid fa-user-doctor"></i></div>
      <div class="floating-icon floating-icon-cloud" aria-hidden="true"><i class="fa-solid fa-cloud"></i></div>
      <div class="floating-icon floating-icon-shield" aria-hidden="true"><i class="fa-solid fa-shield-heart"></i></div>
      <div class="floating-icon floating-icon-notes" aria-hidden="true"><i class="fa-solid fa-notes-medical"></i></div>

      <div class="container position-relative hero-content-layer">
        <div class="row align-items-center g-5">
          <div class="col-lg-6">
            <div class="hero-id-badge"><span class="dot"></span> National Digital Health Initiative — Live in 5,000+
              facilities</div>
            <div class="hero-eyebrow">Government-Grade Healthcare Exchange</div>
            <h1>One Nation<br><span class="accent">One Health Record</span></h1>
            <p class="lead-nhre">Securely access and share healthcare records anywhere, anytime. Empowering patients,
              doctors, hospitals, laboratories, and pharmacies through a unified national healthcare exchange.</p>
            <div class="d-flex gap-3 flex-wrap">
              <a href="register.php" class="btn btn-amber ripple">Get Started <i
                  class="fa-solid fa-arrow-right ms-1"></i></a>
              <a href="#how-it-works" class="btn btn-ghost-light ripple">Learn More</a>
            </div>
            <div class="hero-trust-row" aria-label="NHRE platform highlights">
              <span><i class="fa-solid fa-shield-halved"></i> Privacy-first</span>
              <span><i class="fa-solid fa-bolt"></i> Fast, connected care</span>
              <span><i class="fa-solid fa-circle-check"></i> Consent-controlled</span>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="hero-visual" aria-hidden="true">
              <div class="hero-photo"></div>
              <div class="network-wrap">
              <svg class="network-svg" viewBox="0 0 500 460" xmlns="http://www.w3.org/2000/svg">
                <defs>
                  <linearGradient id="lineGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#4FC3E0" stop-opacity="0.7" />
                    <stop offset="100%" stop-color="#E8A33D" stop-opacity="0.5" />
                  </linearGradient>
                </defs>
                <circle cx="250" cy="230" r="70" fill="rgba(255,255,255,0.06)" stroke="rgba(79,195,224,0.4)"
                  stroke-width="1.5" />
                <circle cx="250" cy="230" r="110" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1"
                  stroke-dasharray="4 6" />
                <line x1="250" y1="230" x2="90" y2="110" stroke="url(#lineGrad)" stroke-width="1.5" />
                <line x1="250" y1="230" x2="410" y2="100" stroke="url(#lineGrad)" stroke-width="1.5" />
                <line x1="250" y1="230" x2="80" y2="360" stroke="url(#lineGrad)" stroke-width="1.5" />
                <line x1="250" y1="230" x2="420" y2="370" stroke="url(#lineGrad)" stroke-width="1.5" />
                <circle cx="250" cy="230" r="8" fill="#4FC3E0" />
              </svg>
              <div class="node-card node-card-hospitals"><i class="fa-solid fa-hospital"></i> Hospitals</div>
              <div class="node-card node-card-doctors"><i class="fa-solid fa-user-doctor"></i> Doctors</div>
              <div class="node-card node-card-patients"><i class="fa-solid fa-user"></i> Patients</div>
              <div class="node-card node-card-labs"><i class="fa-solid fa-flask-vial"></i> Laboratories</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <a class="hero-scroll-cue" href="#features"><span>Explore the exchange</span><i class="fa-solid fa-arrow-down"></i></a>
    </header>

    <!-- ================= STATS ================= -->
    <section class="stats-section">
      <div class="container">
        <div class="row g-4 text-center">
          <div class="col-6 col-md">
            <div class="stat-num" data-count="10" data-suffix="M+">0</div>
            <div class="stat-label">Patients</div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-num" data-count="5000" data-suffix="+">0</div>
            <div class="stat-label">Hospitals</div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-num" data-count="25000" data-suffix="+">0</div>
            <div class="stat-label">Doctors</div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-num" data-count="100" data-suffix="M+">0</div>
            <div class="stat-label">Medical Records</div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-num" data-count="99.99" data-suffix="%" data-decimal="2">0</div>
            <div class="stat-label">Availability</div>
          </div>
        </div>
        <p class="stats-note"><i class="fa-solid fa-circle-info me-1"></i>Illustrative figures shown for demonstration purposes only.</p>
      </div>
    </section>

    <!-- ================= FEATURES ================= -->
    <section class="section-pad" id="features">
      <div class="container">
        <div class="section-head reveal">
          <span class="eyebrow">Platform Capabilities</span>
          <h2>Every part of care, on one exchange</h2>
          <p>From the patient's first registration to a lab result landing in a doctor's queue, NHRE keeps every party
            working from the same verified record.</p>
        </div>
        <div class="row g-4">
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-address-card"></i></div>
              <h5>Patient Portal</h5>
              <p>View medical history, prescriptions, vaccinations, allergies, and lab reports in one place.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-user-doctor"></i></div>
              <h5>Doctor Dashboard</h5>
              <p>Access patient history with authorization, create diagnoses, and issue prescriptions.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-hospital"></i></div>
              <h5>Hospital Integration</h5>
              <p>Connect hospitals nationwide through secure, standardized APIs.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-flask-vial"></i></div>
              <h5>Laboratory Exchange</h5>
              <p>Instant upload and retrieval of diagnostic reports between labs and providers.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-qrcode"></i></div>
              <h5>Digital Prescriptions</h5>
              <p>Generate electronic prescriptions with QR codes for instant pharmacy verification.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-truck-medical"></i></div>
              <h5>Emergency Access</h5>
              <p>Authorized emergency doctors can quickly view critical patient information.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-syringe"></i></div>
              <h5>Vaccination Records</h5>
              <p>A centralized, tamper-proof immunization history for every citizen.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-x-ray"></i></div>
              <h5>Medical Imaging</h5>
              <p>Store and retrieve MRI, CT scan, X-ray, and ultrasound references securely.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-user-shield"></i></div>
              <h5>Role-Based Access</h5>
              <p>Different permissions for patients, doctors, hospitals, labs, and administrators.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-4 reveal">
            <div class="feature-card">
              <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
              <h5>Analytics Dashboard</h5>
              <p>Government-level health insights and disease trend monitoring, in real time.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= HOW IT WORKS ================= -->
    <section class="section-pad hiw-section" id="how-it-works">
      <div class="container">
        <div class="section-head reveal">
          <span class="eyebrow">The Record's Journey</span>
          <h2>From registration to retrieval, in six steps</h2>
          <p>Every record moves through the same verified path — no step skipped, no access ungoverned.</p>
        </div>
        <div class="timeline-nhre position-relative">
          <div class="timeline-line" aria-hidden="true"></div>
          <div class="timeline-item reveal">
            <div class="timeline-num">01</div>
            <h5>Patient registers using National ID</h5>
            <p>A single, verified identity anchors every record that follows.</p>
          </div>
          <div class="timeline-item reveal">
            <div class="timeline-num">02</div>
            <h5>Hospital verifies identity</h5>
            <p>Facilities confirm the patient's identity before any record is linked.</p>
          </div>
          <div class="timeline-item reveal">
            <div class="timeline-num">03</div>
            <h5>Doctors upload diagnoses and prescriptions</h5>
            <p>Clinical notes and prescriptions attach directly to the patient's exchange record.</p>
          </div>
          <div class="timeline-item reveal">
            <div class="timeline-num">04</div>
            <h5>Laboratories upload test results</h5>
            <p>Diagnostic results sync automatically, ready for the ordering physician.</p>
          </div>
          <div class="timeline-item reveal">
            <div class="timeline-num">05</div>
            <h5>Patient controls who can access records</h5>
            <p>Consent settings let patients grant or revoke access at any time.</p>
          </div>
          <div class="timeline-item reveal">
            <div class="timeline-num">06</div>
            <h5>Authorized providers securely retrieve records</h5>
            <p>Only consented, role-verified providers can view the record — logged and auditable.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= SECURITY ================= -->
    <section class="section-pad security-section" id="security">
      <div class="container position-relative">
        <div class="row align-items-center g-5">
          <div class="col-lg-5 reveal">
            <span class="eyebrow">Built to Government Standard</span>
            <h2>Security isn't a feature. It's the foundation.</h2>
            <p>Every record, every transfer, every login is governed by layered, auditable protection modeled on
              internationally recognized healthcare privacy principles.</p>
            <div class="shield-wrap shield-preview d-none d-lg-flex">
              <svg class="shield-svg" width="150" height="170" viewBox="0 0 150 170" xmlns="http://www.w3.org/2000/svg">
                <path d="M75 5 L140 30 V80 C140 125 112 152 75 165 C38 152 10 125 10 80 V30 Z"
                  fill="rgba(79,195,224,0.12)" stroke="#4FC3E0" stroke-width="2" />
                <path d="M55 85 L70 100 L100 62" fill="none" stroke="#E8A33D" stroke-width="6" stroke-linecap="round"
                  stroke-linejoin="round" />
              </svg>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="row g-3">
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-lock"></i><span>AES-256 Encryption</span></div>
              </div>
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-user-shield"></i><span>Role-Based Access Control</span>
                </div>
              </div>
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-clipboard-list"></i><span>Audit Logs</span></div>
              </div>
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-mobile-screen-button"></i><span>Two-Factor
                    Authentication</span></div>
              </div>
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-plug-circle-bolt"></i><span>Secure APIs</span></div>
              </div>
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-hand-holding-medical"></i><span>Consent-Based Sharing</span>
                </div>
              </div>
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-file-shield"></i><span>HIPAA-Inspired Principles</span>
                </div>
              </div>
              <div class="col-sm-6 col-md-4 reveal">
                <div class="sec-card"><i class="fa-solid fa-database"></i><span>Data Privacy Protection</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= BENEFITS ================= -->
    <section class="section-pad" id="benefits">
      <div class="container">
        <div class="section-head reveal">
          <span class="eyebrow">Why It Matters</span>
          <h2>Built for every stakeholder in the system</h2>
          <p>A shared record changes what's possible for the people who use it — and the system that runs on it.</p>
        </div>
        <div class="row g-4">
          <div class="col-md-6 col-lg-3 reveal">
            <div class="benefit-card">
              <h5><span class="b-icon"><i class="fa-solid fa-user"></i></span> Patients</h5>
              <ul>
                <li><i class="fa-solid fa-check"></i> One medical history</li>
                <li><i class="fa-solid fa-check"></i> No repeated paperwork</li>
                <li><i class="fa-solid fa-check"></i> Faster treatment</li>
                <li><i class="fa-solid fa-check"></i> Better emergency care</li>
              </ul>
            </div>
          </div>
          <div class="col-md-6 col-lg-3 reveal">
            <div class="benefit-card">
              <h5><span class="b-icon"><i class="fa-solid fa-user-doctor"></i></span> Doctors</h5>
              <ul>
                <li><i class="fa-solid fa-check"></i> Complete patient history</li>
                <li><i class="fa-solid fa-check"></i> Better diagnosis</li>
                <li><i class="fa-solid fa-check"></i> Faster consultations</li>
              </ul>
            </div>
          </div>
          <div class="col-md-6 col-lg-3 reveal">
            <div class="benefit-card">
              <h5><span class="b-icon"><i class="fa-solid fa-hospital"></i></span> Hospitals</h5>
              <ul>
                <li><i class="fa-solid fa-check"></i> Reduced duplicate records</li>
                <li><i class="fa-solid fa-check"></i> Better collaboration</li>
                <li><i class="fa-solid fa-check"></i> Digital workflows</li>
              </ul>
            </div>
          </div>
          <div class="col-md-6 col-lg-3 reveal">
            <div class="benefit-card">
              <h5><span class="b-icon"><i class="fa-solid fa-landmark"></i></span> Government</h5>
              <ul>
                <li><i class="fa-solid fa-check"></i> Public health monitoring</li>
                <li><i class="fa-solid fa-check"></i> Disease outbreak analytics</li>
                <li><i class="fa-solid fa-check"></i> Better policy decisions</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= TECH STACK ================= -->
    <section class="section-pad tech-section">
      <div class="container">
        <div class="section-head reveal">
          <span class="eyebrow">Under the Hood</span>
          <h2>Built on standards a national system can trust</h2>
        </div>
        <div class="row g-4">
          <div class="col-6 col-lg-3 reveal">
            <div class="tech-card">
              <div class="tech-icon"><i class="fa-solid fa-display"></i></div>
              <h6>Frontend</h6>
              <div><span class="badge-tech">HTML</span><span class="badge-tech">CSS</span><span
                  class="badge-tech">Bootstrap</span><span class="badge-tech">JavaScript</span></div>
            </div>
          </div>
          <div class="col-6 col-lg-3 reveal">
            <div class="tech-card">
              <div class="tech-icon"><i class="fa-solid fa-server"></i></div>
              <h6>Backend</h6>
              <div><span class="badge-tech">FastAPI</span><span class="badge-tech">PHP</span></div>
            </div>
          </div>
          <div class="col-6 col-lg-3 reveal">
            <div class="tech-card">
              <div class="tech-icon"><i class="fa-solid fa-database"></i></div>
              <h6>Database</h6>
              <div><span class="badge-tech">MySQL</span></div>
            </div>
          </div>
          <div class="col-6 col-lg-3 reveal">
            <div class="tech-card">
              <div class="tech-icon"><i class="fa-solid fa-key"></i></div>
              <h6>Auth &amp; Hosting</h6>
              <div><span class="badge-tech">JWT</span><span class="badge-tech">Cloud Ready</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= TESTIMONIALS ================= -->
    <section class="section-pad">
      <div class="container">
        <div class="section-head reveal">
          <span class="eyebrow">From the Field</span>
          <h2>What the people on the exchange say</h2>
        </div>
        <div class="row g-4">
          <div class="col-md-4 reveal">
            <div class="testi-card">
              <p class="testi-quote">Before NHRE, I spent the first ten minutes of every consultation piecing together a
                history from memory and old paper files. Now it's already on the screen when the patient sits down.</p>
              <div class="testi-person">
                <div class="testi-avatar">RA</div>
                <div>
                  <div class="testi-name">Dr. Rukhsana Ahmed</div>
                  <div class="testi-role">Internal Medicine, Physician</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4 reveal">
            <div class="testi-card">
              <p class="testi-quote">Duplicate admissions used to cost us hours of reconciliation every week. The
                exchange gives every department the same verified record, and that alone changed how we operate.</p>
              <div class="testi-person">
                <div class="testi-avatar">MH</div>
                <div>
                  <div class="testi-name">Mahmudul Haque</div>
                  <div class="testi-role">Hospital Administrator</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4 reveal">
            <div class="testi-card">
              <p class="testi-quote">I moved cities last year and never had to carry a folder of old test results again.
                My new doctor already had everything, and I got to decide who could see it.</p>
              <div class="testi-person">
                <div class="testi-avatar">SN</div>
                <div>
                  <div class="testi-name">Sabrina Noor</div>
                  <div class="testi-role">Patient</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= FAQ ================= -->
    <section class="section-pad hiw-section">
      <div class="container">
        <div class="row">
          <div class="col-lg-5">
            <div class="section-head reveal">
              <span class="eyebrow">Common Questions</span>
              <h2>Answers before you register</h2>
              <p>Still unsure about something? Reach the team directly using the contact section below.</p>
            </div>
          </div>
          <div class="col-lg-7 reveal">
            <div class="accordion accordion-nhre" id="faqAccordion">
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">What
                    is NHRE?</button>
                </h3>
                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">NHRE is the National Healthcare Record Exchange — a unified platform that
                    connects hospitals, clinics, laboratories, pharmacies, and patients so a single, verified medical
                    record can move securely between them.</div>
                </div>
              </div>
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#faq2">Who can access my records?</button>
                </h3>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">Only healthcare providers you've granted consent to, plus authorized
                    emergency responders in critical situations. Every access is logged and visible to you.</div>
                </div>
              </div>
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#faq3">Is my data secure?</button>
                </h3>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">Yes. Records are protected with AES-256 encryption, two-factor
                    authentication, and role-based access control, with full audit logs on every transaction.</div>
                </div>
              </div>
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#faq4">Can hospitals share information?</button>
                </h3>
                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">Yes — hospitals, clinics, and labs connect through secure APIs and can
                    share diagnostic results, prescriptions, and history once patient consent is confirmed.</div>
                </div>
              </div>
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#faq5">Can I revoke access?</button>
                </h3>
                <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">Absolutely. Patients can review who currently has access and revoke
                    permissions at any time from the patient portal.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= CTA ================= -->
    <section class="section-pad" id="contact">
      <span class="anchor-target" id="login"></span>
      <span class="anchor-target" id="register"></span>
      <div class="container">
        <div class="cta-banner reveal">
          <div class="row align-items-center">
            <div class="col-lg-8">
              <h2>Join the Future of Digital Healthcare</h2>
              <p>Register your facility or create a patient account to start using the national healthcare exchange
                today.</p>
            </div>
            <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 mt-4 mt-lg-0 cta-actions">
              <a href="register.php" class="btn btn-amber ripple">Register</a>
              <a href="#how-it-works" class="btn btn-ghost-light ripple">Learn More</a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- ================= FOOTER ================= -->
  <footer class="footer-nhre">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img-footer">
          </div>
          <p class="footer-summary">A national initiative connecting every hospital, clinic, laboratory, and pharmacy
            into one secure healthcare exchange.</p>
        </div>
        <div class="col-6 col-lg-2">
          <h6>About</h6>
          <a href="site_page.php?page=mission" class="f-link">Our Mission</a>
          <a href="site_page.php?page=leadership" class="f-link">Leadership</a>
          <a href="site_page.php?page=newsroom" class="f-link">Newsroom</a>
        </div>
        <div class="col-6 col-lg-2">
          <h6>Services</h6>
          <a href="site_page.php?page=patient-portal" class="f-link">Patient Portal</a>
          <a href="site_page.php?page=doctor-dashboard" class="f-link">Doctor Dashboard</a>
          <a href="site_page.php?page=security" class="f-link">Security</a>
        </div>
        <div class="col-6 col-lg-2">
          <h6>Resources</h6>
          <a href="site_page.php?page=how-it-works" class="f-link">How It Works</a>
          <a href="site_page.php?page=developer-apis" class="f-link">Developer APIs</a>
          <a href="site_page.php?page=support-center" class="f-link">Support Center</a>
        </div>
        <div class="col-6 col-lg-2">
          <h6>Legal</h6>
          <a href="privacy_policy.php" class="f-link">Privacy Policy</a>
          <a href="terms_conditions.php" class="f-link">Terms of Service</a>
          <a href="site_page.php?page=contact" class="f-link">Contact</a>
        </div>
      </div>
      <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>© 2026 National Healthcare Record Exchange. All rights reserved.</div>
        <div>
          <a href="#" class="footer-social" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="#" class="footer-social" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>
          <a href="#" class="footer-social" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
          <a href="#" class="footer-social" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>

</html>
