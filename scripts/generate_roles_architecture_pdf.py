#!/usr/bin/env python3
"""
=============================================================================
Marvelous Hotel & Dormitory — Role Guide, Process Flows & Architecture
(AI / USER / STAFF / ADMIN)
Target: docs/MARVELOUS_HOTEL_ROLES_ARCHITECTURE_GUIDE.pdf
Engine: Python 3 + WeasyPrint + Vector SVG Diagrams
=============================================================================
"""

import os
import weasyprint


def build_html() -> str:
    return """<!DOCTYPE html>
<html lang="tl">
<head>
<meta charset="UTF-8">
<title>Marvelous Hotel &amp; Dormitory — Role Guide, Process Flows &amp; System Architecture</title>
<style>
  @page {
    size: A4 portrait;
    margin: 12mm 11mm 12mm 11mm;
    @top-left {
      content: "MARVELOUS HOTEL & DORMITORY — AI / USER / STAFF / ADMIN ARCHITECTURE GUIDE";
      font-size: 6.6pt;
      font-weight: 700;
      color: #94a3b8;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      letter-spacing: 0.4px;
    }
    @top-right {
      content: "COMPLETE ROLE & PROCESS BLUEPRINT";
      font-size: 6.6pt;
      font-weight: 700;
      color: #b48a4e;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    @bottom-left {
      content: "3-Tier PHP 8 / Slim 4 | MySQL 14 Tables | Marvi AI (NVIDIA NIM SSE) | Isolated Portals";
      font-size: 6.6pt;
      color: #94a3b8;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    @bottom-right {
      content: "Page " counter(page) " of " counter(pages);
      font-size: 7pt;
      font-weight: 700;
      color: #0f172a;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
  }

  @page:first {
    margin: 0mm;
    @top-left { content: none; }
    @top-right { content: none; }
    @bottom-left { content: none; }
    @bottom-right { content: none; }
  }

  * { box-sizing: border-box; }

  body {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #1e293b;
    font-size: 8pt;
    line-height: 1.38;
    margin: 0;
    padding: 0;
    background: #ffffff;
  }

  .page {
    page-break-after: always;
    position: relative;
  }

  .page.last {
    page-break-after: avoid;
  }

  /* Cover Page */
  .cover {
    background: linear-gradient(145deg, #090e1a 0%, #0f172a 52%, #1e293b 100%);
    color: #ffffff;
    min-height: 297mm;
    padding: 17mm 16mm 15mm 16mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .cover-top {
    border-bottom: 1.5px solid rgba(197, 168, 128, 0.45);
    padding-bottom: 11px;
  }

  .cover-badge {
    display: inline-block;
    background: rgba(197, 168, 128, 0.16);
    border: 1px solid #c5a880;
    color: #e8cfa8;
    padding: 4px 13px;
    font-size: 7.8pt;
    font-weight: 700;
    border-radius: 20px;
    letter-spacing: 1.1px;
    text-transform: uppercase;
    margin-bottom: 10px;
  }

  .cover-title {
    font-size: 22pt;
    font-weight: 900;
    color: #ffffff;
    line-height: 1.16;
    margin: 0 0 7px 0;
    letter-spacing: -0.4px;
  }

  .cover-title span {
    color: #e2c290;
  }

  .cover-sub {
    font-size: 10.2pt;
    color: #cbd5e1;
    line-height: 1.42;
    margin: 0;
  }

  .pillar-grid {
    display: table;
    width: 100%;
    border-collapse: separate;
    border-spacing: 8px;
    margin: 8px 0;
  }

  .pillar-row {
    display: table-row;
  }

  .pillar-card {
    display: table-cell;
    width: 50%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    padding: 10px 12px;
    vertical-align: top;
  }

  .pillar-card.ai    { border-top: 3px solid #a78bfa; }
  .pillar-card.user  { border-top: 3px solid #38bdf8; }
  .pillar-card.staff { border-top: 3px solid #34d399; }
  .pillar-card.admin { border-top: 3px solid #fbbf24; }

  .pillar-tag {
    display: inline-block;
    font-size: 6.8pt;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    padding: 2px 7px;
    border-radius: 10px;
    margin-bottom: 5px;
  }
  .tag-ai    { background: rgba(167, 139, 250, 0.2); color: #ddd6fe; }
  .tag-user  { background: rgba(56, 189, 248, 0.2); color: #bae6fd; }
  .tag-staff { background: rgba(52, 211, 153, 0.2); color: #a7f3d0; }
  .tag-admin { background: rgba(251, 191, 36, 0.2); color: #fde68a; }

  .pillar-title {
    font-size: 10.5pt;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 4px 0;
  }

  .pillar-desc {
    font-size: 7.7pt;
    color: #cbd5e1;
    line-height: 1.38;
    margin: 0;
  }

  .cover-toc {
    background: rgba(15, 23, 42, 0.72);
    border: 1px solid rgba(197, 168, 128, 0.35);
    border-radius: 8px;
    padding: 10px 14px;
  }

  .cover-toc-title {
    font-size: 8.2pt;
    font-weight: 800;
    color: #e2c290;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    margin-bottom: 6px;
  }

  .toc-cols {
    display: table;
    width: 100%;
  }

  .toc-col {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    font-size: 7.5pt;
    color: #e2e8f0;
    line-height: 1.5;
    padding-right: 8px;
  }

  .cover-foot {
    border-top: 1px solid rgba(255, 255, 255, 0.16);
    padding-top: 9px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 7.2pt;
    color: #94a3b8;
  }

  .cover-foot code,
  .section-sub code {
    background: rgba(255, 255, 255, 0.12);
    color: #f8fafc;
    border: 1px solid rgba(255, 255, 255, 0.22);
    font-size: 6.8pt;
  }

  /* Page Header Banner */
  .section-header {
    background: linear-gradient(90deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    padding: 8px 13px;
    border-radius: 7px;
    border-left: 5px solid #c5a880;
    margin-bottom: 9px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .section-header.ai-hdr    { border-left-color: #8b5cf6; }
  .section-header.user-hdr  { border-left-color: #0284c7; }
  .section-header.staff-hdr { border-left-color: #059669; }
  .section-header.admin-hdr { border-left-color: #d97706; }

  .section-h1 {
    font-size: 12.2pt;
    font-weight: 800;
    margin: 0;
    color: #ffffff;
  }

  .section-sub {
    font-size: 7.4pt;
    color: #cbd5e1;
    margin-top: 2px;
  }

  .section-pill {
    font-size: 7pt;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 12px;
    background: rgba(197, 168, 128, 0.2);
    color: #fde68a;
    border: 1px solid rgba(197, 168, 128, 0.45);
    white-space: nowrap;
  }

  h2 {
    font-size: 9.4pt;
    font-weight: 800;
    color: #0f172a;
    margin: 8px 0 5px 0;
    padding-bottom: 2px;
    border-bottom: 1.5px solid #e2e8f0;
  }

  h3 {
    font-size: 8.4pt;
    font-weight: 700;
    color: #1e293b;
    margin: 5px 0 3px 0;
  }

  p {
    margin: 0 0 5px 0;
  }

  ul, ol {
    margin: 2px 0 6px 0;
    padding-left: 16px;
  }

  li {
    margin-bottom: 2.5px;
  }

  code {
    font-family: 'Courier New', monospace;
    font-size: 7.2pt;
    background: #f1f5f9;
    color: #0f172a;
    padding: 1px 4px;
    border-radius: 3px;
    border: 1px solid #e2e8f0;
  }

  .diagram-box {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 7px 8px;
    margin-bottom: 8px;
    text-align: center;
  }

  .diagram-caption {
    font-size: 7pt;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    text-align: left;
  }

  svg {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
  }

  .grid-2 {
    display: table;
    width: 100%;
    border-collapse: separate;
    border-spacing: 7px 0;
    margin-bottom: 6px;
  }

  .col-2 {
    display: table-cell;
    width: 50%;
    vertical-align: top;
  }

  .grid-3 {
    display: table;
    width: 100%;
    border-collapse: separate;
    border-spacing: 6px 0;
    margin-bottom: 6px;
  }

  .col-3 {
    display: table-cell;
    width: 33.33%;
    vertical-align: top;
  }

  .card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 7px 9px;
    margin-bottom: 6px;
  }

  .card.soft {
    background: #f8fafc;
  }

  .card.ai    { border-left: 3.5px solid #8b5cf6; }
  .card.user  { border-left: 3.5px solid #0284c7; }
  .card.staff { border-left: 3.5px solid #059669; }
  .card.admin { border-left: 3.5px solid #d97706; }
  .card.warn  { border-left: 3.5px solid #dc2626; background: #fef2f2; border-color: #fecaca; }
  .card.gold  { border-left: 3.5px solid #c5a880; background: #fffbeb; border-color: #fde68a; }

  .card-title {
    font-size: 8.3pt;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 3px;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 7px;
    font-size: 7.2pt;
  }

  th {
    background: #0f172a;
    color: #ffffff;
    text-align: left;
    padding: 4.5px 6px;
    font-weight: 700;
    border: 1px solid #0f172a;
  }

  td {
    padding: 4px 6px;
    border: 1px solid #cbd5e1;
    vertical-align: top;
  }

  tr:nth-child(even) td {
    background: #f8fafc;
  }

  .badge {
    display: inline-block;
    padding: 1.5px 6px;
    border-radius: 9px;
    font-size: 6.5pt;
    font-weight: 800;
    text-transform: uppercase;
  }
  .b-ai    { background: #ede9fe; color: #5b21b6; }
  .b-user  { background: #e0f2fe; color: #0369a1; }
  .b-staff { background: #d1fae5; color: #047857; }
  .b-admin { background: #fef3c7; color: #b45309; }
  .b-no    { background: #fee2e2; color: #b91c1c; }
  .b-rw    { background: #dbeafe; color: #1d4ed8; }
  .b-ro    { background: #e2e8f0; color: #334155; }

  .speech-box {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-left: 4px solid #0f172a;
    border-radius: 6px;
    padding: 8px 10px;
    margin-bottom: 7px;
  }

  .speech-title {
    font-size: 8.4pt;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 3px;
  }

  .speech-quote {
    font-size: 7.7pt;
    color: #334155;
    line-height: 1.42;
  }
</style>
</head>
<body>

<!-- ==================== PAGE 1: COVER ==================== -->
<div class="page">
  <div class="cover">
    <div>
      <div class="cover-top">
        <div class="cover-badge">Marvelous Hotel &amp; Dormitory &bull; Complete Role &amp; Architecture Blueprint</div>
        <h1 class="cover-title">Gabay sa System Roles, Proseso, at <span>Architecture</span></h1>
        <p class="cover-sub">
          Kumpletong paliwanag sa <strong>AI (Marvi AI)</strong>, <strong>USER (Guest)</strong>, <strong>STAFF (Frontdesk)</strong>, at <strong>ADMIN (Super Admin)</strong> — ano ang kanilang ginagawa, ano ang kaya at bawal, at paano nagko-connect ang buong sistema mula Frontend hanggang Database.
        </p>
      </div>

      <div class="pillar-grid" style="margin-top: 14px;">
        <div class="pillar-row">
          <div class="pillar-card ai">
            <div class="pillar-tag tag-ai">Pillar 1 &bull; Intelligent Layer</div>
            <div class="pillar-title">AI — Marvi AI Engine</div>
            <p class="pillar-desc">
              Hindi lang simpleng FAQ bot: may <strong>Live SSE Streaming Chatbot</strong> (bilingual EN/Taglish + Voice Mic/TTS), <strong>Clickable Room Cards &amp; Navigation Buttons</strong>, <strong>Smart AI Room Matcher</strong> para sa User, <strong>Shift Co-Pilot</strong> para sa Staff, at <strong>Executive BI Digest</strong> para sa Admin. Powered by NVIDIA NIM + 3-tier failover.
            </p>
          </div>
          <div class="pillar-card user">
            <div class="pillar-tag tag-user">Pillar 2 &bull; Customer Portal (:8080)</div>
            <div class="pillar-title">USER — Guest Self-Service</div>
            <p class="pillar-desc">
              Puwedeng mag-browse ng rooms, magtanong kay Marvi AI, mag-register/login, mag-upload ng <strong>KYC Valid ID</strong>, mag-book na may real-time overlap prevention, magbayad gamit ang <strong>GCash / Maya QR</strong> + receipt upload, mag-track ng voucher, at mag-submit ng rating/review.
            </p>
          </div>
        </div>
        <div class="pillar-row">
          <div class="pillar-card staff">
            <div class="pillar-tag tag-staff">Pillar 3 &bull; Operations Desk (:8082)</div>
            <div class="pillar-title">STAFF — Frontdesk Operations</div>
            <p class="pillar-desc">
              Nakafocus sa pang-araw-araw na operasyon: tumatanggap/tumatanggi ng pending bookings, nagbe-verify ng GCash/Maya payments (delegated), nagpo-proseso ng <strong>Check-in / Check-out</strong>, at nag-a-update ng <strong>Room Housekeeping Status</strong> (Available / Dirty / Maintenance). Bawal ang user/pricing/AI/settings changes.
            </p>
          </div>
          <div class="pillar-card admin">
            <div class="pillar-tag tag-admin">Pillar 4 &bull; Master Control (:8081)</div>
            <div class="pillar-title">ADMIN — Super Admin Governance</div>
            <p class="pillar-desc">
              May hawak ng kabuuang kontrol: financial analytics + AI insights, Room Inventory CRUD, Booking status override, Payment audit, <strong>User lifecycle &amp; KYC approval</strong>, <strong>Live Password Reset Dispatch</strong>, <strong>Closed Staff Provisioning</strong>, <strong>Marvi AI Config &amp; Threat Logs</strong>, Audit Trail, at Hotel/E-Wallet Settings.
            </p>
          </div>
        </div>
      </div>

      <div class="cover-toc" style="margin-top: 10px;">
        <div class="cover-toc-title">Nilalaman ng Gabay na Ito (10 Pages)</div>
        <div class="toc-cols">
          <div class="toc-col">
            <strong>Page 2:</strong> Master 3-Tier Architecture Structure (Portals, API, AI, DB)<br>
            <strong>Page 3:</strong> Cross-Role Connection Map &amp; Capability Matrix<br>
            <strong>Page 4:</strong> AI (Marvi) Deep Dive — 4 Modules, Pipeline &amp; Failover<br>
            <strong>Page 5:</strong> USER (Guest) — 7-Step Journey &amp; Connections<br>
            <strong>Page 6:</strong> STAFF (Frontdesk) — Operasyon, State Machine &amp; Limits
          </div>
          <div class="toc-col">
            <strong>Page 7:</strong> ADMIN (Super Admin) — 9 Modules &amp; Master Controls<br>
            <strong>Page 8:</strong> End-to-End Cross-Role Flows (3 Sequence Diagrams)<br>
            <strong>Page 9:</strong> Technical Connection Matrix (14 Tables &amp; Endpoints)<br>
            <strong>Page 10:</strong> Taglish Oral Explanation Cheat Sheet (Quick Script)
          </div>
        </div>
      </div>
    </div>

    <div class="cover-foot">
      <div><strong>Tech Stack:</strong> PHP 8 &bull; Slim 4 REST API &bull; MySQL PDO (14 Tables) &bull; Tailwind CSS + Alpine.js &bull; NVIDIA NIM SSE</div>
      <div><strong>Session Isolation:</strong> <code>MH_USER_SESS</code> | <code>MH_STAFF_SESS</code> | <code>MH_ADMIN_SESS</code></div>
    </div>
  </div>
</div>

<!-- ==================== PAGE 2: MASTER 3-TIER ARCHITECTURE ==================== -->
<div class="page">
  <div class="section-header">
    <div>
      <div class="section-h1">1. Master 3-Tier Architecture Structure</div>
      <div class="section-sub">Paano naka-organize ang buong system mula sa browser ng user hanggang sa backend logic at database</div>
    </div>
    <div class="section-pill">SYSTEM ARCHITECTURE</div>
  </div>

  <p>
    Ang <strong>Marvelous Hotel &amp; Dormitory</strong> ay gumagamit ng <strong>Strict 3-Tier Architecture</strong>. Ang ibig sabihin nito, nakahiwalay ang <strong>Presentation Layer</strong> (<code>frontend/</code>), ang <strong>Application &amp; AI Logic Layer</strong> (<code>backend/</code>), at ang <strong>Data Layer</strong> (<code>database/</code> + protected <code>uploads/</code>). Dahil dito, malinis ang code, madaling i-maintain, at may security boundaries sa bawat layer.
  </p>

  <div class="diagram-box">
    <div class="diagram-caption">Figure 1: Complete 3-Tier Architecture &amp; Session Isolation Flow</div>
    <svg viewBox="0 0 740 435" xmlns="http://www.w3.org/2000/svg">
      <!-- Tier 1 Background -->
      <rect x="8" y="6" width="724" height="120" rx="8" fill="#eff6ff" stroke="#93c5fd" stroke-width="1.5"/>
      <text x="20" y="23" font-size="9.5" font-weight="800" fill="#1e3a8a">TIER 1: PRESENTATION LAYER (frontend/ &mdash; Tailwind CSS + Alpine.js + AI Chat Widget)</text>

      <!-- User Portal -->
      <rect x="20" y="33" width="222" height="82" rx="6" fill="#ffffff" stroke="#0284c7" stroke-width="1.8"/>
      <rect x="20" y="33" width="222" height="20" rx="6" fill="#0284c7"/>
      <text x="131" y="46.5" text-anchor="middle" font-size="8.5" font-weight="800" fill="#ffffff">USER / GUEST PORTAL (:8080)</text>
      <text x="28" y="65" font-size="7.5" fill="#0f172a" font-weight="700">Cookie: MH_USER_SESS (30 days)</text>
      <text x="28" y="77" font-size="7" fill="#475569">Public: index, rooms, about, contact</text>
      <text x="28" y="88" font-size="7" fill="#475569">Auth: login, register, forgot-password</text>
      <text x="28" y="99" font-size="7" fill="#475569">Hub: booking, payment, profile, ai-recommend</text>

      <!-- Staff Portal -->
      <rect x="259" y="33" width="222" height="82" rx="6" fill="#ffffff" stroke="#059669" stroke-width="1.8"/>
      <rect x="259" y="33" width="222" height="20" rx="6" fill="#059669"/>
      <text x="370" y="46.5" text-anchor="middle" font-size="8.5" font-weight="800" fill="#ffffff">STAFF PORTAL (:8082)</text>
      <text x="267" y="65" font-size="7.5" fill="#0f172a" font-weight="700">Cookie: MH_STAFF_SESS (8 hours)</text>
      <text x="267" y="77" font-size="7" fill="#475569">Direct Login Only (No Public Signup)</text>
      <text x="267" y="88" font-size="7" fill="#475569">Pages: dashboard, payments, check-in</text>
      <text x="267" y="99" font-size="7" fill="#475569">rooms-status, account (change pass)</text>

      <!-- Admin Portal -->
      <rect x="498" y="33" width="222" height="82" rx="6" fill="#ffffff" stroke="#d97706" stroke-width="1.8"/>
      <rect x="498" y="33" width="222" height="20" rx="6" fill="#d97706"/>
      <text x="609" y="46.5" text-anchor="middle" font-size="8.5" font-weight="800" fill="#ffffff">SUPER ADMIN PORTAL (:8081)</text>
      <text x="506" y="65" font-size="7.5" fill="#0f172a" font-weight="700">Cookie: MH_ADMIN_SESS (8 hours)</text>
      <text x="506" y="77" font-size="7" fill="#475569">Direct Login Only (Full Master Control)</text>
      <text x="506" y="88" font-size="7" fill="#475569">dashboard, rooms, bookings, payments</text>
      <text x="506" y="99" font-size="7" fill="#475569">users, staff, ai-config, audit-logs, settings</text>

      <!-- Arrows Tier 1 -> Tier 2 -->
      <line x1="131" y1="115" x2="131" y2="148" stroke="#0284c7" stroke-width="1.8"/>
      <polygon points="127,144 135,144 131,151" fill="#0284c7"/>
      <line x1="370" y1="115" x2="370" y2="148" stroke="#059669" stroke-width="1.8"/>
      <polygon points="366,144 374,144 370,151" fill="#059669"/>
      <line x1="609" y1="115" x2="609" y2="148" stroke="#d97706" stroke-width="1.8"/>
      <polygon points="605,144 613,144 609,151" fill="#d97706"/>

      <!-- Tier 2 Background -->
      <rect x="8" y="152" width="724" height="156" rx="8" fill="#f5f3ff" stroke="#c4b5fd" stroke-width="1.5"/>
      <text x="20" y="169" font-size="9.5" font-weight="800" fill="#4c1d95">TIER 2: APPLICATION, SECURITY &amp; AI ENGINE LAYER (backend/ &mdash; PHP 8 + Slim 4)</text>

      <!-- Slim 4 REST API -->
      <rect x="20" y="179" width="228" height="118" rx="6" fill="#ffffff" stroke="#4f46e5" stroke-width="1.5"/>
      <rect x="20" y="179" width="228" height="19" rx="6" fill="#4f46e5"/>
      <text x="134" y="192" text-anchor="middle" font-size="8" font-weight="800" fill="#ffffff">Slim 4 REST API (/backend/api/v1/*)</text>
      <text x="28" y="210" font-size="7.2" font-weight="700" fill="#1e293b">PSR-15 Security Middleware:</text>
      <text x="28" y="221" font-size="6.9" fill="#475569">&bull; RoleAuthMiddleware (user/staff/admin)</text>
      <text x="28" y="232" font-size="6.9" fill="#475569">&bull; CsrfProtectionMiddleware (POST guard)</text>
      <text x="28" y="243" font-size="6.9" fill="#475569">&bull; JsonBodyParserMiddleware</text>
      <text x="28" y="257" font-size="7.2" font-weight="700" fill="#1e293b">Controllers &rarr; Services (PSR-4):</text>
      <text x="28" y="268" font-size="6.9" fill="#475569">Auth, Room, Booking, Payment, Dashboard</text>
      <text x="28" y="279" font-size="6.9" fill="#475569">/v1/health, /rooms, /availability, /bookings</text>
      <text x="28" y="290" font-size="6.9" fill="#475569">/v1/payments/{id}/decision, /dashboard/metrics</text>

      <!-- Core PHP & Endpoints -->
      <rect x="258" y="179" width="224" height="118" rx="6" fill="#ffffff" stroke="#0f172a" stroke-width="1.5"/>
      <rect x="258" y="179" width="224" height="19" rx="6" fill="#0f172a"/>
      <text x="370" y="192" text-anchor="middle" font-size="8" font-weight="800" fill="#ffffff">Core Infrastructure &amp; Endpoints</text>
      <text x="266" y="210" font-size="7" fill="#334155"><tspan font-weight="700">config.php:</tspan> getActivePortal(), cookie names</text>
      <text x="266" y="221" font-size="7" fill="#334155"><tspan font-weight="700">session.php:</tspan> Global CSRF, Remember Me, Locks</text>
      <text x="266" y="232" font-size="7" fill="#334155"><tspan font-weight="700">db.php:</tspan> PDO singleton, dbRow/dbRows/dbExec</text>
      <text x="266" y="243" font-size="7" fill="#334155"><tspan font-weight="700">functions.php:</tspan> Overlap check, KYC, auditLog</text>
      <text x="266" y="254" font-size="7" fill="#334155"><tspan font-weight="700">rate-limiter.php:</tspan> 5 auth attempts / 15 min</text>
      <text x="266" y="265" font-size="7" fill="#334155"><tspan font-weight="700">email.php:</tspan> PHPMailer 7 SMTP / reset templates</text>
      <text x="266" y="278" font-size="7" font-weight="700" fill="#0f172a">Endpoints:</text>
      <text x="266" y="289" font-size="6.8" fill="#475569">serve-file.php (RBAC proxy), check-reset-status.php</text>

      <!-- Marvi AI Engine -->
      <rect x="492" y="179" width="228" height="118" rx="6" fill="#ffffff" stroke="#7c3aed" stroke-width="1.5"/>
      <rect x="492" y="179" width="228" height="19" rx="6" fill="#7c3aed"/>
      <text x="606" y="192" text-anchor="middle" font-size="8" font-weight="800" fill="#ffffff">Marvi AI Engine (backend/ai/*)</text>
      <text x="500" y="210" font-size="7" fill="#334155"><tspan font-weight="700">chatbot.php:</tspan> SSE stream, EN/TL, Room Cards</text>
      <text x="500" y="221" font-size="7" fill="#334155"><tspan font-weight="700">staff-assistant.php:</tspan> Shift Ops Briefing (5m cache)</text>
      <text x="500" y="232" font-size="7" fill="#334155"><tspan font-weight="700">admin-insights.php:</tspan> Executive BI Digest</text>
      <text x="500" y="243" font-size="7" fill="#334155"><tspan font-weight="700">nvidia-client.php:</tspan> Role prompts &amp; failover</text>
      <text x="500" y="257" font-size="7" font-weight="700" fill="#5b21b6">External Cloud AI (HTTPS):</text>
      <rect x="500" y="263" width="212" height="27" rx="4" fill="#ede9fe" stroke="#a78bfa"/>
      <text x="606" y="274" text-anchor="middle" font-size="6.9" font-weight="700" fill="#4c1d95">NVIDIA NIM API (integrate.api.nvidia.com)</text>
      <text x="606" y="284" text-anchor="middle" font-size="6.5" fill="#5b21b6">nemotron-3.5-bolt-e4b &rarr; 30B &rarr; MiniMax &rarr; Local FAQ</text>

      <!-- Arrows Tier 2 -> Tier 3 -->
      <line x1="180" y1="297" x2="180" y2="336" stroke="#475569" stroke-width="1.8"/>
      <polygon points="176,332 184,332 180,339" fill="#475569"/>
      <line x1="370" y1="297" x2="370" y2="336" stroke="#475569" stroke-width="1.8"/>
      <polygon points="366,332 374,332 370,339" fill="#475569"/>
      <line x1="565" y1="297" x2="565" y2="336" stroke="#475569" stroke-width="1.8"/>
      <polygon points="561,332 569,332 565,339" fill="#475569"/>

      <!-- Tier 3 Background -->
      <rect x="8" y="340" width="724" height="88" rx="8" fill="#ecfdf5" stroke="#6ee7b7" stroke-width="1.5"/>
      <text x="20" y="357" font-size="9.5" font-weight="800" fill="#065f46">TIER 3: DATA &amp; PROTECTED STORAGE LAYER (database/ &amp; uploads/)</text>

      <rect x="20" y="366" width="465" height="53" rx="6" fill="#ffffff" stroke="#059669" stroke-width="1.4"/>
      <text x="28" y="380" font-size="8" font-weight="800" fill="#065f46">MySQL / MariaDB (InnoDB, utf8mb4_unicode_ci) &mdash; 14 Tables via PDO</text>
      <text x="28" y="393" font-size="6.9" fill="#334155">users &bull; room_types &bull; rooms &bull; bookings &bull; payments &bull; reviews &bull; notifications</text>
      <text x="28" y="405" font-size="6.9" fill="#334155">audit_logs &bull; ai_logs &bull; settings &bull; contact_messages &bull; rate_limits &bull; password_reset_requests &bull; schema_migrations</text>

      <rect x="495" y="366" width="225" height="53" rx="6" fill="#ffffff" stroke="#dc2626" stroke-width="1.4"/>
      <text x="503" y="380" font-size="8" font-weight="800" fill="#991b1b">Protected uploads/ (.htaccess 403)</text>
      <text x="503" y="393" font-size="6.9" fill="#334155">documents/ (KYC IDs) &bull; proofs/ (Receipts)</text>
      <text x="503" y="405" font-size="6.9" fill="#334155">avatars/ &bull; rooms/ &rarr; via serve-file.php</text>
    </svg>
  </div>

  <div class="grid-2">
    <div class="col-2">
      <div class="card soft">
        <div class="card-title">Bakit 3 magkaibang Session Cookies?</div>
        <p style="margin-bottom:0;">
          Sa <code>backend/core/config.php</code>, dine-detect ng <code>getActivePortal()</code> ang active portal via port (<code>:8080</code> User, <code>:8081</code> Admin, <code>:8082</code> Staff), path (<code>/admin/</code>, <code>/staff/</code>), o <code>X-Portal</code> header. Gumagawa ito ng hiwalay na cookie (<code>MH_USER_SESS</code>, <code>MH_STAFF_SESS</code>, <code>MH_ADMIN_SESS</code>) para <strong>puwedeng naka-login nang sabay-sabay</strong> ang User, Staff, at Admin sa iisang browser nang walang collision.
        </p>
      </div>
    </div>
    <div class="col-2">
      <div class="card soft">
        <div class="card-title">Bakit Non-Blocking ang Session &amp; Protected ang Files?</div>
        <p style="margin-bottom:0;">
          Ginagamit ng pages at AI endpoints ang <code>releaseReadOnlySession()</code> / <code>releaseSessionLock()</code> bago mag-query sa DB o mag-stream sa NVIDIA NIM para hindi mag-freeze ang ibang tabs. Samantala, naka-block sa direct URL ang <code>uploads/</code>, <code>database/</code>, at <code>backend/core/</code>; lahat ng ID at payment receipt ay dumadaan muna sa <code>serve-file.php</code> RBAC check.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ==================== PAGE 3: CROSS-ROLE CONNECTION MAP ==================== -->
<div class="page">
  <div class="section-header">
    <div>
      <div class="section-h1">2. Cross-Role Connection Map (/ AI / USER / STAFF / ADMIN)</div>
      <div class="section-sub">Paano nagpapalitan ng data, triggers, at approvals ang apat na bahagi ng sistema</div>
    </div>
    <div class="section-pill">ROLE INTERCONNECTION</div>
  </div>

  <div class="diagram-box">
    <div class="diagram-caption">Figure 2: How AI, USER, STAFF, and ADMIN Connect Through Shared Data &amp; Workflows</div>
    <svg viewBox="0 0 740 315" xmlns="http://www.w3.org/2000/svg">
      <!-- Center Hub: Shared MySQL & Files -->
      <rect x="272" y="114" width="196" height="86" rx="10" fill="#0f172a" stroke="#c5a880" stroke-width="2.2"/>
      <text x="370" y="135" text-anchor="middle" font-size="9" font-weight="800" fill="#fde68a">SHARED SYSTEM CORE</text>
      <text x="370" y="150" text-anchor="middle" font-size="7.3" fill="#e2e8f0">MySQL 14 Tables + ACID Locks</text>
      <text x="370" y="163" text-anchor="middle" font-size="7.3" fill="#e2e8f0">notifications &bull; audit_logs &bull; ai_logs</text>
      <text x="370" y="176" text-anchor="middle" font-size="7.3" fill="#e2e8f0">serve-file.php &bull; Slim 4 /v1/* API</text>
      <text x="370" y="189" text-anchor="middle" font-size="6.8" fill="#94a3b8">Single Source of Truth</text>

      <!-- Top-Left: AI -->
      <rect x="16" y="14" width="215" height="90" rx="8" fill="#f5f3ff" stroke="#7c3aed" stroke-width="2"/>
      <rect x="16" y="14" width="215" height="22" rx="8" fill="#7c3aed"/>
      <text x="123" y="28.5" text-anchor="middle" font-size="9" font-weight="800" fill="#ffffff">AI (Marvi Engine)</text>
      <text x="26" y="50" font-size="7.2" fill="#1e293b">&bull; Guest SSE Chat + Room Cards</text>
      <text x="26" y="62" font-size="7.2" fill="#1e293b">&bull; Smart Room Matcher (user/ai-recommend)</text>
      <text x="26" y="74" font-size="7.2" fill="#1e293b">&bull; Staff Shift Briefing (staff-assistant)</text>
      <text x="26" y="86" font-size="7.2" fill="#1e293b">&bull; Admin BI Digest (admin-insights)</text>
      <text x="26" y="98" font-size="7" font-weight="700" fill="#5b21b6">&bull; Security Bad-Intent Filter &rarr; ai_logs</text>

      <!-- Top-Right: USER -->
      <rect x="509" y="14" width="215" height="90" rx="8" fill="#eff6ff" stroke="#0284c7" stroke-width="2"/>
      <rect x="509" y="14" width="215" height="22" rx="8" fill="#0284c7"/>
      <text x="616" y="28.5" text-anchor="middle" font-size="9" font-weight="800" fill="#ffffff">USER (Guest)</text>
      <text x="519" y="50" font-size="7.2" fill="#1e293b">&bull; Browse Rooms &amp; Chat/Voice w/ Marvi</text>
      <text x="519" y="62" font-size="7.2" fill="#1e293b">&bull; Register + Upload KYC Valid ID</text>
      <text x="519" y="74" font-size="7.2" fill="#1e293b">&bull; Create Booking + GCash/Maya Receipt</text>
      <text x="519" y="86" font-size="7.2" fill="#1e293b">&bull; View Vouchers, Cancel, Request Reset</text>
      <text x="519" y="98" font-size="7.2" fill="#1e293b">&bull; Submit Stay Reviews &amp; Ratings</text>

      <!-- Bottom-Left: STAFF -->
      <rect x="16" y="210" width="215" height="90" rx="8" fill="#ecfdf5" stroke="#059669" stroke-width="2"/>
      <rect x="16" y="210" width="215" height="22" rx="8" fill="#059669"/>
      <text x="123" y="224.5" text-anchor="middle" font-size="9" font-weight="800" fill="#ffffff">STAFF (Frontdesk)</text>
      <text x="26" y="246" font-size="7.2" fill="#1e293b">&bull; Accept / Decline Pending Bookings</text>
      <text x="26" y="258" font-size="7.2" fill="#1e293b">&bull; Verify / Reject GCash &amp; Maya Proofs</text>
      <text x="26" y="270" font-size="7.2" fill="#1e293b">&bull; Process Guest Check-in &amp; Check-out</text>
      <text x="26" y="282" font-size="7.2" fill="#1e293b">&bull; Room Housekeeping Status Board</text>
      <text x="26" y="294" font-size="7" font-weight="700" fill="#047857">&bull; Read AI Shift Briefing on Dashboard</text>

      <!-- Bottom-Right: ADMIN -->
      <rect x="509" y="210" width="215" height="90" rx="8" fill="#fffbeb" stroke="#d97706" stroke-width="2"/>
      <rect x="509" y="210" width="215" height="22" rx="8" fill="#d97706"/>
      <text x="616" y="224.5" text-anchor="middle" font-size="9" font-weight="800" fill="#ffffff">ADMIN (Super Admin)</text>
      <text x="519" y="246" font-size="7.2" fill="#1e293b">&bull; Full Rooms CRUD &amp; Pricing Control</text>
      <text x="519" y="258" font-size="7.2" fill="#1e293b">&bull; Approve/Reject KYC &amp; Ban Users</text>
      <text x="519" y="270" font-size="7.2" fill="#1e293b">&bull; Live Password Reset &amp; Staff Accounts</text>
      <text x="519" y="282" font-size="7.2" fill="#1e293b">&bull; AI Model/Key Config &amp; Threat Logs</text>
      <text x="519" y="294" font-size="7.2" fill="#1e293b">&bull; Audit Trail &amp; Hotel/E-Wallet Settings</text>

      <!-- Connection Lines between Roles -->
      <!-- AI <-> USER -->
      <line x1="231" y1="48" x2="509" y2="48" stroke="#7c3aed" stroke-width="1.8"/>
      <polygon points="503,44 503,52 510,48" fill="#7c3aed"/>
      <polygon points="237,44 237,52 230,48" fill="#7c3aed"/>
      <rect x="285" y="34" width="170" height="19" rx="4" fill="#ffffff" stroke="#c4b5fd"/>
      <text x="370" y="46.5" text-anchor="middle" font-size="6.8" font-weight="700" fill="#5b21b6">Tanong / Budget &harr; Room Cards &amp; Booking Link</text>

      <!-- AI -> STAFF -->
      <line x1="110" y1="104" x2="110" y2="210" stroke="#059669" stroke-width="1.8"/>
      <polygon points="106,204 114,204 110,211" fill="#059669"/>
      <rect x="20" y="144" width="180" height="22" rx="4" fill="#ffffff" stroke="#6ee7b7"/>
      <text x="110" y="157" text-anchor="middle" font-size="6.7" font-weight="700" fill="#047857">Shift Ops Briefing (Arrivals/Dirty/Pending)</text>

      <!-- USER <-> STAFF (via Core) -->
      <line x1="509" y1="88" x2="468" y2="122" stroke="#0284c7" stroke-width="1.6"/>
      <line x1="272" y1="186" x2="231" y2="218" stroke="#059669" stroke-width="1.6"/>
      <polygon points="236,219 231,212 229,220" fill="#059669"/>

      <!-- USER <-> ADMIN (via Core) -->
      <line x1="616" y1="104" x2="616" y2="210" stroke="#d97706" stroke-width="1.8"/>
      <polygon points="612,204 620,204 616,211" fill="#d97706"/>
      <polygon points="612,110 620,110 616,103" fill="#0284c7"/>
      <rect x="526" y="144" width="180" height="26" rx="4" fill="#ffffff" stroke="#fcd34d"/>
      <text x="616" y="155" text-anchor="middle" font-size="6.6" font-weight="700" fill="#92400e">KYC Docs, Password Resets, Payments</text>
      <text x="616" y="165" text-anchor="middle" font-size="6.4" fill="#b45309">&harr; Live Popup, Verification &amp; Overrides</text>

      <!-- AI <-> ADMIN -->
      <line x1="215" y1="104" x2="272" y2="132" stroke="#7c3aed" stroke-width="1.5"/>
      <line x1="468" y1="184" x2="515" y2="210" stroke="#d97706" stroke-width="1.5"/>

      <!-- STAFF <-> ADMIN -->
      <line x1="231" y1="258" x2="509" y2="258" stroke="#0f172a" stroke-width="1.8"/>
      <polygon points="503,254 503,262 510,258" fill="#0f172a"/>
      <polygon points="237,254 237,262 230,258" fill="#0f172a"/>
      <rect x="275" y="246" width="190" height="22" rx="4" fill="#ffffff" stroke="#94a3b8"/>
      <text x="370" y="256" text-anchor="middle" font-size="6.6" font-weight="700" fill="#0f172a">Admin creates Staff &amp; Audits Actions</text>
      <text x="370" y="265" text-anchor="middle" font-size="6.4" fill="#475569">ACID Payment Lock Sync (Auto-Sync)</text>
    </svg>
  </div>

  <h2>4-Role Capability &amp; Permission Matrix</h2>
  <table>
    <thead>
      <tr>
        <th style="width:24%;">System Function / Capability</th>
        <th style="width:19%;">AI (Marvi Engine)</th>
        <th style="width:19%;">USER (Guest)</th>
        <th style="width:19%;">STAFF (Frontdesk)</th>
        <th style="width:19%;">ADMIN (Super Admin)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Account Registration / Creation</strong></td>
        <td><span class="badge b-no">N/A</span> System service</td>
        <td><span class="badge b-user">Self-Register</span> <code>auth/register.php</code></td>
        <td><span class="badge b-no">Closed</span> Admin-created only</td>
        <td><span class="badge b-admin">Full</span> Creates Staff &amp; Walk-in Users</td>
      </tr>
      <tr>
        <td><strong>AI Interaction &amp; Control</strong></td>
        <td><span class="badge b-ai">Executes</span> Live SSE + Failover</td>
        <td><span class="badge b-user">Uses</span> Chat, Voice, Room Matcher</td>
        <td><span class="badge b-staff">Reads</span> Shift Ops Briefing</td>
        <td><span class="badge b-admin">Configures</span> Model/Key, Threat Logs, BI</td>
      </tr>
      <tr>
        <td><strong>Room Catalog &amp; Pricing</strong></td>
        <td><span class="badge b-ro">Reads</span> Live DB catalog &amp; rates</td>
        <td><span class="badge b-ro">Views</span> Availability &amp; prices</td>
        <td><span class="badge b-staff">Status Only</span> Available/Dirty/Maint.</td>
        <td><span class="badge b-admin">Full CRUD</span> Add/Edit/Delete &amp; Price</td>
      </tr>
      <tr>
        <td><strong>Bookings Lifecycle</strong></td>
        <td><span class="badge b-ai">Links</span> Direct Room Cards</td>
        <td><span class="badge b-user">Creates/Cancels</span> Own bookings</td>
        <td><span class="badge b-staff">Ops</span> Accept/Decline, Check-in/Out</td>
        <td><span class="badge b-admin">Full Override</span> All states + Audit</td>
      </tr>
      <tr>
        <td><strong>GCash / Maya Payments</strong></td>
        <td><span class="badge b-ro">Explains</span> QR &amp; payment steps</td>
        <td><span class="badge b-user">Submits</span> Ref # + Proof image</td>
        <td><span class="badge b-staff">Delegated</span> Verify / Reject (ACID)</td>
        <td><span class="badge b-admin">Master Audit</span> Verify / Reject (ACID)</td>
      </tr>
      <tr>
        <td><strong>KYC Valid ID Verification</strong></td>
        <td><span class="badge b-ro">Checks</span> Logged-in KYC state</td>
        <td><span class="badge b-user">Uploads</span> ID via <code>profile.php</code></td>
        <td><span class="badge b-ro">Views</span> KYC badge at check-in</td>
        <td><span class="badge b-admin">Approves/Rejects</span> Reviews ID file</td>
      </tr>
      <tr>
        <td><strong>Password Resets &amp; Security</strong></td>
        <td><span class="badge b-ai">Flags</span> Bad-intent attempts</td>
        <td><span class="badge b-user">Requests</span> <code>forgot-password.php</code></td>
        <td><span class="badge b-no">Restricted</span> Own password only</td>
        <td><span class="badge b-admin">Dispatches</span> Temp Pass / Link + Audit</td>
      </tr>
      <tr>
        <td><strong>Global Settings &amp; Audit Logs</strong></td>
        <td><span class="badge b-ro">Reads</span> <code>ai_*</code> &amp; payment settings</td>
        <td><span class="badge b-no">No Access</span></td>
        <td><span class="badge b-no">No Access</span></td>
        <td><span class="badge b-admin">Full Access</span> Settings &amp; Audit Logs</td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ==================== PAGE 4: PILLAR 1 - AI (MARVI) ==================== -->
<div class="page">
  <div class="section-header ai-hdr">
    <div>
      <div class="section-h1">3. Pillar 1: AI (Marvi AI) &mdash; Ano ang Kaya Nito at Paano Gumagana?</div>
      <div class="section-sub">Real-time streaming concierge para sa Guest, Room Matcher, Frontdesk Ops Co-Pilot, at Executive BI Digest</div>
    </div>
    <div class="section-pill">PILLAR 1 &bull; MARVI AI</div>
  </div>

  <p>
    Hindi isang chatbot lang ang AI sa Marvelous Hotel &mdash; binubuo ito ng <strong>apat na role-aware AI modules</strong> na naka-centralize sa <code>backend/ai/nvidia-client.php</code>. Bawat role ay may sariling system prompt, cache, at security rules.
  </p>

  <div class="diagram-box">
    <div class="diagram-caption">Figure 3: Marvi AI Request Pipeline, Security Guardrails &amp; 4-Level Failover</div>
    <svg viewBox="0 0 740 198" xmlns="http://www.w3.org/2000/svg">
      <!-- Step 1: Input -->
      <rect x="10" y="24" width="112" height="72" rx="6" fill="#eff6ff" stroke="#0284c7" stroke-width="1.6"/>
      <text x="66" y="40" text-anchor="middle" font-size="7.8" font-weight="800" fill="#0369a1">1. User Query</text>
      <text x="66" y="53" text-anchor="middle" font-size="6.8" fill="#334155">Text o Voice Mic</text>
      <text x="66" y="64" text-anchor="middle" font-size="6.8" fill="#334155">(ai-chat.js &rarr; POST</text>
      <text x="66" y="75" text-anchor="middle" font-size="6.8" fill="#334155">chatbot.php?stream=1)</text>
      <text x="66" y="88" text-anchor="middle" font-size="6.4" font-weight="700" fill="#0284c7">Multi-turn History</text>

      <line x1="122" y1="60" x2="142" y2="60" stroke="#475569" stroke-width="1.5"/>
      <polygon points="138,56 138,64 145,60" fill="#475569"/>

      <!-- Step 2: Rate Limit & Security -->
      <rect x="145" y="24" width="128" height="72" rx="6" fill="#fef2f2" stroke="#dc2626" stroke-width="1.6"/>
      <text x="209" y="40" text-anchor="middle" font-size="7.8" font-weight="800" fill="#991b1b">2. Security Filter</text>
      <text x="209" y="53" text-anchor="middle" font-size="6.8" fill="#334155">&bull; Rate Limit (60/min</text>
      <text x="209" y="64" text-anchor="middle" font-size="6.8" fill="#334155">local, 20/min public)</text>
      <text x="209" y="75" text-anchor="middle" font-size="6.8" fill="#334155">&bull; Bad-Intent Keywords</text>
      <text x="209" y="88" text-anchor="middle" font-size="6.4" font-weight="700" fill="#b91c1c">Flag &rarr; ai_logs + audit</text>

      <line x1="273" y1="60" x2="293" y2="60" stroke="#475569" stroke-width="1.5"/>
      <polygon points="289,56 289,64 296,60" fill="#475569"/>

      <!-- Step 3: Context Injection -->
      <rect x="296" y="16" width="146" height="88" rx="6" fill="#f5f3ff" stroke="#7c3aed" stroke-width="1.6"/>
      <text x="369" y="32" text-anchor="middle" font-size="7.8" font-weight="800" fill="#5b21b6">3. Live Context Builder</text>
      <text x="304" y="45" font-size="6.7" fill="#334155">&bull; Auto-detect Lang (EN / TL)</text>
      <text x="304" y="56" font-size="6.7" fill="#334155">&bull; Live Room Catalog + IDs</text>
      <text x="304" y="67" font-size="6.7" fill="#334155">&bull; Active Booked Room Notice</text>
      <text x="304" y="78" font-size="6.7" fill="#334155">&bull; Guest KYC &amp; Last 3 Bookings</text>
      <text x="304" y="89" font-size="6.7" fill="#334155">&bull; Maya / GCash &amp; Check-in Rules</text>
      <text x="304" y="99" font-size="6.3" font-weight="700" fill="#6d28d9">Role Prompt: guest / staff / admin</text>

      <line x1="442" y1="60" x2="462" y2="60" stroke="#475569" stroke-width="1.5"/>
      <polygon points="458,56 458,64 465,60" fill="#475569"/>

      <!-- Step 4: Model Failover Chain -->
      <rect x="465" y="12" width="142" height="96" rx="6" fill="#0f172a" stroke="#c5a880" stroke-width="1.8"/>
      <text x="536" y="27" text-anchor="middle" font-size="7.6" font-weight="800" fill="#fde68a">4. NVIDIA NIM + Failover</text>
      <rect x="474" y="34" width="124" height="16" rx="3" fill="#4c1d95"/>
      <text x="536" y="45" text-anchor="middle" font-size="6.4" font-weight="700" fill="#ffffff">1) nemotron-3.5-bolt-e4b</text>
      <rect x="474" y="53" width="124" height="15" rx="3" fill="#334155"/>
      <text x="536" y="63" text-anchor="middle" font-size="6.2" fill="#e2e8f0">2) nemotron-3.5-lightning-30b</text>
      <rect x="474" y="71" width="124" height="15" rx="3" fill="#334155"/>
      <text x="536" y="81" text-anchor="middle" font-size="6.2" fill="#e2e8f0">3) minimax-m3.1-flash</text>
      <rect x="474" y="89" width="124" height="15" rx="3" fill="#065f46"/>
      <text x="536" y="99" text-anchor="middle" font-size="6.2" font-weight="700" fill="#a7f3d0">4) Local Budget/FAQ Engine</text>

      <line x1="607" y1="60" x2="627" y2="60" stroke="#475569" stroke-width="1.5"/>
      <polygon points="623,56 623,64 630,60" fill="#475569"/>

      <!-- Step 5: Action Parsing & SSE Output -->
      <rect x="630" y="18" width="100" height="84" rx="6" fill="#ecfdf5" stroke="#059669" stroke-width="1.6"/>
      <text x="680" y="34" text-anchor="middle" font-size="7.6" font-weight="800" fill="#065f46">5. SSE Output</text>
      <text x="680" y="48" text-anchor="middle" font-size="6.6" fill="#334155">Live Token Stream</text>
      <text x="680" y="59" text-anchor="middle" font-size="6.6" fill="#334155">+ Room Cards</text>
      <text x="680" y="70" text-anchor="middle" font-size="6.6" fill="#334155">+ Nav Buttons</text>
      <text x="680" y="81" text-anchor="middle" font-size="6.6" fill="#334155">+ Voice TTS</text>
      <text x="680" y="94" text-anchor="middle" font-size="6.3" font-weight="700" fill="#047857">Log &rarr; ai_logs</text>

      <!-- Bottom Callout: Action Tag Resolution -->
      <rect x="10" y="122" width="720" height="66" rx="6" fill="#ffffff" stroke="#cbd5e1"/>
      <text x="20" y="137" font-size="7.8" font-weight="800" fill="#0f172a">Server-Verified Interactive Actions (Hindi Gawa-gawa ang Links ng AI):</text>
      <text x="20" y="151" font-size="7" fill="#334155">&bull; Kapag nag-output ang LLM ng <tspan font-weight="700">[[BOOK_ROOM:7]]</tspan>, chine-check muna ng PHP sa MySQL kung totoo ang Room #7 at bumubuo ng clickable <tspan font-weight="700">Room Card</tspan> (may presyo, sukli sa budget, at Book link).</text>
      <text x="20" y="164" font-size="7" fill="#334155">&bull; Kapag nag-output ng <tspan font-weight="700">[[GOTO:payment]]</tspan> o nagtanong ang user ng <tspan font-style="italic">"ipunta mo ako doon"</tspan>, nagre-render ng verified <tspan font-weight="700">Take Me There</tspan> button patungong tamang page.</text>
      <text x="20" y="177" font-size="7" fill="#334155">&bull; Kapag offline ang cloud API o walang API key, awtomatikong sasalo ang <tspan font-weight="700">buildLocalFallbackReply()</tspan> (booking lookup, budget matcher, at bilingual <tspan font-family="Courier New, monospace">faq.json</tspan>).</text>
    </svg>
  </div>

  <h2>Apat (4) na AI Capabilities Across the System</h2>
  <div class="grid-2">
    <div class="col-2">
      <div class="card ai">
        <div class="card-title">1. Guest Concierge Chatbot (<code>backend/ai/chatbot.php</code>)</div>
        <ul>
          <li><strong>Bilingual Auto-Detect:</strong> Kusang nakikilala kung English o Tagalog/Taglish ang tanong at sumasagot sa parehong wika (may language stickiness para sa maiikling follow-up tulad ng <code>"ok"</code> o <code>"1000"</code>).</li>
          <li><strong>Live Awareness:</strong> Alam ang presyo, kapasidad, at occupancy ng lahat ng kwarto sa MySQL, plus KYC status at huling 3 bookings ng naka-login na guest.</li>
          <li><strong>Budget Extraction &amp; Voice:</strong> Naiintindihan ang <code>"1k"</code>, <code>"1 libo"</code>, <code>"₱1,500"</code>; may Voice Mic input at Speech Synthesis reply sa <code>ai-chat.js</code>.</li>
          <li><strong>Security Guard:</strong> Hinaharang at nilo-log sa <code>ai_logs</code> (<code>flagged = 1</code>) + <code>audit_logs</code> ang SQL injection, exploit, o restricted keywords mula sa <code>bad-intent-keywords.json</code>.</li>
        </ul>
      </div>
      <div class="card ai">
        <div class="card-title">2. Smart AI Room Matcher (<code>frontend/user/ai-recommend.php</code>)</div>
        <ul>
          <li>Structured form para sa logged-in User: <strong>Budget Limit</strong>, <strong>Pax Count</strong>, optional <strong>Check-in/Check-out dates</strong>, at <strong>Preference</strong> (Balanced, Budget/Dormitory, Luxury/Family).</li>
          <li>Ginagamit ang <code>isRoomAvailable()</code> para hindi irekomenda ang kwartong may conflicting dates, sabay calculate ng matitipid sa budget.</li>
        </ul>
      </div>
    </div>
    <div class="col-2">
      <div class="card ai">
        <div class="card-title">3. Staff Operations AI Co-Pilot (<code>backend/ai/staff-assistant.php</code>)</div>
        <ul>
          <li>Naka-display sa <strong>Staff Dashboard</strong> (protected para sa Staff/Admin session only; may 5-minute file cache sa <code>storage/cache/</code> para hindi bumagal ang dashboard).</li>
          <li>Kinukuha ang live counts ngayong araw: <strong>Expected Arrivals</strong>, <strong>Expected Departures</strong>, <strong>Dirty / Maintenance Rooms</strong>, <strong>Pending Bookings</strong>, at <strong>Unpaid Arrivals</strong>.</li>
          <li>Gumagawa ng 3-part Taglish Shift Operational Briefing (Highlights, Housekeeping Priority, Frontdesk Reminder) — may programmatic fallback kapag offline.</li>
        </ul>
      </div>
      <div class="card ai">
        <div class="card-title">4. Admin Executive AI Insights (<code>backend/ai/admin-insights.php</code>)</div>
        <ul>
          <li>Para sa <strong>Super Admin Dashboard</strong> lamang (<code>isAdmin()</code> check; 5-minute cache + manual Refresh button).</li>
          <li>Sini-synthesize ang <strong>All-Time &amp; Monthly Verified Revenue</strong>, <strong>Maya vs. GCash channel share</strong>, <strong>Room Occupancy Rate (%)</strong>, confirmed vs. cancelled bookings, at backlog (pending payments &amp; KYC).</li>
          <li>Nagbibigay ng strategic Taglish recommendations (hal. promo para sa low occupancy o priority backlog verification).</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ==================== PAGE 5: PILLAR 2 - USER (GUEST) ==================== -->
<div class="page">
  <div class="section-header user-hdr">
    <div>
      <div class="section-h1">4. Pillar 2: USER (Guest) &mdash; Ano ang Mga Ginagawa at Kaya Nito?</div>
      <div class="section-sub">Public Showcase + Self-Service Guest Portal (:8080 / <code>frontend/user/</code> &amp; <code>frontend/auth/</code>)</div>
    </div>
    <div class="section-pill">PILLAR 2 &bull; GUEST / USER</div>
  </div>

  <p>
    Ang <strong>User / Guest</strong> ang tanging role na may <strong>public self-registration</strong> (<code>auth/register.php</code>). Puwede siyang magsimula bilang bisita sa public website (mag-browse ng rooms at magtanong kay Marvi AI) bago mag-create ng account, magpa-verify ng KYC ID, mag-book, magbayad via GCash/Maya, at mag-review pagkatapos ng stay.
  </p>

  <div class="diagram-box">
    <div class="diagram-caption">Figure 4: End-to-End 7-Step Guest Booking &amp; Verification Funnel</div>
    <svg viewBox="0 0 740 132" xmlns="http://www.w3.org/2000/svg">
      <!-- 7 boxes across -->
      <rect x="8" y="18" width="94" height="82" rx="6" fill="#f5f3ff" stroke="#7c3aed" stroke-width="1.5"/>
      <text x="55" y="34" text-anchor="middle" font-size="7.3" font-weight="800" fill="#5b21b6">1. Discover &amp; Ask AI</text>
      <text x="55" y="48" text-anchor="middle" font-size="6.4" fill="#334155">index.php / rooms.php</text>
      <text x="55" y="59" text-anchor="middle" font-size="6.4" fill="#334155">+ Marvi AI Chatbot</text>
      <text x="55" y="70" text-anchor="middle" font-size="6.4" fill="#334155">o Smart AI Matcher</text>
      <text x="55" y="86" text-anchor="middle" font-size="6.2" font-weight="700" fill="#7c3aed">Public / User</text>

      <polygon points="103,56 109,60 103,64" fill="#475569"/>

      <rect x="112" y="18" width="94" height="82" rx="6" fill="#eff6ff" stroke="#0284c7" stroke-width="1.5"/>
      <text x="159" y="34" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">2. Register / Login</text>
      <text x="159" y="48" text-anchor="middle" font-size="6.4" fill="#334155">auth/register.php</text>
      <text x="159" y="59" text-anchor="middle" font-size="6.4" fill="#334155">Unique name check</text>
      <text x="159" y="70" text-anchor="middle" font-size="6.4" fill="#334155">+ Remember Me</text>
      <text x="159" y="86" text-anchor="middle" font-size="6.2" font-weight="700" fill="#0284c7">MH_USER_SESS</text>

      <polygon points="207,56 213,60 207,64" fill="#475569"/>

      <rect x="216" y="18" width="96" height="82" rx="6" fill="#fffbeb" stroke="#d97706" stroke-width="1.5"/>
      <text x="264" y="34" text-anchor="middle" font-size="7.3" font-weight="800" fill="#b45309">3. KYC Valid ID</text>
      <text x="264" y="48" text-anchor="middle" font-size="6.4" fill="#334155">user/profile.php</text>
      <text x="264" y="59" text-anchor="middle" font-size="6.4" fill="#334155">Avatar, Phone, Address</text>
      <text x="264" y="70" text-anchor="middle" font-size="6.4" fill="#334155">+ Gov/School ID upload</text>
      <text x="264" y="86" text-anchor="middle" font-size="6.2" font-weight="700" fill="#b45309">Required bago Book!</text>

      <polygon points="313,56 319,60 313,64" fill="#475569"/>

      <rect x="322" y="18" width="96" height="82" rx="6" fill="#eff6ff" stroke="#0284c7" stroke-width="1.5"/>
      <text x="370" y="34" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">4. Book a Room</text>
      <text x="370" y="48" text-anchor="middle" font-size="6.4" fill="#334155">user/booking.php</text>
      <text x="370" y="59" text-anchor="middle" font-size="6.4" fill="#334155">Dates, Pax &amp; Overlap</text>
      <text x="370" y="70" text-anchor="middle" font-size="6.4" fill="#334155">check (FOR UPDATE)</text>
      <text x="370" y="86" text-anchor="middle" font-size="6.2" font-weight="700" fill="#0284c7">Status: PENDING</text>

      <polygon points="419,56 425,60 419,64" fill="#475569"/>

      <rect x="428" y="18" width="96" height="82" rx="6" fill="#ecfdf5" stroke="#059669" stroke-width="1.5"/>
      <text x="476" y="34" text-anchor="middle" font-size="7.3" font-weight="800" fill="#065f46">5. Pay GCash/Maya</text>
      <text x="476" y="48" text-anchor="middle" font-size="6.4" fill="#334155">user/payment.php</text>
      <text x="476" y="59" text-anchor="middle" font-size="6.4" fill="#334155">Scan QR, enter Ref #</text>
      <text x="476" y="70" text-anchor="middle" font-size="6.4" fill="#334155">+ Upload receipt img</text>
      <text x="476" y="86" text-anchor="middle" font-size="6.2" font-weight="700" fill="#047857">Verify by Staff/Admin</text>

      <polygon points="525,56 531,60 525,64" fill="#475569"/>

      <rect x="534" y="18" width="96" height="82" rx="6" fill="#eff6ff" stroke="#0284c7" stroke-width="1.5"/>
      <text x="582" y="34" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">6. Track &amp; Stay</text>
      <text x="582" y="48" text-anchor="middle" font-size="6.4" fill="#334155">my-bookings.php</text>
      <text x="582" y="59" text-anchor="middle" font-size="6.4" fill="#334155">booking-detail.php</text>
      <text x="582" y="70" text-anchor="middle" font-size="6.4" fill="#334155">Voucher + Notifs</text>
      <text x="582" y="86" text-anchor="middle" font-size="6.2" font-weight="700" fill="#0284c7">Check-in at Desk</text>

      <polygon points="631,56 637,60 631,64" fill="#475569"/>

      <rect x="640" y="18" width="92" height="82" rx="6" fill="#fffbeb" stroke="#d97706" stroke-width="1.5"/>
      <text x="686" y="34" text-anchor="middle" font-size="7.3" font-weight="800" fill="#b45309">7. Rate &amp; Review</text>
      <text x="686" y="48" text-anchor="middle" font-size="6.4" fill="#334155">user/reviews.php</text>
      <text x="686" y="59" text-anchor="middle" font-size="6.4" fill="#334155">Star rating + comment</text>
      <text x="686" y="70" text-anchor="middle" font-size="6.4" fill="#334155">para sa confirmed /</text>
      <text x="686" y="86" text-anchor="middle" font-size="6.2" font-weight="700" fill="#b45309">checked-out stays</text>

      <text x="370" y="120" text-anchor="middle" font-size="6.8" font-weight="700" fill="#475569">Note: Kung hindi pa Verified ang KYC ng User, awtomatikong ire-redirect ng booking.php ang User pabalik sa profile.php.</text>
    </svg>
  </div>

  <div class="grid-2">
    <div class="col-2">
      <div class="card user">
        <div class="card-title">Lahat ng Kaya at Ginagawa ng USER</div>
        <ul>
          <li><strong>Public Exploration:</strong> Tingnan ang hotel landing page (<code>index.php</code>), rooms &amp; dorm catalog (<code>rooms.php</code>), <code>about.php</code>, at magpadala ng inquiry sa <code>contact.php</code> (<code>contact_messages</code> table).</li>
          <li><strong>AI Assistance:</strong> Gamitin ang floating <strong>Marvi AI Chatbot</strong> (text o voice) o ang <strong>Smart AI Room Matcher</strong> (<code>user/ai-recommend.php</code>) para humanap ng kwartong swak sa budget at pax.</li>
          <li><strong>Account &amp; KYC Verification (<code>user/profile.php</code>):</strong> Mag-upload ng avatar, kumpletuhin ang contact/address info, at mag-submit ng Valid ID document (stored securely sa <code>uploads/documents/</code>, status nagiging <code>pending</code> hanggang ma-approve ng Admin).</li>
          <li><strong>Booking + Anti-Double-Booking (<code>user/booking.php</code>):</strong> Puwedeng pumili ng <em>Book &amp; Pay Now</em> o <em>Book Only</em> (may 24-hour stale unpaid sweep). Chine-check ng DB row lock (<code>FOR UPDATE</code>) at <code>findConflictingBooking()</code> ang overlap sa dates <code>[check_in, check_out)</code>.</li>
          <li><strong>GCash &amp; Maya Payments (<code>user/payment.php</code>):</strong> Mag-scan ng Maya/GCash QR, maglagay ng reference number, at mag-upload ng receipt screenshot (max 15MB, MIME-checked via <code>finfo</code>).</li>
          <li><strong>Self-Service Management:</strong> Tingnan ang bookings at vouchers (<code>my-bookings.php</code>, <code>booking-detail.php</code>), payment history (<code>payment-history.php</code>), notifications (<code>notifications.php</code>), at mag-cancel via Slim API (<code>POST /v1/bookings/{id}/cancel</code>).</li>
        </ul>
      </div>
    </div>
    <div class="col-2">
      <div class="card user">
        <div class="card-title">Account Recovery &amp; Security Guards ng USER</div>
        <ul>
          <li><strong>Unique Full-Name Check:</strong> Sa registration, tinitiyak ng <code>check-name.php</code> na walang kaparehong duplicate name + normalized email validation.</li>
          <li><strong>Forgot Password Live Flow (<code>auth/forgot-password.php</code>):</strong> Nagse-save sa <code>password_reset_requests</code> na may SHA-256 <code>request_token</code> at nagpo-poll sa <code>check-reset-status.php</code> kada 3 segundo. Pag-dispatch ng Admin, may one-time popup ng temporary password o reset link sa screen ng guest.</li>
          <li><strong>Brute-Force Rate Limit:</strong> Hanggang 5 failed auth attempts per 15 minutes per IP sa login, register, forgot/reset password, at resend verification.</li>
        </ul>
      </div>
      <div class="card warn">
        <div class="card-title">Ano ang HINDI Kaya / Bawal sa USER?</div>
        <ul style="margin-bottom:0;">
          <li><strong>Hindi puwedeng mag-book nang walang KYC approval</strong> (maliban kung verified na ng Admin).</li>
          <li><strong>Hindi puwedeng mag-access</strong> ng <code>/admin/</code> o <code>/staff/</code> portals (ibabalik ng <code>requireRole()</code>).</li>
          <li><strong>Hindi puwedeng tumingin</strong> ng ibang guest's bookings, IDs, o payment receipts (naka-owner check sa <code>serve-file.php</code> at Slim API).</li>
          <li><strong>Hindi puwedeng mag-approve</strong> ng sariling bayad o magbago ng room price/status.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ==================== PAGE 6: PILLAR 3 - STAFF (FRONTDESK) ==================== -->
<div class="page">
  <div class="section-header staff-hdr">
    <div>
      <div class="section-h1">5. Pillar 3: STAFF (Frontdesk) &mdash; Operasyon, Kaya at Limitasyon</div>
      <div class="section-sub">Frontdesk Operations Server (:8082 / <code>frontend/staff/</code>) &mdash; Principle of Least Privilege</div>
    </div>
    <div class="section-pill">PILLAR 3 &bull; FRONTDESK STAFF</div>
  </div>

  <p>
    Ang <strong>Staff</strong> ang humahawak ng <strong>day-to-day hotel operations</strong>. Dinisenyo ito gamit ang <strong>Principle of Least Privilege</strong>: lahat ng kailangan sa pagtanggap ng bisita, pag-verify ng bayad sa frontdesk, pag-check-in/out, at housekeeping status ay bukas sa kanila, pero naka-lock ang sensitibong account, pricing, at system controls para sa Super Admin lang.
  </p>

  <div class="diagram-box">
    <div class="diagram-caption">Figure 5: Frontdesk Operational Workflow &amp; Room Housekeeping State Machine</div>
    <svg viewBox="0 0 740 185" xmlns="http://www.w3.org/2000/svg">
      <!-- Left: Daily Operations Flow -->
      <rect x="8" y="10" width="380" height="166" rx="7" fill="#ffffff" stroke="#059669" stroke-width="1.5"/>
      <rect x="8" y="10" width="380" height="20" rx="7" fill="#059669"/>
      <text x="198" y="23.5" text-anchor="middle" font-size="8" font-weight="800" fill="#ffffff">FRONTDESK DAILY OPERATIONS FLOW</text>

      <rect x="18" y="40" width="166" height="44" rx="5" fill="#f5f3ff" stroke="#8b5cf6"/>
      <text x="101" y="54" text-anchor="middle" font-size="7.3" font-weight="800" fill="#5b21b6">1. Read AI Shift Briefing</text>
      <text x="101" y="66" text-anchor="middle" font-size="6.4" fill="#334155">staff-assistant.php: arrivals,</text>
      <text x="101" y="76" text-anchor="middle" font-size="6.4" fill="#334155">departures, dirty &amp; unpaid list</text>

      <line x1="184" y1="62" x2="202" y2="62" stroke="#475569" stroke-width="1.4"/>
      <polygon points="198,58 198,66 205,62" fill="#475569"/>

      <rect x="205" y="40" width="172" height="44" rx="5" fill="#ecfdf5" stroke="#059669"/>
      <text x="291" y="54" text-anchor="middle" font-size="7.3" font-weight="800" fill="#065f46">2. Accept / Decline Booking</text>
      <text x="291" y="66" text-anchor="middle" font-size="6.4" fill="#334155">staff/dashboard.php: atomic</text>
      <text x="291" y="76" text-anchor="middle" font-size="6.4" fill="#334155">PENDING &rarr; CONFIRMED / REJECTED</text>

      <line x1="291" y1="84" x2="291" y2="104" stroke="#475569" stroke-width="1.4"/>
      <polygon points="287,100 295,100 291,107" fill="#475569"/>

      <rect x="205" y="107" width="172" height="56" rx="5" fill="#eff6ff" stroke="#0284c7"/>
      <text x="291" y="121" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">3. Delegated Payment Verify</text>
      <text x="291" y="133" text-anchor="middle" font-size="6.4" fill="#334155">staff/payments.php: inspect proof</text>
      <text x="291" y="144" text-anchor="middle" font-size="6.4" fill="#334155">ACID lock + auto-sync w/ Admin</text>
      <text x="291" y="155" text-anchor="middle" font-size="6.3" font-weight="700" fill="#0284c7">Verify / Reject + Guest Notif</text>

      <line x1="205" y1="135" x2="187" y2="135" stroke="#475569" stroke-width="1.4"/>
      <polygon points="191,131 191,139 184,135" fill="#475569"/>

      <rect x="18" y="107" width="166" height="56" rx="5" fill="#fffbeb" stroke="#d97706"/>
      <text x="101" y="121" text-anchor="middle" font-size="7.3" font-weight="800" fill="#b45309">4. Check-In / Check-Out</text>
      <text x="101" y="133" text-anchor="middle" font-size="6.4" fill="#334155">staff/check-in.php: check conflict,</text>
      <text x="101" y="144" text-anchor="middle" font-size="6.4" fill="#334155">payment (or Desk Override), KYC</text>
      <text x="101" y="155" text-anchor="middle" font-size="6.3" font-weight="700" fill="#b45309">&rarr; Updates Room Occupancy</text>

      <!-- Right: Room State Machine -->
      <rect x="402" y="10" width="330" height="166" rx="7" fill="#ffffff" stroke="#0f172a" stroke-width="1.5"/>
      <rect x="402" y="10" width="330" height="20" rx="7" fill="#0f172a"/>
      <text x="567" y="23.5" text-anchor="middle" font-size="8" font-weight="800" fill="#fde68a">ROOM HOUSEKEEPING STATE MACHINE (rooms-status.php)</text>

      <!-- States -->
      <rect x="422" y="48" width="95" height="36" rx="6" fill="#d1fae5" stroke="#059669" stroke-width="1.6"/>
      <text x="469.5" y="64" text-anchor="middle" font-size="8" font-weight="800" fill="#065f46">AVAILABLE</text>
      <text x="469.5" y="76" text-anchor="middle" font-size="6.3" fill="#047857">Ready for Guest</text>

      <rect x="615" y="48" width="98" height="36" rx="6" fill="#dbeafe" stroke="#2563eb" stroke-width="1.6"/>
      <text x="664" y="64" text-anchor="middle" font-size="8" font-weight="800" fill="#1e40af">OCCUPIED</text>
      <text x="664" y="76" text-anchor="middle" font-size="6.3" fill="#1d4ed8">Guest Checked In</text>

      <rect x="615" y="122" width="98" height="36" rx="6" fill="#fef3c7" stroke="#d97706" stroke-width="1.6"/>
      <text x="664" y="138" text-anchor="middle" font-size="8" font-weight="800" fill="#92400e">DIRTY</text>
      <text x="664" y="150" text-anchor="middle" font-size="6.3" fill="#b45309">Needs Cleaning</text>

      <rect x="422" y="122" width="95" height="36" rx="6" fill="#fee2e2" stroke="#dc2626" stroke-width="1.6"/>
      <text x="469.5" y="138" text-anchor="middle" font-size="8" font-weight="800" fill="#991b1b">MAINTENANCE</text>
      <text x="469.5" y="150" text-anchor="middle" font-size="6.3" fill="#b91c1c">Blocked / Repair</text>

      <!-- Transitions -->
      <line x1="517" y1="62" x2="611" y2="62" stroke="#0f172a" stroke-width="1.5"/>
      <polygon points="607,58 607,66 614,62" fill="#0f172a"/>
      <text x="565" y="57" text-anchor="middle" font-size="6.4" font-weight="700" fill="#1e293b">Check-In</text>

      <line x1="664" y1="84" x2="664" y2="118" stroke="#0f172a" stroke-width="1.5"/>
      <polygon points="660,114 668,114 664,121" fill="#0f172a"/>
      <text x="698" y="105" text-anchor="middle" font-size="6.3" font-weight="700" fill="#92400e">Check-Out</text>

      <line x1="615" y1="132" x2="514" y2="85" stroke="#059669" stroke-width="1.5"/>
      <polygon points="517,82 513,89 509,83" fill="#059669"/>
      <text x="556" y="103" text-anchor="middle" font-size="6.3" font-weight="700" fill="#047857">Cleaned</text>

      <line x1="469" y1="84" x2="469" y2="118" stroke="#dc2626" stroke-width="1.3"/>
      <polygon points="465,114 473,114 469,121" fill="#dc2626"/>
      <polygon points="465,88 473,88 469,81" fill="#059669"/>
      <text x="438" y="104" text-anchor="middle" font-size="6.1" fill="#475569">Repair / Fix</text>
    </svg>
  </div>

  <div class="grid-2">
    <div class="col-2">
      <div class="card staff">
        <div class="card-title">Lahat ng Kaya at Pages ng STAFF</div>
        <ul>
          <li><strong>Frontdesk Dashboard (<code>staff/dashboard.php</code>):</strong> May live stats, AI Shift Briefing card, pending bookings queue (Accept/Decline with reason), at quick payment verification queue.</li>
          <li><strong>Payment Verification (<code>staff/payments.php</code>):</strong> Delegated power para i-inspect ang GCash/Maya screenshot proofs (via <code>serve-file.php</code>) at i-verify o i-reject. Gumagamit ng <code>processPaymentDecision()</code> na naka-sync sa Admin para hindi magka-double approval.</li>
          <li><strong>Check-In &amp; Check-Out (<code>staff/check-in.php</code>):</strong>
            <ul>
              <li>Bago mag-check-in: bawal kung <code>maintenance</code> ang room, may nakatirang guest sa non-dorm room, o may overlapping booking.</li>
              <li>Chine-check ang verified payment (may optional <em>Desk Override</em> checkbox) at pinapakita ang KYC status ng bisita.</li>
              <li>Sa check-out: ginagamit ang <code>releaseRoomOccupancyIfIdle()</code> &mdash; kung dormitory room at may iba pang naka-check-in, mananatiling <code>occupied</code>; kung bakante na, magiging <code>dirty</code>.</li>
            </ul>
          </li>
          <li><strong>Room Status (<code>staff/rooms-status.php</code>):</strong> Puwedeng magpalit sa pagitan ng <code>available</code>, <code>dirty</code>, at <code>maintenance</code> (bawal baguhin habang may active <code>checked_in</code> guest).</li>
          <li><strong>Personal Account (<code>staff/account.php</code>):</strong> Magpalit ng sariling password (at mag-clear ng Admin temp password flag).</li>
        </ul>
      </div>
    </div>
    <div class="col-2">
      <div class="card warn">
        <div class="card-title">Mahigpit na Bawal / Restricted sa STAFF</div>
        <ul>
          <li><strong>Walang Public Signup:</strong> Nakasarado ang <code>auth/register-staff.php</code> (302 redirect sa <code>staff/login.php</code>). Admin lang ang pwedeng gumawa ng Staff account.</li>
          <li><strong>Walang User Account Management:</strong> Kapag binuksan ng Staff ang <code>staff/guests.php</code>, agad silang ibinabalik sa <code>staff/dashboard.php</code> na may warning. Hindi sila puwedeng mag-ban, mag-delete, mag-edit ng guest profile, o mag-reset ng password ng iba.</li>
          <li><strong>Walang Room Inventory / Pricing Control:</strong> Hindi puwedeng magdagdag, mag-delete, o magpalit ng presyo ng kwarto.</li>
          <li><strong>Walang AI Config, Audit Logs, at Settings:</strong> Hindi nakikita o nabubura ang <code>audit_logs</code>, <code>ai_logs</code>, NVIDIA API keys, at GCash/Maya global account settings.</li>
        </ul>
      </div>
      <div class="card soft">
        <div class="card-title">Audit &amp; Accountability ng Staff</div>
        <p style="margin-bottom:0;">
          Lahat ng kilos ng Staff (pag-accept/decline ng booking, pag-verify/reject ng payment, check-in/out, room status update, pagpalit ng password) ay awtomatikong nire-record sa <code>audit_logs</code> sa ilalim ng <code>Staff Module</code> / <code>Staff Auth</code> at nakikita nang real-time ng Super Admin.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ==================== PAGE 7: PILLAR 4 - ADMIN (SUPER ADMIN) ==================== -->
<div class="page">
  <div class="section-header admin-hdr">
    <div>
      <div class="section-h1">6. Pillar 4: ADMIN (Super Admin) &mdash; Master Control &amp; Governance</div>
      <div class="section-sub">Super Admin Server (:8081 / <code>frontend/admin/</code>) &mdash; 9 Dedicated Control Modules</div>
    </div>
    <div class="section-pill">PILLAR 4 &bull; SUPER ADMIN</div>
  </div>

  <p>
    Ang <strong>Super Admin</strong> ang may pinakamataas na awtoridad sa buong sistema (para sa hotel owner o general manager). Direct Login Only sa <code>admin/login.php</code> (<code>MH_ADMIN_SESS</code>) at may kabuuang sakop sa finances, inventory, tao, AI, seguridad, at configuration.
  </p>

  <div class="diagram-box">
    <div class="diagram-caption">Figure 6: Super Admin Master Control Hub (9 Modules &amp; Connected Subsystems)</div>
    <svg viewBox="0 0 740 165" xmlns="http://www.w3.org/2000/svg">
      <!-- Top center Admin Core -->
      <rect x="255" y="8" width="230" height="34" rx="6" fill="#0f172a" stroke="#d97706" stroke-width="2"/>
      <text x="370" y="23" text-anchor="middle" font-size="8.5" font-weight="800" fill="#fde68a">SUPER ADMIN CONTROL HUB (:8081)</text>
      <text x="370" y="35" text-anchor="middle" font-size="6.8" fill="#cbd5e1">Direct Login Only &bull; Full System Authority</text>

      <!-- Connecting trunk -->
      <line x1="370" y1="42" x2="370" y2="54" stroke="#d97706" stroke-width="1.6"/>
      <line x1="90" y1="54" x2="650" y2="54" stroke="#d97706" stroke-width="1.6"/>
      <line x1="90" y1="54" x2="90" y2="66" stroke="#d97706" stroke-width="1.5"/>
      <line x1="276" y1="54" x2="276" y2="66" stroke="#d97706" stroke-width="1.5"/>
      <line x1="464" y1="54" x2="464" y2="66" stroke="#d97706" stroke-width="1.5"/>
      <line x1="650" y1="54" x2="650" y2="66" stroke="#d97706" stroke-width="1.5"/>

      <!-- 4 Domain Boxes -->
      <rect x="10" y="66" width="166" height="90" rx="6" fill="#fffbeb" stroke="#d97706" stroke-width="1.5"/>
      <text x="93" y="81" text-anchor="middle" font-size="7.5" font-weight="800" fill="#92400e">A. Operations &amp; Revenue</text>
      <text x="18" y="95" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">dashboard.php</tspan> (KPI + AI BI)</text>
      <text x="18" y="107" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">rooms.php</tspan> (CRUD + Photos)</text>
      <text x="18" y="119" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">bookings.php</tspan> (Override + Audit)</text>
      <text x="18" y="131" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">payments.php</tspan> (Side-by-side</text>
      <text x="24" y="141" font-size="6.5" fill="#334155">Maya/GCash verification)</text>

      <rect x="190" y="66" width="172" height="90" rx="6" fill="#eff6ff" stroke="#0284c7" stroke-width="1.5"/>
      <text x="276" y="81" text-anchor="middle" font-size="7.5" font-weight="800" fill="#0369a1">B. People, KYC &amp; Resets</text>
      <text x="198" y="95" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">users.php</tspan>: Create Walk-in,</text>
      <text x="204" y="106" font-size="6.5" fill="#334155">Edit, Ban/Unban, Delete</text>
      <text x="198" y="118" font-size="6.6" fill="#334155">&bull; Approve/Reject KYC ID Docs</text>
      <text x="198" y="130" font-size="6.6" fill="#334155">&bull; Live Password Reset Queue</text>
      <text x="198" y="142" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">staff.php</tspan>: Create/Manage Staff</text>

      <rect x="376" y="66" width="174" height="90" rx="6" fill="#f5f3ff" stroke="#7c3aed" stroke-width="1.5"/>
      <text x="463" y="81" text-anchor="middle" font-size="7.5" font-weight="800" fill="#5b21b6">C. AI Engine Governance</text>
      <text x="384" y="95" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">ai-config.php</tspan>: Enable/Disable</text>
      <text x="384" y="107" font-size="6.6" fill="#334155">&bull; Switch NVIDIA Model Preset</text>
      <text x="384" y="119" font-size="6.6" fill="#334155">&bull; Update/Clear API Key</text>
      <text x="384" y="131" font-size="6.6" fill="#334155">&bull; Live Connection Latency Probe</text>
      <text x="384" y="143" font-size="6.6" fill="#334155">&bull; Inspect Flagged AI Threat Logs</text>

      <rect x="564" y="66" width="166" height="90" rx="6" fill="#ecfdf5" stroke="#059669" stroke-width="1.5"/>
      <text x="647" y="81" text-anchor="middle" font-size="7.5" font-weight="800" fill="#065f46">D. Security &amp; Settings</text>
      <text x="572" y="95" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">audit-logs.php</tspan>: Immutable</text>
      <text x="578" y="106" font-size="6.5" fill="#334155">filterable trail (User/Staff/</text>
      <text x="578" y="117" font-size="6.5" fill="#334155">Admin/Failed Attempts)</text>
      <text x="572" y="129" font-size="6.6" fill="#334155">&bull; <tspan font-weight="700">settings.php</tspan>: Hotel Info,</text>
      <text x="578" y="140" font-size="6.5" fill="#334155">Check-in/out &amp; Maya/GCash</text>
    </svg>
  </div>

  <h2>Detalyadong Paliwanag ng Mga Ginagawa ng ADMIN</h2>
  <table>
    <thead>
      <tr>
        <th style="width:22%;">Admin Module</th>
        <th style="width:52%;">Ano ang Ginagawa at Kaya ng Admin</th>
        <th style="width:26%;">Connected Tables / Files</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Executive Dashboard</strong><br><code>admin/dashboard.php</code></td>
        <td>Tinitingnan ang Total Verified Revenue, Total Bookings, Guests/Staff counts, separate activity feeds (Staff vs. User vs. All), Security Threat Alerts, at <strong>Marvi Executive AI Digest</strong> (<code>admin-insights.php</code>).</td>
        <td><code>payments</code>, <code>bookings</code>, <code>users</code>, <code>audit_logs</code>, <code>ai_logs</code></td>
      </tr>
      <tr>
        <td><strong>Manage Rooms</strong><br><code>admin/rooms.php</code></td>
        <td>Tanging role na puwedeng mag-<strong>Add, Edit, at Delete</strong> ng rooms/dormitory beds, magtakda ng presyo per night/weekend rate, capacity, amenities, at mag-upload ng room thumbnails (validated image upload).</td>
        <td><code>rooms</code>, <code>room_types</code>, <code>uploads/rooms/</code></td>
      </tr>
      <tr>
        <td><strong>All Bookings &amp; Payments</strong><br><code>bookings.php</code> / <code>payments.php</code></td>
        <td>Kumpletong oversight ng lahat ng reservations (may status override + reason), side-by-side payment inspection modal, at ACID-locked verify/reject na may auto-sync sa Staff decisions.</td>
        <td><code>bookings</code>, <code>payments</code>, <code>notifications</code>, <code>serve-file.php</code></td>
      </tr>
      <tr>
        <td><strong>User Accounts &amp; KYC</strong><br><code>admin/users.php</code></td>
        <td>Gumagawa ng walk-in guest account, nag-e-edit/ban/delete ng users, nag-i-inspect at nag-a-approve/reject ng <strong>KYC Valid IDs</strong>, at nagdi-dispatch ng <strong>Temporary Password o Reset Link</strong> (live popup sa guest).</td>
        <td><code>users</code>, <code>password_reset_requests</code>, <code>uploads/documents/</code></td>
      </tr>
      <tr>
        <td><strong>Staff Provisioning</strong><br><code>admin/staff.php</code></td>
        <td>Tanging paraan para makagawa ng Frontdesk Staff account (Add, Edit, Activate/Deactivate, Reset Password, Delete). Walang self-registration ang Staff.</td>
        <td><code>users</code> (<code>role='staff'</code>), <code>audit_logs</code></td>
      </tr>
      <tr>
        <td><strong>AI Config &amp; Threat Logs</strong><br><code>admin/ai-config.php</code></td>
        <td>I-on/off ang Live AI (vs. Local FAQ fallback), pumili ng NVIDIA NIM model ID, mag-update ng API key, mag-test ng connection latency, maglinis ng routine logs (&gt;7 days), at mag-monitor ng flagged security threats.</td>
        <td><code>settings</code> (<code>ai_*</code>), <code>ai_logs</code>, <code>nvidia-client.php</code></td>
      </tr>
      <tr>
        <td><strong>Audit &amp; System Settings</strong><br><code>audit-logs.php</code> / <code>settings.php</code></td>
        <td>Sinusuri ang immutable <code>audit_logs</code> (by User, Staff, Admin, o Failed/Blocked attempts). Sa <code>settings.php</code>, ina-update ang <strong>Hotel Info &amp; Check-in/Out Policies</strong> at <strong>Maya / GCash Payment Accounts</strong>.</td>
        <td><code>audit_logs</code>, <code>settings</code></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ==================== PAGE 8: END-TO-END CROSS-ROLE FLOWS ==================== -->
<div class="page">
  <div class="section-header">
    <div>
      <div class="section-h1">7. End-to-End Cross-Role Connection Scenarios</div>
      <div class="section-sub">Tatlong kongkretong senaryo kung paano sabay-sabay kumikilos at nagko-connect ang AI, USER, STAFF, at ADMIN</div>
    </div>
    <div class="section-pill">PROCESS SEQUENCES</div>
  </div>

  <!-- Scenario 1 -->
  <div class="diagram-box">
    <div class="diagram-caption">Scenario A: AI-Assisted Booking &rarr; GCash/Maya Payment &rarr; Frontdesk Stay &rarr; Admin Analytics</div>
    <svg viewBox="0 0 740 124" xmlns="http://www.w3.org/2000/svg">
      <rect x="8" y="10" width="112" height="96" rx="5" fill="#eff6ff" stroke="#0284c7" stroke-width="1.4"/>
      <text x="64" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">1. USER asks AI</text>
      <text x="64" y="39" text-anchor="middle" font-size="6.4" fill="#334155">"1k budget room?"</text>
      <text x="64" y="51" text-anchor="middle" font-size="6.4" fill="#334155">Marvi checks MySQL</text>
      <text x="64" y="63" text-anchor="middle" font-size="6.4" fill="#334155">&amp; renders clickable</text>
      <text x="64" y="75" text-anchor="middle" font-size="6.4" font-weight="700" fill="#5b21b6">[[BOOK_ROOM:1]]</text>
      <text x="64" y="92" text-anchor="middle" font-size="6.1" fill="#475569">chatbot.php (SSE)</text>

      <polygon points="122,54 128,58 122,62" fill="#475569"/>

      <rect x="131" y="10" width="114" height="96" rx="5" fill="#fffbeb" stroke="#d97706" stroke-width="1.4"/>
      <text x="188" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#b45309">2. ADMIN KYC Gate</text>
      <text x="188" y="39" text-anchor="middle" font-size="6.4" fill="#334155">User uploads Valid ID</text>
      <text x="188" y="51" text-anchor="middle" font-size="6.4" fill="#334155">sa profile.php.</text>
      <text x="188" y="63" text-anchor="middle" font-size="6.4" fill="#334155">Admin verifies via</text>
      <text x="188" y="75" text-anchor="middle" font-size="6.4" font-weight="700" fill="#b45309">admin/users.php</text>
      <text x="188" y="92" text-anchor="middle" font-size="6.1" fill="#475569">&rarr; Guest Notified</text>

      <polygon points="247,54 253,58 247,62" fill="#475569"/>

      <rect x="256" y="10" width="114" height="96" rx="5" fill="#eff6ff" stroke="#0284c7" stroke-width="1.4"/>
      <text x="313" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">3. USER Books &amp; Pays</text>
      <text x="313" y="39" text-anchor="middle" font-size="6.4" fill="#334155">booking.php locks row</text>
      <text x="313" y="51" text-anchor="middle" font-size="6.4" fill="#334155">(FOR UPDATE) + checks</text>
      <text x="313" y="63" text-anchor="middle" font-size="6.4" fill="#334155">date overlap. Uploads</text>
      <text x="313" y="75" text-anchor="middle" font-size="6.4" font-weight="700" fill="#0284c7">GCash/Maya Proof</text>
      <text x="313" y="92" text-anchor="middle" font-size="6.1" fill="#475569">bookings + payments</text>

      <polygon points="372,54 378,58 372,62" fill="#475569"/>

      <rect x="381" y="10" width="116" height="96" rx="5" fill="#ecfdf5" stroke="#059669" stroke-width="1.4"/>
      <text x="439" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#065f46">4. STAFF Operations</text>
      <text x="439" y="39" text-anchor="middle" font-size="6.4" fill="#334155">AI Shift Briefing alerts</text>
      <text x="439" y="51" text-anchor="middle" font-size="6.4" fill="#334155">Staff. Staff verifies</text>
      <text x="439" y="63" text-anchor="middle" font-size="6.4" fill="#334155">payment (ACID) &amp; does</text>
      <text x="439" y="75" text-anchor="middle" font-size="6.4" font-weight="700" fill="#047857">Check-In &rarr; Check-Out</text>
      <text x="439" y="92" text-anchor="middle" font-size="6.1" fill="#475569">Room: occupied &rarr; dirty</text>

      <polygon points="499,54 505,58 499,62" fill="#475569"/>

      <rect x="508" y="10" width="106" height="96" rx="5" fill="#eff6ff" stroke="#0284c7" stroke-width="1.4"/>
      <text x="561" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">5. USER Reviews</text>
      <text x="561" y="39" text-anchor="middle" font-size="6.4" fill="#334155">Receives Check-out</text>
      <text x="561" y="51" text-anchor="middle" font-size="6.4" fill="#334155">notification linking</text>
      <text x="561" y="63" text-anchor="middle" font-size="6.4" fill="#334155">to reviews.php.</text>
      <text x="561" y="75" text-anchor="middle" font-size="6.4" font-weight="700" fill="#0284c7">Submits Star Rating</text>
      <text x="561" y="92" text-anchor="middle" font-size="6.1" fill="#475569">Saved in reviews</text>

      <polygon points="616,54 622,58 616,62" fill="#475569"/>

      <rect x="625" y="10" width="107" height="96" rx="5" fill="#fffbeb" stroke="#d97706" stroke-width="1.4"/>
      <text x="678" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#b45309">6. ADMIN BI + Audit</text>
      <text x="678" y="39" text-anchor="middle" font-size="6.4" fill="#334155">Verified revenue &amp;</text>
      <text x="678" y="51" text-anchor="middle" font-size="6.4" fill="#334155">occupancy feed into</text>
      <text x="678" y="63" text-anchor="middle" font-size="6.4" fill="#334155">admin-insights.php</text>
      <text x="678" y="75" text-anchor="middle" font-size="6.4" font-weight="700" fill="#5b21b6">+ Executive AI Digest</text>
      <text x="678" y="92" text-anchor="middle" font-size="6.1" fill="#475569">+ audit_logs trail</text>
    </svg>
  </div>

  <!-- Scenario 2 -->
  <div class="diagram-box">
    <div class="diagram-caption">Scenario B: Live Admin-Assisted Password Reset Popup Flow (Zero Email-Delay Blocker)</div>
    <svg viewBox="0 0 740 104" xmlns="http://www.w3.org/2000/svg">
      <rect x="10" y="12" width="165" height="80" rx="5" fill="#eff6ff" stroke="#0284c7" stroke-width="1.4"/>
      <text x="92" y="27" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">1. USER Requests Reset</text>
      <text x="92" y="41" text-anchor="middle" font-size="6.4" fill="#334155">Enters email in forgot-password.php.</text>
      <text x="92" y="53" text-anchor="middle" font-size="6.4" fill="#334155">Inserts row sa password_reset_requests</text>
      <text x="92" y="65" text-anchor="middle" font-size="6.4" fill="#334155">with SHA-256 request_token.</text>
      <text x="92" y="79" text-anchor="middle" font-size="6.3" font-weight="700" fill="#0284c7">Polls check-reset-status.php (3s)</text>

      <polygon points="177,48 183,52 177,56" fill="#475569"/>

      <rect x="186" y="12" width="175" height="80" rx="5" fill="#fffbeb" stroke="#d97706" stroke-width="1.4"/>
      <text x="273" y="27" text-anchor="middle" font-size="7.3" font-weight="800" fill="#b45309">2. ADMIN Live Alert &amp; Dispatch</text>
      <text x="273" y="41" text-anchor="middle" font-size="6.4" fill="#334155">Sidebar banner (#adminResetAlertBanner)</text>
      <text x="273" y="53" text-anchor="middle" font-size="6.4" fill="#334155">alerts Admin. Sa users.php?tab=resets,</text>
      <text x="273" y="65" text-anchor="middle" font-size="6.4" fill="#334155">Admin clicks Send Temp Password o</text>
      <text x="273" y="79" text-anchor="middle" font-size="6.3" font-weight="700" fill="#b45309">Send Reset Link (1-hr expiry)</text>

      <polygon points="363,48 369,52 363,56" fill="#475569"/>

      <rect x="372" y="12" width="175" height="80" rx="5" fill="#ecfdf5" stroke="#059669" stroke-width="1.4"/>
      <text x="459" y="27" text-anchor="middle" font-size="7.3" font-weight="800" fill="#065f46">3. USER Receives Live Popup</text>
      <text x="459" y="41" text-anchor="middle" font-size="6.4" fill="#334155">check-reset-status.php detects completed</text>
      <text x="459" y="53" text-anchor="middle" font-size="6.4" fill="#334155">row and displays 1-time masked popup</text>
      <text x="459" y="65" text-anchor="middle" font-size="6.4" fill="#334155">sa screen ng guest (15-min window)</text>
      <text x="459" y="79" text-anchor="middle" font-size="6.3" font-weight="700" fill="#047857">+ Optional SMTP email if configured</text>

      <polygon points="549,48 555,52 549,56" fill="#475569"/>

      <rect x="558" y="12" width="172" height="80" rx="5" fill="#f8fafc" stroke="#0f172a" stroke-width="1.4"/>
      <text x="644" y="27" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0f172a">4. Acknowledge &amp; Audit</text>
      <text x="644" y="41" text-anchor="middle" font-size="6.4" fill="#334155">POST action=acknowledge updates</text>
      <text x="644" y="53" text-anchor="middle" font-size="6.4" fill="#334155">user_notified_at. Admin UI switches</text>
      <text x="644" y="65" text-anchor="middle" font-size="6.4" fill="#334155">from "Waiting" &rarr; "Viewed by user".</text>
      <text x="644" y="79" text-anchor="middle" font-size="6.3" font-weight="700" fill="#0f172a">Staff has ZERO access to this.</text>
    </svg>
  </div>

  <!-- Scenario 3 -->
  <div class="diagram-box">
    <div class="diagram-caption">Scenario C: AI Bad-Intent Security Detection &rarr; Admin Threat Monitoring Loop</div>
    <svg viewBox="0 0 740 94" xmlns="http://www.w3.org/2000/svg">
      <rect x="10" y="10" width="168" height="72" rx="5" fill="#eff6ff" stroke="#0284c7" stroke-width="1.4"/>
      <text x="94" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#0369a1">1. Suspicious Prompt</text>
      <text x="94" y="40" text-anchor="middle" font-size="6.4" fill="#334155">Visitor/User sends exploit or</text>
      <text x="94" y="52" text-anchor="middle" font-size="6.4" fill="#334155">SQL injection attempt to</text>
      <text x="94" y="64" text-anchor="middle" font-size="6.4" font-weight="700" fill="#0284c7">backend/ai/chatbot.php</text>

      <polygon points="180,42 186,46 180,50" fill="#dc2626"/>

      <rect x="189" y="10" width="180" height="72" rx="5" fill="#fef2f2" stroke="#dc2626" stroke-width="1.5"/>
      <text x="279" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#991b1b">2. AI Guardrail Blocks Early</text>
      <text x="279" y="40" text-anchor="middle" font-size="6.4" fill="#334155">Matched vs bad-intent-keywords.json</text>
      <text x="279" y="52" text-anchor="middle" font-size="6.4" fill="#334155">BEFORE calling NVIDIA NIM API.</text>
      <text x="279" y="64" text-anchor="middle" font-size="6.4" font-weight="700" fill="#b91c1c">Returns Warning Alert (is_alert: true)</text>

      <polygon points="371,42 377,46 371,50" fill="#dc2626"/>

      <rect x="380" y="10" width="168" height="72" rx="5" fill="#f5f3ff" stroke="#7c3aed" stroke-width="1.4"/>
      <text x="464" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#5b21b6">3. Dual Database Logging</text>
      <text x="464" y="40" text-anchor="middle" font-size="6.4" fill="#334155">Inserts ai_logs (flagged=1,</text>
      <text x="464" y="52" text-anchor="middle" font-size="6.4" fill="#334155">intent='security_alert') +</text>
      <text x="464" y="64" text-anchor="middle" font-size="6.4" font-weight="700" fill="#5b21b6">audit_logs (AI Module + IP)</text>

      <polygon points="550,42 556,46 550,50" fill="#d97706"/>

      <rect x="559" y="10" width="171" height="72" rx="5" fill="#fffbeb" stroke="#d97706" stroke-width="1.4"/>
      <text x="644" y="25" text-anchor="middle" font-size="7.3" font-weight="800" fill="#b45309">4. ADMIN Threat Review</text>
      <text x="644" y="40" text-anchor="middle" font-size="6.4" fill="#334155">Sidebar badge alerts Admin.</text>
      <text x="644" y="52" text-anchor="middle" font-size="6.4" fill="#334155">Inspects in ai-config.php?filter=flagged.</text>
      <text x="644" y="64" text-anchor="middle" font-size="6.4" font-weight="700" fill="#b45309">Flagged logs cannot be purged!</text>
    </svg>
  </div>

  <div class="card gold">
    <div class="card-title">Bakit Importanteng Magkakahiwalay Pero Magkakakonekta Sila?</div>
    <p style="margin-bottom:0;">
      Nakikita sa tatlong flow sa itaas ang design principle ng system: <strong>Decoupled Portals, Single Source of Truth</strong>. Kahit magkakaiba ang login server at session cookie ng User, Staff, at Admin, iisang MySQL database at Slim 4/Core PHP layer ang kanilang sinusulatan at binabasa &mdash; kaya ang bawat action ng isa (hal. payment upload ng User, check-out ng Staff, KYC approval ng Admin, o alert ng AI) ay agad sumasalamin sa kabilang role nang walang conflict.
    </p>
  </div>
</div>

<!-- ==================== PAGE 9: TECHNICAL CONNECTION REFERENCE ==================== -->
<div class="page">
  <div class="section-header">
    <div>
      <div class="section-h1">8. Technical Connection Reference (Database &amp; API Matrix)</div>
      <div class="section-sub">Eksaktong koneksyon ng AI, USER, STAFF, at ADMIN sa 14 MySQL tables at backend endpoints</div>
    </div>
    <div class="section-pill">DB &amp; API CONNECTIONS</div>
  </div>

  <h2>Role &times; 14 MySQL Tables Read/Write Matrix</h2>
  <table>
    <thead>
      <tr>
        <th style="width:20%;">Table Name</th>
        <th style="width:28%;">Purpose</th>
        <th style="width:13%;">AI (Marvi)</th>
        <th style="width:13%;">USER</th>
        <th style="width:13%;">STAFF</th>
        <th style="width:13%;">ADMIN</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>users</code></td><td>Accounts (user/staff/admin), KYC IDs &amp; tokens</td><td><span class="badge b-ro">Read</span> KYC/name</td><td><span class="badge b-rw">R/W</span> Own profile</td><td><span class="badge b-ro">Read</span> + Own pass</td><td><span class="badge b-rw">Full</span> All roles</td></tr>
      <tr><td><code>room_types</code></td><td>Accommodation categories</td><td><span class="badge b-ro">Read</span></td><td><span class="badge b-ro">Read</span></td><td><span class="badge b-ro">Read</span></td><td><span class="badge b-rw">R/W</span></td></tr>
      <tr><td><code>rooms</code></td><td>Room inventory, prices, amenities, status</td><td><span class="badge b-ro">Read</span> Catalog</td><td><span class="badge b-ro">Read</span></td><td><span class="badge b-rw">Status</span> only</td><td><span class="badge b-rw">Full CRUD</span></td></tr>
      <tr><td><code>bookings</code></td><td>Reservations, dates, lifecycle timestamps</td><td><span class="badge b-ro">Read</span> Live state</td><td><span class="badge b-rw">R/W</span> Own only</td><td><span class="badge b-rw">Ops</span> Accept/In/Out</td><td><span class="badge b-rw">Full</span> Override</td></tr>
      <tr><td><code>payments</code></td><td>Maya/GCash proofs, Ref #, verification</td><td><span class="badge b-ro">Read</span> Analytics</td><td><span class="badge b-rw">R/W</span> Submit</td><td><span class="badge b-rw">Verify/Reject</span></td><td><span class="badge b-rw">Verify/Reject</span></td></tr>
      <tr><td><code>reviews</code></td><td>Guest star ratings &amp; feedback</td><td><span class="badge b-no">None</span></td><td><span class="badge b-rw">R/W</span> Own stays</td><td><span class="badge b-no">None</span></td><td><span class="badge b-ro">Read</span></td></tr>
      <tr><td><code>notifications</code></td><td>In-app alerts per user</td><td><span class="badge b-no">None</span></td><td><span class="badge b-rw">Read/Mark</span></td><td><span class="badge b-rw">Trigger</span> To guest</td><td><span class="badge b-rw">Trigger</span> To guest</td></tr>
      <tr><td><code>audit_logs</code></td><td>Immutable actor/module/IP security trail</td><td><span class="badge b-rw">Write</span> Alerts</td><td><span class="badge b-rw">Auto-Log</span></td><td><span class="badge b-rw">Auto-Log</span></td><td><span class="badge b-ro">Read Only</span></td></tr>
      <tr><td><code>ai_logs</code></td><td>Marvi AI chats, intent, flagged threats</td><td><span class="badge b-rw">Write</span></td><td><span class="badge b-no">None</span></td><td><span class="badge b-no">None</span></td><td><span class="badge b-rw">Read/Clean</span> (&gt;7d)</td></tr>
      <tr><td><code>settings</code></td><td>Hotel info, Maya/GCash, AI model/key</td><td><span class="badge b-ro">Read</span></td><td><span class="badge b-ro">Read</span> Public</td><td><span class="badge b-ro">Read</span> Public</td><td><span class="badge b-rw">Full R/W</span></td></tr>
      <tr><td><code>contact_messages</code></td><td>Public contact form inquiries</td><td><span class="badge b-no">None</span></td><td><span class="badge b-rw">Insert</span></td><td><span class="badge b-no">None</span></td><td><span class="badge b-ro">Read</span></td></tr>
      <tr><td><code>rate_limits</code></td><td>Sliding-window auth &amp; AI rate throttling</td><td><span class="badge b-rw">R/W</span></td><td><span class="badge b-rw">Auto</span> Check</td><td><span class="badge b-rw">Auto</span> Login</td><td><span class="badge b-rw">Auto</span> Login</td></tr>
      <tr><td><code>password_reset_requests</code></td><td>Live Admin-assisted password reset queue</td><td><span class="badge b-no">None</span></td><td><span class="badge b-rw">Request/Poll</span></td><td><span class="badge b-no">None</span></td><td><span class="badge b-rw">Dispatch</span></td></tr>
      <tr><td><code>schema_migrations</code></td><td>SQL migration version ledger</td><td><span class="badge b-no">None</span></td><td><span class="badge b-no">None</span></td><td><span class="badge b-no">None</span></td><td><span class="badge b-ro">CLI Runner</span></td></tr>
    </tbody>
  </table>

  <h2>Backend API, AI &amp; Core Endpoint Connections</h2>
  <table>
    <thead>
      <tr>
        <th style="width:31%;">Endpoint / Route</th>
        <th style="width:12%;">Method</th>
        <th style="width:24%;">Allowed Roles &amp; Middleware</th>
        <th style="width:33%;">Function &amp; Connection</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>/backend/api/v1/health</code> &amp; <code>/auth/status</code></td><td>GET</td><td>Public (All Portals)</td><td>API liveness &amp; active portal session check</td></tr>
      <tr><td><code>/backend/api/v1/rooms</code>, <code>/rooms/{id}</code>, <code>/availability</code></td><td>GET</td><td>Public (All Portals)</td><td>Live catalog, room details, &amp; date availability check</td></tr>
      <tr><td><code>/backend/api/v1/bookings</code> &amp; <code>/payments</code></td><td>GET</td><td><code>RoleAuth(['user','staff','admin'])</code></td><td>User gets own records; Staff/Admin get full records</td></tr>
      <tr><td><code>/backend/api/v1/bookings/{id}/cancel</code></td><td>POST</td><td><code>RoleAuth(['user'])</code> + <code>CSRF</code></td><td>Guest cancels own pending/confirmed booking</td></tr>
      <tr><td><code>/backend/api/v1/payments/{id}/decision</code></td><td>POST</td><td><code>RoleAuth(['staff','admin'])</code> + <code>CSRF</code></td><td>ACID-locked verify/reject payment &rarr; updates booking + notif</td></tr>
      <tr><td><code>/backend/api/v1/dashboard/metrics</code></td><td>GET</td><td><code>RoleAuth(['user','staff','admin'])</code></td><td>Role-scoped live KPIs &amp; operational metrics</td></tr>
      <tr><td><code>/backend/ai/chatbot.php</code></td><td>POST / SSE</td><td>Public/User + Rate Limit</td><td>Streaming Marvi AI chat, Room Cards, &amp; security filter</td></tr>
      <tr><td><code>/backend/ai/staff-assistant.php</code></td><td>GET</td><td>Staff &amp; Admin (<code>releaseSessionLock</code>)</td><td>Frontdesk Shift Operational Briefing (5-min cache)</td></tr>
      <tr><td><code>/backend/ai/admin-insights.php</code></td><td>GET</td><td>Admin Only (<code>releaseSessionLock</code>)</td><td>Executive BI Digest (Revenue, Occupancy, Backlog)</td></tr>
      <tr><td><code>/backend/endpoints/serve-file.php</code></td><td>GET</td><td>Owner / Staff / Admin RBAC</td><td>Streams protected KYC IDs, payment proofs, &amp; avatars</td></tr>
      <tr><td><code>/backend/endpoints/check-reset-status.php</code></td><td>GET / POST</td><td>Guest Session Watch + CSRF</td><td>Polls &amp; acknowledges Admin-dispatched reset popup</td></tr>
    </tbody>
  </table>
</div>

<!-- ==================== PAGE 10: TAGLISH CHEAT SHEET ==================== -->
<div class="page last">
  <div class="section-header">
    <div>
      <div class="section-h1">9. Taglish Oral Explanation Cheat Sheet</div>
      <div class="section-sub">Ready-to-use scripts para madaling ipaliwanag ang AI, USER, STAFF, ADMIN, at ang pagko-connect nila</div>
    </div>
    <div class="section-pill">QUICK PRESENTATION GUIDE</div>
  </div>

  <div class="speech-box" style="border-left-color:#7c3aed;">
    <div class="speech-title">1. Paano I-explain ang AI (Marvi AI)</div>
    <div class="speech-quote">
      "Ang <strong>Marvi AI</strong> namin ay hindi lang static FAQ chatbot. Gumagamit ito ng <strong>Live SSE Streaming</strong> via NVIDIA NIM (<code>nemotron-3.5-bolt-e4b</code>) na may 3-level failover at local fallback para 0% downtime kahit walang internet. Nakakakonekta ito sa live MySQL room catalog at sa account ng naka-login na guest, nakaka-detect ng Tagalog o English, may Voice Mic/TTS, at naglalabas ng server-verified <strong>Room Cards</strong> at <strong>Take Me There</strong> buttons. Bukod sa Guest Chatbot at <strong>Smart AI Room Matcher</strong>, may sarili ring AI Shift Co-Pilot ang <strong>Staff</strong> (<code>staff-assistant.php</code>) at Executive BI Digest ang <strong>Admin</strong> (<code>admin-insights.php</code>)."
    </div>
  </div>

  <div class="speech-box" style="border-left-color:#0284c7;">
    <div class="speech-title">2. Paano I-explain ang USER (Guest)</div>
    <div class="speech-quote">
      "Ang <strong>User / Guest</strong> ang customer side sa Port <code>:8080</code> (<code>MH_USER_SESS</code>). Puwede siyang mag-browse ng rooms, magtanong kay Marvi AI kung anong kwarto ang pasok sa budget niya, at mag-self-register. Bago makapag-book, kailangan niyang kumpletuhin ang <strong>KYC Valid ID Verification</strong> sa <code>profile.php</code>. Kapag verified na ng Admin, puwede na siyang mag-book (may automatic date-overlap protection at <code>FOR UPDATE</code> lock), magbayad via <strong>GCash o Maya QR</strong> sabay upload ng receipt, mag-track ng voucher, at mag-submit ng star rating pagkatapos ng stay."
    </div>
  </div>

  <div class="speech-box" style="border-left-color:#059669;">
    <div class="speech-title">3. Paano I-explain ang STAFF (Frontdesk)</div>
    <div class="speech-quote">
      "Ang <strong>Staff</strong> ang frontdesk operations team sa Port <code>:8082</code> (<code>MH_STAFF_SESS</code>). Wala silang public registration &mdash; Admin lang ang pwedeng gumawa ng account nila. Pag-login, binabati sila ng <strong>AI Shift Briefing</strong> kung ilan ang arrivals, departures, at dirty rooms ngayong araw. Kaya nilang mag-accept/decline ng pending bookings, mag-verify ng GCash/Maya payments (may ACID lock para hindi magbanggaan sa Admin), mag-process ng <strong>Check-in at Check-out</strong>, at mag-update ng <strong>Room Housekeeping Status</strong> (Available, Dirty, Maintenance). Pero para sa seguridad, bawal sila sa user accounts, password resets ng guests, room pricing, AI config, at audit logs."
    </div>
  </div>

  <div class="speech-box" style="border-left-color:#d97706;">
    <div class="speech-title">4. Paano I-explain ang ADMIN (Super Admin)</div>
    <div class="speech-quote">
      "Ang <strong>Super Admin</strong> ang master controller sa Port <code>:8081</code> (<code>MH_ADMIN_SESS</code>). Dito nakasentro ang <strong>Executive Dashboard + AI Insights</strong>, pag-Add/Edit/Delete at pricing ng <strong>Rooms</strong>, pag-override ng bookings, side-by-side payment audit, <strong>KYC ID verification</strong> ng guests, <strong>Live Password Reset Dispatch</strong> (may instant popup sa screen ng guest), paggawa ng <strong>Staff accounts</strong>, pag-configure at pag-monitor ng <strong>Marvi AI + Flagged Security Threats</strong>, pagbabasa ng immutable <strong>Audit Logs</strong>, at pamamahala ng Hotel Info at Maya/GCash numbers sa <strong>System Settings</strong>."
    </div>
  </div>

  <div class="speech-box" style="border-left-color:#0f172a; background:#fffbeb; border-color:#fde68a;">
    <div class="speech-title">5. Paano Nagko-connect ang Lahat (/ AI / USER / STAFF / ADMIN)?</div>
    <div class="speech-quote">
      "Nakahiwalay ang apat na bahagi sa pamamagitan ng <strong>3 isolated session cookies</strong> (<code>MH_USER_SESS</code>, <code>MH_STAFF_SESS</code>, <code>MH_ADMIN_SESS</code>) at <strong>RoleAuth + CSRF middleware</strong> sa Slim 4 API, pero nagko-connect sila sa iisang <strong>MySQL InnoDB database (14 tables)</strong> at protected file proxy (<code>serve-file.php</code>). Halimbawa: kapag nagtanong ang <strong>User</strong> sa <strong>AI</strong> at nag-book + nag-upload ng GCash receipt, nakikita ito ng <strong>Staff</strong> sa Shift Briefing at check-in queue; kapag na-verify at na-check-out ng Staff, nakikita ng <strong>Admin</strong> ang revenue, occupancy, at AI Executive Digest sa Dashboard, kasama ang buong trail sa <code>audit_logs</code>."
    </div>
  </div>

  <div class="card soft" style="margin-top:8px;">
    <div class="card-title">Quick Reference: Default Portal URLs (Standard XAMPP)</div>
    <p style="margin-bottom:0; font-size:7.4pt;">
      &bull; <strong>Public &amp; Guest Portal (:8080):</strong> <code>http://localhost/hotel/</code> &amp; <code>http://localhost/hotel/auth/login.php</code><br>
      &bull; <strong>Staff Frontdesk Portal (:8082):</strong> <code>http://localhost/hotel/staff/login.php</code><br>
      &bull; <strong>Super Admin Portal (:8081):</strong> <code>http://localhost/hotel/admin/login.php</code>
    </p>
  </div>
</div>

</body>
</html>
"""


def main() -> None:
    output_path = "/opt/lampp/htdocs/hotel/docs/MARVELOUS_HOTEL_ROLES_ARCHITECTURE_GUIDE.pdf"
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    html = build_html()
    weasyprint.HTML(string=html).write_pdf(output_path)
    print(f"Generated PDF: {output_path}")
    print(f"Size: {os.path.getsize(output_path):,} bytes")


if __name__ == "__main__":
    main()
