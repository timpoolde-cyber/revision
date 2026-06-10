<?php
// Globale Header-Datei mit VIP-Kanal-Routing
// Wird auf allen CRM-Innenseiten eingebunden

// VIP-Kanal-Erkennung
$current_tunnel = $project['tunnel'] ?? ($tunnel ?? 'standard');
$is_vip_channel = in_array($current_tunnel, ['vip', 'anfrage', 'abgeschaltet']);
$is_terminated = $current_tunnel === 'abgeschaltet';
?>

<header>
  <div class="brand-container">
    <div class="brand"><span class="brand-name">Revision100™</span></div>

    <?php if ($is_vip_channel): ?>
      <!-- VIP-Kanal: Zeige neuen Status-Header -->
      <div class="vip-header-display" style="<?php echo $is_terminated ? 'opacity: 0.6;' : ''; ?>">
        <?php include __DIR__ . '/../flow/vip/snippet_status_vip_header.php'; ?>
      </div>
    <?php else: ?>
      <!-- Standard-Kanal: Zeige altes VisionControl -->
      <div id="statusSquares" class="status-squares"></div>
    <?php endif; ?>
  </div>
</header>
