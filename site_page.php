<?php
$pageKey = (string)($_GET['page'] ?? '');
$pages = [
    'mission' => [
        'eyebrow' => 'About NHRE', 'title' => 'Our Mission',
        'intro' => 'To make secure, connected healthcare information available wherever it is needed for better care.',
        'sections' => [
            ['One connected health record', 'NHRE brings hospitals, clinics, laboratories, pharmacies, and people onto one secure exchange so care teams can work from verified information.'],
            ['People first', 'We design services around dignity, consent, and practical access to care for every person.'],
            ['Built for trust', 'Privacy, accountable access, and resilient national infrastructure guide every part of the platform.'],
        ],
    ],
    'leadership' => [
        'eyebrow' => 'About NHRE', 'title' => 'Leadership',
        'intro' => 'NHRE is guided by healthcare, technology, privacy, and public-service leaders committed to a safer health ecosystem.',
        'sections' => [
            ['Clinical leadership', 'Clinical advisors help ensure workflows support safe, effective care in real healthcare settings.'],
            ['Technology leadership', 'Digital-health leaders shape reliable, interoperable services that can serve organizations across the country.'],
            ['Public accountability', 'Governance and privacy specialists help keep the exchange transparent, secure, and aligned with the public interest.'],
        ],
    ],
    'newsroom' => [
        'eyebrow' => 'About NHRE', 'title' => 'Newsroom',
        'intro' => 'Updates, service announcements, and stories from the National Healthcare Record Exchange.',
        'sections' => [
            ['Platform updates', 'Service updates and new capabilities will be shared here as they become available.'],
            ['Healthcare partnerships', 'NHRE will publish verified partnership and integration announcements in this newsroom.'],
            ['Media enquiries', 'For media and public-information enquiries, please use the Support Center contact route.'],
        ],
    ],
    'patient-portal' => [
        'eyebrow' => 'Services', 'title' => 'Patient Portal',
        'intro' => 'Manage your healthcare journey in one protected place.',
        'sections' => [
            ['Your record, in your hands', 'Review profile information, appointments, reports, vaccination details, and other available health services from your NHRE account.'],
            ['Care connections', 'Find care services, request appointments, and share relevant information with authorized providers.'],
            ['Get started', 'Create an NHRE account to access the patient portal and manage your healthcare information.'],
        ], 'cta' => ['Create an account', 'register.php'],
    ],
    'doctor-dashboard' => [
        'eyebrow' => 'Services', 'title' => 'Doctor Dashboard',
        'intro' => 'A focused workspace for managing appointments, patient access, and care coordination.',
        'sections' => [
            ['Appointment management', 'Review appointment schedules, patient requests, and follow-up notes in one workspace.'],
            ['Authorized information access', 'Access is designed to follow role-based controls and patient authorization requirements.'],
            ['Connected care', 'Coordinate with laboratories, pharmacies, and care teams through the NHRE ecosystem.'],
        ], 'cta' => ['Login to dashboard', 'login.php'],
    ],
    'security' => [
        'eyebrow' => 'Services', 'title' => 'Security',
        'intro' => 'Security and privacy are core requirements for every NHRE service.',
        'sections' => [
            ['Protected accounts', 'Secure authentication and role-based permissions help protect account access.'],
            ['Controlled sharing', 'Health information is intended to be available only to authorized people and organizations for legitimate purposes.'],
            ['Continuous improvement', 'We review platform controls and operational practices as services and requirements evolve.'],
        ], 'cta' => ['Read Privacy Policy', 'privacy_policy.php'],
    ],
    'how-it-works' => [
        'eyebrow' => 'Resources', 'title' => 'How It Works',
        'intro' => 'NHRE connects the important moments in a person’s care journey.',
        'sections' => [
            ['1. Create a verified account', 'A person or authorized healthcare professional creates an account with the information required for their role.'],
            ['2. Connect to services', 'Use the platform to access relevant care services, appointment tools, and available records.'],
            ['3. Share with authorization', 'Information is exchanged through role-based and authorized access workflows.'],
        ],
    ],
    'developer-apis' => [
        'eyebrow' => 'Resources', 'title' => 'Developer APIs',
        'intro' => 'Integration resources for organizations building trusted connections to NHRE.',
        'sections' => [
            ['Designed for interoperability', 'API services are being developed to support secure, standards-aligned healthcare integrations.'],
            ['Access for approved organizations', 'Technical access is intended for approved partners operating within NHRE security and governance requirements.'],
            ['Stay informed', 'Integration documentation and onboarding information will be published as partner API access becomes available.'],
        ],
    ],
    'support-center' => [
        'eyebrow' => 'Resources', 'title' => 'Support Center',
        'intro' => 'Find help with your NHRE account, platform access, and common service questions.',
        'sections' => [
            ['Account help', 'Use the password recovery flow if you cannot sign in, and keep your profile details up to date.'],
            ['Service guidance', 'Visit the Help & Support area after signing in for account-specific support options.'],
            ['Privacy and safety', 'If you believe your account has been accessed without permission, change your password and contact support promptly.'],
        ], 'cta' => ['Login for support', 'login.php'],
    ],
    'contact' => [
        'eyebrow' => 'Contact NHRE', 'title' => 'Contact',
        'intro' => 'Get in touch with the National Healthcare Record Exchange team.',
        'sections' => [
            ['General support', 'Sign in and use Help & Support for account-specific questions and service guidance.'],
            ['Organizations and partnerships', 'Healthcare organizations seeking to connect with NHRE can begin by reviewing the Developer APIs resource.'],
            ['Privacy enquiries', 'For questions about information handling, please review the Privacy Policy before contacting support.'],
        ], 'cta' => ['Open support center', 'site_page.php?page=support-center'],
    ],
];

if (!isset($pages[$pageKey])) {
    http_response_code(404);
    $pageKey = 'mission';
}
$page = $pages[$pageKey];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?> - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260811-16">
</head>
<body class="auth-body">
  <main class="policy-page container py-5">
    <article class="policy-card glass-card">
      <a class="auth-back-link" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
      <div class="policy-heading">
        <span class="auth-kicker"><?= htmlspecialchars($page['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
        <h1><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($page['intro'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <?php foreach ($page['sections'] as [$heading, $text]): ?>
        <section>
          <h2><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
          <p><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></p>
        </section>
      <?php endforeach; ?>
      <?php if (isset($page['cta'])): ?>
        <a href="<?= htmlspecialchars($page['cta'][1], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-auth-primary ripple mt-4"><?= htmlspecialchars($page['cta'][0], ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-arrow-right ms-1"></i></a>
      <?php endif; ?>
    </article>
  </main>
</body>
</html>
