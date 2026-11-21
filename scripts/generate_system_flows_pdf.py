#!/usr/bin/env python3
"""
=============================================================================
Marvelous Hotel & Dormitory — Complete System Architecture & System Flows
Document Generator (November 20, 2026 Defense Edition)
Target: docs/marvelous_hotel_system_flows.pdf (Clean Root Policy)
Engine: Python 3 + WeasyPrint
=============================================================================
"""

import os
import weasyprint


def build_html() -> str:
    return """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marvelous Hotel &amp; Dormitory — Complete System Architecture &amp; Flows (Defense Edition)</title>
<style>
  @page {
    size: A4 portrait;
    margin: 13mm 12mm 13mm 12mm;
    @top-left {
      content: "MARVELOUS HOTEL & DORMITORY — 3-TIER ARCHITECTURE & COMPLETE SYSTEM FLOWS";
      font-size: 6.8pt;
      font-weight: 700;
      color: #94a3b8;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      letter-spacing: 0.4px;
    }
    @top-right {
      content: "NOVEMBER 2026 DEFENSE EDITION";
      font-size: 6.8pt;
      font-weight: 700;
      color: #c5a880;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    @bottom-left {
      content: "Tailwind CSS + Alpine.js | Slim 4 PHP 8 | MySQL PDO | Marvi Live AI (NVIDIA NIM SSE)";
      font-size: 6.8pt;
      color: #94a3b8;
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }
    @bottom-right {
      content: "Page " counter(page) " of " counter(pages);
      font-size: 7.2pt;
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
    line-height: 1.36;
    margin: 0;
    padding: 0;
    background: #ffffff;
  }

  .page-container {
    page-break-after: always;
    position: relative;
  }

  .page-container.last-page {
    page-break-after: avoid;
  }

  /* Cover Page */
  .cover-wrapper {
    background: linear-gradient(135deg, #0b1120 0%, #0f172a 48%, #1e293b 100%);
    color: #ffffff;
    min-height: 297mm;
    padding: 20mm 18mm 18mm 18mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .cover-top {
    border-bottom: 2px solid rgba(197, 168, 128, 0.45);
    padding-bottom: 14px;
  }

  .brand-badge {
    display: inline-block;
    background: rgba(197, 168, 128, 0.16);
    border: 1px solid #c5a880;
    color: #e2c9a5;
    padding: 4px 14px;
    font-size: 8pt;
    font-weight: 700;
    border-radius: 20px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin-bottom: 12px;
  }

  .cover-title {
    font-size: 25pt;
    font-weight: 900;
    color: #ffffff;
    line-height: 1.15;
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
  }

  .cover-title span { color: #c5a880; }

  .cover-subtitle {
    font-size: 10.5pt;
    color: #94a3b8;
    line-height: 1.42;
    margin-bottom: 14px;
  }

  .cover-badges-grid {
    display: table;
    width: 100%;
    margin-top: 10px;
  }

  .cover-badge-item {
    display: table-cell;
    padding: 4px 3px;
    width: 16.66%;
    text-align: center;
  }

  .cover-pill {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 7px;
    padding: 7px 5px;
    font-size: 7pt;
    color: #f1f5f9;
    font-weight: 600;
  }

  .cover-pill .pill-k {
    display: block;
    color: #c5a880;
    font-size: 8pt;
    margin-bottom: 3px;
    font-weight: 800;
  }

  .cover-main-box {
    background: rgba(15, 23, 42, 0.72);
    border: 1px solid rgba(197, 168, 128, 0.32);
    border-radius: 10px;
    padding: 12px 15px;
    margin: 10px 0;
  }

  .cover-main-box h3 {
    color: #c5a880;
    margin: 0 0 6px 0;
    font-size: 9pt;
    text-transform: uppercase;
    letter-spacing: 0.9px;
  }

  .cover-main-box p {
    color: #cbd5e1;
    font-size: 7.8pt;
    line-height: 1.4;
    margin: 0 0 6px 0;
  }

  .cover-toc-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
  }

  .cover-toc-table th {
    background: rgba(197, 168, 128, 0.22);
    color: #e2c9a5;
    font-size: 7pt;
    padding: 4px 6px;
    text-align: left;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }

  .cover-toc-table td {
    padding: 3.2px 6px;
    font-size: 7pt;
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .cover-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.16);
    padding-top: 10px;
    display: table;
    width: 100%;
  }

  .cover-footer-col {
    display: table-cell;
    width: 33.33%;
    vertical-align: top;
    font-size: 7.3pt;
    color: #94a3b8;
  }

  .cover-footer-col strong {
    color: #ffffff;
    display: block;
    font-size: 8.2pt;
    margin-bottom: 2px;
  }

  /* Content Typography */
  h1 {
    font-size: 13pt;
    color: #0f172a;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    border-bottom: 1.8px solid #c5a880;
    padding-bottom: 4px;
  }

  .section-tag {
    background: #0f172a;
    color: #c5a880;
    font-size: 6.8pt;
    padding: 2px 7px;
    border-radius: 4px;
    margin-right: 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .intro-text {
    font-size: 7.7pt;
    color: #475569;
    margin: 4px 0 8px 0;
    line-height: 1.36;
  }

  h2 {
    font-size: 9pt;
    color: #0f172a;
    margin: 7px 0 4px 0;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
  }

  table.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 7px;
    font-size: 7.1pt;
  }

  table.data-table th {
    background: #0f172a;
    color: #f8fafc;
    padding: 4px 6px;
    font-weight: 700;
    text-align: left;
    border: 1px solid #334155;
  }

  table.data-table td {
    padding: 4px 6px;
    border: 1px solid #cbd5e1;
    color: #1e293b;
    vertical-align: top;
  }

  table.data-table tr:nth-child(even) { background: #f8fafc; }

  .badge {
    display: inline-block;
    padding: 1.5px 5px;
    border-radius: 3px;
    font-size: 6.3pt;
    font-weight: 700;
    text-transform: uppercase;
  }

  .badge-admin { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
  .badge-staff { background: #e0f2fe; color: #075985; border: 1px solid #38bdf8; }
  .badge-guest { background: #dcfce7; color: #166534; border: 1px solid #4ade80; }
  .badge-ai    { background: #f3e8ff; color: #6b21a8; border: 1px solid #c084fc; }
  .badge-gold  { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }

  .diagram-container {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 7px 9px;
    margin-bottom: 7px;
  }

  .diagram-title {
    font-size: 7.7pt;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
    border-bottom: 1px dashed #cbd5e1;
    padding-bottom: 3px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .diagram-title span.badge-pill {
    background: #c5a880;
    color: #0f172a;
    font-size: 6.2pt;
    padding: 1px 6px;
    border-radius: 10px;
  }

  .arch-grid {
    display: table;
    width: 100%;
    margin-bottom: 4px;
  }

  .arch-col {
    display: table-cell;
    padding: 3px;
    vertical-align: top;
  }

  .card-box {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    padding: 6px 7px;
  }

  .card-box.primary { border-top: 3px solid #2563eb; }
  .card-box.warning { border-top: 3px solid #f59e0b; }
  .card-box.success { border-top: 3px solid #10b981; }
  .card-box.danger  { border-top: 3px solid #ef4444; }
  .card-box.gold    { border-top: 3px solid #c5a880; }
  .card-box.purple  { border-top: 3px solid #8b5cf6; }

  .card-header {
    font-weight: 700;
    font-size: 7.4pt;
    color: #0f172a;
    margin-bottom: 3px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .card-body {
    font-size: 6.9pt;
    color: #334155;
    line-height: 1.35;
  }

  .step-node {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-left: 3.5px solid #0f172a;
    border-radius: 4px;
    padding: 4.5px 7px;
    margin-bottom: 4px;
    font-size: 7pt;
  }

  .step-node.green { border-left-color: #10b981; }
  .step-node.blue  { border-left-color: #2563eb; }
  .step-node.gold  { border-left-color: #c5a880; }
  .step-node.red   { border-left-color: #ef4444; }
  .step-node.amber { border-left-color: #f59e0b; }
  .step-node.purple { border-left-color: #8b5cf6; }

  .step-node strong { color: #0f172a; font-weight: 700; }

  .step-subtext {
    color: #64748b;
    font-size: 6.6pt;
    margin-top: 1px;
  }

  .flow-arrow-down {
    text-align: center;
    color: #64748b;
    font-weight: 800;
    font-size: 8.5pt;
    line-height: 1;
    margin: 1px 0 3px 0;
  }

  .pipeline-step {
    display: table-cell;
    padding: 3px;
    text-align: center;
    vertical-align: top;
  }

  .pipeline-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    padding: 5px 4px;
  }

  .pipeline-num {
    display: inline-block;
    width: 15px;
    height: 15px;
    line-height: 15px;
    background: #0f172a;
    color: #c5a880;
    border-radius: 50%;
    font-size: 6.5pt;
    font-weight: bold;
    margin-bottom: 2px;
  }

  code {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    padding: 0.5px 3px;
    border-radius: 3px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 6.7pt;
    font-weight: 600;
  }

  .callout-box {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-left: 3.5px solid #d97706;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 7pt;
    color: #92400e;
    margin-bottom: 6px;
  }

  .callout-box.info {
    background: #eff6ff;
    border-color: #bfdbfe;
    border-left-color: #2563eb;
    color: #1e40af;
  }

  .callout-box.success {
    background: #f0fdf4;
    border-color: #bbf7d0;
    border-left-color: #16a34a;
    color: #166534;
  }

  .callout-box.purple {
    background: #faf5ff;
    border-color: #e9d5ff;
    border-left-color: #7c3aed;
    color: #5b21b6;
  }

  .state-chip {
    display: inline-block;
    padding: 3px 7px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 6.8pt;
    border: 1px solid #cbd5e1;
    background: #ffffff;
  }
</style>
</head>
<body>

<!-- ========================================================================= -->
<!-- PAGE 1: COVER PAGE                                                        -->
<!-- ========================================================================= -->
<div class="page-container">
  <div class="cover-wrapper">
    <div class="cover-top">
      <div class="brand-badge">Capstone Defense System Architecture &amp; Flows</div>
      <h1 class="cover-title" style="border:none; padding:0; display:block;">MARVELOUS HOTEL &amp; DORMITORY<br><span>COMPLETE SYSTEM FLOWS</span></h1>
      <div class="cover-subtitle">
        Framework-Powered 3-Tier Architecture (<code>frontend/</code> &bull; <code>backend/</code> &bull; <code>database/</code>), Marvi Live SSE + Voice AI, Slim 4 REST API, Live Password Reset Popup, Atomic Dual-Approval Engine &amp; Automated Housekeeping State Machine
      </div>

      <div class="cover-badges-grid">
        <div class="cover-badge-item">
          <div class="cover-pill">
            <span class="pill-k">TIER 1 UI</span>
            Tailwind CSS v3<br>Alpine.js 3 + Voice
          </div>
        </div>
        <div class="cover-badge-item">
          <div class="cover-pill">
            <span class="pill-k">TIER 2 API</span>
            PHP 8 + Slim 4<br>PSR-4 / PSR-15
          </div>
        </div>
        <div class="cover-badge-item">
          <div class="cover-pill">
            <span class="pill-k">TIER 3 DB</span>
            MySQL InnoDB<br>14 Tables / 17 Mig.
          </div>
        </div>
        <div class="cover-badge-item">
          <div class="cover-pill">
            <span class="pill-k">MARVI AI</span>
            NVIDIA NIM SSE<br>Tri-Role + Failover
          </div>
        </div>
        <div class="cover-badge-item">
          <div class="cover-pill">
            <span class="pill-k">SECURITY</span>
            3 Isolated Sessions<br>CSRF + File Proxy
          </div>
        </div>
        <div class="cover-badge-item">
          <div class="cover-pill">
            <span class="pill-k">QA SUITE</span>
            PHPUnit 10<br>32/32 Tests Pass
          </div>
        </div>
      </div>
    </div>

    <div class="cover-main-box">
      <h3>Executive Summary &amp; Architecture Scope</h3>
      <p>This Capstone Defense manual documents the complete languages, frameworks, 3-tier folder boundaries, and end-to-end operational flows of the <strong>Marvelous Hotel &amp; Dormitory Booking System</strong> across all four actors: <strong>User (Guest)</strong>, <strong>Frontdesk Staff</strong>, <strong>Super Admin</strong>, and <strong>Marvi Live AI</strong>.</p>
      <p>Every module documented here is verified live on Apache 2.4 + MySQL InnoDB + Composer PSR-4, backed by 32 passing PHPUnit 10 unit/integration tests (185 assertions) and clean root `.htaccess` routing with HTTP 403 internal directory isolation.</p>

      <table class="cover-toc-table">
        <thead>
          <tr>
            <th style="width: 11%;">Page</th>
            <th style="width: 44%;">Section &amp; Core Flow</th>
            <th style="width: 45%;">Technical Components Covered</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 1</td>
            <td>Executive Cover &amp; System Blueprint</td>
            <td>High-level 3-tier overview, badges, and defense table of contents.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 2</td>
            <td>Complete Languages, Frameworks &amp; 3-Tier Directory Flow</td>
            <td>Frontend, Backend, AI, Database &amp; QA stack + Apache clean routing.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 3</td>
            <td>Authentication, Isolated Sessions &amp; Live Reset Popup Flow</td>
            <td>MH_USER/STAFF/ADMIN cookies, Bcrypt, CSRF, SHA-256 3s reset modal.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 4</td>
            <td>User / Guest End-to-End Booking &amp; KYC Flow</td>
            <td>Alpine + Slim 4 availability check, KYC ID gate, Maya/GCash &amp; reviews.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 5</td>
            <td>Atomic Payment Verification &amp; Dual-Approval Flow</td>
            <td>Protected file proxy, <code>SELECT FOR UPDATE</code> lock, Staff/Admin sync.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 6</td>
            <td>Frontdesk Staff Operations &amp; Housekeeping State Machine</td>
            <td>Least-privilege desk ops, check-in/out timestamps, <code>dirty</code> room cycle.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 7</td>
            <td>Super Admin Master Governance &amp; Full RBAC Matrix</td>
            <td>KYC lightbox, live reset dispatcher, SMTP/AI config, immutable audit trail.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 8</td>
            <td>Marvi Live AI Flow (SSE Streaming, Voice &amp; Tri-Role Engine)</td>
            <td><code>nemotron-3.5-bolt-e4b</code> failover chain, bad-intent guard, action cards.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 9</td>
            <td>Database Schema (14 Tables) &amp; Slim 4 REST API v1 Catalog</td>
            <td>PDO prepared statements, 17 migrations, 10 REST endpoints &amp; PSR-15 stack.</td>
          </tr>
          <tr>
            <td style="font-weight:bold; color:#c5a880;">Page 10</td>
            <td>Cross-Actor Collaboration, QA Proof &amp; Defense Deployment</td>
            <td>PHPUnit 10 (32/32 tests), Cloudflare/Ngrok tunnel, Docker &amp; credentials.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="cover-footer">
      <div class="cover-footer-col">
        <strong>FRONTEND &amp; BACKEND STACK</strong>
        HTML5, CSS3, ES6+ JS, Tailwind v3, Alpine 3<br>
        PHP 8, Slim 4 (PSR-4/15), PHPMailer 7
      </div>
      <div class="cover-footer-col">
        <strong>DATABASE &amp; AI ENGINE</strong>
        MySQL / MariaDB InnoDB (PDO SQL)<br>
        NVIDIA NIM Live SSE + Voice Mode
      </div>
      <div class="cover-footer-col" style="text-align: right;">
        <strong>EDITION &amp; VERIFICATION</strong>
        November 20, 2026 • Capstone Defense<br>
        PHPUnit 10: 32/32 Passed (185 Assertions)
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- PAGE 2: LANGUAGES, FRAMEWORKS & 3-TIER ARCHITECTURE                       -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 1</span> Technology Stack, Frameworks &amp; Clean 3-Tier Architecture</h1>
  <div class="intro-text">
    The codebase is strictly consolidated into three cleanly separated architectural tiers (<code>frontend/</code>, <code>backend/</code>, <code>database/</code>) with zero loose PHP scripts at the root. Apache <code>.htaccess</code> maps clean URLs internally while blocking direct HTTP access (403 Forbidden) to sensitive backend libraries, database schemas, and private uploads.
  </div>

  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 17%;">Tier / Folder</th>
        <th style="width: 18%;">Languages</th>
        <th style="width: 31%;">Frameworks &amp; Core Libraries</th>
        <th style="width: 34%;">Role &amp; Defense Justification</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Tier 1: Frontend</strong><br><code>frontend/</code></td>
        <td>HTML5, CSS3, JavaScript (ES6+)</td>
        <td><strong>Tailwind CSS v3.4</strong> (<code>preflight: false</code>, Navy/Gold tokens), <strong>Alpine.js v3.14</strong> (offline bundle), Font Awesome 6, <code>HotelAPI</code>, Web Speech API</td>
        <td>Zero-build-server reactive UX: live room availability checks, real-time SSE AI chat bubbles, Voice Mic/TTS, and live password reset popup modal.</td>
      </tr>
      <tr>
        <td><strong>Tier 2: Backend</strong><br><code>backend/</code></td>
        <td>PHP 8 (JSON &amp; SSE Streams)</td>
        <td><strong>Slim 4 Framework</strong> (<code>slim/slim</code>, <code>slim/psr7</code>, FastRoute), <strong>PSR-4</strong> (<code>MarvelousHotel\\</code>), <strong>PSR-15 Middleware</strong>, Composer, <strong>PHPMailer 7</strong></td>
        <td>Structured REST API (<code>/backend/api/v1/*</code>), Controller &rarr; Service separation, RBAC &amp; CSRF middleware, isolated portal sessions, and Gmail SMTP dispatch.</td>
      </tr>
      <tr>
        <td><strong>AI Engine</strong><br><code>backend/ai/</code></td>
        <td>PHP 8, JSON, SSE</td>
        <td><strong>NVIDIA NIM API</strong> (<code>nvidia/nemotron-3.5-bolt-e4b</code> primary + 2 failover models) &amp; local bilingual rule engine</td>
        <td>Sub-second token streaming concierge, multi-turn memory, interactive Room Cards, Staff Co-Pilot, Admin Executive BI, and 0% downtime local fallback.</td>
      </tr>
      <tr>
        <td><strong>Tier 3: Database</strong><br><code>database/</code></td>
        <td>SQL (DDL &amp; DML)</td>
        <td><strong>MySQL / MariaDB InnoDB</strong> (<code>utf8mb4_unicode_ci</code>), <strong>PHP PDO</strong> Prepared Statements, 14 Tables, 17 SQL Migrations</td>
        <td>ACID transactions with row-level locking (<code>SELECT ... FOR UPDATE</code>), zero SQL injection risk, overlap prevention, and reproducible migration ledger.</td>
      </tr>
      <tr>
        <td><strong>QA &amp; DevOps</strong><br><code>tests/</code>, <code>scripts/</code></td>
        <td>PHP 8, Bash, Apache Conf</td>
        <td><strong>PHPUnit 10</strong> (32 tests / 185 assertions), Apache <code>mod_rewrite</code>, XAMPP / Docker, Cloudflare &amp; Ngrok Tunnel</td>
        <td>Automated Controller/Service/Middleware regression testing and instant mobile-ready live defense sharing via <code>share-capstone.sh</code>.</td>
      </tr>
    </tbody>
  </table>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>3-Tier Request &amp; Data Flow Blueprint</span>
      <span class="badge-pill">Clean Root Consolidation</span>
    </div>

    <div class="arch-grid">
      <div class="arch-col" style="width: 33.3%;">
        <div class="card-box primary">
          <div class="card-header">
            <span>TIER 1: FRONTEND</span>
            <span class="badge badge-guest">frontend/</span>
          </div>
          <div class="card-body">
            &bull; <code>pages/</code>: index, rooms, about, contact, 404, 500<br>
            &bull; <code>auth/</code>: login, register, forgot/reset, verify-email<br>
            &bull; <code>user/</code>: 12 guest portal pages + Marvi widget<br>
            &bull; <code>staff/</code>: 6 frontdesk operational pages<br>
            &bull; <code>admin/</code>: 10 super admin governance pages<br>
            &bull; <code>components/</code>: frameworks loader, nav, AI chat, reset modal<br>
            &bull; <code>assets/</code>: compiled <code>tailwind.css</code>, offline <code>alpine.min.js</code>, <code>api-client.js</code>
          </div>
        </div>
      </div>

      <div class="arch-col" style="width: 33.3%;">
        <div class="card-box gold">
          <div class="card-header">
            <span>TIER 2: BACKEND</span>
            <span class="badge badge-gold">backend/</span>
          </div>
          <div class="card-body">
            &bull; <code>api/index.php</code>: Slim 4 REST Front Controller<br>
            &bull; <code>src/ApiAppFactory.php</code>: 10 REST v1 endpoints<br>
            &bull; <code>src/Middleware/</code>: JSON Parser, RoleAuth, CSRF<br>
            &bull; <code>src/Controllers/</code> &amp; <code>Services/</code>: PSR-4 domain logic<br>
            &bull; <code>core/</code> (403 blocked): config, db, session, email, logger, rate-limiter<br>
            &bull; <code>ai/</code>: nvidia-client, chatbot, staff-assistant, admin-insights<br>
            &bull; <code>endpoints/</code>: serve-file, check-reset-status, check-name, logout
          </div>
        </div>
      </div>

      <div class="arch-col" style="width: 33.3%;">
        <div class="card-box success">
          <div class="card-header">
            <span>TIER 3: DATABASE</span>
            <span class="badge badge-staff">database/ (403)</span>
          </div>
          <div class="card-body">
            &bull; <code>marvelous_hotel.sql</code>: Master schema &amp; seeds<br>
            &bull; <code>migrations/</code>: <code>0001</code> &rarr; <code>0017</code> incremental SQL<br>
            &bull; <code>migrate.php</code>: Canonical CLI migration runner<br>
            &bull; <code>ERD.md</code>: 14-table Mermaid ERD &amp; dictionary<br>
            &bull; <code>.htaccess</code>: Strict Deny From All (HTTP 403)<br>
            &bull; <code>uploads/</code> (403): Private KYC IDs &amp; payment proofs served only via <code>serve-file.php</code>
          </div>
        </div>
      </div>
    </div>

    <div class="callout-box info" style="margin-bottom:0; margin-top:4px;">
      <strong>Apache Clean Internal Routing (<code>/.htaccess</code>):</strong> External URLs stay clean and backwards-compatible (<code>/</code>, <code>/rooms.php</code>, <code>/user/*</code>, <code>/staff/*</code>, <code>/admin/*</code>, <code>/auth/*</code>, <code>/ai/*</code>, <code>/serve-file.php</code>, <code>/backend/api/v1/*</code>) while physical files remain cleanly grouped inside <code>frontend/</code> and <code>backend/</code>.
    </div>
  </div>

  <div class="callout-box success">
    <strong>Defense Key Point:</strong> Separating presentation (<code>frontend/</code>), API/business logic (<code>backend/</code>), and persistence (<code>database/</code>) proves true software engineering discipline—each layer can be tested, scaled, or replaced independently without breaking the others.
  </div>
</div>

<!-- ========================================================================= -->
<!-- PAGE 3: AUTH, ISOLATED SESSIONS & LIVE PASSWORD RESET POPUP               -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 2</span> Authentication, Isolated Sessions &amp; Live Password Reset Popup</h1>
  <div class="intro-text">
    To eliminate session collisions and privilege escalation across portals, Marvelous Hotel enforces three isolated session cookies alongside Bcrypt hashing, sliding-window rate limiting, CSRF protection, and a real-time session-bound Password Reset Popup that works even when email delivery is delayed.
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>3-Actor Isolated Session &amp; Authentication Pipeline</span>
      <span class="badge-pill">Zero Cross-Portal Bleed</span>
    </div>

    <div class="arch-grid">
      <div class="arch-col" style="width: 33.3%;">
        <div class="card-box success">
          <div class="card-header"><span>GUEST PORTAL</span><span class="badge badge-guest">MH_USER_SESS</span></div>
          <div class="card-body">
            &bull; Entry: <code>/auth/login.php</code> &amp; <code>/auth/register.php</code><br>
            &bull; Live Name Check: <code>check-name.php</code> prevents duplicate full-name accounts<br>
            &bull; Email Verification: Gmail SMTP token via <code>verify-email.php</code><br>
            &bull; Guard: <code>requireRole('user')</code> + Remember-Me SHA-256 selector
          </div>
        </div>
      </div>
      <div class="arch-col" style="width: 33.3%;">
        <div class="card-box primary">
          <div class="card-header"><span>FRONTDESK STAFF</span><span class="badge badge-staff">MH_STAFF_SESS</span></div>
          <div class="card-body">
            &bull; Entry: <code>/staff/login.php</code> (Closed registration — provisioned by Admin only)<br>
            &bull; Guard: <code>requireRole('staff')</code> rejects guest/unauthenticated access<br>
            &bull; API Context: Sends <code>X-Portal: staff</code> to Slim 4 API endpoints<br>
            &bull; Audit: Every login, check-in, and approval written to <code>audit_logs</code>
          </div>
        </div>
      </div>
      <div class="arch-col" style="width: 33.3%;">
        <div class="card-box danger">
          <div class="card-header"><span>SUPER ADMIN</span><span class="badge badge-admin">MH_ADMIN_SESS</span></div>
          <div class="card-body">
            &bull; Entry: <code>/admin/login.php</code> (Dedicated credentials &amp; rate-limit bucket)<br>
            &bull; Guard: <code>requireRole('admin')</code> blocks all staff/guest escalations<br>
            &bull; Concurrent Demo: Can stay logged in simultaneously with Staff &amp; Guest tabs<br>
            &bull; Security: <code>session_regenerate_id(true)</code> on every successful login
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>Live Password Reset Popup Flow (Zero-Email-Delay Delivery)</span>
      <span class="badge-pill">Migration 0016 • SHA-256 Bound</span>
    </div>

    <div class="arch-grid">
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">1</div>
          <div style="font-weight:700; font-size:7pt;">Guest Requests Reset</div>
          <div class="step-subtext"><code>forgot-password.php</code> cleans stale pending rows, creates row + SHA-256 <code>request_token</code> stored in guest session, PRG redirects to persistent waiting card.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">2</div>
          <div style="font-weight:700; font-size:7pt;">3s Non-Blocking Poll</div>
          <div class="step-subtext"><code>reset-status-modal.php</code> polls <code>check-reset-status.php</code> every 3s. Calls <code>releaseSessionLock()</code> so navigation never hangs; validates 15-min TTL.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">3</div>
          <div style="font-weight:700; font-size:7pt;">Admin Dispatches</div>
          <div class="step-subtext">In <code>admin/users.php</code>, Admin clicks <strong>Send Temp Password</strong> or <strong>Send Reset Link</strong>. Resolves all pending rows for user &amp; sends Gmail SMTP (with graceful offline fallback).</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">4</div>
          <div style="font-weight:700; font-size:7pt;">Instant 1-Time Popup</div>
          <div class="step-subtext">Modal auto-opens on guest's current page showing masked temporary password (Show/Hide + Copy + Proceed to Login with prefilled email) OR direct Reset Link button.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">5</div>
          <div style="font-weight:700; font-size:7pt;">Acknowledge &amp; Sync</div>
          <div class="step-subtext">Guest clicks dismiss/proceed &rarr; CSRF-verified POST stamps <code>user_notified_at</code> &amp; clears watch. Admin queue updates live: <em>Waiting for user</em> &rarr; <strong>Viewed by user</strong>.</div>
        </div>
      </div>
    </div>
  </div>

  <h2>Security Hardening Controls</h2>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 24%;">Security Mechanism</th>
        <th style="width: 34%;">Implementation Location</th>
        <th style="width: 42%;">Threat Mitigated</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>CSRF Synchronization Token</strong></td>
        <td><code>backend/core/session.php</code> + <code>CsrfProtectionMiddleware</code></td>
        <td>Blocks Cross-Site Request Forgery on all HTML forms and Slim 4 state-changing POST endpoints (<code>X-CSRF-Token</code>).</td>
      </tr>
      <tr>
        <td><strong>Sliding-Window Rate Limiter</strong></td>
        <td><code>backend/core/rate-limiter.php</code> &rarr; <code>rate_limits</code> table</td>
        <td>Stops credential stuffing on login portals, spam reset requests, and AI API quota abuse.</td>
      </tr>
      <tr>
        <td><strong>Constant-Time Token Checks</strong></td>
        <td><code>hash_equals()</code> in <code>check-reset-status.php</code> &amp; CSRF</td>
        <td>Prevents timing side-channel attacks on password reset watch tokens and CSRF tokens.</td>
      </tr>
      <tr>
        <td><strong>Protected File Proxy</strong></td>
        <td><code>backend/endpoints/serve-file.php</code> + <code>uploads/.htaccess</code></td>
        <td>Prevents unauthorized direct downloads of guest Government/Student IDs and payment receipts.</td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ========================================================================= -->
<!-- PAGE 4: USER / GUEST END-TO-END FLOW                                      -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 3</span> User / Guest End-to-End Lifecycle &amp; Booking Flow</h1>
  <div class="intro-text">
    The Guest Portal (<code>frontend/pages/</code> &amp; <code>frontend/user/</code>) provides a guided booking funnel enhanced by Alpine.js + Slim 4 live room availability checks, strict KYC identity gating for both Hotel Guests and Dormitory Students, QR settlement, and post-stay verified reviews.
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>End-to-End Guest Journey Flowchart</span>
      <span class="badge-pill">MH_USER_SESS • KYC Gated</span>
    </div>

    <div class="arch-grid">
      <div class="arch-col" style="width: 50%;">
        <div class="step-node green">
          <strong>Step 1: Discovery &amp; Live Availability Check</strong>
          <div class="step-subtext">Guest browses <code>/</code> &amp; <code>/rooms.php</code> (or asks <strong>Marvi Live AI</strong> / <code>user/ai-recommend.php</code>). Alpine.js widget calls Slim 4 <code>GET /backend/api/v1/availability</code> to calculate nights, weekend pricing, and real-time room conflict status before login.</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node blue">
          <strong>Step 2: Account Registration &amp; Verification</strong>
          <div class="step-subtext"><code>/auth/register.php</code> validates unique full name (<code>check-name.php</code>), password strength, and sends Gmail verification link (<code>verify-email.php</code>). Guest logs into <code>/user/dashboard.php</code>.</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node amber">
          <strong>Step 3: KYC Identity Gate (<code>/user/profile.php</code>)</strong>
          <div class="step-subtext">Guest uploads valid <strong>Government ID</strong> (Passport, PhilID, UMID, Driver's License) or <strong>Student/School ID</strong> (for Dormitory stays) + ID Number. Status transitions: <code>unverified</code> &rarr; <code>pending</code> &rarr; Admin reviews via lightbox &rarr; <code>verified</code> (unlocks booking) or <code>rejected</code> (with reason).</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node gold">
          <strong>Step 4: Booking Submission &amp; Overlap Lock (<code>/user/booking.php</code>)</strong>
          <div class="step-subtext">Only KYC-verified guests can submit. Alpine.js verifies live availability via Slim 4; server re-verifies <code>[check_in, check_out)</code> overlap atomically against active <code>bookings</code> rows and generates a unique <code>booking_code</code> (<code>status = pending</code>).</div>
        </div>
      </div>

      <div class="arch-col" style="width: 50%;">
        <div class="step-node purple">
          <strong>Step 5: Maya / GCash QR Payment (<code>/user/payment.php</code>)</strong>
          <div class="step-subtext">Displays exact payable amount and official Maya / GCash QR code configured by Super Admin. Guest uploads receipt image (stored privately in <code>uploads/payments/</code>) and submits Reference Number (<code>payments.status = pending</code>).</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node green">
          <strong>Step 6: Confirmation &amp; Digital Voucher (<code>/user/booking-detail.php</code>)</strong>
          <div class="step-subtext">Once Staff or Admin verifies the receipt, booking automatically transitions to <code>confirmed</code>, guest receives in-app notification + Gmail receipt, and printable Official Booking Voucher unlocks.</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node blue">
          <strong>Step 7: Stay Execution &amp; Self-Service Tracking</strong>
          <div class="step-subtext">Guest presents Voucher at frontdesk for Check-In (<code>checked_in</code>) and Check-Out (<code>checked_out</code>). Guest can track history in <code>my-bookings.php</code>, <code>payment-history.php</code>, and cancel pending bookings via Slim 4 <code>POST /v1/bookings/{id}/cancel</code>.</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node gold">
          <strong>Step 8: Verified Stay Reviews (<code>/user/reviews.php</code>)</strong>
          <div class="step-subtext">Completed guests submit 1&ndash;5 star room ratings and comments (plus general hotel reviews), feeding live satisfaction scores back into the room catalog and Marvi AI recommendations.</div>
        </div>
      </div>
    </div>
  </div>

  <h2>Guest Portal Module Map (12 Pages — All Embedded with Marvi Live AI)</h2>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 26%;">Route</th>
        <th style="width: 28%;">Physical File (Tier 1)</th>
        <th style="width: 46%;">Primary Function &amp; Data Flow</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>/user/dashboard.php</code></td><td><code>frontend/user/dashboard.php</code></td><td>Guest KPIs, active bookings summary, KYC alert banner, quick actions</td></tr>
      <tr><td><code>/user/booking.php</code></td><td><code>frontend/user/booking.php</code></td><td>Alpine.js + Slim 4 live availability checker &amp; atomic reservation creator</td></tr>
      <tr><td><code>/user/payment.php</code></td><td><code>frontend/user/payment.php</code></td><td>Maya/GCash QR display, receipt upload, reference number validation</td></tr>
      <tr><td><code>/user/my-bookings.php</code></td><td><code>frontend/user/my-bookings.php</code></td><td>Reservation list, status badges, CSRF-protected pending cancellation</td></tr>
      <tr><td><code>/user/booking-detail.php</code></td><td><code>frontend/user/booking-detail.php</code></td><td>Full stay breakdown, payment receipt status, printable official voucher</td></tr>
      <tr><td><code>/user/profile.php</code></td><td><code>frontend/user/profile.php</code></td><td>Personal info, password change, Government/Student KYC ID upload</td></tr>
      <tr><td><code>/user/ai-recommend.php</code></td><td><code>frontend/user/ai-recommend.php</code></td><td>Budget, capacity, and amenity scoring Smart Room Matcher</td></tr>
      <tr><td><code>/user/reviews.php</code> &amp; <code>notifications.php</code></td><td><code>frontend/user/reviews.php</code>, <code>notifications.php</code></td><td>Verified post-stay ratings and real-time KYC/payment status alerts</td></tr>
    </tbody>
  </table>
</div>

<!-- ========================================================================= -->
<!-- PAGE 5: ATOMIC PAYMENT SETTLEMENT & DUAL-APPROVAL ENGINE                  -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 4</span> Payment Settlement &amp; Synchronized Atomic Dual-Approval</h1>
  <div class="intro-text">
    Both Super Admin (<code>/admin/payments.php</code>) and delegated Frontdesk Staff (<code>/staff/payments.php</code>) can verify Maya and GCash payment proofs—either via portal UI or via Slim 4 REST API (<code>POST /backend/api/v1/payments/{id}/decision</code>). To guarantee zero double-approvals or conflicting statuses, every decision runs inside an atomic MySQL transaction with row-level locking.
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>Atomic Payment Verification Flow (Concurrency-Safe)</span>
      <span class="badge-pill">InnoDB Row Lock • FOR UPDATE</span>
    </div>

    <div class="arch-grid">
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">1</div>
          <div style="font-weight:700; font-size:7pt;">Guest Proof Upload</div>
          <div class="step-subtext">Receipt image validated (MIME, extension, size), saved in 403-protected <code>uploads/payments/</code>, row inserted with <code>status = pending</code>.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">2</div>
          <div style="font-weight:700; font-size:7pt;">Secure Inspection</div>
          <div class="step-subtext">Staff or Admin opens receipt via <code>serve-file.php?type=payment&amp;file=...</code> and verifies Maya/GCash Reference No &amp; exact amount.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">3</div>
          <div style="font-weight:700; font-size:7pt;">Row Lock Acquired</div>
          <div class="step-subtext"><code>PaymentService::decide()</code> starts <code>BEGIN TRANSACTION</code> and runs <code>SELECT ... FROM payments WHERE id = ? FOR UPDATE</code>.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">4</div>
          <div style="font-weight:700; font-size:7pt;">Idempotency Guard</div>
          <div class="step-subtext">Checks <code>status === 'pending'</code>. If another Staff/Admin already acted milliseconds earlier, transaction rolls back cleanly with conflict alert.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 20%;">
        <div class="pipeline-card">
          <div class="pipeline-num">5</div>
          <div style="font-weight:700; font-size:7pt;">Commit &amp; Notify</div>
          <div class="step-subtext">Updates <code>payments</code> (<code>verified_by</code>, <code>verified_at</code>), promotes <code>bookings.status = confirmed</code>, writes <code>audit_logs</code> &amp; guest notification, then <code>COMMIT</code>.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="arch-grid">
    <div class="arch-col" style="width: 50%;">
      <div class="card-box success">
        <div class="card-header"><span>APPROVE OUTCOME</span><span class="badge badge-guest">Payment = verified</span></div>
        <div class="card-body">
          &bull; <code>payments.status</code> &rarr; <code>verified</code>; records <code>verified_by</code> (Staff or Admin User ID) &amp; <code>verified_at</code>.<br>
          &bull; <code>bookings.status</code> &rarr; <code>confirmed</code>; records <code>accepted_at</code>.<br>
          &bull; <code>rooms.status</code> &rarr; reflects confirmed reservation window.<br>
          &bull; In-app notification + Gmail confirmation dispatched to Guest.<br>
          &bull; Actor role badge (<strong>Verified by Staff</strong> / <strong>Verified by Admin</strong>) visible across both portals immediately.
        </div>
      </div>
    </div>
    <div class="arch-col" style="width: 50%;">
      <div class="card-box danger">
        <div class="card-header"><span>REJECT OUTCOME</span><span class="badge badge-admin">Payment = rejected</span></div>
        <div class="card-body">
          &bull; <code>payments.status</code> &rarr; <code>rejected</code> with mandatory reviewer reason (e.g., blurred screenshot, wrong reference number, short amount).<br>
          &bull; <code>bookings.status</code> stays <code>pending</code> so guest can re-submit valid proof without losing their slot immediately.<br>
          &bull; Guest receives instant notification with the exact rejection reason.<br>
          &bull; Immutable record logged in <code>audit_logs</code> with reviewer IP and timestamp.
        </div>
      </div>
    </div>
  </div>

  <div class="callout-box info" style="margin-top:6px;">
    <strong>Booking &amp; Financial State Integrity:</strong> Because both <code>frontend/staff/payments.php</code>, <code>frontend/admin/payments.php</code>, and Slim 4 <code>POST /backend/api/v1/payments/{id}/decision</code> share the same atomic <code>PaymentService</code> contract, business rules stay 100% consistent across Web UI and REST API.
  </div>
</div>

<!-- ========================================================================= -->
<!-- PAGE 6: STAFF OPERATIONS & HOUSEKEEPING STATE MACHINE                     -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 5</span> Frontdesk Staff Flow &amp; Automated Housekeeping State Machine</h1>
  <div class="intro-text">
    Engineered under the <strong>Principle of Least Privilege</strong>, the Staff Portal (<code>frontend/staff/</code>) empowers frontdesk personnel to run daily check-ins, check-outs, delegated payment reviews, and room hygiene cycles rapidly—while restricting sensitive administrative operations exclusively to Super Admin.
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>Daily Frontdesk Staff Operational Workflow</span>
      <span class="badge-pill">MH_STAFF_SESS • Least Privilege</span>
    </div>

    <div class="arch-grid">
      <div class="arch-col" style="width: 50%;">
        <div class="step-node blue">
          <strong>1. Shift Start &amp; Marvi AI Co-Pilot (<code>/staff/dashboard.php</code>)</strong>
          <div class="step-subtext">Staff logs in on isolated cookie <code>MH_STAFF_SESS</code>. Dashboard loads today's arrivals, expected check-outs, pending bookings/payments, and dirty rooms. One click triggers <code>POST /ai/staff-assistant.php</code> for an instant prioritized shift briefing.</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node gold">
          <strong>2. Booking &amp; Payment Clearance (<code>/staff/payments.php</code>)</strong>
          <div class="step-subtext">Staff approves or declines pending reservations and verifies Maya/GCash walk-in or online receipts with full accountability (<code>verified_by</code> + <code>audit_logs</code>).</div>
        </div>
      </div>
      <div class="arch-col" style="width: 50%;">
        <div class="step-node green">
          <strong>3. Fast Check-In &amp; Check-Out Desk (<code>/staff/check-in.php</code>)</strong>
          <div class="step-subtext"><strong>Check-In:</strong> Verifies guest voucher &rarr; sets <code>bookings.status = checked_in</code>, logs <code>checked_in_at</code>, sets <code>rooms.status = occupied</code>.<br><strong>Check-Out:</strong> Sets <code>bookings.status = checked_out</code>, logs <code>checked_out_at</code>, and <strong>auto-transitions room to <code>dirty</code></strong>.</div>
        </div>
        <div class="flow-arrow-down">&darr;</div>
        <div class="step-node amber">
          <strong>4. Live Floor &amp; Housekeeping Board (<code>/staff/rooms-status.php</code>)</strong>
          <div class="step-subtext">Floor-by-floor status cards show every room/dorm bed. Once housekeeping cleans a room, Staff flips <code>dirty &rarr; available</code> (or <code>maintenance</code> if repairs are needed).</div>
        </div>
      </div>
    </div>
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>Automated Room &amp; Housekeeping State Machine</span>
      <span class="badge-pill">Zero Uncleaned Turnovers</span>
    </div>

    <div style="text-align:center; padding: 6px 2px;">
      <span class="state-chip" style="background:#dcfce7; border-color:#4ade80; color:#166534;">1. AVAILABLE</span>
      <span style="font-weight:800; color:#64748b; margin:0 4px;">&rarr;</span>
      <span class="state-chip" style="background:#fef3c7; border-color:#f59e0b; color:#92400e;">2. RESERVED</span>
      <span style="font-weight:800; color:#64748b; margin:0 4px;">&rarr;</span>
      <span class="state-chip" style="background:#e0f2fe; border-color:#38bdf8; color:#075985;">3. OCCUPIED</span>
      <span style="font-weight:800; color:#64748b; margin:0 4px;">&rarr;</span>
      <span class="state-chip" style="background:#fee2e2; border-color:#f87171; color:#991b1b;">4. DIRTY (AUTO)</span>
      <span style="font-weight:800; color:#64748b; margin:0 4px;">&rarr;</span>
      <span class="state-chip" style="background:#dcfce7; border-color:#4ade80; color:#166534;">CLEAN &rarr; AVAILABLE</span>
      <div style="margin-top:6px; font-size:7pt; color:#475569;">
        Branch path: <code>available</code> or <code>dirty</code> &rarr; <span class="state-chip" style="background:#f3e8ff; border-color:#c084fc; color:#6b21a8; padding:1.5px 5px;">MAINTENANCE</span> (repair/inspection) &rarr; <code>available</code>
      </div>
    </div>
  </div>

  <h2>Strict Least-Privilege Boundaries for Frontdesk Staff</h2>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 30%;">Permitted Operations (Allowed)</th>
        <th style="width: 35%;">Restricted Operations (Blocked by Role Guard)</th>
        <th style="width: 35%;">Security Rationale</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>&bull; Daily Check-In &amp; Check-Out<br>&bull; Delegated Maya/GCash verification<br>&bull; Booking approvals/declines<br>&bull; Housekeeping room status toggles<br>&bull; Marvi Shift Co-Pilot briefings</td>
        <td>&bull; KYC Government/Student ID approval<br>&bull; Live Password Reset dispatch<br>&bull; Creating or banning users/staff<br>&bull; Room pricing &amp; inventory deletion<br>&bull; Editing SMTP/AI config or viewing/deleting Audit Logs</td>
        <td>Prevents internal fraud, unauthorized price tampering, account takeover, and audit log concealment while keeping frontdesk turnaround fast.</td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ========================================================================= -->
<!-- PAGE 7: SUPER ADMIN GOVERNANCE & RBAC MATRIX                              -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 6</span> Super Admin Master Governance &amp; Full RBAC Matrix</h1>
  <div class="intro-text">
    The Super Admin Portal (<code>frontend/admin/</code>) centralizes financial oversight, identity verification, live password recovery dispatch, closed staff provisioning, room inventory CRUD, Gmail SMTP configuration, Marvi AI tuning, and immutable security auditing.
  </div>

  <div class="arch-grid">
    <div class="arch-col" style="width: 50%;">
      <div class="card-box danger" style="margin-bottom:5px;">
        <div class="card-header"><span>EXECUTIVE BI &amp; OPERATIONS</span><span class="badge badge-admin">Control Tower</span></div>
        <div class="card-body">
          &bull; <strong>Dashboard (<code>admin/dashboard.php</code>):</strong> All-time &amp; monthly verified revenue, occupancy %, Maya vs GCash ratio, and <strong>Marvi Executive BI Digest</strong>.<br>
          &bull; <strong>Master Bookings (<code>admin/bookings.php</code>):</strong> Full booking timeline, status override, check-in/out intervention, CSV/print views.<br>
          &bull; <strong>Payment Hub (<code>admin/payments.php</code>):</strong> Master receipt lightbox &amp; atomic approval/rejection.<br>
          &bull; <strong>Room Inventory (<code>admin/rooms.php</code>):</strong> Full CRUD for hotel rooms &amp; dorm beds, weekend rates, amenities, and orphan-image cleanup.
        </div>
      </div>
    </div>
    <div class="arch-col" style="width: 50%;">
      <div class="card-box gold" style="margin-bottom:5px;">
        <div class="card-header"><span>IDENTITY, AI &amp; AUDIT GOVERNANCE</span><span class="badge badge-gold">Master Authority</span></div>
        <div class="card-body">
          &bull; <strong>Users, KYC &amp; Live Resets (<code>admin/users.php</code>):</strong> Approve/Reject Government &amp; Student IDs, Ban/Unban accounts, Dispatch Temp Passwords/Reset Links with live <em>Viewed by user</em> / <em>Waiting</em> badges.<br>
          &bull; <strong>Staff Provisioning (<code>admin/staff.php</code>):</strong> Closed creation &amp; lifecycle control of frontdesk accounts.<br>
          &bull; <strong>Settings &amp; AI Config (<code>admin/settings.php</code>, <code>ai-config.php</code>):</strong> Gmail SMTP, Maya/GCash QR, NVIDIA model presets + live connection latency tester + flagged threat logs.<br>
          &bull; <strong>Audit Logs (<code>admin/audit-logs.php</code>):</strong> View-only immutable log of actor, module, action, IP, and timestamp.
        </div>
      </div>
    </div>
  </div>

  <h2>Complete 4-Actor Role-Based Access Control (RBAC) Matrix</h2>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 34%;">System Capability / Module</th>
        <th style="width: 16%; text-align:center;">Guest<br>(<code>MH_USER_SESS</code>)</th>
        <th style="width: 16%; text-align:center;">Staff<br>(<code>MH_STAFF_SESS</code>)</th>
        <th style="width: 17%; text-align:center;">Super Admin<br>(<code>MH_ADMIN_SESS</code>)</th>
        <th style="width: 17%; text-align:center;">Marvi AI<br>(<code>backend/ai/</code>)</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>Room Catalog &amp; Slim 4 Live Availability</td><td style="text-align:center;">Yes</td><td style="text-align:center;">Yes</td><td style="text-align:center;">Yes</td><td style="text-align:center;">Reads Live DB</td></tr>
      <tr><td>Marvi Live SSE Chat + Voice Mode</td><td style="text-align:center;">Yes (10+ Pages)</td><td style="text-align:center;">Shift Co-Pilot</td><td style="text-align:center;">Executive BI</td><td style="text-align:center;">Core Engine</td></tr>
      <tr><td>Upload KYC Government / Student ID</td><td style="text-align:center;">Yes</td><td style="text-align:center;">No</td><td style="text-align:center;">No</td><td style="text-align:center;">Status Aware</td></tr>
      <tr><td>KYC Document Adjudication (Approve/Reject)</td><td style="text-align:center;">Blocked</td><td style="text-align:center;">Blocked</td><td style="text-align:center;"><strong>Exclusive</strong></td><td style="text-align:center;">No</td></tr>
      <tr><td>Book Room / Cancel Own Pending Booking</td><td style="text-align:center;">Yes (If Verified)</td><td style="text-align:center;">No</td><td style="text-align:center;">Override Only</td><td style="text-align:center;">Room Cards</td></tr>
      <tr><td>Upload Maya / GCash Payment Proof</td><td style="text-align:center;">Yes</td><td style="text-align:center;">No</td><td style="text-align:center;">No</td><td style="text-align:center;">Payment Guide</td></tr>
      <tr><td>Atomic Payment Verification (<code>FOR UPDATE</code>)</td><td style="text-align:center;">Blocked</td><td style="text-align:center;">Delegated</td><td style="text-align:center;">Master Control</td><td style="text-align:center;">No</td></tr>
      <tr><td>Guest Check-In &amp; Check-Out</td><td style="text-align:center;">Blocked</td><td style="text-align:center;">Daily Desk</td><td style="text-align:center;">Master Override</td><td style="text-align:center;">Tracks Arrivals</td></tr>
      <tr><td>Room Housekeeping Toggle (Clean/Dirty/Maint.)</td><td style="text-align:center;">Blocked</td><td style="text-align:center;">Yes</td><td style="text-align:center;">Yes</td><td style="text-align:center;">Flags Dirty Rooms</td></tr>
      <tr><td>Live Password Reset Dispatch &amp; View Tracking</td><td style="text-align:center;">Popup Receiver</td><td style="text-align:center;">Blocked</td><td style="text-align:center;"><strong>Exclusive</strong></td><td style="text-align:center;">No</td></tr>
      <tr><td>Staff Creation, Room Pricing &amp; SMTP/AI Config</td><td style="text-align:center;">Blocked</td><td style="text-align:center;">Blocked</td><td style="text-align:center;"><strong>Exclusive</strong></td><td style="text-align:center;">Reads Settings</td></tr>
      <tr><td>Security Audit Logs (<code>audit_logs</code>)</td><td style="text-align:center;">Blocked</td><td style="text-align:center;">Blocked</td><td style="text-align:center;"><strong>View-Only</strong></td><td style="text-align:center;">Logs AI Threats</td></tr>
    </tbody>
  </table>
</div>

<!-- ========================================================================= -->
<!-- PAGE 8: MARVI LIVE AI FLOW (SSE STREAMING, VOICE & TRI-ROLE)              -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 7</span> Marvi Live AI Architecture (SSE Streaming, Voice &amp; Tri-Role)</h1>
  <div class="intro-text">
    Marvi AI operates as a real-time, tri-role hospitality intelligence engine powered by <strong>NVIDIA NIM</strong> (<code>nvidia/nemotron-3.5-bolt-e4b</code>) with pure-PHP Server-Sent Events (SSE) token streaming, automatic failover, Web Speech Voice Mode (Tagalog/English Mic + TTS), multi-turn memory, server-verified interactive action cards, and strict security guardrails.
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>6-Stage Real-Time Guest Concierge Pipeline (<code>POST /ai/chatbot.php?stream=1</code>)</span>
      <span class="badge-pill">~0.8s Live Streaming + 0% Downtime</span>
    </div>

    <div class="arch-grid">
      <div class="pipeline-step" style="width: 16.66%;">
        <div class="pipeline-card">
          <div class="pipeline-num">1</div>
          <div style="font-weight:700; font-size:6.8pt;">Input &amp; Voice</div>
          <div class="step-subtext">Text or Web Speech Mic (<code>fil-PH</code> / <code>en-US</code>). Persisted across pages via <code>sessionStorage</code>.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 16.66%;">
        <div class="pipeline-card">
          <div class="pipeline-num">2</div>
          <div style="font-weight:700; font-size:6.8pt;">Security Gate</div>
          <div class="step-subtext">Rate limiter + <code>bad-intent-keywords.json</code> blocks SQLi, XSS, port scans, prompt injection &rarr; logs <code>flagged=1</code>.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 16.66%;">
        <div class="pipeline-card">
          <div class="pipeline-num">3</div>
          <div style="font-weight:700; font-size:6.8pt;">Live DB Context</div>
          <div class="step-subtext">Injects live rooms/IDs/prices, guest's own booking &amp; KYC status, language mode, and 12-turn session history.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 16.66%;">
        <div class="pipeline-card">
          <div class="pipeline-num">4</div>
          <div style="font-weight:700; font-size:6.8pt;">NIM SSE Stream</div>
          <div class="step-subtext"><code>streamNvidiaAI()</code> streams tokens live from <code>nemotron-3.5-bolt-e4b</code> with Stop button support.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 16.66%;">
        <div class="pipeline-card">
          <div class="pipeline-num">5</div>
          <div style="font-weight:700; font-size:6.8pt;">Failover Chain</div>
          <div class="step-subtext">Auto-retries <code>nemotron-3.5-lightning-30b-a3b</code> &rarr; <code>minimax-m3.1-flash</code> &rarr; Local Bilingual FAQ &amp; Room Engine.</div>
        </div>
      </div>
      <div class="pipeline-step" style="width: 16.66%;">
        <div class="pipeline-card">
          <div class="pipeline-num">6</div>
          <div style="font-weight:700; font-size:6.8pt;">Actions &amp; TTS</div>
          <div class="step-subtext">Parses <code>[[BOOK_ROOM:id]]</code> &amp; <code>[[GOTO:route]]</code> into verified Room Cards &amp; Take Me There buttons + optional TTS voice reply.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="arch-grid">
    <div class="arch-col" style="width: 33.3%;">
      <div class="card-box purple">
        <div class="card-header"><span>GUEST AI SUITE</span><span class="badge badge-ai">Concierge + Matcher</span></div>
        <div class="card-body">
          &bull; Embedded on public pages &amp; all 10 guest portal pages.<br>
          &bull; Understands contextual follow-ups (e.g., <em>"ipunta mo ako doon"</em> &rarr; navigates to previously recommended room/page).<br>
          &bull; <code>user/ai-recommend.php</code> scores rooms by budget, capacity, and amenities.
        </div>
      </div>
    </div>
    <div class="arch-col" style="width: 33.3%;">
      <div class="card-box primary">
        <div class="card-header"><span>STAFF CO-PILOT</span><span class="badge badge-staff">staff-assistant.php</span></div>
        <div class="card-body">
          &bull; Reads live DB counts for today's arrivals, departures, pending bookings/payments, and <code>dirty</code> rooms.<br>
          &bull; Synthesizes prioritized shift action list in clean Taglish/English.<br>
          &bull; Accelerates frontdesk handovers during peak check-in hours.
        </div>
      </div>
    </div>
    <div class="arch-col" style="width: 33.3%;">
      <div class="card-box danger">
        <div class="card-header"><span>ADMIN EXECUTIVE BI</span><span class="badge badge-admin">admin-insights.php</span></div>
        <div class="card-body">
          &bull; Analyzes verified revenue, occupancy rate, Maya vs GCash share, pending KYC backlog, and flagged AI incidents.<br>
          &bull; Recommends dynamic pricing/promo strategies.<br>
          &bull; Configurable + live connection probe in <code>admin/ai-config.php</code>.
        </div>
      </div>
    </div>
  </div>

  <div class="callout-box purple" style="margin-top:5px;">
    <strong>Output Sanitization &amp; Grounding:</strong> Before any streamed or fallback response finishes, server-side output guards strip internal file paths, server port numbers, and secrets, and verify every <code>[[BOOK_ROOM:id]]</code> card against the live MySQL <code>rooms</code> table so Marvi can never hallucinate fake rooms or prices.
  </div>
</div>

<!-- ========================================================================= -->
<!-- PAGE 9: DATABASE SCHEMA (14 TABLES) & SLIM 4 REST API V1                  -->
<!-- ========================================================================= -->
<div class="page-container">
  <h1><span class="section-tag">Section 8</span> Database Architecture (14 Tables) &amp; Slim 4 REST API v1</h1>
  <div class="intro-text">
    Data persistence runs on <strong>MySQL / MariaDB InnoDB (<code>utf8mb4_unicode_ci</code>)</strong> accessed exclusively through <strong>PDO Prepared Statements</strong>. The application layer exposes a standardized, PSR-7/PSR-15 compliant <strong>Slim 4 REST API</strong> consumed by Alpine.js via <code>window.HotelAPI</code>.
  </div>

  <h2>Slim 4 REST API v1 Route Catalog (<code>backend/src/ApiAppFactory.php</code>)</h2>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 8%;">Verb</th>
        <th style="width: 30%;">Endpoint Path</th>
        <th style="width: 24%;">PSR-15 Middleware</th>
        <th style="width: 38%;">Controller &rarr; Service Action</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/health</code></td><td>Public</td><td><code>HealthController</code> &rarr; API health, framework &amp; version payload</td></tr>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/rooms</code></td><td>Public</td><td><code>RoomController</code> &rarr; <code>RoomService</code> filterable room list</td></tr>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/rooms/{id}</code></td><td>Public</td><td><code>RoomController</code> &rarr; <code>RoomService</code> single room details</td></tr>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/availability</code></td><td>Public</td><td><code>RoomController</code> &rarr; Real-time date overlap &amp; dynamic price calc</td></tr>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/auth/status</code></td><td>Portal-Aware</td><td><code>AuthController</code> &rarr; Active role, user profile &amp; CSRF token</td></tr>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/bookings</code></td><td><code>RoleAuth(user,staff,admin)</code></td><td><code>BookingController</code> &rarr; Role-scoped reservation feed</td></tr>
      <tr><td><code>POST</code></td><td><code>/backend/api/v1/bookings/{id}/cancel</code></td><td><code>RoleAuth(user)</code> + <code>CSRF</code></td><td><code>BookingController</code> &rarr; Guest pending reservation cancellation</td></tr>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/payments</code></td><td><code>RoleAuth(user,staff,admin)</code></td><td><code>PaymentController</code> &rarr; Role-scoped payment records</td></tr>
      <tr><td><code>POST</code></td><td><code>/backend/api/v1/payments/{id}/decision</code></td><td><code>RoleAuth(staff,admin)</code> + <code>CSRF</code></td><td><code>PaymentController</code> &rarr; Atomic <code>FOR UPDATE</code> verify/reject</td></tr>
      <tr><td><code>GET</code></td><td><code>/backend/api/v1/dashboard/metrics</code></td><td><code>RoleAuth(user,staff,admin)</code></td><td><code>DashboardController</code> &rarr; Role-specific live dashboard KPIs</td></tr>
    </tbody>
  </table>

  <h2>Normalized 14-Table MySQL Schema (17 Versioned Migrations: <code>0001</code>&ndash;<code>0017</code>)</h2>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 21%;">Table Name</th>
        <th style="width: 44%;">Core Purpose</th>
        <th style="width: 35%;">Key Constraints / Relations</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>users</code></td><td>Guest, Staff &amp; Admin accounts, KYC ID metadata, verification &amp; tokens</td><td><code>UNIQUE(email)</code>, normalized name index</td></tr>
      <tr><td><code>room_types</code> &amp; <code>rooms</code></td><td>Accommodation categories, physical rooms/dorm beds, prices, housekeeping status</td><td><code>UNIQUE(room_number)</code>, <code>FK(room_type_id)</code></td></tr>
      <tr><td><code>bookings</code></td><td>Reservations, <code>[check_in, check_out)</code> windows, lifecycle timestamps</td><td><code>UNIQUE(booking_code)</code>, <code>FK(user_id, room_id)</code></td></tr>
      <tr><td><code>payments</code></td><td>Maya/GCash payment receipts, reference numbers, dual-approval tracker</td><td><code>FK(booking_id, user_id, verified_by)</code></td></tr>
      <tr><td><code>reviews</code> &amp; <code>notifications</code></td><td>Verified stay &amp; general hotel ratings; real-time user/admin alert feed</td><td><code>FK(user_id)</code>, nullable <code>FK(room_id, booking_id)</code></td></tr>
      <tr><td><code>password_reset_requests</code></td><td>Live Admin-assisted reset queue with SHA-256 session token &amp; view status</td><td><code>FK(user_id)</code>, indexed <code>(status, request_token)</code></td></tr>
      <tr><td><code>audit_logs</code> &amp; <code>ai_logs</code></td><td>Immutable actor/IP action trail; Marvi AI chat transcripts &amp; flagged threats</td><td>Indexed on <code>created_at</code>, <code>user_id</code>, <code>flagged</code></td></tr>
      <tr><td><code>settings</code> &amp; <code>contact_messages</code></td><td>Dynamic Maya/GCash, Gmail SMTP, NVIDIA NIM AI config; public inquiries</td><td><code>UNIQUE(key)</code> on <code>settings</code></td></tr>
      <tr><td><code>rate_limits</code> &amp; <code>schema_migrations</code></td><td>Sliding-window brute-force/AI throttle ledger; CLI SQL migration tracker</td><td>Indexed <code>(key, created_at)</code>; <code>PK(migration)</code></td></tr>
    </tbody>
  </table>
</div>

<!-- ========================================================================= -->
<!-- PAGE 10: CROSS-ACTOR FLOW, QA PROOF & DEFENSE DEPLOYMENT                  -->
<!-- ========================================================================= -->
<div class="page-container last-page">
  <h1><span class="section-tag">Section 9</span> Cross-Actor Collaboration, Automated QA &amp; Defense Deployment</h1>
  <div class="intro-text">
    This final summary links all four actors in a single operational narrative alongside automated PHPUnit 10 verification proof, live hybrid tunneling, and evaluation credentials for the Capstone Defense panel.
  </div>

  <div class="diagram-container">
    <div class="diagram-title">
      <span>Unified End-to-End Collaboration Lifecycle (All 4 Actors)</span>
      <span class="badge-pill">Zero Manual Friction</span>
    </div>
    <div class="step-node purple">
      <strong>1. AI-Guided Discovery:</strong> Guest asks <strong>Marvi Live AI</strong> (text or voice) for rooms under budget &rarr; Marvi streams recommendations + clickable Room Cards &rarr; Alpine.js calls Slim 4 <code>GET /v1/availability</code> to verify exact dates and price.
    </div>
    <div class="step-node amber">
      <strong>2. Identity &amp; Account Security:</strong> Guest registers (unique name + email verification) and uploads Student/Government ID &rarr; <strong>Super Admin</strong> inspects via protected <code>serve-file.php</code> lightbox and approves KYC. (If password forgotten, Admin triggers instant Live Reset Popup.)
    </div>
    <div class="step-node green">
      <strong>3. Atomic Booking &amp; Payment:</strong> Guest reserves room (protected against date overlap) and uploads Maya/GCash proof &rarr; <strong>Frontdesk Staff</strong> or <strong>Super Admin</strong> verifies via atomic <code>SELECT ... FOR UPDATE</code> transaction &rarr; Voucher unlocks.
    </div>
    <div class="step-node blue">
      <strong>4. Frontdesk Operations &amp; Executive BI:</strong> <strong>Staff</strong> uses Marvi Shift Co-Pilot, executes Check-In and Check-Out (auto-flagging room <code>dirty</code> &rarr; cleaned to <code>available</code>) &rarr; Guest leaves verified review &rarr; <strong>Super Admin</strong> reviews revenue &amp; Marvi Executive BI Digest.
    </div>
  </div>

  <div class="arch-grid">
    <div class="arch-col" style="width: 33.3%;">
      <div class="card-box primary">
        <div class="card-header"><span>LOCAL &amp; DOCKER STACK</span><span class="badge badge-staff">Zero Latency</span></div>
        <div class="card-body">
          &bull; <strong>Runtime:</strong> XAMPP Apache 2.4 + PHP 8 + MySQL InnoDB<br>
          &bull; <strong>Composer Platform:</strong> Pinned to PHP 8.0.30 compatibility<br>
          &bull; <strong>Migrations:</strong> <code>php database/migrate.php</code> (17/17 applied)<br>
          &bull; <strong>Container:</strong> Dockerfile + entrypoint ready for cloud PaaS
        </div>
      </div>
    </div>
    <div class="arch-col" style="width: 33.3%;">
      <div class="card-box gold">
        <div class="card-header"><span>LIVE DEFENSE TUNNEL</span><span class="badge badge-gold">share-capstone.sh</span></div>
        <div class="card-body">
          &bull; <strong>Health Check:</strong> <code>./share-capstone.sh --check</code><br>
          &bull; <strong>Cloudflare / Ngrok:</strong> Instant HTTPS URL + terminal QR code for panelist smartphones<br>
          &bull; <strong>Daemon Mode:</strong> Non-blocking detached background runner<br>
          &bull; <strong>API Health:</strong> Live Slim 4 <code>/backend/api/v1/health</code> probe
        </div>
      </div>
    </div>
    <div class="arch-col" style="width: 33.3%;">
      <div class="card-box success">
        <div class="card-header"><span>AUTOMATED QA SUITE</span><span class="badge badge-guest">PHPUnit 10</span></div>
        <div class="card-body">
          &bull; <strong>Test Suite:</strong> <strong>32 / 32 Tests Passed (185 Assertions)</strong><br>
          &bull; <strong>Coverage:</strong> Slim 4 PSR-7 routes, RoleAuth &amp; CSRF middleware, Room/Booking/Payment services, core helpers<br>
          &bull; <strong>Static Check:</strong> 86/86 PHP files 0 lint errors; JS &amp; Bash verified<br>
          &bull; <strong>Security Check:</strong> 403 Verified on <code>core/</code>, <code>src/</code>, <code>database/</code>, <code>uploads/</code>
        </div>
      </div>
    </div>
  </div>

  <div class="card-box purple" style="margin-top:6px;">
    <div class="card-header">
      <span>SYNCHRONIZED DEFENSE EVALUATION CREDENTIALS</span>
      <span class="badge badge-ai">Live Demo Ready</span>
    </div>
    <div class="card-body">
      <strong>Super Admin (<code>/admin/login.php</code>):</strong> <code>admin@marveloushotel.com</code> / <code>password</code> &nbsp;&bull;&nbsp;
      <strong>Frontdesk Staff (<code>/staff/login.php</code>):</strong> <code>staff@marveloushotel.com</code> / <code>password</code> &nbsp;&bull;&nbsp;
      <strong>Verified Guest (<code>/auth/login.php</code>):</strong> <code>lan@gmail.com</code> / <code>password</code>
    </div>
  </div>

  <div class="callout-box success" style="margin-top:7px;">
    <strong>Capstone Defense Readiness:</strong> All documentation, system flow diagrams, 3-tier directories, Slim 4 REST API endpoints, Marvi Live SSE + Voice AI, Live Password Reset Popup, and automated PHPUnit 10 tests are 100% synchronized and production-verified.
  </div>
</div>

</body>
</html>
"""


def generate_pdf():
    print("Building HTML content for Marvelous Hotel System Flows (Nov 20, 2026 Defense Edition)...")
    html_str = build_html()

    # Keep project root clean: output exclusively inside docs/
    output_path = "/opt/lampp/htdocs/hotel/docs/marvelous_hotel_system_flows.pdf"
    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    print(f"Compiling PDF via WeasyPrint {weasyprint.__version__}...")
    doc = weasyprint.HTML(string=html_str)
    doc.write_pdf(output_path)
    print(f"Successfully generated {output_path} ({os.path.getsize(output_path):,} bytes)")


if __name__ == "__main__":
    generate_pdf()
