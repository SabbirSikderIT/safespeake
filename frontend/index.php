<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SafeSpeak — Anonymous Reporting Platform</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #0a0e27;
  --blue: #1a56e8;
  --blue-light: #3b82f6;
  --cyan: #06b6d4;
  --teal: #0d9488;
  --white: #ffffff;
  --gray-50: #f8fafc;
  --gray-100: #f1f5f9;
  --gray-200: #e2e8f0;
  --gray-400: #94a3b8;
  --gray-600: #475569;
  --gray-800: #1e293b;
  --red: #ef4444;
  --amber: #f59e0b;
  --green: #10b981;
  --grad: linear-gradient(135deg, #1a56e8 0%, #06b6d4 100%);
  --grad-dark: linear-gradient(135deg, #0f2460 0%, #0a3d6b 100%);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: var(--white); overflow-x: hidden; }

/* NAV */
nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
  padding: 1rem 2rem;
  background: rgba(10,14,39,0.85);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  display: flex; align-items: center; justify-content: space-between;
}
.nav-logo { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800; }
.nav-logo span { color: var(--cyan); }
.nav-links { display: flex; align-items: center; gap: 2rem; }
.nav-links a { color: var(--gray-400); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
.nav-links a:hover { color: var(--white); }
.nav-btns { display: flex; gap: 0.75rem; }
.btn { padding: 0.6rem 1.4rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; font-family: 'DM Sans', sans-serif; }
.btn-outline { background: transparent; border: 1.5px solid rgba(255,255,255,0.2); color: var(--white); }
.btn-outline:hover { border-color: var(--blue-light); color: var(--blue-light); }
.btn-primary { background: var(--grad); color: var(--white); }
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 8px 25px rgba(26,86,232,0.4); }
.btn-lg { padding: 0.9rem 2rem; font-size: 1rem; border-radius: 10px; }
.btn-white { background: var(--white); color: var(--navy); font-weight: 600; }
.btn-white:hover { background: var(--gray-100); transform: translateY(-1px); }

/* HERO */
.hero {
  min-height: 100vh;
  display: flex; align-items: center;
  position: relative; overflow: hidden;
  padding-top: 80px;
}
.hero-bg {
  position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(26,86,232,0.25) 0%, transparent 60%),
              radial-gradient(ellipse 60% 50% at 80% 50%, rgba(6,182,212,0.15) 0%, transparent 60%),
              var(--navy);
}
.hero-grid {
  position: absolute; inset: 0; opacity: 0.04;
  background-image: linear-gradient(rgba(255,255,255,0.3) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,0.3) 1px, transparent 1px);
  background-size: 60px 60px;
}
.hero-content { position: relative; max-width: 1200px; margin: 0 auto; padding: 0 2rem; text-align: center; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: rgba(26,86,232,0.15); border: 1px solid rgba(26,86,232,0.3);
  padding: 0.4rem 1rem; border-radius: 100px; font-size: 0.8rem; color: var(--blue-light);
  margin-bottom: 2rem; animation: fadeDown 0.6s ease;
}
.hero-badge::before { content: '✨'; }
.hero h1 {
  font-family: 'Syne', sans-serif; font-size: clamp(2.5rem, 6vw, 5rem); font-weight: 800;
  line-height: 1.05; margin-bottom: 1.5rem; animation: fadeUp 0.7s ease 0.1s both;
}
.hero h1 .accent { background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.hero p {
  font-size: 1.2rem; color: var(--gray-400); max-width: 600px; margin: 0 auto 2.5rem;
  line-height: 1.7; animation: fadeUp 0.7s ease 0.2s both;
}
.hero-btns { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; animation: fadeUp 0.7s ease 0.3s both; }
.hero-stats {
  display: flex; gap: 3rem; justify-content: center; margin-top: 4rem;
  flex-wrap: wrap; animation: fadeUp 0.7s ease 0.5s both;
}
.stat { text-align: center; }
.stat-num { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--white); }
.stat-num span { color: var(--cyan); }
.stat-label { font-size: 0.8rem; color: var(--gray-400); margin-top: 0.25rem; }

/* FLOATING CARDS */
.hero-float {
  display: flex; justify-content: center; gap: 1.5rem; margin-top: 3.5rem;
  flex-wrap: wrap; animation: fadeUp 0.7s ease 0.4s both;
}
.float-card {
  background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
  backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem 1.5rem;
  display: flex; align-items: center; gap: 0.75rem;
  transition: transform 0.2s, border-color 0.2s;
}
.float-card:hover { transform: translateY(-3px); border-color: rgba(26,86,232,0.4); }
.float-card .icon { font-size: 1.3rem; }
.float-card .text { font-size: 0.85rem; color: var(--gray-200); font-weight: 500; }

/* SECTIONS */
section { padding: 5rem 2rem; }
.container { max-width: 1200px; margin: 0 auto; }
.section-tag { color: var(--cyan); font-size: 0.8rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.75rem; }
.section-title { font-family: 'Syne', sans-serif; font-size: clamp(1.8rem, 3.5vw, 2.8rem); font-weight: 700; margin-bottom: 1rem; }
.section-desc { color: var(--gray-400); font-size: 1.05rem; max-width: 550px; line-height: 1.7; }

/* STATS SECTION */
.stats-section { background: rgba(255,255,255,0.02); border-top: 1px solid rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.06); }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; }
.stat-card {
  background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px; padding: 2rem; text-align: center;
  transition: border-color 0.2s, transform 0.2s;
}
.stat-card:hover { border-color: rgba(26,86,232,0.4); transform: translateY(-4px); }
.stat-card .big-num { font-family: 'Syne', sans-serif; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; }
.stat-card.red .big-num { color: #ef4444; }
.stat-card.amber .big-num { color: #f59e0b; }
.stat-card.blue .big-num { color: var(--blue-light); }
.stat-card p { color: var(--gray-400); font-size: 0.875rem; line-height: 1.5; }
.stat-card .source { font-size: 0.7rem; color: var(--gray-600); margin-top: 0.5rem; font-style: italic; }

/* FEATURES */
.features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 3rem; }
.feature-card {
  background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px; padding: 1.75rem;
  transition: all 0.3s;
  position: relative; overflow: hidden;
}
.feature-card::before {
  content: ''; position: absolute; inset: 0; opacity: 0;
  transition: opacity 0.3s;
  background: linear-gradient(135deg, rgba(26,86,232,0.1) 0%, transparent 60%);
}
.feature-card:hover { border-color: rgba(26,86,232,0.3); transform: translateY(-4px); }
.feature-card:hover::before { opacity: 1; }
.feature-icon { font-size: 2rem; margin-bottom: 1rem; }
.feature-card h3 { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
.feature-card p { color: var(--gray-400); font-size: 0.875rem; line-height: 1.6; }

/* HOW IT WORKS */
.how-section { background: rgba(255,255,255,0.01); }
.steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 3rem; }
.step { text-align: center; padding: 1.5rem; }
.step-num {
  width: 52px; height: 52px; border-radius: 50%; margin: 0 auto 1rem;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 800;
  background: var(--grad); color: white;
}
.step h3 { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 0.4rem; }
.step p { color: var(--gray-400); font-size: 0.85rem; line-height: 1.6; }
.step-connector { display: none; }

/* CTA */
.cta-section {
  background: var(--grad);
  border-radius: 24px; padding: 4rem; text-align: center;
  margin: 0 2rem 5rem;
  position: relative; overflow: hidden;
}
.cta-section::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse 60% 80% at 50% 50%, rgba(255,255,255,0.1) 0%, transparent 70%);
}
.cta-section h2 { font-family: 'Syne', sans-serif; font-size: 2.2rem; font-weight: 800; margin-bottom: 1rem; position: relative; }
.cta-section p { color: rgba(255,255,255,0.8); font-size: 1.05rem; margin-bottom: 2rem; position: relative; }
.cta-btns { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; }

/* PORTAL CARDS */
.portals { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 3rem; }
.portal-card {
  border-radius: 20px; padding: 2.5rem; position: relative; overflow: hidden;
  transition: transform 0.3s, box-shadow 0.3s;
}
.portal-card:hover { transform: translateY(-6px); }
.portal-student { background: linear-gradient(135deg, rgba(26,86,232,0.2) 0%, rgba(6,182,212,0.1) 100%); border: 1px solid rgba(26,86,232,0.3); }
.portal-admin { background: linear-gradient(135deg, rgba(13,148,136,0.2) 0%, rgba(6,182,212,0.1) 100%); border: 1px solid rgba(13,148,136,0.3); }
.portal-card .portal-icon { font-size: 3rem; margin-bottom: 1.25rem; }
.portal-card h3 { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 700; margin-bottom: 0.5rem; }
.portal-card p { color: var(--gray-400); font-size: 0.9rem; margin-bottom: 1.75rem; line-height: 1.6; }
.portal-features { list-style: none; margin-bottom: 1.75rem; }
.portal-features li { font-size: 0.85rem; color: var(--gray-300); padding: 0.3rem 0; }
.portal-features li::before { content: '✓ '; color: var(--cyan); font-weight: 700; }

/* FOOTER */
footer {
  background: rgba(0,0,0,0.3); border-top: 1px solid rgba(255,255,255,0.06);
  padding: 2rem; text-align: center; color: var(--gray-600); font-size: 0.875rem;
}

/* ANIMATIONS */
@keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

/* RESPONSIVE */
@media (max-width: 768px) {
  nav { padding: 1rem; }
  .nav-links { display: none; }
  .portals { grid-template-columns: 1fr; }
  .hero-stats { gap: 1.5rem; }
  .cta-section { margin: 0 1rem 3rem; padding: 2.5rem 1.5rem; }
  .cta-section h2 { font-size: 1.6rem; }
}

/* Track Report Bar */
.track-bar {
  background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
  border-radius: 16px; padding: 1.5rem 2rem;
  display: flex; align-items: center; gap: 1rem; margin-top: 2rem;
  max-width: 600px; margin: 2rem auto 0;
  flex-wrap: wrap;
}
.track-bar input {
  flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15);
  border-radius: 8px; padding: 0.7rem 1rem; color: var(--white); font-size: 0.9rem;
  font-family: 'DM Sans', sans-serif; outline: none; min-width: 180px;
}
.track-bar input::placeholder { color: var(--gray-600); }
.track-bar input:focus { border-color: var(--blue-light); }
</style>
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>

<nav>
  <div class="nav-logo">Safe<span>Speak</span></div>
  <div class="nav-links">
    <a href="#problem">Problem</a>
    <a href="#solution">Solution</a>
    <a href="#how-it-works">How It Works</a>
    <a href="#portals">Portals</a>
  </div>
  <div class="nav-btns">
    <a href="login.php" class="btn btn-outline">Student Login</a>
    <a href="admin/login.php" class="btn btn-primary">Admin Panel</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-badge">AI-Enhanced Platform</div>
    <h1>Speak Up.<br><span class="accent">Stay Anonymous.</span><br>Be Safe.</h1>
    <p>SafeSpeak empowers students to report bullying, harassment, and misconduct anonymously — with AI-powered analysis ensuring every report gets the attention it deserves.</p>
    <div class="hero-btns">
      <a href="register.php" class="btn btn-primary btn-lg">Submit a Report →</a>
      <a href="report_status.php" class="btn btn-outline btn-lg">Track My Report</a>
    </div>
    <div class="hero-float">
      <div class="float-card"><span class="icon">🔒</span><span class="text">100% Anonymous</span></div>
      <div class="float-card"><span class="icon">🤖</span><span class="text">AI-Powered Analysis</span></div>
      <div class="float-card"><span class="icon">🛡️</span><span class="text">Zero Tolerance Policy</span></div>
      <div class="float-card"><span class="icon">📊</span><span class="text">Real-Time Dashboard</span></div>
    </div>

    <!-- Quick Track -->
    <div class="track-bar">
      <span style="font-size:0.85rem;color:var(--gray-400);white-space:nowrap;">Quick Track:</span>
      <input type="text" id="quickTrackInput" placeholder="Enter Tracking ID e.g. SS-2024-000001">
      <button class="btn btn-primary" onclick="quickTrack()">Track</button>
    </div>
  </div>
</section>

<!-- STATS / PROBLEM -->
<section class="stats-section" id="problem">
  <div class="container">
    <div class="section-tag">Evidence-Based Research</div>
    <h2 class="section-title">The Problem Is Real</h2>
    <p class="section-desc">Alarming statistics from Bangladesh's educational institutions reveal a crisis that demands immediate action.</p>
    <div class="stats-grid" style="margin-top:2.5rem;">
      <div class="stat-card red">
        <div class="big-num">50%</div>
        <p>Students experienced bullying in the past year in Bangladesh schools</p>
        <div class="source">Source: ScienceDirect Research, 2021</div>
      </div>
      <div class="stat-card amber">
        <div class="big-num">37%</div>
        <p>University students affected by anxiety, depression & academic issues due to harassment</p>
        <div class="source">Source: Academia Research Study</div>
      </div>
      <div class="stat-card blue">
        <div class="big-num">80%+</div>
        <p>Cases remain completely unreported due to fear of identity exposure and retaliation</p>
        <div class="source">Source: Internal Research</div>
      </div>
      <div class="stat-card" style="border-color:rgba(16,185,129,0.3);">
        <div class="big-num" style="color:var(--green);">0</div>
        <p>Proper anonymous reporting platforms in most Bangladesh universities before SafeSpeak</p>
        <div class="source">Gap in existing systems</div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES / SOLUTION -->
<section id="solution">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center;" class="sol-grid">
      <div>
        <div class="section-tag">Our Solution</div>
        <h2 class="section-title">Why SafeSpeak?</h2>
        <p class="section-desc">A dedicated platform where students can anonymously report bullying or harassment to their university proctor office, who then reviews and takes action.</p>
        <div style="margin-top:2rem;display:flex;flex-direction:column;gap:0.75rem;">
          <div style="display:flex;align-items:center;gap:0.75rem;"><span style="color:var(--green);font-size:1.2rem;">✓</span><span style="font-size:0.9rem;">Full anonymity — no identity ever revealed</span></div>
          <div style="display:flex;align-items:center;gap:0.75rem;"><span style="color:var(--green);font-size:1.2rem;">✓</span><span style="font-size:0.9rem;">AI auto-analyzes urgency &amp; sentiment</span></div>
          <div style="display:flex;align-items:center;gap:0.75rem;"><span style="color:var(--green);font-size:1.2rem;">✓</span><span style="font-size:0.9rem;">Organized admin dashboard for proctors</span></div>
          <div style="display:flex;align-items:center;gap:0.75rem;"><span style="color:var(--green);font-size:1.2rem;">✓</span><span style="font-size:0.9rem;">Unique tracking ID for every report</span></div>
          <div style="display:flex;align-items:center;gap:0.75rem;"><span style="color:var(--green);font-size:1.2rem;">✓</span><span style="font-size:0.9rem;">AI spam & fake report detection</span></div>
        </div>
      </div>
      <div class="features-grid" style="grid-template-columns:1fr 1fr;">
        <div class="feature-card">
          <div class="feature-icon">🔒</div>
          <h3>Full Anonymity</h3>
          <p>Reports submitted with zero identity linkage. Even the system doesn't know who you are.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🤖</div>
          <h3>AI Analysis</h3>
          <p>ML models detect sentiment, priority, and spam in every report automatically.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">📋</div>
          <h3>Smart Dashboard</h3>
          <p>Proctors get organized, filtered, AI-prioritized views of all incoming reports.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🔍</div>
          <h3>Status Tracking</h3>
          <p>Use your unique ID to track report progress from submission to resolution.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how-it-works">
  <div class="container">
    <div class="section-tag">AI Workflow</div>
    <h2 class="section-title" style="text-align:center;">How It Works</h2>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><h3>Submit Report</h3><p>Student writes complaint, selects category, optionally uploads evidence. No login required for anonymous submission.</p></div>
      <div class="step"><div class="step-num">2</div><h3>AI Analysis</h3><p>Python ML models automatically process text for sentiment, urgency, and spam detection in seconds.</p></div>
      <div class="step"><div class="step-num">3</div><h3>AI Insights</h3><p>Priority level, sentiment score, and spam flag are generated and attached to the report.</p></div>
      <div class="step"><div class="step-num">4</div><h3>Proctor Reviews</h3><p>Admin sees AI-powered dashboard with filtered, prioritized cases — urgent ones surface first.</p></div>
      <div class="step"><div class="step-num">5</div><h3>Action Taken</h3><p>Proctor updates status, adds notes, resolves case. Reporter can track progress with their ID.</p></div>
    </div>
  </div>
</section>

<!-- PORTALS -->
<section id="portals">
  <div class="container">
    <div class="section-tag">Access Points</div>
    <h2 class="section-title">Choose Your Portal</h2>
    <div class="portals">
      <div class="portal-card portal-student">
        <div class="portal-icon">🎓</div>
        <h3>Student Portal</h3>
        <p>Report issues anonymously or with your account. Track your report status. Safe, secure, private.</p>
        <ul class="portal-features">
          <li>Anonymous report submission</li>
          <li>Evidence file upload</li>
          <li>Unique tracking ID</li>
          <li>Report status tracking</li>
          <li>Personal report history</li>
        </ul>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <a href="login.php" class="btn btn-primary">Login as Student</a>
          <a href="register.php" class="btn btn-outline">Register</a>
        </div>
      </div>
      <div class="portal-card portal-admin">
        <div class="portal-icon">🛡️</div>
        <h3>Admin / Proctor Panel</h3>
        <p>Review, manage and resolve reports. Access AI insights, analytics, and institutional oversight tools.</p>
        <ul class="portal-features">
          <li>AI-powered case dashboard</li>
          <li>Priority filtering & search</li>
          <li>Status management & notes</li>
          <li>Analytics & reporting</li>
          <li>Spam detection alerts</li>
        </ul>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <a href="admin/login.php" class="btn btn-primary" style="background:linear-gradient(135deg,#0d9488,#06b6d4);">Admin Login</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<div class="cta-section">
  <h2>Ready to Make Your Campus Safer?</h2>
  <p>Join SafeSpeak today. Your voice matters — and your identity stays protected.</p>
  <div class="cta-btns">
    <a href="register.php" class="btn btn-white btn-lg">🎓 Student Sign Up</a>
    <a href="report_anonymous.php" class="btn btn-outline btn-lg" style="border-color:rgba(255,255,255,0.4);color:white;">📝 Report Anonymously</a>
  </div>
</div>

<footer>
  <p>SafeSpeak &copy; 2024 — Software Engineering Lab Project | Built with ❤️ for safer campuses</p>
  <p style="margin-top:0.5rem;">Team: Md Ashmam Zaman Apon (Leader) · Sabbir Sikder · Tohidul Islam Bhuiyan</p>
</footer>

<script>
function quickTrack() {
  const id = document.getElementById('quickTrackInput').value.trim();
  if (!id) { alert('Please enter a tracking ID'); return; }
  window.location.href = 'report_status.php?id=' + encodeURIComponent(id);
}
document.getElementById('quickTrackInput').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') quickTrack();
});

// Intersection Observer for animations
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.style.opacity = '1'; });
}, { threshold: 0.1 });
</script>
</body>
</html>
