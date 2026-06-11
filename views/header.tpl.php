<?php
// Globaler Header der CRM-Innenseiten — R400 Status-Cockpit
$r4_states = (isset($db) && isset($project) && is_array($project))
    ? r400_stage_states_for_project($db, $project)
    : r400_stage_states($project ?? []);
?>

<header>
  <div class="brand-container">
    <div class="brand"><span class="brand-name">R400™</span></div>
    <?php r400_status_sprite(); ?>
    <div class="r4-cockpit-row">
      <?php echo r400_status_cockpit($r4_states, 'header'); ?>
      <?php echo r400_kanal_badge($project['channel'] ?? 'lead'); ?>
    </div>
  </div>
</header>
