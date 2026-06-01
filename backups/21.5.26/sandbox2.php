<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="REVISION100™: Radikale Quelltext-Sanierung für Unternehmens-Websites. Behebung von Sichtbarkeitsabstürzen und Ladeblockaden zum Festpreis (4.800 EUR). Exklusiv für Inhaber.">
    <title>REVISION100™ — Quelltext-Sanierung bei Sichtbarkeitsabsturz</title>
    <style>
        /* --- SYSTEM DESIGN SYSTEM (HfG Ulm / Funktionalismus) --- */
        :root {
            --bg-color: #1a2324;
            --panel-bg: #111819;
            --border-color: #2c3a3c;
            --text-main: #d1dcd3;
            --text-muted: #a8b8ba;
            --teal-accent: #339999;
            --orange-action: #ff6600;
            --orange-hover: #e05500;
            --font-mono: ui-monospace, SFMono-Regular, "SF Pro Mono", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            font-size: 16px;
            padding: 15px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        @media (min-width: 600px) {
            body {
                font-size: 16px;
                padding: 25px;
            }
        }

        .site-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* --- HEADER --- */
        header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        @media (min-width: 768px) {
            header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .logo-block {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            font-size: 32px;
            font-family: var(--font-mono);
        }

        .logo-sub {
            font-size: 11px;
            color: #ffffff;
            text-transform: uppercase;
            font-weight: normal;
        }

        .case-study-container {
            margin-top: 10px;
            padding: 10px;
            background-color: #0b1011;
            border-radius: 4px;
            border-left: 3px solid var(--teal-accent);
        }

        .case-study-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .case-study-metric {
            font-weight: bold;
            margin-bottom: 2px;
        }

        .case-study-metric.before {
            color: #ff5555;
        }

        .case-study-metric.after {
            color: var(--teal-accent);
        }

        .case-study-divider {
            border-top: 1px solid var(--border-color);
            padding-top: 8px;
            margin-top: 10px;
        }

        .case-study-result {
            color: var(--orange-action);
            margin-top: 8px;
            font-weight: bold;
            font-size: 12px;
        }

        /* --- ERWEITERUNG FÜR ERFOLGSBEISPIEL --- */
        .terminal-log {
            list-style: none;
            margin-top: 8px;
            margin-bottom: 10px;
            padding-left: 0;
        }

        .terminal-log li {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
            position: relative;
            padding-left: 12px;
        }

        .terminal-log li::before {
            content: ">";
            position: absolute;
            left: 0;
            color: var(--teal-accent);
        }

        /* Grüne LED: Signalisiert aktiven Betriebzustand (Emittierend) */
        .led-green-on {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #00ff66;
            border-radius: 50%;
            box-shadow: 0 0 8px #00ff66;
            vertical-align: middle;
            margin-right: 8px;
        }

        /* Gelbe LED: Signalisiert Warteliste / Begrenzte Kapazität */
        .led-yellow-wait {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #ffaa00;
            border-radius: 50%;
            box-shadow: 0 0 8px #ffaa00;
            vertical-align: middle;
            margin-right: 8px;
        }

        /* Rote LED: Signalisiert inaktiven Warnzustand (Matt / Aus) */
        .led-red-off {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #3a1414;
            border: 1px solid #541c1c;
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 4px;
        }

        .manifest-banner {
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 1px;
            line-height: 1.6;
        }

        /* --- RESPONSIVE RASTER (Mobile First) --- */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
            align-items: start;
        }

        @media (min-width: 900px) {
            .main-grid {
                grid-template-columns: 1fr 1.3fr;
                gap: 35px;
            }
        }

        /* --- VISUALISIERUNG ARCHITEKTUR-KOMPLEXITÄT --- */
        .visual-container {
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: #131b1c;
        }

        .svg-architecture {
            width: 100%;
            height: auto;
            max-width: 400px;
            opacity: 0.95;
        }

        .visual-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            line-height: 1.4;
        }

        /* --- DIAGNOSTIK-TERMINAL --- */
        .terminal {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }

        .terminal-header {
            background-color: #0b1011;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .terminal-body {
            padding: 15px;
        }

        @media (min-width: 600px) {
            .terminal-body { padding: 25px; }
        }

        .hero-statement {
            font-size: 16px;
            font-weight: normal;
            line-height: 1.4;
            margin-bottom: 25px;
            border-left: 3px solid var(--teal-accent);
            padding-left: 12px;
            color: #ffffff;
        }

        @media (min-width: 600px) {
            .hero-statement { font-size: 18px; }
        }

        .hero-statement strong {
            color: var(--orange-action);
            font-weight: normal;
        }

        /* --- PRÜFBERICHT (KONTRAST) --- */
        .section-title {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 10px;
            letter-spacing: 1px;
            font-weight: bold;
            font-family: var(--font-mono);
        }

        .audit-panel {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 25px;
            background-color: #0d1314;
        }

        .audit-grid {
            display: grid;
            grid-template-columns: 1fr;
        }

        @media (min-width: 600px) {
            .audit-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .audit-box {
            padding: 15px;
        }

        .audit-box:first-child {
            border-bottom: 1px solid var(--border-color);
        }

        @media (min-width: 600px) {
            .audit-box:first-child {
                border-bottom: none;
                border-right: 1px solid var(--border-color);
            }
        }

        .audit-box-title {
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .error-list {
            list-style: none;
        }

        .error-list li {
            margin-bottom: 12px;
            font-size: 12px;
            line-height: 1.4;
        }

        .error-list.tech li {
            position: relative;
            padding-left: 15px;
        }

        .error-list.tech li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #ff5555;
        }

        .error-list.business li {
            position: relative;
            padding-left: 15px;
            color: #ffffff;
        }

        .error-list.business li::before {
            content: "→";
            position: absolute;
            left: 0;
            color: var(--teal-accent);
        }

        .error-list li span.highlight {
            color: #ff5555;
        }

        .damage-metric {
            border-top: 1px solid var(--border-color);
            padding: 12px 15px;
            background-color: #0b1011;
            font-size: 12px;
            color: #ffffff;
        }

        /* --- CONVERSION AREA --- */
        .conversion-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 600px) {
            .conversion-grid {
                grid-template-columns: 1.3fr 1fr;
            }
        }

        .form-panel {
            border: 1px solid var(--orange-action);
            border-radius: 6px;
            padding: 15px;
            background-color: #12191a;
        }

        @media (min-width: 600px) {
            .form-panel { padding: 20px; }
        }

        .form-group {
            margin-bottom: 12px;
        }

        label {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        input[type="url"],
        input[type="email"],
        select {
            width: 100%;
            background-color: #161f20;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px;
            color: var(--text-main);
            font-family: var(--font-mono);
            font-size: 13px;
            appearance: none;
            outline: none;
        }

        input:focus, select:focus {
            border-color: var(--teal-accent);
        }

        select {
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'><polygon points='0,0 10,0 5,5' fill='%23788a8c'/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            background-color: var(--orange-action);
            border: none;
            border-radius: 4px;
            color: #ffffff;
            font-family: var(--font-mono);
            font-size: 13px;
            font-weight: bold;
            padding: 12px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background-color: var(--orange-hover);
        }

        .meta-side-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .meta-panel {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            background-color: #0d1314;
        }

        .meta-value {
            font-size: 14px;
            margin-top: 4px;
        }

        .meta-value.highlight {
            color: var(--teal-accent);
            font-weight: bold;
            font-size: 16px;
        }

        /* --- FOOTER --- */
        footer {
            margin-top: 40px;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        @media (min-width: 600px) {
            footer {
                flex-direction: row;
                justify-content: space-between;
            }
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
        }

        .footer-links a:hover {
            color: var(--text-main);
        }
    </style>
</head>
<body>

    <div class="site-container">

        <header>
            <h1 class="logo-block">
                REVISION100™<br>
                <span class="logo-sub">Radikale Quelltext-Sanierung</span>
            </h1>
            <!-- STATUS REGELBETRIEB (Kapazitäten frei) -->
            <div class="manifest-banner">
                System aktiv <span class="led-red-off" aria-hidden="true"></span><span class="led-green-on" aria-hidden="true"></span> // Kapazitäten für neue Audits: Vorhanden.
            </div>

            <!-- STATUS WARTELISTE (Bei Vollauslastung aktivieren) -->
            <!--
            <div class="manifest-banner">
                Warteliste aktiv <span class="led-red-off" aria-hidden="true"></span><span class="led-yellow-wait" aria-hidden="true"></span> // Aktuell 14 Tage Vorlaufzeit für neue Projekte.
            </div>
            -->
        </header>

        <main class="main-grid">

            <section class="visual-container">
                <svg class="svg-architecture" viewBox="0 0 220 260" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <g stroke="#2c3a3c" stroke-width="0.5" fill="none">
                        <path d="M15 30 l50 -15 l50 15 l-50 15 z" />
                        <path d="M20 40 l45 -12 l45 12 l-45 12 z" />
                        <path d="M25 50 l40 -10 l40 10 l-40 10 z" />
                        <path d="M30 60 l35 -8   l35 8   l-35 8   z" />
                        <path d="M35 70 l30 -6   l30 6   l-30 6   z" />

                        <path d="M15 30 v45 l50 15 v-45 z" />
                        <path d="M115 30 v45 l-50 15 v-45 z" />
                        <path d="M20 40 v30 l45 12 v-30 z" />
                        <path d="M30 60 v15 l35 8 v-15 z" />

                        <path d="M65 25 l30 -5 v20" stroke="#445659" />
                        <path d="M75 45 l40 15" stroke="#445659" />
                        <path d="M10 55 h35 v20" stroke="#445659" />

                        <line x1="45" y1="35" x2="45" y2="85" stroke="#ff5555" stroke-width="1" />
                        <line x1="85" y1="20" x2="85" y2="70" stroke="#ff5555" stroke-width="0.75" />
                        <path d="M22 43 l15 5 m-15 15 l20 -5" stroke="#ff5555" stroke-width="0.75" />
                    </g>
                    <text x="15" y="95" fill="#ff5555" font-family="monospace" font-size="6.5" letter-spacing="0.5">UNOPTIMIERTER AGENTUR-CODE (CHAOS-STRUKTUR)</text>

                    <g stroke="#339999" stroke-width="1" fill="none">
                        <path d="M110 115 v25 m-4 -4 l4 4 l4 -4" />
                    </g>

                    <g stroke="#339999" stroke-width="0.75" fill="none">
                        <path d="M35 165 l65 -15 l65 15 l-65 15 z" />
                        <path d="M35 165 v40 l65 15 v-40 z" />
                        <path d="M165 165 v40 l-65 15 v-40 z" />

                        <path d="M50 175 l25 -6 v15" stroke-width="0.5" />
                        <path d="M75 169 l25 6 v15" stroke-width="0.5" />
                        <path d="M100 175 l40 -10 v15" stroke-width="0.5" />

                        <line x1="100" y1="150" x2="100" y2="220" stroke-width="0.5" stroke-dasharray="2,2" />
                        <line x1="65" y1="158" x2="65" y2="198" stroke-width="0.5" stroke-dasharray="1,3" />
                        <line x1="135" y1="158" x2="135" y2="198" stroke-width="0.5" stroke-dasharray="1,3" />
                    </g>
                    <text x="35" y="235" fill="#339999" font-family="monospace" font-size="6.5" letter-spacing="0.5">SANIERTE CODE-LANDSCHAFT // 100 PERFORMANCE</text>
                </svg>
                <div class="visual-label">Strukturanalyse: Unorganisierter Quelltext-Ballast vs. Maschinenlesbare Präzisions-Architektur</div>
            </section>

            <section class="terminal">
                <div class="terminal-header">
                    REVISION100™. DIAGNOSTIK-SYSTEM FÜR UNTERNEHMENS-WEBSITES.
                </div>

                <div class="terminal-body">
                    <div class="hero-statement">
                        <strong>SYSTEM-ZUSTAND:</strong> Sichtbarkeitsabsturz nach Relaunch, mobile Ladezeitverzögerung und blockierte Lead-Generierung durch unorganisierten Quelltext-Ballast.<br><br>

                        <strong>OPERATIVER EINGRIFF:</strong> Revision100 eliminiert redundante Code-Strukturen der bestehenden Website. Ziel: Fehlerfreie maschinelle Lesbarkeit für Suchmaschinen-Crawler und KI-Systeme.
                    </div>

                    <div class="section-title">Vorschau: Diagnostisches Gutachten (Kausalitäten)</div>
                    <div class="audit-panel">
                        <div class="audit-grid">
                            <div class="audit-box">
                                <div class="audit-box-title" style="color: #ff5555;">[1] Technische Ursache</div>
                                <ul class="error-list tech">
                                    <li>Verschachtelte Code-Strukturen blockieren die vollständige Indexierung durch Suchmaschinen.</li>
                                    <li>Skript-Ballast verzögert den mobilen Seitenaufbau (Time to Interactive).</li>
                                    <li>Fehlende semantische Datenstrukturen verhindern die Erfassung durch KI-Suchsysteme.</li>
                                </ul>
                            </div>
                            <div class="audit-box">
                                <div class="audit-box-title" style="color: var(--teal-accent);">[2] Wirtschaftliches Symptom</div>
                                <ul class="error-list business">
                                    <li>Google-Crawler verlässt die Seite vorzeitig. Massiver Ranking-Absturz.</li>
                                    <li>Seite reagiert mobil träge. 40% der Mobilnutzer springen sofort ab.</li>
                                    <li>Moderne KI-Suchsysteme (AI Overviews) können Ihr Angebot nicht auslesen.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="damage-metric">
                            WIRTSCHAFTLICHER SCHADEN: Ø 2.800 EUR bis 12.000 EUR entgangener Deckungsbeitrag pro Monat durch Quelltext-Mängel.
                        </div>
                    </div>

                    <div class="conversion-grid">

                        <form class="form-panel" action="#" method="POST">
                            <div class="section-title" style="color: var(--orange-action); margin-bottom: 12px;">Ursachen-Analyse anfordern</div>

                            <div class="form-group">
                                <label for="url">Projekt-URL</label>
                                <input type="url" id="url" name="url" required placeholder="https://ihre-firma.de">
                            </div>

                            <div class="form-group">
                                <label for="email">Direkte E-Mail-Adresse (Inhaber / GF)</label>
                                <input type="email" id="email" name="email" required placeholder="name@firma.de">
                            </div>

                            <div class="form-group">
                                <label for="cms">CMS / Systembasis</label>
                                <select id="cms" name="cms" required>
                                    <option value="" disabled selected>Bitte auswählen...</option>
                                    <option value="wordpress">WordPress</option>
                                    <option value="typo3">TYPO3</option>
                                    <option value="custom">Custom / Eigen-Entwicklung</option>
                                    <option value="other">Sonstiges (Drupal, Joomla, etc.)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tech">Hauptsymptom der Website wählen</label>
                                <select id="tech" name="tech" required>
                                    <option value="" disabled selected>Bitte auswählen...</option>
                                    <option value="crash">Sichtbarkeitsabsturz nach Relaunch</option>
                                    <option value="slow">Website lädt mobil spürbar zu langsam</option>
                                    <option value="stagnant">Blockierter Kundenfluss trotz Traffic</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-submit">System-Audit anfordern</button>
                        </form>

                        <div class="meta-side-grid">
                            <div class="meta-panel">
                                <div class="section-title">Investition (Festpreis)</div>
                                <div class="meta-value">Revision100™ Code-Sanierung:</div>
                                <div class="meta-value highlight">4.800 EUR (netto)</div>
                                <div class="meta-value" style="font-size:11px; color:var(--text-muted); margin-top:5px;">Einmalige Sanierung. Keine laufenden Abo-Kosten.</div>
                                <div style="border-top: 1px solid var(--border-color); margin-top: 10px; padding-top: 10px;">
                                    <div class="section-title">Operative Ausführung</div>
                                    <div class="meta-value" style="font-size: 12px; color: var(--teal-accent);">100 % manuelle Sanierung exklusiv durch Inhaber. Keine Delegation.</div>
                                </div>
                            </div>

                            <div class="meta-panel">
                                <div class="section-title">Erfolgsbeispiel (Audit-Log)</div>
                                <div class="meta-value">Echtes Projekt, vollständige System-Sanierung:</div>
                                <div class="case-study-container">

                                    <!-- STATUS QUO -->
                                    <div class="case-study-label">Status Quo (Vorher)</div>
                                    <div class="case-study-metric before">Performance: 46/100</div>
                                    <div style="color: var(--text-muted); font-size: 11px; margin-bottom: 5px;">Fehler: DOM-Tiefe &gt; 200, blockierende Ressourcen</div>

                                    <div class="case-study-divider"></div>

                                    <!-- OPERATIVE EINGRIFFE -->
                                    <div class="case-study-label" style="color: var(--text-main); margin-top: 5px;">Operative Eingriffe (Auszug)</div>
                                    <ul class="terminal-log">
                                        <li>Reduzierung der mobilen Ladezeit auf unter 1 Sekunde.</li>
                                        <li>Strukturierung der Daten zur fehlerfreien Erfassung durch AI-Crawler.</li>
                                        <li>Herstellung der uneingeschränkten Barrierefreiheit nach aktuellen WCAG-Kriterien.</li>
                                    </ul>

                                    <div class="case-study-divider"></div>

                                    <!-- ZIEL-METRIK -->
                                    <div class="case-study-label" style="margin-top: 5px;">Ziel-Metrik (Nachher)</div>
                                    <div class="case-study-metric after">Performance: 100/100</div>
                                    <div class="case-study-metric after">Accessibility: 100/100</div>
                                    <div class="case-study-metric after">Best Practices: 100/100</div>
                                    <div class="case-study-metric after">SEO: 100/100</div>
                                    <div class="case-study-result">Ergebnis: +118 % messbare Verbesserung</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>
        </main>

        <footer>
            <div>Revision100™ ein Service von Timo E. Pohlhaus – <a href="https://timpool.de" style="color: var(--text-muted); text-decoration: none;">timpool.de</a></div>
            <div class="manifest-banner" style="max-width: none;">Manifest: Kein Schmuck. Nur System. Keine Marketing-Floskeln. Nur Daten.</div>
            <div class="footer-links">
                <a href="/datenschutz">Datenschutz</a>
                <a href="/impressum">Impressum</a>
            </div>
        </footer>

    </div>

</body>
</html>