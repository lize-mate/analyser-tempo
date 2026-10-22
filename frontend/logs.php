<?php
require_once 'includes/config.php';
require_once 'includes/api.php';

requireAdmin();

$pageTitle     = 'logs';
$activeSection = 'logs';

include 'includes/header.php';
?>

    <!-- COLUNA DIREITA: FEED -->
    <div class="dbw-feed">
      <div class="dbw-feed-head">
        <span class="dbw-feed-title">Live Activity Feed</span>
        <span class="dbw-live"><span class="dot"></span> Live</span>
      </div>
      <div class="dbw-feed-list" id="dbwFeed">
        <div class="dbw-feed-item">
          <div class="ft">A carregar</div>
          <div class="fm">A ligar ao serviço de análise…</div>
          <div class="fh">agora</div>
        </div>
      </div>
    </div>
