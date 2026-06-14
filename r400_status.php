<?php
/**
 * r400_status.php — Zentrale, wiederverwendbare Status-Cockpit-Komponente
 *
 * Eine Quelle für alle Seiten:
 *   - r400_status_sprite()                 → SVG-Sprite (5 Symbole), genau einmal pro Request
 *   - r400_stage_states($project)          → leitet die 5 Zustände aus Projektdaten ab
 *   - r400_stage_states_for_project($db,$p)→ wie oben, ergänzt has_quick/has_deep per Query
 *   - r400_status_cockpit($states,$variant)→ baut das Markup ('header' | 'card')
 *
 * Zustände je Box: 'grau' | 'schwarz' | 'gruen' | 'rot' | 'faellig'
 *   grau    = noch nicht erreicht
 *   schwarz = läuft / dieser Schritt ist dran
 *   gruen   = erledigt / positiv bestätigt
 *   rot     = Handlungsbedarf (Zahlung offen)
 *   faellig = Handlungsbedarf, der DICH zwingt (Anruf fällig) → blinkt
 *
 * Strich: non-scaling-stroke steckt als Attribut in jedem Symbol, damit der
 * Strich in jeder Größe gleich dünn bleibt (greift auch durch <use>).
 */

if (!function_exists('r400_status_sprite')) {

    /** Gibt das SVG-Sprite genau einmal pro Request aus. Vor erstem Cockpit aufrufen. */
    function r400_status_sprite(): void {
        static $printed = false;
        if ($printed) { return; }
        $printed = true;
        $ns = 'vector-effect="non-scaling-stroke"';
        echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>'
        . '<symbol id="r4-eingang" viewBox="0 0 80 110"><g fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="8" y="6" width="64" height="98" rx="6" ' . $ns . '/>'
            . '<line x1="24" y1="30" x2="56" y2="30" ' . $ns . '/>'
            . '<line x1="24" y1="42" x2="52" y2="42" ' . $ns . '/>'
            . '<line x1="24" y1="54" x2="56" y2="54" ' . $ns . '/>'
            . '<path d="M28 76 L37 85 L54 66" ' . $ns . '/></g></symbol>'
        . '<symbol id="r4-quick" viewBox="0 0 80 110"><g fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="8" y="6" width="64" height="98" rx="6" ' . $ns . '/>'
            . '<path d="M46 18 L28 54 L39 54 L34 92 L56 50 L45 50 Z" ' . $ns . '/></g></symbol>'
        . '<symbol id="r4-psi" viewBox="0 0 80 110"><g fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="8" y="6" width="64" height="98" rx="6" ' . $ns . '/>'
            . '<path d="M22 66 A18 18 0 0 1 58 66" ' . $ns . '/>'
            . '<line x1="40" y1="66" x2="53" y2="48" ' . $ns . '/>'
            . '<circle cx="40" cy="66" r="3.2" fill="currentColor" stroke="none"/>'
            . '<line x1="24" y1="78" x2="56" y2="78" ' . $ns . '/></g></symbol>'
        . '<symbol id="r4-anruf" viewBox="0 0 80 110"><g fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="8" y="6" width="64" height="98" rx="6" ' . $ns . '/>'
            . '<g transform="translate(19 21) scale(1.78)"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" ' . $ns . '/></g></g></symbol>'
        . '<symbol id="r4-faktura" viewBox="0 0 80 110"><g fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="8" y="6" width="64" height="98" rx="6" ' . $ns . '/>'
            . '<path d="M53 31 C43 27 31 33 31 48 C31 63 43 69 53 65" ' . $ns . '/>'
            . '<line x1="27" y1="43" x2="49" y2="43" ' . $ns . '/>'
            . '<line x1="27" y1="53" x2="47" y2="53" ' . $ns . '/>'
            . '<line x1="27" y1="82" x2="53" y2="82" ' . $ns . '/></g></symbol>'
        . '</defs></svg>';
    }

    /**
     * Leitet die fünf Stufen-Zustände aus den vorhandenen Projektdaten ab.
     * Erwartete Schlüssel (alle optional, null-sicher):
     *   target_url, email|phone|phone_mobile,
     *   has_quick, has_deep (bool),
     *   phase_3_contacted_at, phase_4_engaged_at, phase_5_implemented_at, phase_6_closed_at
     */
    function r400_stage_states(array $p): array {
        $has = static function (string $k) use ($p): bool {
            return isset($p[$k]) && $p[$k] !== '' && $p[$k] !== null && $p[$k] !== 0 && $p[$k] !== '0';
        };
        $contact = $has('email') || $has('phone') || $has('phone_mobile');

        // 1 · Eingang (Formular / Lead da)
        if (!$has('target_url'))            { $eingang = 'grau'; }
        elseif ($contact)                   { $eingang = 'gruen'; }
        else                                { $eingang = 'schwarz'; }

        // 2 · Quick-Report
        if ($has('has_quick'))              { $quick = 'gruen'; }
        elseif ($has('target_url'))         { $quick = 'schwarz'; }
        else                                { $quick = 'grau'; }

        // 3 · PSI-Report (deep)
        if ($has('has_deep'))               { $psi = 'gruen'; }
        elseif ($has('has_quick'))          { $psi = 'schwarz'; }
        else                                { $psi = 'grau'; }

        // 4 · Anruf (manuell über Phasen-Stempel)
        if ($has('phase_4_engaged_at'))     { $anruf = 'gruen'; }      // Gespräch positiv
        elseif ($has('phase_3_contacted_at')){ $anruf = 'schwarz'; }   // im Gespräch / Versuche
        elseif ($has('has_deep'))           { $anruf = 'faellig'; }    // PSI raus → Anruf fällig (blinkt)
        else                                { $anruf = 'grau'; }

        // 5 · Faktura
        if ($has('phase_6_closed_at'))      { $faktura = 'gruen'; }    // bezahlt / abgeschlossen
        elseif ($has('phase_5_implemented_at')){ $faktura = 'rot'; }   // Arbeit fertig, Zahlung offen
        elseif ($has('phase_4_engaged_at')) { $faktura = 'schwarz'; }  // beauftragt, in Arbeit
        else                                { $faktura = 'grau'; }

        return [
            'eingang' => $eingang,
            'quick'   => $quick,
            'psi'     => $psi,
            'anruf'   => $anruf,
            'faktura' => $faktura,
        ];
    }

    /** Wie r400_stage_states(), ergänzt has_quick/has_deep aus psi_results. */
    function r400_stage_states_for_project(PDO $db, array $project): array {
        $id = (int)($project['id'] ?? 0);
        if ($id > 0) {
            try {
                $st = $db->prepare(
                    "SELECT
                        MAX(CASE WHEN report_quick_json IS NOT NULL AND report_quick_json <> '' THEN 1 ELSE 0 END) AS hq,
                        MAX(CASE WHEN report_deep       IS NOT NULL AND report_deep       <> '' THEN 1 ELSE 0 END) AS hd
                     FROM psi_results WHERE project_id = ?"
                );
                $st->execute([$id]);
                $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $project['has_quick'] = !empty($r['hq']);
                $project['has_deep']  = !empty($r['hd']);
            } catch (Throwable $e) {
                // psi_results evtl. nicht vorhanden → Boxen bleiben grau, kein Fatal
            }
        }
        return r400_stage_states($project);
    }

    /** Baut das Cockpit-Markup. $variant: 'header' | 'card'. */
    function r400_status_cockpit(array $states, string $variant = 'header'): string {
        $labels  = ['eingang' => 'in', 'quick' => 'quick', 'psi' => 'psi', 'anruf' => 'anruf', 'faktura' => 'faktura'];
        $allowed = ['grau', 'schwarz', 'gruen', 'rot', 'faellig'];
        $v = ($variant === 'card') ? 'card' : 'header';

        $out = '<div class="r4-cockpit r4-cockpit--' . $v . '">';
        foreach ($labels as $key => $label) {
            $st = $states[$key] ?? 'grau';
            if (!in_array($st, $allowed, true)) { $st = 'grau'; }
            $out .= '<span class="r4ic r4ic--' . $st . '" title="' . $label . '">'
                  . '<svg class="r4ic__icon"><use href="#r4-' . $key . '"/></svg>'
                  . '<span class="r4ic__label">' . $label . '</span>'
                  . '</span>';
        }
        return $out . '</div>';
    }

    /** Baut die schwarze Kanal-Badge (LEAD | MAPS | VIP). */
    function r400_kanal_badge(string $channel = 'lead'): string {
        $labels = ['lead' => 'LEAD', 'maps' => 'MAPS', 'vip' => 'VIP'];
        $label = $labels[$channel] ?? 'LEAD';
        return '<div class="r4-kanal r4-kanal--' . htmlspecialchars($channel, ENT_QUOTES) . '">'
             . htmlspecialchars($label, ENT_QUOTES)
             . '</div>';
    }
}
