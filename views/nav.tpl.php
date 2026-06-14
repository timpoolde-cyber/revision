<?php
// /Users/timpoolair/R100-CRM/nav.tpl.php

$current_page = basename($_SERVER['PHP_SELF']);

// Blendet die Navigationsleiste auf der Haupt-Werkbank (crm.php) komplett aus
if ($current_page === 'crm.php') {
    return;
}

// Holt die ID aus allen denkbaren Konstellationen der Controller
$navProjectId = $currentProjectId ?? ($id ?? ($_GET['id'] ?? null));
$project_id_param = $navProjectId ? '?id=' . (int)$navProjectId : '';
?>
<div class="sub-nav" style="display: flex; flex-wrap: wrap; gap: 8px; padding: 8px 16px 14px 16px; border-bottom: 1px solid #000; background: #fff; box-sizing: border-box; max-width: 600px; margin-left: auto; margin-right: auto;">
  <a href="crm.php" class="sub-nav-item" style="display: inline-block; padding: 6px 12px; border: 1px solid #000; font-family: monospace; font-size: 12px; font-weight: 700; text-transform: uppercase; text-decoration: none; color: #000; background: #fff; white-space: nowrap;">
    Leads
  </a>
  <a href="project.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'project.php' || $activeControl === 'History') ? 'active' : '' ?>" style="display: inline-block; padding: 6px 12px; border: 1px solid #000; font-family: monospace; font-size: 12px; font-weight: 700; text-transform: uppercase; text-decoration: none; color: #000; background: #fff; white-space: nowrap;">
    History
  </a>
  <a href="edit_data.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'edit_data.php') ? 'active' : '' ?>" style="display: inline-block; padding: 6px 12px; border: 1px solid #000; font-family: monospace; font-size: 12px; font-weight: 700; text-transform: uppercase; text-decoration: none; color: #000; background: #fff; white-space: nowrap;">
    Data
  </a>
  <a href="mail.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'mail.php' || $activeControl === 'Mail') ? 'active' : '' ?>" style="display: inline-block; padding: 6px 12px; border: 1px solid #000; font-family: monospace; font-size: 12px; font-weight: 700; text-transform: uppercase; text-decoration: none; color: #000; background: #fff; white-space: nowrap;">
    Mail
  </a>
  <a href="invoice.php<?= $project_id_param ?>" class="sub-nav-item <?= ($current_page === 'invoice.php' || $activeControl === 'INV') ? 'active' : '' ?>" style="display: inline-block; padding: 6px 12px; border: 1px solid #000; font-family: monospace; font-size: 12px; font-weight: 700; text-transform: uppercase; text-decoration: none; color: #000; background: #fff; white-space: nowrap;">
    INV
  </a>
</div>

<style>
  .sub-nav-item.active {
    color: #fff !important;
    background: #000 !important;
  }
</style>