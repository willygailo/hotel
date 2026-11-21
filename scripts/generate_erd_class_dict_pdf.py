#!/usr/bin/env python3
"""
=============================================================================
Marvelous Hotel & Dormitory — System Architecture, ERD Flow, UML Class Diagram
& Complete 16-Table Database Dictionary (Capstone Defense Appendix PDF)
Target: docs/MARVELOUS_HOTEL_ERD_CLASS_DICTIONARY_ARCHITECTURE.pdf
Engine: Python 3 + WeasyPrint (Vector Inline SVG Diagrams + Structured Tables)
=============================================================================
"""

import os
import weasyprint


def build_html() -> str:
    return r"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marvelous Hotel — System Architecture, ERD Flow, Class Diagram &amp; Database Dictionary</title>
<style>
  @page {
    size: A4 portrait;
    margin: 13mm 11mm 13mm 11mm;
    @top-left {
      content: "MARVELOUS HOTEL & DORMITORY — ARCHITECTURE, ERD, CLASS DIAGRAM & DATA DICTIONARY";
      font-size: 6.6pt;
      font-weight: 700;
      color: #94a3b8;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    @top-right {
      content: "CAPSTONE TECHNICAL SPECIFICATION • NOV 2026";
      font-size: 6.6pt;
      font-weight: 700;
      color: #c5a880;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    @bottom-left {
      content: "3-Tier Clean Architecture | 16 MySQL InnoDB Tables (17 Migrations) | Slim 4 PSR-4/PSR-15";
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
    font-size: 7.6pt;
    line-height: 1.34;
    margin: 0;
    padding: 0;
    background: #ffffff;
  }

  .page-break { page-break-after: always; }

  /* Cover */
  .cover {
    background: linear-gradient(135deg, #0b1120 0%, #0f172a 50%, #1e293b 100%);
    color: #ffffff;
    min-height: 297mm;
    padding: 22mm 18mm 18mm 18mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    page-break-after: always;
  }

  .cover-badge {
    display: inline-block;
    background: rgba(197, 168, 128, 0.16);
    border: 1px solid #c5a880;
    color: #e2c9a5;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 8pt;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin-bottom: 14px;
  }

  .cover-title {
    font-size: 24pt;
    font-weight: 900;
    line-height: 1.16;
    margin: 0 0 10px 0;
    color: #ffffff;
  }

  .cover-title span { color: #c5a880; }

  .cover-sub {
    font-size: 10.5pt;
    color: #94a3b8;
    line-height: 1.45;
    margin-bottom: 18px;
  }

  .cover-box {
    background: rgba(15, 23, 42, 0.72);
    border: 1px solid rgba(197, 168, 128, 0.35);
    border-radius: 10px;
    padding: 14px 16px;
    margin: 10px 0;
  }

  .cover-box h3 {
    color: #c5a880;
    font-size: 9pt;
    text-transform: uppercase;
    letter-spacing: 0.9px;
    margin: 0 0 8px 0;
  }

  .cover-grid {
    display: table;
    width: 100%;
    margin-top: 8px;
  }

  .cover-cell {
    display: table-cell;
    width: 25%;
    padding: 4px;
    text-align: center;
  }

  .cover-stat {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    padding: 9px 6px;
  }

  .cover-stat strong {
    display: block;
    font-size: 14pt;
    color: #c5a880;
    margin-bottom: 2px;
  }

  .cover-stat span {
    font-size: 7pt;
    color: #e2e8f0;
    font-weight: 600;
  }

  .cover-toc {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }

  .cover-toc th {
    background: rgba(197, 168, 128, 0.22);
    color: #e2c9a5;
    font-size: 7.2pt;
    padding: 5px 7px;
    text-align: left;
    border: 1px solid rgba(255, 255, 255, 0.12);
  }

  .cover-toc td {
    padding: 4.5px 7px;
    font-size: 7.2pt;
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .cover-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.18);
    padding-top: 10px;
    display: table;
    width: 100%;
    font-size: 7.4pt;
    color: #94a3b8;
  }

  .cover-footer-col { display: table-cell; width: 33.33%; }
  .cover-footer-col strong { display: block; color: #ffffff; font-size: 8.2pt; margin-bottom: 2px; }

  /* Content Headings */
  h1 {
    font-size: 12.5pt;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 5px 0;
    padding-bottom: 4px;
    border-bottom: 1.8px solid #c5a880;
    display: flex;
    align-items: center;
    page-break-after: avoid;
  }

  .sec-tag {
    background: #0f172a;
    color: #c5a880;
    font-size: 6.6pt;
    padding: 2px 7px;
    border-radius: 4px;
    margin-right: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  h2 {
    font-size: 8.8pt;
    font-weight: 800;
    color: #1e3a8a;
    margin: 8px 0 4px 0;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    page-break-after: avoid;
  }

  h3.tbl-head {
    font-size: 8pt;
    font-weight: 800;
    color: #0f172a;
    background: #f1f5f9;
    border-left: 3.5px solid #c5a880;
    padding: 3px 7px;
    margin: 7px 0 3px 0;
    page-break-after: avoid;
  }

  .intro {
    font-size: 7.4pt;
    color: #475569;
    margin: 0 0 7px 0;
  }

  .svg-box {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px;
    margin-bottom: 7px;
    text-align: center;
    page-break-inside: avoid;
  }

  .svg-caption {
    font-size: 6.8pt;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
    text-align: left;
    border-bottom: 1px dashed #cbd5e1;
    padding-bottom: 2px;
  }

  table.dict {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
    font-size: 6.7pt;
  }

  table.dict thead { display: table-header-group; }
  table.dict tr { page-break-inside: avoid; }

  table.dict th {
    background: #0f172a;
    color: #f8fafc;
    padding: 3.2px 5px;
    text-align: left;
    font-weight: 700;
    border: 1px solid #334155;
  }

  table.dict td {
    padding: 2.8px 5px;
    border: 1px solid #cbd5e1;
    vertical-align: top;
    color: #1e293b;
  }

  table.dict tr:nth-child(even) { background: #f8fafc; }

  code {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    padding: 0.5px 2.5px;
    border-radius: 2.5px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 6.4pt;
    font-weight: 600;
  }

  .k-pk { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; padding: 0.5px 3.5px; border-radius: 2.5px; font-weight: 700; font-size: 6pt; }
  .k-fk { background: #dbeafe; color: #1e40af; border: 1px solid #60a5fa; padding: 0.5px 3.5px; border-radius: 2.5px; font-weight: 700; font-size: 6pt; }
  .k-uk { background: #f3e8ff; color: #6b21a8; border: 1px solid #c084fc; padding: 0.5px 3.5px; border-radius: 2.5px; font-weight: 700; font-size: 6pt; }
  .k-ix { background: #e2e8f0; color: #334155; border: 1px solid #94a3b8; padding: 0.5px 3.5px; border-radius: 2.5px; font-weight: 700; font-size: 6pt; }

  .callout {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 3.5px solid #2563eb;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 7pt;
    color: #1e3a8a;
    margin-bottom: 6px;
  }
</style>
</head>
<body>

<!-- ==================== COVER PAGE ==================== -->
<div class="cover">
  <div>
    <div class="cover-badge">Capstone Technical Architecture &amp; Database Specification</div>
    <div class="cover-title">MARVELOUS HOTEL &amp; DORMITORY<br><span>SYSTEM ARCHITECTURE, ERD, CLASS DIAGRAM &amp; DATABASE DICTIONARY</span></div>
    <div class="cover-sub">
      100% Codebase-Synchronized Technical Diagrams &amp; Relational Schema Reference covering Tier 1 Frontend (Tailwind CSS + Alpine.js), Tier 2 Backend (PHP 8 + Slim 4 PSR-4/PSR-15 + Marvi Live AI), and Tier 3 Database (MySQL InnoDB PDO).
    </div>

    <div class="cover-grid">
      <div class="cover-cell"><div class="cover-stat"><strong>3-Tier</strong><span>Clean Separation<br>FE &bull; BE &bull; DB</span></div></div>
      <div class="cover-cell"><div class="cover-stat"><strong>16 Tables</strong><span>MySQL InnoDB<br>17 Migrations</span></div></div>
      <div class="cover-cell"><div class="cover-stat"><strong>16 Classes</strong><span>Slim 4 PSR-4<br>Controllers/Svcs</span></div></div>
      <div class="cover-cell"><div class="cover-stat"><strong>32 / 32</strong><span>PHPUnit 10 Pass<br>185 Assertions</span></div></div>
    </div>
  </div>

  <div class="cover-box">
    <h3>Contents &amp; Defense Appendix Blueprint</h3>
    <table class="cover-toc">
      <thead>
        <tr>
          <th style="width:16%;">Section</th>
          <th style="width:42%;">Specification Module</th>
          <th style="width:42%;">Contents Included</th>
        </tr>
      </thead>
      <tbody>
        <tr><td style="color:#c5a880;font-weight:700;">Section 1</td><td>System Architecture &amp; Request Flow</td><td>Vector 3-Tier Architecture Diagram, Apache Clean Router, 3 Isolated Sessions &amp; Tech Stack.</td></tr>
        <tr><td style="color:#c5a880;font-weight:700;">Section 2</td><td>Entity-Relationship Diagram (ERD) &amp; Data Flow</td><td>Vector 16-Table ERD with Crow's-Foot Cardinality, Transactional Data Flow &amp; FK Matrix.</td></tr>
        <tr><td style="color:#c5a880;font-weight:700;">Section 3</td><td>UML Class Diagram &amp; Runtime Interaction Flow</td><td>Vector UML Class Diagram of all PSR-4 Controllers, Services, PSR-15 Middleware &amp; <code>HotelAPI</code>.</td></tr>
        <tr><td style="color:#c5a880;font-weight:700;">Section 4</td><td>Complete 16-Table Database Data Dictionary</td><td>Master Table Summary + Column-by-Column Schema Tables (Type, Null/Default, Keys, Purpose).</td></tr>
      </tbody>
    </table>
  </div>

  <div class="cover-footer">
    <div class="cover-footer-col">
      <strong>PRESENTATION &amp; API</strong>
      Tailwind CSS v3.4 &bull; Alpine.js v3.14<br>
      PHP 8 &bull; Slim 4 &bull; PHPMailer 7
    </div>
    <div class="cover-footer-col">
      <strong>DATABASE &amp; AI</strong>
      MySQL / MariaDB InnoDB (PDO)<br>
      NVIDIA NIM Live SSE + Voice Mode
    </div>
    <div class="cover-footer-col" style="text-align:right;">
      <strong>EDITION &amp; STATUS</strong>
      November 20, 2026 &bull; Defense Ready<br>
      Synced with <code>marvelous_hotel.sql</code> + 0017
    </div>
  </div>
</div>

<!-- ==================== SECTION 1: SYSTEM ARCHITECTURE ==================== -->
<h1><span class="sec-tag">Section 1</span> 3-Tier Clean System Architecture &amp; Request Flow</h1>
<p class="intro">
  Ang buong sistema ay nakahiwalay sa tatlong malinis na tier (<code>frontend/</code>, <code>backend/</code>, <code>database/</code>) nang walang loose PHP scripts sa project root. Ang Apache <code>.htaccess</code> internal router ang nagre-route ng traffic at nagpapatupad ng HTTP 403 isolation sa mga internal folders.
</p>

<div class="svg-box">
  <div class="svg-caption">Figure 1.1 — Marvelous Hotel 3-Tier System Architecture &amp; Data Flow Diagram</div>
  <svg viewBox="0 0 760 470" width="100%" xmlns="http://www.w3.org/2000/svg" font-family="Helvetica Neue, Arial, sans-serif">
    <defs>
      <marker id="arr" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
        <path d="M 0 1 L 10 5 L 0 9 z" fill="#475569"/>
      </marker>
      <marker id="arrGold" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
        <path d="M 0 1 L 10 5 L 0 9 z" fill="#b45309"/>
      </marker>
    </defs>

    <!-- Client Layer -->
    <rect x="10" y="6" width="740" height="58" rx="7" fill="#0f172a"/>
    <text x="24" y="23" fill="#c5a880" font-size="9" font-weight="800">CLIENT LAYER — DESKTOP &amp; MOBILE BROWSERS (3 ISOLATED SESSION COOKIES)</text>
    <rect x="24" y="30" width="224" height="26" rx="4" fill="#166534" stroke="#4ade80"/>
    <text x="136" y="42" fill="#fff" font-size="8.2" font-weight="700" text-anchor="middle">Guest Browser (MH_USER_SESS)</text>
    <text x="136" y="52" fill="#dcfce7" font-size="7" text-anchor="middle">Public, Auth, Guest Portal + Marvi Voice</text>

    <rect x="268" y="30" width="224" height="26" rx="4" fill="#075985" stroke="#38bdf8"/>
    <text x="380" y="42" fill="#fff" font-size="8.2" font-weight="700" text-anchor="middle">Staff Browser (MH_STAFF_SESS)</text>
    <text x="380" y="52" fill="#e0f2fe" font-size="7" text-anchor="middle">Check-In/Out, Payments, Housekeeping</text>

    <rect x="512" y="30" width="224" height="26" rx="4" fill="#991b1b" stroke="#f87171"/>
    <text x="624" y="42" fill="#fff" font-size="8.2" font-weight="700" text-anchor="middle">Admin Browser (MH_ADMIN_SESS)</text>
    <text x="624" y="52" fill="#fee2e2" font-size="7" text-anchor="middle">Executive BI, KYC, Resets, SMTP, AI, Audit</text>

    <!-- Arrows Client -> Router -->
    <line x1="136" y1="64" x2="136" y2="82" stroke="#475569" stroke-width="1.5" marker-end="url(#arr)"/>
    <line x1="380" y1="64" x2="380" y2="82" stroke="#475569" stroke-width="1.5" marker-end="url(#arr)"/>
    <line x1="624" y1="64" x2="624" y2="82" stroke="#475569" stroke-width="1.5" marker-end="url(#arr)"/>

    <!-- Apache Clean Router -->
    <rect x="10" y="84" width="740" height="44" rx="6" fill="#fef3c7" stroke="#d97706" stroke-width="1.4"/>
    <text x="24" y="100" fill="#92400e" font-size="8.8" font-weight="800">APACHE 2.4 CLEAN INTERNAL ROUTER (/.htaccess — ZERO LOOSE ROOT PHP FILES)</text>
    <text x="24" y="114" fill="#78350f" font-size="7.3">Routes /, /rooms.php, /auth/*, /user/*, /staff/*, /admin/* -&gt; frontend/  |  /backend/api/v1/* -&gt; Slim 4  |  /ai/* &amp; /serve-file.php -&gt; backend/</text>
    <text x="24" y="123" fill="#b91c1c" font-size="7" font-weight="700">HTTP 403 FORBIDDEN BLOCK: Direct web access blocked on backend/core/, backend/src/, database/, uploads/</text>

    <!-- Arrows Router -> Tier 1 & Tier 2 -->
    <line x1="185" y1="128" x2="185" y2="146" stroke="#475569" stroke-width="1.5" marker-end="url(#arr)"/>
    <line x1="555" y1="128" x2="555" y2="146" stroke="#b45309" stroke-width="1.5" marker-end="url(#arrGold)"/>

    <!-- TIER 1: FRONTEND -->
    <rect x="10" y="148" width="355" height="145" rx="7" fill="#eff6ff" stroke="#2563eb" stroke-width="1.4"/>
    <text x="22" y="164" fill="#1e3a8a" font-size="9" font-weight="800">TIER 1: FRONTEND PRESENTATION LAYER (frontend/)</text>

    <rect x="22" y="172" width="160" height="52" rx="4" fill="#ffffff" stroke="#93c5fd"/>
    <text x="30" y="185" fill="#0f172a" font-size="7.8" font-weight="700">Portal UI Pages</text>
    <text x="30" y="196" fill="#475569" font-size="6.8">pages/, auth/, user/ (12)</text>
    <text x="30" y="206" fill="#475569" font-size="6.8">staff/ (6), admin/ (10)</text>
    <text x="30" y="216" fill="#1d4ed8" font-size="6.7" font-weight="700">HTML5 + CSS3 + Tailwind v3.4</text>

    <rect x="192" y="172" width="161" height="52" rx="4" fill="#ffffff" stroke="#93c5fd"/>
    <text x="200" y="185" fill="#0f172a" font-size="7.8" font-weight="700">Reactive Components</text>
    <text x="200" y="196" fill="#475569" font-size="6.8">Alpine.js v3.14 (Offline)</text>
    <text x="200" y="206" fill="#475569" font-size="6.8">HotelAPI (api-client.js)</text>
    <text x="200" y="216" fill="#1d4ed8" font-size="6.7" font-weight="700">Live Availability + X-CSRF</text>

    <rect x="22" y="232" width="160" height="50" rx="4" fill="#ffffff" stroke="#c084fc"/>
    <text x="30" y="245" fill="#5b21b6" font-size="7.8" font-weight="700">Marvi Live AI Widget</text>
    <text x="30" y="256" fill="#475569" font-size="6.8">ai-chat.js (SSE Streaming)</text>
    <text x="30" y="266" fill="#475569" font-size="6.8">Web Speech Mic (PH/EN) + TTS</text>
    <text x="30" y="276" fill="#6b21a8" font-size="6.7" font-weight="700">Room Cards &amp; Take Me There</text>

    <rect x="192" y="232" width="161" height="50" rx="4" fill="#ffffff" stroke="#f59e0b"/>
    <text x="200" y="245" fill="#92400e" font-size="7.8" font-weight="700">Live Reset Popup Modal</text>
    <text x="200" y="256" fill="#475569" font-size="6.8">reset-status-modal.php</text>
    <text x="200" y="266" fill="#475569" font-size="6.8">3s Non-Blocking Polling</text>
    <text x="200" y="276" fill="#b45309" font-size="6.7" font-weight="700">1-Time Reveal + 15m TTL</text>

    <!-- Arrow Tier 1 <-> Tier 2 -->
    <line x1="365" y1="205" x2="392" y2="205" stroke="#2563eb" stroke-width="1.8" marker-end="url(#arr)"/>
    <line x1="392" y1="220" x2="365" y2="220" stroke="#2563eb" stroke-width="1.8" marker-end="url(#arr)"/>
    <text x="378" y="198" fill="#1e40af" font-size="6.4" font-weight="700" text-anchor="middle">JSON</text>
    <text x="378" y="232" fill="#1e40af" font-size="6.4" font-weight="700" text-anchor="middle">SSE</text>

    <!-- TIER 2: BACKEND -->
    <rect x="395" y="148" width="355" height="196" rx="7" fill="#fffbeb" stroke="#c5a880" stroke-width="1.5"/>
    <text x="407" y="164" fill="#0f172a" font-size="9" font-weight="800">TIER 2: BACKEND APPLICATION &amp; API LAYER (backend/)</text>

    <rect x="407" y="172" width="331" height="52" rx="4" fill="#ffffff" stroke="#d97706"/>
    <text x="415" y="185" fill="#0f172a" font-size="7.8" font-weight="700">Slim 4 REST API (/backend/api/v1/* — ApiAppFactory.php)</text>
    <text x="415" y="196" fill="#475569" font-size="6.8">PSR-15 Middleware: JsonBodyParser -&gt; RoleAuth -&gt; CsrfProtection</text>
    <text x="415" y="206" fill="#475569" font-size="6.8">PSR-4 Controllers (6) -&gt; Domain Services (5) -&gt; JsonResponder</text>
    <text x="415" y="216" fill="#b45309" font-size="6.7" font-weight="700">Namespace: MarvelousHotel\ (Composer Autoloader)</text>

    <rect x="407" y="230" width="160" height="52" rx="4" fill="#ffffff" stroke="#94a3b8"/>
    <text x="415" y="243" fill="#0f172a" font-size="7.8" font-weight="700">Core Libraries (core/)</text>
    <text x="415" y="254" fill="#475569" font-size="6.8">config, db (PDO), session</text>
    <text x="415" y="264" fill="#475569" font-size="6.8">functions, rate-limiter, logger</text>
    <text x="415" y="274" fill="#0f172a" font-size="6.7" font-weight="700">PHPMailer 7 (Gmail SMTP)</text>

    <rect x="577" y="230" width="161" height="52" rx="4" fill="#ffffff" stroke="#8b5cf6"/>
    <text x="585" y="243" fill="#5b21b6" font-size="7.8" font-weight="700">Marvi AI Engine (ai/)</text>
    <text x="585" y="254" fill="#475569" font-size="6.8">nvidia-client.php (SSE)</text>
    <text x="585" y="264" fill="#475569" font-size="6.8">chatbot, staff-assistant, BI</text>
    <text x="585" y="274" fill="#6b21a8" font-size="6.7" font-weight="700">Guardrails + Failover Chain</text>

    <rect x="407" y="288" width="331" height="46" rx="4" fill="#ffffff" stroke="#10b981"/>
    <text x="415" y="301" fill="#065f46" font-size="7.8" font-weight="700">Protected Non-HTML Endpoints (backend/endpoints/)</text>
    <text x="415" y="312" fill="#475569" font-size="6.8">serve-file.php (Role-Verified KYC &amp; Receipt Proxy) | check-reset-status.php</text>
    <text x="415" y="323" fill="#475569" font-size="6.8">check-name.php (Live Unique Name Check) | logout.php (Role-Aware Destroyer)</text>

    <!-- External Cloud Box (under Tier 1 left) -->
    <rect x="10" y="302" width="355" height="42" rx="6" fill="#faf5ff" stroke="#8b5cf6" stroke-width="1.2"/>
    <text x="22" y="317" fill="#5b21b6" font-size="8" font-weight="800">EXTERNAL CLOUD SERVICES (HTTPS / TLS)</text>
    <text x="22" y="330" fill="#475569" font-size="7">NVIDIA NIM API (nemotron-3.5-bolt-e4b + 2 Fallbacks)  |  Gmail SMTP (Port 587 TLS)</text>
    <line x1="365" y1="322" x2="392" y2="322" stroke="#7c3aed" stroke-width="1.5" stroke-dasharray="3,2" marker-end="url(#arr)"/>
    <text x="378" y="315" fill="#6b21a8" font-size="6" font-weight="700" text-anchor="middle">TLS</text>

    <!-- Arrows Tier 2 -> Tier 3 -->
    <line x1="505" y1="344" x2="505" y2="368" stroke="#0f172a" stroke-width="1.6" marker-end="url(#arr)"/>
    <line x1="655" y1="344" x2="655" y2="368" stroke="#0f172a" stroke-width="1.6" marker-end="url(#arr)"/>

    <!-- TIER 3: DATABASE & STORAGE -->
    <rect x="10" y="370" width="740" height="92" rx="7" fill="#f0fdf4" stroke="#16a34a" stroke-width="1.5"/>
    <text x="24" y="387" fill="#166534" font-size="9" font-weight="800">TIER 3: DATA PERSISTENCE &amp; PRIVATE FILE STORAGE (database/ &amp; uploads/ — HTTP 403 BLOCKED)</text>

    <rect x="24" y="396" width="465" height="56" rx="5" fill="#ffffff" stroke="#16a34a"/>
    <text x="34" y="410" fill="#0f172a" font-size="8.2" font-weight="800">MySQL / MariaDB InnoDB — marvelous_hotel (utf8mb4_unicode_ci)</text>
    <text x="34" y="422" fill="#334155" font-size="7">Access: PHP PDO Prepared Statements + ACID Transactions (BEGIN / SELECT ... FOR UPDATE / COMMIT)</text>
    <text x="34" y="433" fill="#334155" font-size="7">16 Relational Tables | 17 Versioned Migrations (0001..0017 via database/migrate.php)</text>
    <text x="34" y="444" fill="#15803d" font-size="6.9" font-weight="700">Master Schema: database/marvelous_hotel.sql | Zero SQL Injection | Anti-Double-Booking</text>

    <rect x="502" y="396" width="234" height="56" rx="5" fill="#ffffff" stroke="#0284c7"/>
    <text x="512" y="410" fill="#0f172a" font-size="8.2" font-weight="800">Private Uploads &amp; QA Logs</text>
    <text x="512" y="422" fill="#334155" font-size="7">uploads/ids/ (Govt &amp; Student KYC IDs)</text>
    <text x="512" y="433" fill="#334155" font-size="7">uploads/payments/ (Maya &amp; GCash Proofs)</text>
    <text x="512" y="444" fill="#0369a1" font-size="6.9" font-weight="700">Role-Verified Delivery via serve-file.php</text>
  </svg>
</div>

<table class="dict">
  <thead>
    <tr>
      <th style="width:18%;">Tier / Folder</th>
      <th style="width:20%;">Languages</th>
      <th style="width:32%;">Frameworks &amp; Libraries</th>
      <th style="width:30%;">Architectural Role</th>
    </tr>
  </thead>
  <tbody>
    <tr><td><strong>Tier 1: Frontend</strong> (<code>frontend/</code>)</td><td>HTML5, CSS3, ES6+ JS</td><td><strong>Tailwind CSS v3.4</strong>, <strong>Alpine.js v3.14</strong>, Font Awesome 6, <code>HotelAPI</code>, Web Speech API</td><td>Multi-portal UI, reactive availability checks, SSE AI chat, Voice Mic/TTS, live reset popup.</td></tr>
    <tr><td><strong>Tier 2: Backend</strong> (<code>backend/</code>)</td><td>PHP 8 (JSON &amp; SSE)</td><td><strong>Slim 4</strong> (<code>slim/slim</code>, <code>slim/psr7</code>), PSR-4/PSR-15, Composer, <strong>PHPMailer 7</strong>, NVIDIA NIM</td><td>REST API v1, Controller/Service logic, RBAC &amp; CSRF guards, atomic payments, live AI streaming.</td></tr>
    <tr><td><strong>Tier 3: Database</strong> (<code>database/</code>)</td><td>SQL (DDL &amp; DML)</td><td><strong>MySQL / MariaDB InnoDB</strong>, <strong>PHP PDO</strong>, 16 Tables, 17 Versioned Migrations</td><td>ACID bookings/payments, row locking, KYC &amp; audit persistence, zero SQL injection.</td></tr>
    <tr><td><strong>QA &amp; Server</strong> (<code>tests/</code>, <code>.htaccess</code>)</td><td>PHP 8, Bash, Apache</td><td><strong>PHPUnit 10</strong> (32 tests / 185 assertions), Apache <code>mod_rewrite</code>, Docker, Tunnel</td><td>Automated route/middleware/service testing, clean routing, 403 internal isolation.</td></tr>
  </tbody>
</table>

<div class="page-break"></div>

<!-- ==================== SECTION 2: ERD & DATA FLOW ==================== -->
<h1><span class="sec-tag">Section 2</span> Entity-Relationship Diagram (ERD) &amp; Transactional Flow</h1>
<p class="intro">
  Ang ERD diagram sa ibaba ay kumakatawan sa lahat ng <strong>16 MySQL InnoDB tables</strong> sa <code>marvelous_hotel</code> kasama ang eksaktong Primary Keys (<code>PK</code>), Foreign Keys (<code>FK</code>), Unique Keys (<code>UK</code>), cardinality (<code>1:N</code>, <code>1:0..1</code>), at delete rules.
</p>

<div class="svg-box">
  <div class="svg-caption">Figure 2.1 — Complete 16-Table Entity-Relationship Diagram (Crow's-Foot Notation)</div>
  <svg viewBox="0 0 760 465" width="100%" xmlns="http://www.w3.org/2000/svg" font-family="Helvetica Neue, Arial, sans-serif">
    <defs>
      <marker id="erdArr" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5.5" markerHeight="5.5" orient="auto">
        <path d="M 0 1 L 10 5 L 0 9 z" fill="#2563eb"/>
      </marker>
    </defs>

    <!-- Relationship Lines (drawn first behind boxes) -->
    <!-- room_types -> rooms -->
    <line x1="150" y1="54" x2="188" y2="54" stroke="#2563eb" stroke-width="1.5" marker-end="url(#erdArr)"/>
    <text x="169" y="49" fill="#1e40af" font-size="6.5" font-weight="700" text-anchor="middle">1:N</text>

    <!-- rooms -> bookings -->
    <line x1="348" y1="68" x2="394" y2="68" stroke="#2563eb" stroke-width="1.5" marker-end="url(#erdArr)"/>
    <text x="371" y="63" fill="#1e40af" font-size="6.5" font-weight="700" text-anchor="middle">1:N</text>

    <!-- bookings -> payments -->
    <line x1="564" y1="62" x2="600" y2="62" stroke="#2563eb" stroke-width="1.5" marker-end="url(#erdArr)"/>
    <text x="582" y="57" fill="#1e40af" font-size="6.5" font-weight="700" text-anchor="middle">1:N</text>

    <!-- bookings -> reviews (1:0..1) -->
    <path d="M 480 142 L 480 170" stroke="#7c3aed" stroke-width="1.5" marker-end="url(#erdArr)"/>
    <text x="488" y="167" fill="#6b21a8" font-size="6.4" font-weight="700">1:0..1 (UK)</text>

    <!-- rooms -> reviews -->
    <path d="M 290 142 L 290 212 L 394 212" fill="none" stroke="#2563eb" stroke-width="1.2" stroke-dasharray="3,2" marker-end="url(#erdArr)"/>
    <text x="300" y="207" fill="#1e40af" font-size="6.2">1:N (nullable)</text>

    <!-- users -> bookings -->
    <path d="M 155 215 L 250 215 L 250 158 L 415 158 L 415 144" fill="none" stroke="#16a34a" stroke-width="1.5" marker-end="url(#erdArr)"/>
    <text x="200" y="210" fill="#166534" font-size="6.4" font-weight="700">1:N bookings</text>

    <!-- users -> payments -->
    <path d="M 155 245 L 372 245 L 372 150 L 675 150 L 675 138" fill="none" stroke="#16a34a" stroke-width="1.2" stroke-dasharray="3,2" marker-end="url(#erdArr)"/>

    <!-- users -> reviews -->
    <path d="M 155 230 L 394 230" fill="none" stroke="#16a34a" stroke-width="1.3" marker-end="url(#erdArr)"/>
    <text x="275" y="226" fill="#166534" font-size="6.2" font-weight="700">1:N reviews</text>

    <!-- users -> notifications -->
    <path d="M 155 270 L 200 270 L 200 302" fill="none" stroke="#16a34a" stroke-width="1.4" marker-end="url(#erdArr)"/>
    <!-- users -> password_reset_requests -->
    <path d="M 155 285 L 385 285 L 385 302" fill="none" stroke="#d97706" stroke-width="1.4" marker-end="url(#erdArr)"/>
    <!-- users -> audit/ai logs -->
    <path d="M 155 296 L 560 296 L 560 302" fill="none" stroke="#64748b" stroke-width="1.2" stroke-dasharray="3,2" marker-end="url(#erdArr)"/>

    <!-- ENTITY 1: room_types -->
    <rect x="8" y="14" width="142" height="82" rx="4" fill="#fff" stroke="#0f172a" stroke-width="1.3"/>
    <rect x="8" y="14" width="142" height="17" rx="4" fill="#0f172a"/>
    <text x="79" y="26" fill="#c5a880" font-size="7.6" font-weight="800" text-anchor="middle">room_types (5 cols)</text>
    <text x="14" y="42" fill="#92400e" font-size="6.8" font-weight="700">PK id : INT</text>
    <text x="14" y="53" fill="#1e293b" font-size="6.7">name : VARCHAR(100)</text>
    <text x="14" y="64" fill="#475569" font-size="6.6">description : TEXT</text>
    <text x="14" y="75" fill="#475569" font-size="6.6">icon : VARCHAR(100)</text>
    <text x="14" y="86" fill="#475569" font-size="6.6">created_at : DATETIME</text>

    <!-- ENTITY 2: rooms -->
    <rect x="190" y="10" width="158" height="132" rx="4" fill="#fff" stroke="#0f172a" stroke-width="1.3"/>
    <rect x="190" y="10" width="158" height="17" rx="4" fill="#0f172a"/>
    <text x="269" y="22" fill="#c5a880" font-size="7.6" font-weight="800" text-anchor="middle">rooms (19 cols)</text>
    <text x="196" y="38" fill="#92400e" font-size="6.8" font-weight="700">PK id : INT</text>
    <text x="196" y="49" fill="#6b21a8" font-size="6.7" font-weight="700">UK room_number : VARCHAR(20)</text>
    <text x="196" y="60" fill="#1d4ed8" font-size="6.7" font-weight="700">FK room_type_id : INT</text>
    <text x="196" y="71" fill="#1e293b" font-size="6.6">name, floor, capacity</text>
    <text x="196" y="82" fill="#1e293b" font-size="6.6">price_per_night, weekend_rate</text>
    <text x="196" y="93" fill="#475569" font-size="6.5">amenities, tags, images (JSON)</text>
    <text x="196" y="104" fill="#b91c1c" font-size="6.6" font-weight="700">status : ENUM(avail..dirty)</text>
    <text x="196" y="115" fill="#475569" font-size="6.5">is_dorm, bed_count, beds_avail</text>
    <text x="196" y="126" fill="#475569" font-size="6.5">thumbnail, created_at, updated_at</text>

    <!-- ENTITY 3: bookings -->
    <rect x="396" y="10" width="168" height="132" rx="4" fill="#fff" stroke="#2563eb" stroke-width="1.4"/>
    <rect x="396" y="10" width="168" height="17" rx="4" fill="#1e3a8a"/>
    <text x="480" y="22" fill="#fff" font-size="7.6" font-weight="800" text-anchor="middle">bookings (24 cols)</text>
    <text x="402" y="38" fill="#92400e" font-size="6.8" font-weight="700">PK id : INT</text>
    <text x="402" y="49" fill="#6b21a8" font-size="6.7" font-weight="700">UK booking_code : VARCHAR(20)</text>
    <text x="402" y="60" fill="#1d4ed8" font-size="6.7" font-weight="700">FK user_id, room_id : INT</text>
    <text x="402" y="71" fill="#1e293b" font-size="6.6">check_in, check_out (DATE)</text>
    <text x="402" y="82" fill="#1e293b" font-size="6.6">nights, num_guests, subtotal</text>
    <text x="402" y="93" fill="#1e293b" font-size="6.6">discount, total_amount, method</text>
    <text x="402" y="104" fill="#b91c1c" font-size="6.6" font-weight="700">status : ENUM(pend..checked_out)</text>
    <text x="402" y="115" fill="#475569" font-size="6.5">accepted_by, checked_in/out_at</text>
    <text x="402" y="126" fill="#475569" font-size="6.5">ai_recommended, source, timestamps</text>

    <!-- ENTITY 4: payments -->
    <rect x="602" y="10" width="150" height="126" rx="4" fill="#fff" stroke="#16a34a" stroke-width="1.4"/>
    <rect x="602" y="10" width="150" height="17" rx="4" fill="#166534"/>
    <text x="677" y="22" fill="#fff" font-size="7.6" font-weight="800" text-anchor="middle">payments (13 cols)</text>
    <text x="608" y="38" fill="#92400e" font-size="6.8" font-weight="700">PK id : INT</text>
    <text x="608" y="49" fill="#1d4ed8" font-size="6.7" font-weight="700">FK booking_id : INT</text>
    <text x="608" y="60" fill="#1d4ed8" font-size="6.7" font-weight="700">FK user_id : INT</text>
    <text x="608" y="71" fill="#1e293b" font-size="6.6">amount : DECIMAL(10,2)</text>
    <text x="608" y="82" fill="#1e293b" font-size="6.6">method : ENUM(maya,gcash..)</text>
    <text x="608" y="93" fill="#1e293b" font-size="6.6">reference_number, proof_image</text>
    <text x="608" y="104" fill="#b91c1c" font-size="6.6" font-weight="700">status : ENUM(pend,verif,rej)</text>
    <text x="608" y="115" fill="#475569" font-size="6.5">verified_by, verified_at, notes</text>
    <text x="608" y="126" fill="#475569" font-size="6.5">created_at, updated_at</text>

    <!-- ENTITY 5: users (Core Hub) -->
    <rect x="8" y="122" width="147" height="178" rx="4" fill="#fff" stroke="#16a34a" stroke-width="1.6"/>
    <rect x="8" y="122" width="147" height="18" rx="4" fill="#0f172a"/>
    <text x="81" y="134" fill="#4ade80" font-size="7.8" font-weight="800" text-anchor="middle">users (35 cols — HUB)</text>
    <text x="14" y="150" fill="#92400e" font-size="6.8" font-weight="700">PK id : INT</text>
    <text x="14" y="161" fill="#6b21a8" font-size="6.7" font-weight="700">UK email : VARCHAR(191)</text>
    <text x="14" y="172" fill="#1e293b" font-size="6.6">first, middle, last, suffix</text>
    <text x="14" y="183" fill="#1e293b" font-size="6.6">password (Bcrypt), phone</text>
    <text x="14" y="194" fill="#b91c1c" font-size="6.7" font-weight="700">role : ENUM(user,staff,admin)</text>
    <text x="14" y="205" fill="#1e293b" font-size="6.6">status : ENUM(act,inact,ban)</text>
    <text x="14" y="216" fill="#1d4ed8" font-size="6.6" font-weight="700">KYC: id_type, id_number</text>
    <text x="14" y="227" fill="#1d4ed8" font-size="6.6">id_document, profile_verified</text>
    <text x="14" y="238" fill="#1d4ed8" font-size="6.6">verification_status, verified_by</text>
    <text x="14" y="249" fill="#475569" font-size="6.5">email_verified, verify_token</text>
    <text x="14" y="260" fill="#475569" font-size="6.5">reset_token, reset_expires</text>
    <text x="14" y="271" fill="#475569" font-size="6.5">remember_token, expires</text>
    <text x="14" y="282" fill="#475569" font-size="6.5">last_login, created/updated</text>

    <!-- ENTITY 6: reviews -->
    <rect x="396" y="172" width="168" height="94" rx="4" fill="#fff" stroke="#7c3aed" stroke-width="1.3"/>
    <rect x="396" y="172" width="168" height="16" rx="4" fill="#6b21a8"/>
    <text x="480" y="183" fill="#fff" font-size="7.4" font-weight="800" text-anchor="middle">reviews (9 cols)</text>
    <text x="402" y="198" fill="#92400e" font-size="6.7" font-weight="700">PK id : INT</text>
    <text x="402" y="209" fill="#1d4ed8" font-size="6.6" font-weight="700">FK user_id | FK room_id (NULL)</text>
    <text x="402" y="220" fill="#6b21a8" font-size="6.6" font-weight="700">FK/UK booking_id (NULL)</text>
    <text x="402" y="231" fill="#1e293b" font-size="6.5">rating (1-5), title, comment</text>
    <text x="402" y="242" fill="#475569" font-size="6.5">status : ENUM(pend,appr,hid)</text>
    <text x="402" y="253" fill="#475569" font-size="6.5">created_at : DATETIME</text>

    <!-- ENTITY 7: promos -->
    <rect x="602" y="172" width="150" height="94" rx="4" fill="#fff" stroke="#d97706" stroke-width="1.2"/>
    <rect x="602" y="172" width="150" height="16" rx="4" fill="#b45309"/>
    <text x="677" y="183" fill="#fff" font-size="7.4" font-weight="800" text-anchor="middle">promos (12 cols)</text>
    <text x="608" y="198" fill="#92400e" font-size="6.7" font-weight="700">PK id : INT | UK code</text>
    <text x="608" y="209" fill="#1e293b" font-size="6.5">discount_type (percent|fixed)</text>
    <text x="608" y="220" fill="#1e293b" font-size="6.5">discount_value, min_nights</text>
    <text x="608" y="231" fill="#475569" font-size="6.5">max_uses, used_count</text>
    <text x="608" y="242" fill="#475569" font-size="6.5">valid_from, valid_until</text>
    <text x="608" y="253" fill="#475569" font-size="6.5">status (active|inactive)</text>

    <!-- ROW 3: User-linked Operational & Audit Tables -->
    <!-- notifications -->
    <rect x="130" y="304" width="142" height="74" rx="4" fill="#fff" stroke="#0284c7" stroke-width="1.2"/>
    <rect x="130" y="304" width="142" height="16" rx="4" fill="#0369a1"/>
    <text x="201" y="315" fill="#fff" font-size="7.2" font-weight="800" text-anchor="middle">notifications (9 cols)</text>
    <text x="136" y="330" fill="#92400e" font-size="6.5" font-weight="700">PK id | FK user_id</text>
    <text x="136" y="341" fill="#1e293b" font-size="6.4">title, message, type</text>
    <text x="136" y="352" fill="#475569" font-size="6.4">icon, link, is_read</text>
    <text x="136" y="363" fill="#475569" font-size="6.4">created_at : DATETIME</text>

    <!-- password_reset_requests -->
    <rect x="284" y="304" width="182" height="74" rx="4" fill="#fff" stroke="#d97706" stroke-width="1.3"/>
    <rect x="284" y="304" width="182" height="16" rx="4" fill="#92400e"/>
    <text x="375" y="315" fill="#fff" font-size="7.2" font-weight="800" text-anchor="middle">password_reset_requests (11)</text>
    <text x="290" y="330" fill="#92400e" font-size="6.5" font-weight="700">PK id | FK user_id | email, phone</text>
    <text x="290" y="341" fill="#1d4ed8" font-size="6.4" font-weight="700">IDX request_token (SHA-256)</text>
    <text x="290" y="352" fill="#1e293b" font-size="6.4">status, dispatch_type, new_pwd</text>
    <text x="290" y="363" fill="#475569" font-size="6.4">requested_at, resolved, notified_at</text>

    <!-- audit_logs -->
    <rect x="478" y="304" width="132" height="74" rx="4" fill="#fff" stroke="#475569" stroke-width="1.2"/>
    <rect x="478" y="304" width="132" height="16" rx="4" fill="#334155"/>
    <text x="544" y="315" fill="#fff" font-size="7.2" font-weight="800" text-anchor="middle">audit_logs (8 cols)</text>
    <text x="484" y="330" fill="#92400e" font-size="6.5" font-weight="700">PK id | user_id (Ref)</text>
    <text x="484" y="341" fill="#1e293b" font-size="6.4">action, module, details</text>
    <text x="484" y="352" fill="#475569" font-size="6.4">ip_address, user_agent</text>
    <text x="484" y="363" fill="#475569" font-size="6.4">created_at : DATETIME</text>

    <!-- ai_logs -->
    <rect x="620" y="304" width="132" height="74" rx="4" fill="#fff" stroke="#7c3aed" stroke-width="1.2"/>
    <rect x="620" y="304" width="132" height="16" rx="4" fill="#5b21b6"/>
    <text x="686" y="315" fill="#fff" font-size="7.2" font-weight="800" text-anchor="middle">ai_logs (9 cols)</text>
    <text x="626" y="330" fill="#92400e" font-size="6.5" font-weight="700">PK id | user_id, sess_id</text>
    <text x="626" y="341" fill="#1e293b" font-size="6.4">user_msg, ai_response</text>
    <text x="626" y="352" fill="#b91c1c" font-size="6.4" font-weight="700">intent, flagged, reason</text>
    <text x="626" y="363" fill="#475569" font-size="6.4">created_at : DATETIME</text>

    <!-- ROW 4: Standalone Support & Config Tables (5 tables) -->
    <rect x="8" y="392" width="744" height="64" rx="5" fill="#f1f5f9" stroke="#94a3b8" stroke-dasharray="3,2"/>
    <text x="18" y="405" fill="#334155" font-size="7" font-weight="800">CONFIGURATION, OPERATIONS &amp; INFRASTRUCTURE TABLES</text>

    <rect x="18" y="412" width="138" height="38" rx="3" fill="#fff" stroke="#64748b"/>
    <text x="87" y="423" fill="#0f172a" font-size="7" font-weight="800" text-anchor="middle">settings (5 cols)</text>
    <text x="24" y="434" fill="#475569" font-size="6.2">PK id | UK key | value</text>
    <text x="24" y="443" fill="#475569" font-size="6.1">SMTP, QR, NVIDIA NIM</text>

    <rect x="164" y="412" width="144" height="38" rx="3" fill="#fff" stroke="#64748b"/>
    <text x="236" y="423" fill="#0f172a" font-size="7" font-weight="800" text-anchor="middle">announcements (8 cols)</text>
    <text x="170" y="434" fill="#475569" font-size="6.2">PK id | title, target_role</text>
    <text x="170" y="443" fill="#475569" font-size="6.1">priority, created_by, exp</text>

    <rect x="316" y="412" width="140" height="38" rx="3" fill="#fff" stroke="#64748b"/>
    <text x="386" y="423" fill="#0f172a" font-size="7" font-weight="800" text-anchor="middle">contact_messages (6)</text>
    <text x="322" y="434" fill="#475569" font-size="6.2">PK id | name, email</text>
    <text x="322" y="443" fill="#475569" font-size="6.1">message, ip, created_at</text>

    <rect x="464" y="412" width="138" height="38" rx="3" fill="#fff" stroke="#64748b"/>
    <text x="533" y="423" fill="#0f172a" font-size="7" font-weight="800" text-anchor="middle">rate_limits (5 cols)</text>
    <text x="470" y="434" fill="#475569" font-size="6.2">PK id | key, identifier</text>
    <text x="470" y="443" fill="#475569" font-size="6.1">action, created_at (IDX)</text>

    <rect x="610" y="412" width="132" height="38" rx="3" fill="#fff" stroke="#64748b"/>
    <text x="676" y="423" fill="#0f172a" font-size="7" font-weight="800" text-anchor="middle">schema_migrations (2)</text>
    <text x="616" y="434" fill="#475569" font-size="6.2">PK migration (0001..17)</text>
    <text x="616" y="443" fill="#475569" font-size="6.1">applied_at : DATETIME</text>
  </svg>
</div>

<h2>Foreign Key &amp; Referential Integrity Matrix</h2>
<table class="dict">
  <thead>
    <tr>
      <th style="width:20%;">Child Table</th>
      <th style="width:15%;">FK Column</th>
      <th style="width:18%;">Parent Table(Col)</th>
      <th style="width:18%;">Constraint</th>
      <th style="width:15%;">Delete Action</th>
      <th style="width:14%;">Cardinality</th>
    </tr>
  </thead>
  <tbody>
    <tr><td><code>rooms</code></td><td><code>room_type_id</code></td><td><code>room_types(id)</code></td><td><code>fk_room_type</code></td><td><code>RESTRICT</code></td><td>N : 1</td></tr>
    <tr><td><code>bookings</code></td><td><code>user_id</code></td><td><code>users(id)</code></td><td><code>fk_booking_user</code></td><td><code>CASCADE</code></td><td>N : 1</td></tr>
    <tr><td><code>bookings</code></td><td><code>room_id</code></td><td><code>rooms(id)</code></td><td><code>fk_booking_room</code></td><td><code>CASCADE</code></td><td>N : 1</td></tr>
    <tr><td><code>payments</code></td><td><code>booking_id</code></td><td><code>bookings(id)</code></td><td><code>fk_payment_booking</code></td><td><code>CASCADE</code></td><td>N : 1</td></tr>
    <tr><td><code>payments</code></td><td><code>user_id</code></td><td><code>users(id)</code></td><td><code>fk_payment_user</code></td><td><code>CASCADE</code></td><td>N : 1</td></tr>
    <tr><td><code>reviews</code></td><td><code>user_id</code>, <code>room_id</code>, <code>booking_id</code></td><td><code>users</code>, <code>rooms</code>, <code>bookings</code></td><td><code>fk_review_*</code></td><td><code>CASCADE</code></td><td>N:1 / 1:0..1 (UK)</td></tr>
    <tr><td><code>notifications</code></td><td><code>user_id</code></td><td><code>users(id)</code></td><td><code>fk_notif_user</code></td><td><code>CASCADE</code></td><td>N : 1</td></tr>
    <tr><td><code>password_reset_requests</code></td><td><code>user_id</code></td><td><code>users(id)</code></td><td><code>fk_prr_user</code></td><td><code>CASCADE</code></td><td>N : 1 (nullable)</td></tr>
  </tbody>
</table>

<div class="page-break"></div>

<!-- ==================== SECTION 3: CLASS DIAGRAM & FLOW ==================== -->
<h1><span class="sec-tag">Section 3</span> UML Class Diagram &amp; Runtime Interaction Flow</h1>
<p class="intro">
  Ang UML Class Diagram sa ibaba ay direktang hango sa aktwal na PHP 8 PSR-4 classes sa <code>backend/src/</code> (namespace <code>MarvelousHotel\</code>), PSR-15 Middleware, Tier 1 <code>HotelAPI</code> client (<code>api-client.js</code>), Core PDO/Security Facade, at Marvi NVIDIA NIM AI Engine.
</p>

<div class="svg-box">
  <div class="svg-caption">Figure 3.1 — UML Class Diagram (Exact Methods &amp; Relationships Across Tier 1 &amp; Tier 2)</div>
  <svg viewBox="0 0 760 475" width="100%" xmlns="http://www.w3.org/2000/svg" font-family="Helvetica Neue, Arial, sans-serif">
    <defs>
      <marker id="clsArr" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5.5" markerHeight="5.5" orient="auto">
        <path d="M 0 1 L 10 5 L 0 9 z" fill="#334155"/>
      </marker>
    </defs>

    <!-- Connectors -->
    <!-- HotelAPI -> ApiAppFactory -->
    <line x1="204" y1="54" x2="246" y2="54" stroke="#2563eb" stroke-width="1.5" stroke-dasharray="4,2" marker-end="url(#clsArr)"/>
    <text x="225" y="48" fill="#1d4ed8" font-size="6.2" font-weight="700" text-anchor="middle">REST</text>

    <!-- ApiAppFactory -> Middleware -->
    <line x1="422" y1="54" x2="462" y2="54" stroke="#d97706" stroke-width="1.5" marker-end="url(#clsArr)"/>
    <text x="442" y="48" fill="#b45309" font-size="6.2" font-weight="700" text-anchor="middle">uses</text>

    <!-- ApiAppFactory -> Controllers row -->
    <path d="M 334 102 L 334 128" stroke="#334155" stroke-width="1.4"/>
    <line x1="65" y1="128" x2="695" y2="128" stroke="#334155" stroke-width="1.2"/>
    <line x1="65" y1="128" x2="65" y2="142" stroke="#334155" stroke-width="1.2" marker-end="url(#clsArr)"/>
    <line x1="191" y1="128" x2="191" y2="142" stroke="#334155" stroke-width="1.2" marker-end="url(#clsArr)"/>
    <line x1="317" y1="128" x2="317" y2="142" stroke="#334155" stroke-width="1.2" marker-end="url(#clsArr)"/>
    <line x1="443" y1="128" x2="443" y2="142" stroke="#334155" stroke-width="1.2" marker-end="url(#clsArr)"/>
    <line x1="569" y1="128" x2="569" y2="142" stroke="#334155" stroke-width="1.2" marker-end="url(#clsArr)"/>
    <line x1="695" y1="128" x2="695" y2="142" stroke="#334155" stroke-width="1.2" marker-end="url(#clsArr)"/>

    <!-- Controllers -> Services -->
    <line x1="191" y1="224" x2="191" y2="250" stroke="#16a34a" stroke-width="1.3" marker-end="url(#clsArr)"/>
    <line x1="317" y1="224" x2="317" y2="250" stroke="#16a34a" stroke-width="1.3" marker-end="url(#clsArr)"/>
    <line x1="443" y1="224" x2="443" y2="250" stroke="#16a34a" stroke-width="1.3" marker-end="url(#clsArr)"/>
    <line x1="569" y1="224" x2="569" y2="250" stroke="#16a34a" stroke-width="1.3" marker-end="url(#clsArr)"/>
    <line x1="695" y1="224" x2="695" y2="250" stroke="#16a34a" stroke-width="1.3" marker-end="url(#clsArr)"/>
    <!-- HealthController -> JsonResponder -->
    <line x1="65" y1="224" x2="65" y2="250" stroke="#7c3aed" stroke-width="1.3" stroke-dasharray="3,2" marker-end="url(#clsArr)"/>

    <!-- Services -> CoreInfrastructure (clean shared bus) -->
    <line x1="191" y1="346" x2="191" y2="361" stroke="#0f172a" stroke-width="1.2"/>
    <line x1="317" y1="346" x2="317" y2="361" stroke="#0f172a" stroke-width="1.2"/>
    <line x1="443" y1="346" x2="443" y2="361" stroke="#0f172a" stroke-width="1.2"/>
    <line x1="569" y1="346" x2="569" y2="361" stroke="#0f172a" stroke-width="1.2"/>
    <line x1="695" y1="346" x2="695" y2="361" stroke="#0f172a" stroke-width="1.2"/>
    <line x1="191" y1="361" x2="695" y2="361" stroke="#0f172a" stroke-width="1.2"/>
    <line x1="317" y1="361" x2="317" y2="374" stroke="#0f172a" stroke-width="1.4" marker-end="url(#clsArr)"/>
    <!-- MarviNvidiaClient -> CoreInfrastructure -->
    <line x1="466" y1="421" x2="456" y2="421" stroke="#7c3aed" stroke-width="1.4" stroke-dasharray="2,1" marker-end="url(#clsArr)"/>

    <!-- ROW 1: Tier 1 HotelAPI | ApiAppFactory | PSR-15 Middleware Stack -->
    <!-- HotelAPI -->
    <rect x="8" y="8" width="196" height="106" rx="4" fill="#eff6ff" stroke="#2563eb" stroke-width="1.3"/>
    <rect x="8" y="8" width="196" height="22" rx="4" fill="#1e3a8a"/>
    <text x="106" y="18" fill="#93c5fd" font-size="6" text-anchor="middle">&lt;&lt;Tier 1 JS Client&gt;&gt;</text>
    <text x="106" y="27" fill="#fff" font-size="7.6" font-weight="800" text-anchor="middle">HotelAPI (api-client.js)</text>
    <text x="14" y="42" fill="#1e293b" font-size="6.3">+getHealth() : Promise</text>
    <text x="14" y="52" fill="#1e293b" font-size="6.3">+getAuthStatus() : Promise</text>
    <text x="14" y="62" fill="#1e293b" font-size="6.3">+getRooms(filters) / getRoom(id)</text>
    <text x="14" y="72" fill="#1e293b" font-size="6.3">+checkAvailability(id, in, out, sig)</text>
    <text x="14" y="82" fill="#1e293b" font-size="6.3">+getBookings() / cancelBooking()</text>
    <text x="14" y="92" fill="#1e293b" font-size="6.3">+getPayments() / decidePayment()</text>
    <text x="14" y="102" fill="#1e293b" font-size="6.3">+getDashboardMetrics() : Promise</text>

    <!-- ApiAppFactory -->
    <rect x="248" y="14" width="174" height="88" rx="4" fill="#fffbeb" stroke="#d97706" stroke-width="1.4"/>
    <rect x="248" y="14" width="174" height="22" rx="4" fill="#0f172a"/>
    <text x="335" y="24" fill="#c5a880" font-size="6" text-anchor="middle">&lt;&lt;Slim 4 Factory&gt;&gt;</text>
    <text x="335" y="33" fill="#fff" font-size="7.6" font-weight="800" text-anchor="middle">ApiAppFactory</text>
    <text x="255" y="49" fill="#0f172a" font-size="6.6" font-weight="700">+create(basePath) : Slim\App</text>
    <line x1="248" y1="55" x2="422" y2="55" stroke="#fcd34d"/>
    <text x="255" y="66" fill="#475569" font-size="6.3">Mounts /v1 REST Route Group</text>
    <text x="255" y="76" fill="#475569" font-size="6.3">Attaches PSR-15 Middleware</text>
    <text x="255" y="86" fill="#475569" font-size="6.3">Registers JSON Error Handler</text>
    <text x="255" y="96" fill="#92400e" font-size="6.3" font-weight="700">10 REST Endpoints Active</text>

    <!-- PSR-15 Middleware Package -->
    <rect x="464" y="8" width="288" height="106" rx="4" fill="#fef2f2" stroke="#dc2626" stroke-width="1.3"/>
    <rect x="464" y="8" width="288" height="22" rx="4" fill="#991b1b"/>
    <text x="608" y="18" fill="#fecaca" font-size="6" text-anchor="middle">&lt;&lt;PSR-15 MiddlewareInterface&gt;&gt;</text>
    <text x="608" y="27" fill="#fff" font-size="7.5" font-weight="800" text-anchor="middle">MarvelousHotel\Middleware</text>
    <text x="472" y="43" fill="#0f172a" font-size="6.6" font-weight="700">1. JsonBodyParserMiddleware</text>
    <text x="478" y="53" fill="#475569" font-size="6.3">+process(Request, Handler) : Response (decodes JSON body)</text>
    <text x="472" y="66" fill="#0f172a" font-size="6.6" font-weight="700">2. RoleAuthMiddleware</text>
    <text x="478" y="76" fill="#475569" font-size="6.3">-allowedRoles : array | +process(Request, Handler) : Response</text>
    <text x="472" y="89" fill="#0f172a" font-size="6.6" font-weight="700">3. CsrfProtectionMiddleware</text>
    <text x="478" y="99" fill="#475569" font-size="6.3">+process(Request, Handler) : Response (verifies X-CSRF-Token)</text>

    <!-- ROW 2: 6 CONTROLLERS -->
    <!-- HealthController -->
    <rect x="8" y="144" width="116" height="80" rx="4" fill="#fff" stroke="#2563eb"/>
    <rect x="8" y="144" width="116" height="20" rx="4" fill="#1e40af"/>
    <text x="66" y="157" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">HealthController</text>
    <text x="13" y="177" fill="#1e293b" font-size="6.2">+index(req, res)</text>
    <text x="13" y="188" fill="#1e293b" font-size="6.2">+health(req, res)</text>
    <text x="13" y="204" fill="#64748b" font-size="5.9">GET /v1/health</text>
    <text x="13" y="214" fill="#64748b" font-size="5.9">GET /backend/api</text>

    <!-- AuthController -->
    <rect x="134" y="144" width="116" height="80" rx="4" fill="#fff" stroke="#2563eb"/>
    <rect x="134" y="144" width="116" height="20" rx="4" fill="#1e40af"/>
    <text x="192" y="157" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">AuthController</text>
    <text x="139" y="176" fill="#475569" font-size="6.1">-auth : AuthService</text>
    <line x1="134" y1="181" x2="250" y2="181" stroke="#cbd5e1"/>
    <text x="139" y="192" fill="#1e293b" font-size="6.2">+status(req, res)</text>
    <text x="139" y="208" fill="#64748b" font-size="5.9">GET /v1/auth/status</text>

    <!-- RoomController -->
    <rect x="260" y="144" width="116" height="80" rx="4" fill="#fff" stroke="#2563eb"/>
    <rect x="260" y="144" width="116" height="20" rx="4" fill="#1e40af"/>
    <text x="318" y="157" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">RoomController</text>
    <text x="265" y="176" fill="#475569" font-size="6.1">-rooms : RoomService</text>
    <line x1="260" y1="181" x2="376" y2="181" stroke="#cbd5e1"/>
    <text x="265" y="191" fill="#1e293b" font-size="6.2">+list(req, res)</text>
    <text x="265" y="201" fill="#1e293b" font-size="6.2">+show(req, res, args)</text>
    <text x="265" y="211" fill="#1e293b" font-size="6.2">+availability(req, res)</text>

    <!-- BookingController -->
    <rect x="386" y="144" width="116" height="80" rx="4" fill="#fff" stroke="#2563eb"/>
    <rect x="386" y="144" width="116" height="20" rx="4" fill="#1e40af"/>
    <text x="444" y="157" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">BookingController</text>
    <text x="391" y="176" fill="#475569" font-size="6.1">-bookings : BookingSvc</text>
    <line x1="386" y1="181" x2="502" y2="181" stroke="#cbd5e1"/>
    <text x="391" y="192" fill="#1e293b" font-size="6.2">+list(req, res)</text>
    <text x="391" y="203" fill="#1e293b" font-size="6.2">+cancel(req, res, args)</text>
    <text x="391" y="215" fill="#64748b" font-size="5.8">RoleAuth + CSRF</text>

    <!-- PaymentController -->
    <rect x="512" y="144" width="116" height="80" rx="4" fill="#fff" stroke="#2563eb"/>
    <rect x="512" y="144" width="116" height="20" rx="4" fill="#1e40af"/>
    <text x="570" y="157" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">PaymentController</text>
    <text x="517" y="176" fill="#475569" font-size="6.1">-payments : PaymentSvc</text>
    <line x1="512" y1="181" x2="628" y2="181" stroke="#cbd5e1"/>
    <text x="517" y="192" fill="#1e293b" font-size="6.2">+list(req, res)</text>
    <text x="517" y="203" fill="#1e293b" font-size="6.2">+decision(req,res,args)</text>
    <text x="517" y="215" fill="#64748b" font-size="5.8">Staff/Admin + CSRF</text>

    <!-- DashboardController -->
    <rect x="638" y="144" width="114" height="80" rx="4" fill="#fff" stroke="#2563eb"/>
    <rect x="638" y="144" width="114" height="20" rx="4" fill="#1e40af"/>
    <text x="695" y="157" fill="#fff" font-size="6.7" font-weight="800" text-anchor="middle">DashboardController</text>
    <text x="643" y="176" fill="#475569" font-size="6.1">-dashboard : DashSvc</text>
    <line x1="638" y1="181" x2="752" y2="181" stroke="#cbd5e1"/>
    <text x="643" y="192" fill="#1e293b" font-size="6.2">+metrics(req, res)</text>
    <text x="643" y="208" fill="#64748b" font-size="5.9">GET /v1/dashboard</text>

    <!-- ROW 3: JsonResponder + 5 SERVICES -->
    <!-- JsonResponder -->
    <rect x="8" y="252" width="116" height="94" rx="4" fill="#faf5ff" stroke="#7c3aed"/>
    <rect x="8" y="252" width="116" height="20" rx="4" fill="#6b21a8"/>
    <text x="66" y="265" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">JsonResponder</text>
    <text x="13" y="285" fill="#1e293b" font-size="6.1">+ok(res, data, code)$ </text>
    <text x="13" y="297" fill="#1e293b" font-size="6.1">+error(res, msg, code)$</text>
    <text x="13" y="314" fill="#5b21b6" font-size="5.9">Standard Envelope:</text>
    <text x="13" y="324" fill="#5b21b6" font-size="5.8">{ok, data|error, meta}</text>
    <text x="13" y="336" fill="#64748b" font-size="5.8">Used by all Controllers</text>

    <!-- AuthService -->
    <rect x="134" y="252" width="116" height="94" rx="4" fill="#f0fdf4" stroke="#16a34a"/>
    <rect x="134" y="252" width="116" height="20" rx="4" fill="#166534"/>
    <text x="192" y="265" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">AuthService</text>
    <text x="139" y="285" fill="#1e293b" font-size="6.1">+getSessionStatus()</text>
    <text x="139" y="295" fill="#475569" font-size="5.9">  : array</text>
    <text x="139" y="312" fill="#166534" font-size="5.9">Returns active role,</text>
    <text x="139" y="322" fill="#166534" font-size="5.9">KYC status, CSRF token</text>

    <!-- RoomService -->
    <rect x="260" y="252" width="116" height="94" rx="4" fill="#f0fdf4" stroke="#16a34a"/>
    <rect x="260" y="252" width="116" height="20" rx="4" fill="#166534"/>
    <text x="318" y="265" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">RoomService</text>
    <text x="265" y="284" fill="#1e293b" font-size="6">+calculateStayQuote()</text>
    <text x="265" y="295" fill="#1e293b" font-size="6">+listRooms(filters)</text>
    <text x="265" y="306" fill="#1e293b" font-size="6">+getRoomById(id)</text>
    <text x="265" y="317" fill="#1e293b" font-size="6">+checkAvailability()</text>
    <text x="265" y="328" fill="#64748b" font-size="6">-formatRoom(row, ok)</text>

    <!-- BookingService -->
    <rect x="386" y="252" width="116" height="94" rx="4" fill="#f0fdf4" stroke="#16a34a"/>
    <rect x="386" y="252" width="116" height="20" rx="4" fill="#166534"/>
    <text x="444" y="265" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">BookingService</text>
    <text x="391" y="285" fill="#1e293b" font-size="6">+listForActor(id, role)</text>
    <text x="391" y="298" fill="#1e293b" font-size="6">+cancelGuestBooking(</text>
    <text x="395" y="308" fill="#1e293b" font-size="6">id, userId, reason)</text>
    <text x="391" y="324" fill="#166534" font-size="5.9">Enforces ownership &amp;</text>
    <text x="391" y="334" fill="#166534" font-size="5.9">writes audit_logs</text>

    <!-- PaymentService -->
    <rect x="512" y="252" width="116" height="94" rx="4" fill="#f0fdf4" stroke="#16a34a"/>
    <rect x="512" y="252" width="116" height="20" rx="4" fill="#166534"/>
    <text x="570" y="265" fill="#fff" font-size="6.8" font-weight="800" text-anchor="middle">PaymentService</text>
    <text x="517" y="285" fill="#1e293b" font-size="6">+listForActor(id, role)</text>
    <text x="517" y="298" fill="#1e293b" font-size="6">+applyDecision(id,</text>
    <text x="521" y="308" fill="#1e293b" font-size="6">decision, actor, role)</text>
    <text x="517" y="324" fill="#b91c1c" font-size="5.9" font-weight="700">BEGIN + FOR UPDATE</text>
    <text x="517" y="334" fill="#166534" font-size="5.9">Atomic Dual-Approval</text>

    <!-- DashboardService -->
    <rect x="638" y="252" width="114" height="94" rx="4" fill="#f0fdf4" stroke="#16a34a"/>
    <rect x="638" y="252" width="114" height="20" rx="4" fill="#166534"/>
    <text x="695" y="265" fill="#fff" font-size="6.7" font-weight="800" text-anchor="middle">DashboardService</text>
    <text x="643" y="285" fill="#1e293b" font-size="6">+getMetricsForActor(</text>
    <text x="647" y="295" fill="#1e293b" font-size="6">actorId, role) : array</text>
    <text x="643" y="314" fill="#166534" font-size="5.9">Role-scoped KPI counts</text>
    <text x="643" y="324" fill="#166534" font-size="5.9">Revenue, Occupancy,</text>
    <text x="643" y="334" fill="#166534" font-size="5.9">Pending KYC/Payments</text>

    <!-- ROW 4: Core Infrastructure & Marvi AI Client -->
    <rect x="8" y="376" width="446" height="90" rx="5" fill="#f8fafc" stroke="#0f172a" stroke-width="1.3"/>
    <rect x="8" y="376" width="446" height="20" rx="5" fill="#0f172a"/>
    <text x="231" y="389" fill="#c5a880" font-size="7.4" font-weight="800" text-anchor="middle">CoreInfrastructure Facade (backend/core/: db, session, functions, rate-limiter, logger, email)</text>
    <text x="16" y="410" fill="#1e293b" font-size="6.4">+getDB() : PDO  |  +dbRow(sql, params)  |  +dbRows(sql, params)  |  +dbExec(sql, params)  |  +dbInsert(sql, params)</text>
    <text x="16" y="422" fill="#1e293b" font-size="6.4">+verifyCsrfToken(token)  |  +requireRole(...roles)  |  +releaseSessionLock()  |  +checkRateLimit(id, action, max, win)</text>
    <text x="16" y="434" fill="#1e293b" font-size="6.4">+isRoomAvailable(roomId, in, out)  |  +datesOverlap(in1, out1, in2, out2)  |  +createNotification(userId, title, msg)</text>
    <text x="16" y="446" fill="#1e293b" font-size="6.4">+auditLog(action, module, details)  |  +sendEmailSMTP(to, subject, htmlBody) [PHPMailer 7]</text>
    <text x="16" y="458" fill="#166534" font-size="6.4" font-weight="700">Direct PDO Layer -&gt; MySQL InnoDB (marvelous_hotel)</text>

    <rect x="466" y="376" width="286" height="90" rx="5" fill="#faf5ff" stroke="#7c3aed" stroke-width="1.3"/>
    <rect x="466" y="376" width="286" height="20" rx="5" fill="#5b21b6"/>
    <text x="609" y="389" fill="#fff" font-size="7.4" font-weight="800" text-anchor="middle">MarviNvidiaClient (backend/ai/nvidia-client.php)</text>
    <text x="474" y="410" fill="#1e293b" font-size="6.3">+resolveNvidiaConfig(overrideModel) : array</text>
    <text x="474" y="421" fill="#1e293b" font-size="6.3">+buildDynamicRoomCatalogContext() : array</text>
    <text x="474" y="432" fill="#1e293b" font-size="6.3">+buildGuestUserContext(userId) : string</text>
    <text x="474" y="443" fill="#1e293b" font-size="6.3">+queryNvidiaAI(msg, opts) / +streamNvidiaAI(msg, cb, opts)</text>
    <text x="474" y="454" fill="#6b21a8" font-size="6.3" font-weight="700">+getNvidiaLastMeta() : array (Failover + Latency Telemetry)</text>
  </svg>
</div>

<div class="callout">
  <strong>Runtime Class Flow (Mutation Sequence):</strong> <code>HotelAPI.decidePayment()</code> &rarr; <code>ApiAppFactory</code> &rarr; <code>JsonBodyParserMiddleware</code> &rarr; <code>RoleAuthMiddleware(['staff','admin'])</code> &rarr; <code>CsrfProtectionMiddleware</code> &rarr; <code>PaymentController::decision()</code> &rarr; <code>PaymentService::applyDecision()</code> (<code>BEGIN</code> + <code>SELECT ... FOR UPDATE</code> + <code>COMMIT</code> + <code>auditLog</code>) &rarr; <code>JsonResponder::ok()</code>.
</div>

<div class="page-break"></div>

<!-- ==================== SECTION 4: DATABASE DICTIONARY ==================== -->
<h1><span class="sec-tag">Section 4</span> Complete Database Data Dictionary (All 16 Tables)</h1>
<p class="intro">
  Ang sumusunod ay ang kumpletong column-by-column Database Dictionary ng <code>marvelous_hotel</code> (MySQL / MariaDB InnoDB, <code>utf8mb4_unicode_ci</code>), 100% tugma sa <code>database/marvelous_hotel.sql</code> at lahat ng 17 migrations (<code>0001</code>–<code>0017</code>).
</p>

<h3 class="tbl-head">Table 1: <code>users</code> — Guest, Staff &amp; Super Admin Accounts, KYC Identity &amp; Auth Security (35 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code></td><td><code>INT(11)</code></td><td>NOT NULL, AI</td><td><span class="k-pk">PK</span></td><td>Unique user account identifier</td></tr>
    <tr><td><code>first_name</code></td><td><code>VARCHAR(100)</code></td><td>NOT NULL</td><td><span class="k-ix">IDX</span></td><td>User first name (indexed with <code>last_name</code>)</td></tr>
    <tr><td><code>middle_name</code></td><td><code>VARCHAR(100)</code></td><td>NULL</td><td>&mdash;</td><td>Optional middle name (Migration 0007)</td></tr>
    <tr><td><code>last_name</code></td><td><code>VARCHAR(100)</code></td><td>NOT NULL</td><td><span class="k-ix">IDX</span></td><td>User last name</td></tr>
    <tr><td><code>suffix</code></td><td><code>VARCHAR(20)</code></td><td>NULL</td><td>&mdash;</td><td>Optional suffix e.g. Jr., III (Migration 0007)</td></tr>
    <tr><td><code>email</code></td><td><code>VARCHAR(191)</code></td><td>NOT NULL</td><td><span class="k-uk">UK</span> <span class="k-ix">IDX</span></td><td>Unique login &amp; notification email</td></tr>
    <tr><td><code>password</code></td><td><code>VARCHAR(255)</code></td><td>NOT NULL</td><td>&mdash;</td><td>Bcrypt password hash</td></tr>
    <tr><td><code>role</code></td><td><code>ENUM('user','staff','admin')</code></td><td>NOT NULL, <code>'user'</code></td><td><span class="k-ix">IDX</span></td><td>Isolated RBAC actor role</td></tr>
    <tr><td><code>phone</code></td><td><code>VARCHAR(20)</code></td><td>NULL</td><td>&mdash;</td><td>Contact mobile number</td></tr>
    <tr><td><code>address</code> / <code>location</code></td><td><code>TEXT</code> (2 cols)</td><td>NULL</td><td>&mdash;</td><td>Full street address &amp; city/province summary</td></tr>
    <tr><td><code>avatar</code></td><td><code>VARCHAR(255)</code></td><td><code>'default-avatar.png'</code></td><td>&mdash;</td><td>Profile avatar image filename</td></tr>
    <tr><td><code>gender</code></td><td><code>ENUM('male','female','other')</code></td><td>NULL</td><td>&mdash;</td><td>Optional guest gender</td></tr>
    <tr><td><code>date_of_birth</code></td><td><code>DATE</code></td><td>NULL</td><td>&mdash;</td><td>Guest birth date</td></tr>
    <tr><td><code>id_type</code> / <code>id_number</code></td><td><code>VARCHAR(50)</code> / <code>VARCHAR(100)</code></td><td>NULL</td><td>&mdash;</td><td>Submitted KYC ID category &amp; ID number</td></tr>
    <tr><td><code>id_document</code></td><td><code>VARCHAR(255)</code></td><td>NULL</td><td>&mdash;</td><td>Private KYC ID file path (via <code>serve-file.php</code>)</td></tr>
    <tr><td><code>status</code></td><td><code>ENUM('active','inactive','banned')</code></td><td>NOT NULL, <code>'active'</code></td><td><span class="k-ix">IDX</span></td><td>Account status toggle</td></tr>
    <tr><td><code>email_verified</code></td><td><code>TINYINT(1)</code></td><td>NOT NULL, <code>0</code></td><td>&mdash;</td><td>Email verification flag</td></tr>
    <tr><td><code>profile_verified</code></td><td><code>TINYINT(1)</code></td><td>NOT NULL, <code>0</code></td><td>&mdash;</td><td>KYC booking-unlock flag (<code>1</code> = verified)</td></tr>
    <tr><td><code>verification_status</code></td><td><code>ENUM('unverified','pending','verified','rejected')</code></td><td>NOT NULL, <code>'unverified'</code></td><td>&mdash;</td><td>KYC workflow state (Migration 0009)</td></tr>
    <tr><td><code>rejection_reason</code></td><td><code>TEXT</code></td><td>NULL</td><td>&mdash;</td><td>Admin KYC rejection note shown to guest</td></tr>
    <tr><td><code>verified_by</code> / <code>verified_at</code></td><td><code>INT(11)</code> / <code>DATETIME</code></td><td>NULL</td><td>Ref <code>users.id</code></td><td>Adjudicating Admin ID &amp; timestamp</td></tr>
    <tr><td><code>verification_submitted_at</code></td><td><code>DATETIME</code></td><td>NULL</td><td>&mdash;</td><td>KYC submission timestamp</td></tr>
    <tr><td><code>verify_token</code></td><td><code>VARCHAR(255)</code></td><td>NULL</td><td>&mdash;</td><td>Email verification token</td></tr>
    <tr><td><code>reset_token</code> / <code>reset_expires</code></td><td><code>VARCHAR(255)</code> / <code>DATETIME</code></td><td>NULL</td><td>&mdash;</td><td>Password reset link token &amp; expiry</td></tr>
    <tr><td><code>two_fa_secret</code> / <code>two_fa_enabled</code></td><td><code>VARCHAR(255)</code> / <code>TINYINT(1)</code></td><td>NULL / <code>0</code></td><td>&mdash;</td><td>Reserved 2FA configuration fields</td></tr>
    <tr><td><code>remember_token</code> / <code>remember_expires</code></td><td><code>VARCHAR(255)</code> / <code>DATETIME</code></td><td>NULL</td><td>&mdash;</td><td>Persistent login token &amp; expiry (Migration 0014)</td></tr>
    <tr><td><code>last_login</code> / <code>created_at</code> / <code>updated_at</code></td><td><code>DATETIME</code> (3 cols)</td><td>NULL / <code>CURRENT_TIMESTAMP</code></td><td>&mdash;</td><td>Last login, creation &amp; update timestamps</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 2: <code>room_types</code> — Accommodation Categories (5 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code></td><td><code>INT(11)</code></td><td>NOT NULL, AI</td><td><span class="k-pk">PK</span></td><td>Room category identifier</td></tr>
    <tr><td><code>name</code></td><td><code>VARCHAR(100)</code></td><td>NOT NULL</td><td>&mdash;</td><td>Category title (Standard, Deluxe, Suite, Dormitory, etc.)</td></tr>
    <tr><td><code>description</code></td><td><code>TEXT</code></td><td>NULL</td><td>&mdash;</td><td>Category description</td></tr>
    <tr><td><code>icon</code></td><td><code>VARCHAR(100)</code></td><td><code>'fa-bed'</code></td><td>&mdash;</td><td>Font Awesome icon class</td></tr>
    <tr><td><code>created_at</code></td><td><code>DATETIME</code></td><td><code>CURRENT_TIMESTAMP</code></td><td>&mdash;</td><td>Record creation timestamp</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 3: <code>rooms</code> — Physical Hotel Rooms &amp; Dormitory Inventory (19 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code></td><td><code>INT(11)</code></td><td>NOT NULL, AI</td><td><span class="k-pk">PK</span></td><td>Unique room/dorm identifier</td></tr>
    <tr><td><code>room_number</code></td><td><code>VARCHAR(20)</code></td><td>NOT NULL</td><td><span class="k-uk">UK</span></td><td>Room code (e.g. <code>101</code>, <code>201</code>, <code>D01</code>, <code>S01</code>)</td></tr>
    <tr><td><code>room_type_id</code></td><td><code>INT(11)</code></td><td>NOT NULL</td><td><span class="k-fk">FK</span> <span class="k-ix">IDX</span></td><td>References <code>room_types(id)</code> (<code>RESTRICT</code>)</td></tr>
    <tr><td><code>name</code> / <code>description</code></td><td><code>VARCHAR(150)</code> / <code>TEXT</code></td><td>NOT NULL / NULL</td><td>&mdash;</td><td>Room title &amp; full marketing copy</td></tr>
    <tr><td><code>floor</code> / <code>capacity</code></td><td><code>INT(5)</code> (2 cols)</td><td>NOT NULL, <code>1</code></td><td>&mdash;</td><td>Floor level &amp; maximum guest capacity</td></tr>
    <tr><td><code>price_per_night</code> / <code>weekend_rate</code></td><td><code>DECIMAL(10,2)</code> (2 cols)</td><td>NOT NULL / NULL</td><td>&mdash;</td><td>Weekday nightly rate &amp; optional weekend rate (PHP)</td></tr>
    <tr><td><code>amenities</code> / <code>tags</code> / <code>images</code></td><td><code>JSON</code> (3 cols)</td><td>NULL</td><td>&mdash;</td><td>JSON arrays for amenities, AI tags &amp; gallery</td></tr>
    <tr><td><code>thumbnail</code></td><td><code>VARCHAR(255)</code></td><td><code>'default-room.jpg'</code></td><td>&mdash;</td><td>Primary card image filename</td></tr>
    <tr><td><code>status</code></td><td><code>ENUM('available','occupied','dirty','maintenance','reserved')</code></td><td>NOT NULL, <code>'available'</code></td><td><span class="k-ix">IDX</span></td><td>Live occupancy &amp; housekeeping state (Migration 0013)</td></tr>
    <tr><td><code>is_dorm</code> / <code>bed_count</code> / <code>beds_available</code></td><td><code>TINYINT(1)</code> / <code>INT(5)</code> (2)</td><td><code>0</code> / NULL</td><td>&mdash;</td><td>Dormitory bedspace flag &amp; bed counters</td></tr>
    <tr><td><code>created_at</code> / <code>updated_at</code></td><td><code>DATETIME</code> (2 cols)</td><td><code>CURRENT_TIMESTAMP</code></td><td>&mdash;</td><td>Creation &amp; status modification timestamps</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 4: <code>bookings</code> — Guest Reservations &amp; Lifecycle Timestamps (24 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code></td><td><code>INT(11)</code></td><td>NOT NULL, AI</td><td><span class="k-pk">PK</span></td><td>Unique booking identifier</td></tr>
    <tr><td><code>booking_code</code></td><td><code>VARCHAR(20)</code></td><td>NOT NULL</td><td><span class="k-uk">UK</span> <span class="k-ix">IDX</span></td><td>Unique voucher code (e.g. <code>MH-2026-XXXX</code>)</td></tr>
    <tr><td><code>user_id</code></td><td><code>INT(11)</code></td><td>NOT NULL</td><td><span class="k-fk">FK</span> <span class="k-ix">IDX</span></td><td>References <code>users(id)</code> (<code>CASCADE</code>)</td></tr>
    <tr><td><code>room_id</code></td><td><code>INT(11)</code></td><td>NOT NULL</td><td><span class="k-fk">FK</span> <span class="k-ix">IDX</span></td><td>References <code>rooms(id)</code> (<code>CASCADE</code>)</td></tr>
    <tr><td><code>check_in</code> / <code>check_out</code></td><td><code>DATE</code> (2 cols)</td><td>NOT NULL</td><td>&mdash;</td><td>Overlap-checked stay window <code>[check_in, check_out)</code></td></tr>
    <tr><td><code>num_guests</code> / <code>nights</code></td><td><code>INT(5)</code> (2 cols)</td><td>NOT NULL, <code>1</code></td><td>&mdash;</td><td>Guest count &amp; calculated nights</td></tr>
    <tr><td><code>subtotal</code> / <code>discount</code> / <code>total_amount</code></td><td><code>DECIMAL(10,2)</code> (3 cols)</td><td>NOT NULL / <code>0.00</code></td><td>&mdash;</td><td>Base amount, promo deduction &amp; net payable</td></tr>
    <tr><td><code>special_requests</code></td><td><code>TEXT</code></td><td>NULL</td><td>&mdash;</td><td>Optional guest stay notes</td></tr>
    <tr><td><code>payment_method</code></td><td><code>ENUM('maya','gcash','cash')</code></td><td>NOT NULL, <code>'maya'</code></td><td>&mdash;</td><td>Chosen settlement channel (Migration 0011)</td></tr>
    <tr><td><code>status</code></td><td><code>ENUM('pending','confirmed','checked_in','checked_out','cancelled','rejected')</code></td><td>NOT NULL, <code>'pending'</code></td><td><span class="k-ix">IDX</span></td><td>Full booking lifecycle state</td></tr>
    <tr><td><code>accepted_by</code> / <code>accepted_at</code></td><td><code>INT(11)</code> / <code>DATETIME</code></td><td>NULL</td><td>Ref <code>users.id</code></td><td>Actor &amp; confirmation time (Migration 0010)</td></tr>
    <tr><td><code>checked_in_at</code> / <code>checked_out_at</code></td><td><code>DATETIME</code> (2 cols)</td><td>NULL</td><td>&mdash;</td><td>Actual arrival &amp; departure frontdesk stamps</td></tr>
    <tr><td><code>cancelled_reason</code> / <code>cancelled_at</code></td><td><code>TEXT</code> / <code>DATETIME</code></td><td>NULL</td><td>&mdash;</td><td>Cancellation reason &amp; timestamp</td></tr>
    <tr><td><code>ai_recommended</code> / <code>source</code></td><td><code>TINYINT(1)</code> / <code>ENUM('user','staff','admin','ai')</code></td><td><code>0</code> / <code>'user'</code></td><td>&mdash;</td><td>Flags AI-assisted bookings &amp; entry channel</td></tr>
    <tr><td><code>created_at</code> / <code>updated_at</code></td><td><code>DATETIME</code> (2 cols)</td><td><code>CURRENT_TIMESTAMP</code></td><td>&mdash;</td><td>Creation &amp; last update timestamps</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 5: <code>payments</code> — Maya / GCash Proofs &amp; Atomic Dual-Approval (13 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code></td><td><code>INT(11)</code></td><td>NOT NULL, AI</td><td><span class="k-pk">PK</span></td><td>Unique payment submission ID</td></tr>
    <tr><td><code>booking_id</code></td><td><code>INT(11)</code></td><td>NOT NULL</td><td><span class="k-fk">FK</span> <span class="k-ix">IDX</span></td><td>References <code>bookings(id)</code> (<code>CASCADE</code>)</td></tr>
    <tr><td><code>user_id</code></td><td><code>INT(11)</code></td><td>NOT NULL</td><td><span class="k-fk">FK</span> <span class="k-ix">IDX</span></td><td>References <code>users(id)</code> (<code>CASCADE</code>)</td></tr>
    <tr><td><code>amount</code></td><td><code>DECIMAL(10,2)</code></td><td>NOT NULL</td><td>&mdash;</td><td>Remitted payment total (PHP)</td></tr>
    <tr><td><code>method</code></td><td><code>ENUM('gcash','bank_transfer','cash','card','maya')</code></td><td>NOT NULL</td><td>&mdash;</td><td>Payment gateway / method used</td></tr>
    <tr><td><code>reference_number</code> / <code>proof_image</code></td><td><code>VARCHAR(100)</code> / <code>VARCHAR(255)</code></td><td>NULL</td><td>&mdash;</td><td>E-wallet ref no &amp; protected receipt file path</td></tr>
    <tr><td><code>status</code></td><td><code>ENUM('pending','verified','rejected','refunded')</code></td><td>NOT NULL, <code>'pending'</code></td><td><span class="k-ix">IDX</span></td><td>Adjudication state (<code>SELECT ... FOR UPDATE</code> locked)</td></tr>
    <tr><td><code>verified_by</code> / <code>verified_at</code></td><td><code>INT(11)</code> / <code>DATETIME</code></td><td>NULL</td><td>Ref <code>users.id</code></td><td>Approving Staff/Admin ID &amp; timestamp</td></tr>
    <tr><td><code>notes</code></td><td><code>TEXT</code></td><td>NULL</td><td>&mdash;</td><td>Reviewer note / rejection reason</td></tr>
    <tr><td><code>created_at</code> / <code>updated_at</code></td><td><code>DATETIME</code> (2 cols)</td><td><code>CURRENT_TIMESTAMP</code></td><td>&mdash;</td><td>Submission &amp; decision timestamps</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 6: <code>reviews</code> — Verified Stay &amp; General Hotel Ratings (9 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code></td><td><code>INT(11)</code></td><td>NOT NULL, AI</td><td><span class="k-pk">PK</span></td><td>Review identifier</td></tr>
    <tr><td><code>user_id</code></td><td><code>INT(11)</code></td><td>NOT NULL</td><td><span class="k-fk">FK</span></td><td>References <code>users(id)</code> (<code>CASCADE</code>)</td></tr>
    <tr><td><code>room_id</code></td><td><code>INT(11)</code></td><td>NULL</td><td><span class="k-fk">FK</span> <span class="k-ix">IDX</span></td><td>References <code>rooms(id)</code> (nullable for general reviews)</td></tr>
    <tr><td><code>booking_id</code></td><td><code>INT(11)</code></td><td>NULL</td><td><span class="k-fk">FK</span> <span class="k-uk">UK</span></td><td>References <code>bookings(id)</code> (max 1 review per booking)</td></tr>
    <tr><td><code>rating</code></td><td><code>TINYINT(1)</code></td><td>NOT NULL</td><td><code>CHECK 1..5</code></td><td>Guest star score (1 to 5)</td></tr>
    <tr><td><code>title</code> / <code>comment</code></td><td><code>VARCHAR(200)</code> / <code>TEXT</code></td><td>NULL / NOT NULL</td><td>&mdash;</td><td>Review headline &amp; feedback body</td></tr>
    <tr><td><code>status</code> / <code>created_at</code></td><td><code>ENUM('pending','approved','hidden')</code> / <code>DATETIME</code></td><td><code>'pending'</code> / NOW</td><td>&mdash;</td><td>Moderation status &amp; submission date</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 7: <code>notifications</code> — Real-Time Portal Alerts (9 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code> / <code>user_id</code></td><td><code>INT(11)</code> (2 cols)</td><td>NOT NULL</td><td><span class="k-pk">PK</span> / <span class="k-fk">FK</span></td><td>Notification ID &amp; recipient <code>users(id)</code></td></tr>
    <tr><td><code>title</code> / <code>message</code></td><td><code>VARCHAR(200)</code> / <code>TEXT</code></td><td>NOT NULL</td><td>&mdash;</td><td>Alert headline &amp; body</td></tr>
    <tr><td><code>type</code></td><td><code>ENUM('booking','payment','review','system','alert','general')</code></td><td><code>'system'</code></td><td>&mdash;</td><td>Visual category badge</td></tr>
    <tr><td><code>icon</code> / <code>link</code></td><td><code>VARCHAR(100)</code> / <code>VARCHAR(255)</code></td><td><code>'fa-bell'</code> / NULL</td><td>&mdash;</td><td>UI icon &amp; clickable portal redirect path</td></tr>
    <tr><td><code>is_read</code> / <code>created_at</code></td><td><code>TINYINT(1)</code> / <code>DATETIME</code></td><td><code>0</code> / NOW</td><td><span class="k-ix">IDX</span></td><td>Unread counter flag &amp; creation time</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 8: <code>password_reset_requests</code> — Live Password Reset Popup Queue (11 Columns, Migrations 0014 &amp; 0016)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code> / <code>user_id</code></td><td><code>INT(11)</code> (2 cols)</td><td>NOT NULL / NULL</td><td><span class="k-pk">PK</span> / <span class="k-fk">FK</span></td><td>Queue ID &amp; linked <code>users(id)</code></td></tr>
    <tr><td><code>email</code> / <code>phone</code></td><td><code>VARCHAR(191)</code> / <code>VARCHAR(30)</code></td><td>NOT NULL / NULL</td><td>&mdash;</td><td>Requesting user's email &amp; phone</td></tr>
    <tr><td><code>request_token</code></td><td><code>VARCHAR(64)</code></td><td>NULL</td><td><span class="k-ix">IDX</span></td><td>SHA-256 session-bound polling token (<code>hash_equals</code>)</td></tr>
    <tr><td><code>status</code> / <code>dispatch_type</code></td><td><code>ENUM('pending','completed','dismissed')</code> / <code>ENUM('password','link')</code></td><td><code>'pending'</code> / NULL</td><td><span class="k-ix">IDX</span></td><td>Resolution status &amp; Admin dispatch mode</td></tr>
    <tr><td><code>new_password_assigned</code></td><td><code>VARCHAR(255)</code></td><td>NULL</td><td>&mdash;</td><td>1-time popup temporary password or reset link token</td></tr>
    <tr><td><code>requested_at</code> / <code>resolved_at</code> / <code>user_notified_at</code></td><td><code>DATETIME</code> (3 cols)</td><td>NOW / NULL / NULL</td><td>&mdash;</td><td>Request time, Admin dispatch time &amp; guest popup acknowledgment</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 9: <code>audit_logs</code> — Immutable Security &amp; Operational Trail (8 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code> / <code>user_id</code></td><td><code>INT(11)</code> (2 cols)</td><td>NOT NULL / NULL</td><td><span class="k-pk">PK</span> / <span class="k-ix">IDX</span></td><td>Log entry ID &amp; acting user ID (non-cascading)</td></tr>
    <tr><td><code>action</code> / <code>module</code></td><td><code>VARCHAR(200)</code> / <code>VARCHAR(100)</code></td><td>NOT NULL</td><td><span class="k-ix">IDX</span></td><td>Operation name &amp; subsystem (<code>auth</code>, <code>payment</code>, etc.)</td></tr>
    <tr><td><code>details</code></td><td><code>TEXT</code></td><td>NULL</td><td>&mdash;</td><td>Contextual event description / payload</td></tr>
    <tr><td><code>ip_address</code> / <code>user_agent</code></td><td><code>VARCHAR(50)</code> / <code>VARCHAR(255)</code></td><td>NULL</td><td>&mdash;</td><td>Client IP &amp; browser fingerprint</td></tr>
    <tr><td><code>created_at</code></td><td><code>DATETIME</code></td><td><code>CURRENT_TIMESTAMP</code></td><td>&mdash;</td><td>Immutable event timestamp</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 10: <code>ai_logs</code> — Marvi AI Transcripts &amp; Security Threat Shield (9 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code> / <code>user_id</code> / <code>session_id</code></td><td><code>INT(11)</code> (2) / <code>VARCHAR(100)</code></td><td>AI / NULL / NULL</td><td><span class="k-pk">PK</span> / <span class="k-ix">IDX</span></td><td>Log ID, guest ID &amp; browser chat session</td></tr>
    <tr><td><code>user_message</code> / <code>ai_response</code></td><td><code>TEXT</code> (2 cols)</td><td>NOT NULL</td><td>&mdash;</td><td>Raw prompt &amp; sanitized Marvi AI reply</td></tr>
    <tr><td><code>intent</code></td><td><code>VARCHAR(100)</code></td><td>NULL</td><td>&mdash;</td><td>Detected intent or active model tag</td></tr>
    <tr><td><code>flagged</code> / <code>flag_reason</code></td><td><code>TINYINT(1)</code> / <code>VARCHAR(255)</code></td><td><code>0</code> / NULL</td><td><span class="k-ix">IDX</span></td><td>Flags blocked prompt-injection / SQLi / XSS attempts</td></tr>
    <tr><td><code>created_at</code></td><td><code>DATETIME</code></td><td><code>CURRENT_TIMESTAMP</code></td><td>&mdash;</td><td>Chat interaction timestamp</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 11: <code>announcements</code> — Role-Targeted Broadcast Notices (8 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code></td><td><code>INT(11)</code></td><td>NOT NULL, AI</td><td><span class="k-pk">PK</span></td><td>Announcement identifier</td></tr>
    <tr><td><code>title</code> / <code>message</code></td><td><code>VARCHAR(200)</code> / <code>TEXT</code></td><td>NOT NULL</td><td>&mdash;</td><td>Notice headline &amp; body</td></tr>
    <tr><td><code>target_role</code> / <code>priority</code></td><td><code>ENUM('all','user','staff','admin')</code> / <code>ENUM('low','normal','high','urgent')</code></td><td><code>'all'</code> / <code>'normal'</code></td><td><span class="k-ix">IDX</span></td><td>Audience scope &amp; visual priority</td></tr>
    <tr><td><code>created_by</code> / <code>expires_at</code> / <code>created_at</code></td><td><code>INT(11)</code> / <code>DATETIME</code> (2)</td><td>NOT NULL / NULL / NOW</td><td>Ref <code>users.id</code></td><td>Authoring Admin, auto-expiry &amp; publish time</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 12: <code>settings</code> — Dynamic Hotel, Payment, SMTP &amp; AI Configuration (5 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code> / <code>key</code></td><td><code>INT(11)</code> / <code>VARCHAR(100)</code></td><td>NOT NULL</td><td><span class="k-pk">PK</span> / <span class="k-uk">UK</span></td><td>Row ID &amp; unique config key (e.g. <code>ai_nvidia_model</code>, <code>smtp_host</code>)</td></tr>
    <tr><td><code>value</code> / <code>description</code> / <code>updated_at</code></td><td><code>TEXT</code> / <code>VARCHAR(255)</code> / <code>DATETIME</code></td><td>NOT NULL / NULL / NOW</td><td>&mdash;</td><td>Runtime value read by <code>getSetting()</code>, label &amp; last update</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Table 13: <code>promos</code> — Discount Vouchers &amp; Campaign Limits (12 Columns)</h3>
<table class="dict">
  <thead><tr><th style="width:19%;">Column</th><th style="width:23%;">Data Type</th><th style="width:17%;">Null / Default</th><th style="width:13%;">Keys</th><th style="width:28%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>id</code> / <code>code</code></td><td><code>INT(11)</code> / <code>VARCHAR(50)</code></td><td>NOT NULL</td><td><span class="k-pk">PK</span> / <span class="k-uk">UK</span></td><td>Promo ID &amp; unique voucher code (e.g. <code>WELCOME10</code>)</td></tr>
    <tr><td><code>description</code></td><td><code>VARCHAR(255)</code></td><td>NULL</td><td>&mdash;</td><td>Promo summary</td></tr>
    <tr><td><code>discount_type</code> / <code>discount_value</code></td><td><code>ENUM('percent','fixed')</code> / <code>DECIMAL(10,2)</code></td><td><code>'percent'</code> / NOT NULL</td><td>&mdash;</td><td>Percent or flat PHP discount amount</td></tr>
    <tr><td><code>min_nights</code> / <code>max_uses</code> / <code>used_count</code></td><td><code>INT(5)</code> / <code>INT(11)</code> (2)</td><td><code>1</code> / NULL / <code>0</code></td><td>&mdash;</td><td>Eligibility minimum stay &amp; redemption counters</td></tr>
    <tr><td><code>valid_from</code> / <code>valid_until</code></td><td><code>DATE</code> (2 cols)</td><td>NULL</td><td>&mdash;</td><td>Campaign start &amp; expiration dates</td></tr>
    <tr><td><code>status</code> / <code>created_at</code></td><td><code>ENUM('active','inactive')</code> / <code>DATETIME</code></td><td><code>'active'</code> / NOW</td><td>&mdash;</td><td>Promo activation toggle &amp; timestamp</td></tr>
  </tbody>
</table>

<h3 class="tbl-head">Tables 14, 15 &amp; 16: <code>contact_messages</code> (6), <code>rate_limits</code> (5) &amp; <code>schema_migrations</code> (2)</h3>
<table class="dict">
  <thead><tr><th style="width:18%;">Table</th><th style="width:20%;">Column</th><th style="width:22%;">Data Type</th><th style="width:13%;">Keys</th><th style="width:27%;">Description</th></tr></thead>
  <tbody>
    <tr><td><code>contact_messages</code></td><td><code>id</code>, <code>name</code>, <code>email</code>, <code>message</code>, <code>ip_address</code>, <code>created_at</code></td><td><code>INT</code>, <code>VARCHAR(100/191/45)</code>, <code>TEXT</code>, <code>DATETIME</code></td><td><span class="k-pk">PK</span> <code>id</code></td><td>Public contact form guest inquiries (Migration 0001)</td></tr>
    <tr><td><code>rate_limits</code></td><td><code>id</code>, <code>key</code>, <code>identifier</code>, <code>action</code>, <code>created_at</code></td><td><code>INT</code>, <code>VARCHAR(255/191/100)</code>, <code>DATETIME</code></td><td><span class="k-pk">PK</span> <span class="k-ix">IDX</span></td><td>Sliding-window login, reset &amp; AI rate limiter (Migration 0014)</td></tr>
    <tr><td><code>schema_migrations</code></td><td><code>migration</code>, <code>applied_at</code></td><td><code>VARCHAR(191)</code>, <code>DATETIME</code></td><td><span class="k-pk">PK</span> <code>migration</code></td><td>Tracks applied SQL migrations <code>0001</code>&ndash;<code>0017</code> via <code>migrate.php</code></td></tr>
  </tbody>
</table>

</body>
</html>
"""


def generate_pdf():
    output_path = "/opt/lampp/htdocs/hotel/docs/MARVELOUS_HOTEL_ERD_CLASS_DICTIONARY_ARCHITECTURE.pdf"
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    print(f"Compiling PDF via WeasyPrint {weasyprint.__version__}...")
    doc = weasyprint.HTML(string=build_html())
    doc.write_pdf(output_path)
    print(f"Successfully generated {output_path} ({os.path.getsize(output_path):,} bytes)")


if __name__ == "__main__":
    generate_pdf()
