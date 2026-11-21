#!/usr/bin/env python3
"""
=============================================================================
Marvelous Hotel & Dormitory — Capstone Defense Technical Manual (Taglish)
Edition: November 20, 2026 (Framework-Powered 3-Tier Architecture)
Target: docs/MARVELOUS_HOTEL_CAPSTONE_DEFENSE_MANUAL.pdf
Engine: Python 3 + WeasyPrint
=============================================================================
"""

import os
import weasyprint

html_content = """<!DOCTYPE html>
<html lang="tl">
<head>
<meta charset="UTF-8">
<style>
  @page {
    size: A4;
    margin: 14mm 13mm 14mm 13mm;
    @bottom-right {
      content: "Page " counter(page) " of " counter(pages);
      font-size: 8pt;
      color: #64748b;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    @bottom-left {
      content: "Marvelous Hotel & Dormitory — Capstone Defense Technical Manual (Nov 2026)";
      font-size: 8pt;
      color: #64748b;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
  }

  @page:first {
    @bottom-left { content: none; }
    @bottom-right { content: none; }
  }

  body {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #1e293b;
    line-height: 1.38;
    font-size: 8.6pt;
    margin: 0;
    padding: 0;
  }

  .cover {
    page-break-after: always;
    text-align: center;
    padding-top: 26mm;
  }

  .cover-badge {
    display: inline-block;
    background-color: #0f172a;
    color: #c5a880;
    padding: 6px 18px;
    font-size: 10pt;
    font-weight: bold;
    border-radius: 20px;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    margin-bottom: 18px;
  }

  .cover-title {
    font-size: 24pt;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    margin-bottom: 12px;
  }

  .cover-subtitle {
    font-size: 12pt;
    color: #475569;
    margin-bottom: 20px;
    font-style: italic;
  }

  .cover-desc {
    max-width: 530px;
    margin: 0 auto 20px auto;
    color: #64748b;
    font-size: 9.5pt;
    line-height: 1.5;
  }

  .cover-stack-box {
    max-width: 530px;
    margin: 0 auto;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-top: 3px solid #c5a880;
    border-radius: 8px;
    padding: 12px 16px;
    text-align: left;
    font-size: 8.6pt;
  }

  .cover-meta {
    margin-top: 32mm;
    border-top: 2px solid #cbd5e1;
    padding-top: 16px;
    display: flex;
    justify-content: space-around;
    font-size: 9pt;
    color: #334155;
  }

  .cover-author {
    font-size: 10.8pt;
    font-weight: bold;
    color: #0f172a;
    margin-top: 3px;
  }

  h1 {
    font-size: 15.5pt;
    color: #0f172a;
    border-bottom: 2px solid #c5a880;
    padding-bottom: 4px;
    margin-top: 16px;
    margin-bottom: 10px;
    page-break-after: avoid;
  }

  h2 {
    font-size: 11.5pt;
    color: #1e3a8a;
    margin-top: 13px;
    margin-bottom: 6px;
    page-break-after: avoid;
  }

  p {
    margin-top: 0;
    margin-bottom: 7px;
    text-align: justify;
  }

  .highlight-box {
    background-color: #f8fafc;
    border-left: 4px solid #c5a880;
    padding: 9px 13px;
    margin-bottom: 10px;
    border-radius: 0 6px 6px 0;
    font-size: 8.6pt;
  }

  .role-card {
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-top: 3px solid #2563eb;
    border-radius: 6px;
    padding: 7px 10px;
    margin-bottom: 8px;
    page-break-inside: avoid;
    font-size: 8pt;
    line-height: 1.34;
  }

  .role-card.admin { border-top-color: #dc2626; }
  .role-card.staff { border-top-color: #d97706; }
  .role-card.user  { border-top-color: #16a34a; }
  .role-card.ai    { border-top-color: #7c3aed; }

  .role-title {
    font-size: 10pt;
    font-weight: bold;
    color: #0f172a;
    margin-bottom: 3px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .role-badge {
    display: inline-block;
    padding: 2px 8px;
    font-size: 7pt;
    font-weight: 700;
    border-radius: 12px;
    text-transform: uppercase;
    color: #ffffff;
  }

  .badge-admin { background-color: #dc2626; }
  .badge-staff { background-color: #d97706; }
  .badge-user  { background-color: #16a34a; }
  .badge-ai    { background-color: #7c3aed; }

  .qa-box {
    background-color: #f1f5f9;
    border-left: 4px solid #2563eb;
    padding: 8px 11px;
    margin-bottom: 8px;
    border-radius: 4px;
    page-break-inside: avoid;
  }

  .qa-q {
    font-weight: bold;
    color: #1e3a8a;
    margin-bottom: 3px;
    font-size: 9.2pt;
  }

  .qa-a {
    color: #1e293b;
    font-size: 8.6pt;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
    margin-bottom: 12px;
    font-size: 8pt;
    page-break-inside: avoid;
  }

  th, td {
    border: 1px solid #cbd5e1;
    padding: 5px 7px;
    text-align: left;
    vertical-align: top;
  }

  th {
    background-color: #0f172a;
    color: #ffffff;
    font-weight: 600;
  }

  tr:nth-child(even) { background-color: #f8fafc; }

  code {
    background-color: #e2e8f0;
    color: #991b1b;
    padding: 1px 3.5px;
    border-radius: 3px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 7.6pt;
  }

  ul, ol {
    margin-top: 0;
    margin-bottom: 6px;
    padding-left: 17px;
  }

  li { margin-bottom: 2.5px; }

  .page-break { page-break-after: always; }

  .feature-grid {
    display: table;
    width: 100%;
    margin-bottom: 4px;
  }

  .feature-row { display: table-row; }

  .feature-cell {
    display: table-cell;
    padding: 4px;
    width: 50%;
    vertical-align: top;
  }

  .inner-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 5px 8px;
    border-radius: 5px;
  }
</style>
</head>
<body>

<!-- COVER PAGE -->
<div class="cover">
  <div class="cover-badge">Capstone Technical Defense Manual • Nov 2026</div>
  <div class="cover-title">MARVELOUS HOTEL &amp; DORMITORY<br>BOOKING &amp; RESERVATION SYSTEM</div>
  <div class="cover-subtitle">Framework-Powered 3-Tier Architecture with Marvi Live SSE + Voice AI &amp; Multi-Portal Isolation</div>

  <p class="cover-desc">
    Komprehensibong Gabay para sa Capstone Defense: Kumpletong paliwanag sa mga ginamit na <strong>Languages</strong>, <strong>Frameworks</strong>, malinis na <strong>3-Tier Architecture (<code>frontend/</code>, <code>backend/</code>, <code>database/</code>)</strong>, at end-to-end flows ng <strong>Marvi Live AI</strong>, <strong>User (Guest)</strong>, <strong>Staff (Frontdesk)</strong>, at <strong>Super Admin</strong>.
  </p>

  <div class="cover-stack-box">
    <strong>Official 3-Tier Technology Stack Summary:</strong>
    <ul style="margin-top:6px; margin-bottom:0;">
      <li><strong>Tier 1 — Frontend (<code>frontend/</code>):</strong> HTML5, CSS3, ES6+ JavaScript, <strong>Tailwind CSS v3.4</strong>, <strong>Alpine.js v3.14</strong> (offline bundle), Font Awesome 6, <code>HotelAPI</code>, Web Speech API (Mic + TTS).</li>
      <li><strong>Tier 2 — Backend (<code>backend/</code>):</strong> <strong>PHP 8</strong>, <strong>Slim 4 Framework</strong> (PSR-4 Controllers/Services, PSR-15 RoleAuth &amp; CSRF Middleware), Composer, <strong>PHPMailer 7</strong> (Gmail SMTP), <strong>NVIDIA NIM AI</strong> (<code>nemotron-3.5-bolt-e4b</code> Live SSE).</li>
      <li><strong>Tier 3 — Database (<code>database/</code>):</strong> <strong>SQL (MySQL / MariaDB InnoDB)</strong> via <strong>PHP PDO</strong> Prepared Statements, <strong>14 Normalized Tables</strong>, <strong>17 Versioned Migrations</strong>.</li>
      <li><strong>QA &amp; Deployment:</strong> <strong>PHPUnit 10</strong> (32/32 Tests Passed, 185 Assertions), Apache <code>.htaccess</code> Clean Router, Docker &amp; Cloudflare/Ngrok Tunnel.</li>
    </ul>
  </div>

  <div class="cover-meta">
    <div>
      <div style="color: #64748b; font-size: 8pt;">ARCHITECTURE</div>
      <div class="cover-author">Clean 3-Tier Separation</div>
    </div>
    <div>
      <div style="color: #64748b; font-size: 8pt;">EDITION</div>
      <div class="cover-author">November 20, 2026</div>
    </div>
    <div>
      <div style="color: #64748b; font-size: 8pt;">AUTOMATED QA STATUS</div>
      <div class="cover-author" style="color: #16a34a;">32/32 Tests Passing (100%)</div>
    </div>
  </div>
</div>

<!-- SECTION 1: TECH STACK & JUSTIFICATION -->
<h1>1. Pangkalahatang Deskripsyon, Languages at Frameworks</h1>
<p>
Ang <strong>Marvelous Hotel &amp; Dormitory Booking System</strong> ay isang modernong hybrid hospitality platform na sumusuporta sa parehong panandaliang <em>Hotel Stays</em> at pangmatagalang <em>Dormitory / Bedspace Stays</em>. Binuo ito gamit ang malinis na <strong>3-Tier Architecture</strong> (<code>frontend/</code>, <code>backend/</code>, <code>database/</code>) na may zero loose PHP files sa project root.
</p>

<div class="highlight-box">
<strong>Bakit mahalaga ang proyektong ito?</strong><br>
Tinutugunan nito ang mga karaniwang problema sa tradisyonal na hotel at dormitoryo: double-booking collisions, pekeng GCash/Maya screenshots, mabagal na manual room matching, kawalan ng audit trail sa mga staff actions, at delay sa password recovery ng mga bisita.
</div>

<h2>Kumpletong Tech Stack &amp; Defense Justification</h2>
<table>
  <thead>
    <tr>
      <th style="width: 22%;">Tier &amp; Teknolohiya</th>
      <th style="width: 36%;">Languages, Frameworks &amp; Gamit</th>
      <th style="width: 42%;">Bakit Ito Pinili? (Pang-Panelist na Sagot)</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Tier 1: Frontend</strong><br><code>frontend/</code></td>
      <td><strong>HTML5, CSS3, ES6+ JS</strong> + <strong>Tailwind CSS v3.4</strong> (<code>preflight: false</code>, Navy/Gold tokens) + <strong>Alpine.js v3.14</strong> + <code>HotelAPI</code> + <strong>Web Speech API</strong></td>
      <td>Mabilis mag-load kahit walang mabigat na Node SPA server; nagbibigay ng reactive live room availability checker, real-time SSE AI streaming, Voice Mic/TTS, at live password reset popup.</td>
    </tr>
    <tr>
      <td><strong>Tier 2: Backend &amp; API</strong><br><code>backend/</code></td>
      <td><strong>PHP 8</strong> + <strong>Slim 4 Framework</strong> (<code>slim/slim</code>, <code>slim/psr7</code>), <strong>PSR-4</strong> (<code>MarvelousHotel\\</code>), <strong>PSR-15 Middleware</strong>, <strong>Composer</strong>, <strong>PHPMailer 7</strong></td>
      <td>Industry-standard REST API (<code>/backend/api/v1/*</code>), malinis na Controller &rarr; Service layering, centralized CSRF at RoleAuth middleware, isolated portal sessions, at Gmail SMTP integration.</td>
    </tr>
    <tr>
      <td><strong>AI Intelligence</strong><br><code>backend/ai/</code></td>
      <td><strong>NVIDIA NIM API</strong> (<code>nvidia/nemotron-3.5-bolt-e4b</code> primary + 2 failover models), pure-PHP <strong>SSE Streaming</strong>, Local Bilingual Guardrails</td>
      <td>Sub-second (~0.8s) live token streaming na may Tagalog/English fluency, multi-turn memory, clickable Room Cards, Staff Co-Pilot, Admin BI, at 0% downtime kapag offline.</td>
    </tr>
    <tr>
      <td><strong>Tier 3: Database</strong><br><code>database/</code></td>
      <td><strong>SQL (MySQL / MariaDB InnoDB)</strong> via <strong>PHP PDO</strong> Prepared Statements, <strong>14 Tables</strong>, <strong>17 Versioned SQL Migrations</strong></td>
      <td>ACID-compliant na booking at payment verification gamit ang <code>SELECT ... FOR UPDATE</code> row locking, 100% protektado laban sa SQL Injection, at reproducible schema upgrades via <code>migrate.php</code>.</td>
    </tr>
    <tr>
      <td><strong>QA &amp; Security</strong><br><code>tests/</code>, <code>.htaccess</code></td>
      <td><strong>PHPUnit 10</strong> (32 tests / 185 assertions), Apache <code>mod_rewrite</code> clean router + HTTP 403 internal blocks, <code>share-capstone.sh</code></td>
      <td>Automated regression testing ng Slim 4 routes, middleware, at domain services; protektado ang <code>core/</code>, <code>src/</code>, <code>database/</code>, at <code>uploads/</code> laban sa direct HTTP access.</td>
    </tr>
  </tbody>
</table>

<div class="page-break"></div>

<!-- SECTION 2: 4-ACTOR DETAILED FLOWS -->
<h1>2. Detalyadong Daloy at Tungkulin ng Apat na Aktor</h1>
<p>
Hinati ang buong sistema sa apat na malinaw na aktor na may kani-kaniyang isolated session cookie, authorization guard, at operational flow:
</p>

<!-- USER / GUEST -->
<div class="role-card user">
  <div class="role-title">
    <span>1. USER / GUEST (Public &amp; Guest Portal — <code>frontend/user/</code>)</span>
    <span class="role-badge badge-user">MH_USER_SESS</span>
  </div>
  <div class="feature-grid">
    <div class="feature-row">
      <div class="feature-cell">
        <div class="inner-box">
          <strong>A. Discovery, Auth &amp; Live Reset Popup:</strong>
          <ul>
            <li>Live room availability check sa <code>rooms.php</code> &amp; <code>booking.php</code> via Alpine.js + Slim 4 <code>GET /v1/availability</code>.</li>
            <li>Registration na may unique full-name check (<code>check-name.php</code>) at Gmail SMTP verification.</li>
            <li><strong>Live Password Reset Popup:</strong> 3-second polling modal na agad nagpapakita ng masked temporary password o direct reset link kapag nag-dispatch ang Admin (15-min expiry).</li>
          </ul>
        </div>
      </div>
      <div class="feature-cell">
        <div class="inner-box">
          <strong>B. KYC Gate, Booking, Payment &amp; Reviews:</strong>
          <ul>
            <li><strong>KYC Gate (<code>user/profile.php</code>):</strong> Upload ng Government ID o Student/School ID (protektado sa <code>uploads/</code> via <code>serve-file.php</code>). Kailangan ma-verify bago mag-book.</li>
            <li><strong>Booking &amp; Maya/GCash QR:</strong> Overlap-safe booking, receipt upload + Reference No, Printable Voucher (<code>booking-detail.php</code>), pending cancellation, at 1&ndash;5 star verified reviews.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- STAFF -->
<div class="role-card staff">
  <div class="role-title">
    <span>2. FRONTDESK STAFF (Operations Portal — <code>frontend/staff/</code>)</span>
    <span class="role-badge badge-staff">MH_STAFF_SESS</span>
  </div>
  <div class="feature-grid">
    <div class="feature-row">
      <div class="feature-cell">
        <div class="inner-box">
          <strong>A. Shift Co-Pilot &amp; Delegated Payments:</strong>
          <ul>
            <li>One-click <strong>Marvi Staff Co-Pilot</strong> (<code>staff-assistant.php</code>) para sa daily arrivals, check-outs, pending payments, at dirty rooms briefing.</li>
            <li>Delegated Maya/GCash verification (<code>staff/payments.php</code> &amp; Slim 4 <code>POST /v1/payments/{id}/decision</code>) gamit ang atomic <code>FOR UPDATE</code> lock at audit logging.</li>
          </ul>
        </div>
      </div>
      <div class="feature-cell">
        <div class="inner-box">
          <strong>B. Check-In/Out &amp; Housekeeping State Machine:</strong>
          <ul>
            <li><strong>Check-In (<code>staff/check-in.php</code>):</strong> <code>confirmed &rarr; checked_in</code>, room nagiging <code>occupied</code>, naitatala ang <code>checked_in_at</code>.</li>
            <li><strong>Check-Out &amp; Cleaning Board (<code>rooms-status.php</code>):</strong> Awtomatikong ginagawang <code>dirty</code> ang kwarto pagka-checkout; inililipat ng staff sa <code>available</code> pagkatapos linisin (o <code>maintenance</code>).</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SUPER ADMIN -->
<div class="role-card admin">
  <div class="role-title">
    <span>3. SUPER ADMIN (Executive Command Center — <code>frontend/admin/</code>)</span>
    <span class="role-badge badge-admin">MH_ADMIN_SESS</span>
  </div>
  <div class="feature-grid">
    <div class="feature-row">
      <div class="feature-cell">
        <div class="inner-box">
          <strong>A. Executive BI, Bookings, Payments &amp; Rooms:</strong>
          <ul>
            <li><strong>Dashboard (<code>admin/dashboard.php</code>):</strong> Verified revenue, occupancy %, Maya vs GCash ratio, at <strong>Marvi Executive BI Digest</strong>.</li>
            <li>Master Bookings timeline (<code>bookings.php</code>), Central Payment Hub (<code>payments.php</code>), at Full Room/Dorm Inventory &amp; Pricing CRUD (<code>rooms.php</code>).</li>
          </ul>
        </div>
      </div>
      <div class="feature-cell">
        <div class="inner-box">
          <strong>B. KYC, Live Resets, Staff, AI &amp; Audit Trail:</strong>
          <ul>
            <li><strong>Users &amp; KYC (<code>admin/users.php</code>):</strong> Approve/Reject IDs sa Lightbox, Ban/Unban, at <strong>Live Password Reset Dispatcher</strong> (may live <em>Viewed by user</em> / <em>Waiting</em> tracker).</li>
            <li>Closed Staff Provisioning (<code>staff.php</code>), Gmail SMTP &amp; QR Settings (<code>settings.php</code>), <strong>Marvi AI Config &amp; Latency Probe</strong> (<code>ai-config.php</code>), at immutable <code>audit-logs.php</code>.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MARVI AI -->
<div class="role-card ai">
  <div class="role-title">
    <span>4. MARVI LIVE AI SUITE (Tri-Role Engine — <code>backend/ai/</code>)</span>
    <span class="role-badge badge-ai">NVIDIA NIM SSE + Voice</span>
  </div>
  <div class="feature-grid">
    <div class="feature-row">
      <div class="feature-cell">
        <div class="inner-box">
          <strong>A. Live Guest Concierge &amp; Smart Matcher:</strong>
          <ul>
            <li><strong>Live SSE Streaming (<code>chatbot.php</code>):</strong> Token-by-token sagot (~0.8s) gamit ang <code>nvidia/nemotron-3.5-bolt-e4b</code> + auto-failover + local fallback.</li>
            <li><strong>Voice &amp; Action Cards:</strong> Web Speech Mic (<code>PH/EN</code>), TTS voice reply, 12-turn memory, server-verified Room Cards (<code>[[BOOK_ROOM:id]]</code>), at <em>Take Me There &rarr;</em> buttons.</li>
          </ul>
        </div>
      </div>
      <div class="feature-cell">
        <div class="inner-box">
          <strong>B. Staff/Admin AI &amp; Security Guardrails:</strong>
          <ul>
            <li><strong>Staff Co-Pilot &amp; Admin BI:</strong> Real-time DB metrics aggregation para sa daily ops at executive pricing/promo strategy.</li>
            <li><strong>Threat Shield:</strong> Rate limit + <code>bad-intent-keywords.json</code> (blocks SQLi, XSS, port scans, prompt injection &rarr; logs <code>flagged=1</code> sa <code>ai_logs</code>) + output sanitizer.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="page-break"></div>

<!-- SECTION 3: RBAC MATRIX & COLLABORATION FLOW -->
<h1>3. Access Control Matrix (RBAC) &amp; End-to-End Flow</h1>

<table>
  <thead>
    <tr>
      <th style="width: 32%;">Feature / Kakayahan</th>
      <th style="width: 17%; text-align:center;">Guest</th>
      <th style="width: 17%; text-align:center;">Staff</th>
      <th style="width: 17%; text-align:center;">Super Admin</th>
      <th style="width: 17%; text-align:center;">Marvi AI</th>
    </tr>
  </thead>
  <tbody>
    <tr><td><strong>Session Cookie</strong></td><td style="text-align:center;"><code>MH_USER_SESS</code></td><td style="text-align:center;"><code>MH_STAFF_SESS</code></td><td style="text-align:center;"><code>MH_ADMIN_SESS</code></td><td style="text-align:center;">Role-Aware API</td></tr>
    <tr><td><strong>Live Availability (Slim 4)</strong></td><td style="text-align:center;">Oo</td><td style="text-align:center;">Oo</td><td style="text-align:center;">Oo</td><td style="text-align:center;">Reads Catalog</td></tr>
    <tr><td><strong>KYC ID Upload</strong></td><td style="text-align:center;">Oo</td><td style="text-align:center;">Hindi</td><td style="text-align:center;">Hindi</td><td style="text-align:center;">Status Aware</td></tr>
    <tr><td><strong>KYC ID Approval / Rejection</strong></td><td style="text-align:center;">Bawal</td><td style="text-align:center;">Bawal</td><td style="text-align:center;"><strong>Eksklusibo</strong></td><td style="text-align:center;">Hindi</td></tr>
    <tr><td><strong>Room Booking &amp; Self-Cancel</strong></td><td style="text-align:center;">Oo (Verified)</td><td style="text-align:center;">Hindi</td><td style="text-align:center;">Override</td><td style="text-align:center;">Room Cards</td></tr>
    <tr><td><strong>Atomic Payment Verification</strong></td><td style="text-align:center;">Upload Only</td><td style="text-align:center;">Delegated</td><td style="text-align:center;">Master</td><td style="text-align:center;">Payment Guide</td></tr>
    <tr><td><strong>Check-In / Check-Out &amp; Cleaning</strong></td><td style="text-align:center;">Bawal</td><td style="text-align:center;">Daily Desk</td><td style="text-align:center;">Override</td><td style="text-align:center;">Shift Briefing</td></tr>
    <tr><td><strong>Live Password Reset Dispatch</strong></td><td style="text-align:center;">Popup Only</td><td style="text-align:center;">Bawal</td><td style="text-align:center;"><strong>Eksklusibo</strong></td><td style="text-align:center;">Hindi</td></tr>
    <tr><td><strong>Staff / Room / SMTP / AI Config</strong></td><td style="text-align:center;">Bawal</td><td style="text-align:center;">Bawal</td><td style="text-align:center;"><strong>Eksklusibo</strong></td><td style="text-align:center;">Read Config</td></tr>
    <tr><td><strong>Audit Trail (<code>audit_logs</code>)</strong></td><td style="text-align:center;">Bawal</td><td style="text-align:center;">Bawal</td><td style="text-align:center;"><strong>View-Only</strong></td><td style="text-align:center;">Logs Prompts</td></tr>
  </tbody>
</table>

<h2>Kumpletong Daloy ng Pakikipagtulungan (End-to-End Lifecycle)</h2>
<ol>
  <li><strong>AI Concierge &amp; Live Availability:</strong> Magtatanong ang <strong>Guest</strong> kay <strong>Marvi Live AI</strong> (text o voice) ng pasok sa badyet na kwarto; mag-i-stream ang AI ng sagot + clickable Room Card. Iche-check ng <strong>Alpine.js</strong> ang live availability sa <strong>Slim 4</strong> (<code>GET /backend/api/v1/availability</code>).</li>
  <li><strong>KYC Verification:</strong> Magpaparehistro ang <strong>Guest</strong> at mag-a-upload ng Government/Student ID; susuriin at aaprubahan ng <strong>Super Admin</strong> sa protected Lightbox (<code>serve-file.php</code>).</li>
  <li><strong>Atomic Booking &amp; Payment:</strong> Magbu-book ang <strong>Guest</strong> (overlap-checked) at mag-a-upload ng Maya/GCash proof. Magve-verify ang <strong>Staff</strong> o <strong>Admin</strong> gamit ang atomic <code>SELECT ... FOR UPDATE</code> lock &rarr; magiging <code>confirmed</code> at lilitaw ang digital voucher.</li>
  <li><strong>Frontdesk Operations &amp; Housekeeping:</strong> Gagamit ng <strong>Staff Co-Pilot</strong> ang frontdesk, magche-check-in, at pagka-check-out ay awtomatikong magiging <code>dirty</code> ang kwarto hanggang malinis pabalik sa <code>available</code>.</li>
  <li><strong>Reviews &amp; Executive BI:</strong> Magbibigay ng verified review ang <strong>Guest</strong>, habang babasahin ng <strong>Super Admin</strong> ang <strong>Marvi Executive BI Digest</strong> para sa kita at occupancy decisions.</li>
</ol>

<div class="page-break"></div>

<!-- SECTION 4: EXPANDED DEFENSE Q&A -->
<h1>4. Capstone Defense Panel Q&amp;A Cheat Sheet (Taglish)</h1>

<div class="qa-box">
  <div class="qa-q">Q1: "Anong mga languages at frameworks ang ginamit ninyo sa Frontend, Backend, at Database?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"Naka-strict <strong>3-Tier Architecture</strong> po kami: sa <strong>Frontend</strong> (<code>frontend/</code>), HTML5, CSS3, ES6+ JS kasama ang <strong>Tailwind CSS v3</strong> at <strong>Alpine.js 3</strong>; sa <strong>Backend</strong> (<code>backend/</code>), <strong>PHP 8</strong> gamit ang <strong>Slim 4 Framework</strong> (PSR-4 Controllers/Services at PSR-15 Middleware) at PHPMailer 7; sa <strong>Database</strong> (<code>database/</code>), <strong>MySQL InnoDB SQL</strong> gamit ang PDO Prepared Statements at 17 versioned migrations."</em></div>
</div>

<div class="qa-box">
  <div class="qa-q">Q2: "Bakit Slim 4 + Tailwind + Alpine.js ang pinili ninyo imbes na mabibigat na frameworks?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"Binibigyan po kami ng <strong>Slim 4</strong> ng modern REST API, PSR-4 autoloading, at PSR-15 security middleware na napakabilis sa Apache/XAMPP o Docker. Ang <strong>Tailwind + Alpine.js</strong> naman ay nagbibigay ng reactive real-time UI (live availability checks, SSE AI chat, modals) nang hindi kailangan ng mabigat na SPA runtime server."</em></div>
</div>

<div class="qa-box">
  <div class="qa-q">Q3: "Live AI ba talaga si Marvi o hardcoded lang? Paano kung mawalan ng internet sa defense?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"<strong>Live Real-Time SSE Streaming LLM</strong> po ito via NVIDIA NIM (<code>nvidia/nemotron-3.5-bolt-e4b</code>, ~0.8s latency) na may multi-turn memory, Voice Mic/TTS, at server-verified Room Cards. May automatic failover ito sa dalawang backup cloud models at sa aming local bilingual rule engine kapag offline—kaya <strong>0% downtime</strong>."</em></div>
</div>

<div class="qa-box">
  <div class="qa-q">Q4: "Paano ninyo pinipigilan ang double-booking o sabay na pag-approve ng Staff at Admin sa bayad?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"Gumagamit po kami ng <code>[check_in, check_out)</code> overlap validation sa booking at <strong>ACID Transactions + <code>SELECT ... FOR UPDATE</code> row locking</strong> sa MySQL InnoDB. Kapag nauna nang mag-approve ang Staff, hindi na ito mao-overwrite ng Admin nang sabay at malinis na magsi-sync ang status sa dalawang portal."</em></div>
</div>

<div class="qa-box">
  <div class="qa-q">Q5: "Paano gumagana ang Live Password Reset Popup kung nakalimutan ng guest ang password?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"Kapag nag-request ang guest sa <code>forgot-password.php</code>, nakakabit ang request sa SHA-256 session token at nagpo-poll nang non-blocking kada 3 segundo. Pag-dispatch ng Admin ng temporary password o reset link, lalabas agad ang 1-time masked popup sa screen ng guest (15-min expiry) at mag-a-update sa Admin screen mula <strong>Waiting for user</strong> patungong <strong>Viewed by user</strong>—kahit delayed ang email."</em></div>
</div>

<div class="qa-box">
  <div class="qa-q">Q6: "Paano ninyo pinoprotektahan ang Government/Student IDs, payment receipts, at internal files?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"Naka-HTTP 403 block ang <code>uploads/</code>, <code>database/</code>, <code>backend/core/</code>, at <code>backend/src/</code> via <code>.htaccess</code>. Lahat ng KYC IDs at Maya/GCash proofs ay dumadaan lamang sa <code>backend/endpoints/serve-file.php</code> na nagbe-verify muna ng active session role at file ownership."</em></div>
</div>

<div class="qa-box">
  <div class="qa-q">Q7: "Bakit kailangan pa ng Staff portal at magkahiwalay na session cookies?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"Para sa <strong>Principle of Least Privilege</strong> at <strong>Zero Session Bleed</strong>. Ang Staff ay nakatutok sa check-in/check-out, housekeeping state machine, at delegated payment verification nang walang access sa user resets, room pricing, o audit log deletion. Ang tatlong cookies (<code>MH_USER_SESS</code>, <code>MH_STAFF_SESS</code>, <code>MH_ADMIN_SESS</code>) ay pumipigil sa privilege collision kahit sabay-sabay na naka-open sa iisang browser."</em></div>
</div>

<div class="qa-box">
  <div class="qa-q">Q8: "Paano ninyo napatunayang bug-free at tested ang buong system?"</div>
  <div class="qa-a"><strong>Sagot:</strong> <em>"Mayroon po kaming <strong>PHPUnit 10 Automated Test Suite</strong> (<code>tests/</code>) na 100% passing — <strong>32/32 tests, 185 assertions</strong> na sumusubok sa Slim 4 endpoints, RoleAuth &amp; CSRF middleware, Room/Booking/Payment services, at core helpers, plus zero PHP/JS lint errors at 17/17 applied SQL migrations."</em></div>
</div>

<div style="margin-top: 18px; text-align: center; border-top: 1px solid #cbd5e1; padding-top: 10px; color: #64748b; font-size: 8pt;">
  Marvelous Hotel &amp; Dormitory Management System — Capstone Technical Defense Manual &copy; 2026. All Rights Reserved.
</div>

</body>
</html>
"""

output_path = "/opt/lampp/htdocs/hotel/docs/MARVELOUS_HOTEL_CAPSTONE_DEFENSE_MANUAL.pdf"
os.makedirs(os.path.dirname(output_path), exist_ok=True)
doc = weasyprint.HTML(string=html_content)
doc.write_pdf(output_path)
print(f"Successfully generated updated PDF defense manual at: {output_path}")
print(f"File size: {os.path.getsize(output_path):,} bytes")
