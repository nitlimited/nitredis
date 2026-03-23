<?php defined( 'ABSPATH' ) || exit; ?>
<div class="nitredis-wrap">
    <?php require __DIR__ . '/partials/header.php'; ?>

    <div class="nitredis-grid">

        <!-- Status card -->
        <div class="nitredis-card nitredis-card--status">
            <h2 class="nitredis-card__title">Cache Status</h2>
            <?php
            // Always force a fresh connection attempt using the current saved settings.
            NitRedis_Connection::disconnect();
            $connected  = NitRedis_Cache::ping();
            $drop_in    = NitRedis_Diagnostics::is_our_drop_in();
            $status_cls = ( $connected && $drop_in ) ? 'nitredis-badge--ok' : 'nitredis-badge--error';
            $status_lbl = ( $connected && $drop_in ) ? 'Active' : ( $connected ? 'Connected (drop-in missing)' : 'Disconnected' );
            ?>
            <span class="nitredis-badge <?php echo esc_attr( $status_cls ); ?>"><?php echo esc_html( $status_lbl ); ?></span>

            <ul class="nitredis-stat-list" id="nitredis-metrics">
                <li><span>Version</span><strong id="nr-version">—</strong></li>
                <li><span>Memory Used</span><strong id="nr-memory">—</strong></li>
                <li><span>Keys Stored</span><strong id="nr-keys">—</strong></li>
                <li><span>Hit Ratio</span><strong id="nr-hit-ratio">—</strong></li>
                <li><span>Hits</span><strong id="nr-hits">—</strong></li>
                <li><span>Misses</span><strong id="nr-misses">—</strong></li>
                <li><span>Evicted Keys</span><strong id="nr-evicted">—</strong></li>
                <li><span>Uptime</span><strong id="nr-uptime">—</strong></li>
            </ul>
            <p><button class="nitredis-btn nitredis-btn--sm" id="nitredis-refresh-metrics">↻ Refresh</button></p>
        </div>

        <!-- Actions card -->
        <div class="nitredis-card">
            <h2 class="nitredis-card__title">Actions</h2>
            <div class="nitredis-actions">
                <button class="nitredis-btn nitredis-btn--danger" id="nitredis-flush-btn">
                    🗑 Flush Cache
                </button>
                <?php if ( $drop_in ) : ?>
                <button class="nitredis-btn nitredis-btn--secondary" id="nitredis-remove-dropin-btn">
                    ✕ Disable Drop-in
                </button>
                <?php else : ?>
                <button class="nitredis-btn" id="nitredis-install-dropin-btn">
                    ✓ Enable Drop-in
                </button>
                <?php endif; ?>
            </div>
            <div id="nitredis-action-msg" class="nitredis-msg" style="display:none;"></div>

            <hr class="nitredis-divider">

            <h3 class="nitredis-card__subtitle">Quick Links</h3>
            <ul class="nitredis-links">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=nitredis-settings' ) ); ?>">⚙ Settings</a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=nitredis-diagnostics' ) ); ?>">🔍 Diagnostics</a></li>
            </ul>
        </div>

        <!-- Hit ratio chart -->
        <div class="nitredis-card nitredis-card--chart">
            <h2 class="nitredis-card__title">Hit / Miss Ratio</h2>
            <div class="nitredis-donut-wrap">
                <svg id="nitredis-donut" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                    <circle id="nitredis-donut-arc" cx="50" cy="50" r="38" fill="none"
                            stroke="url(#nitredis-grad)" stroke-width="12"
                            stroke-dasharray="0 239" stroke-linecap="round"
                            transform="rotate(-90 50 50)"/>
                    <defs>
                        <linearGradient id="nitredis-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%"   stop-color="#00c6ff"/>
                            <stop offset="100%" stop-color="#0044cc"/>
                        </linearGradient>
                    </defs>
                    <text x="50" y="46" text-anchor="middle" font-size="16" font-weight="700" fill="#1a202c" id="nitredis-donut-pct">—</text>
                    <text x="50" y="58" text-anchor="middle" font-size="7"  fill="#718096">Hit Rate</text>
                </svg>
            </div>
        </div>

    </div><!-- /.nitredis-grid -->
</div><!-- /.nitredis-wrap -->

<script>
jQuery(function($){
    function loadMetrics() {
        $.post(NitRedis.ajax_url, { action:'nitredis_get_metrics', nonce:NitRedis.nonce }, function(res){
            if(!res.success) return;
            var d = res.data;
            $('#nr-version').text(d.redis_version||'—');
            $('#nr-memory').text(d.used_memory_human||'—');
            $('#nr-keys').text(typeof d.key_count!=='undefined' ? d.key_count.toLocaleString() : '—');
            $('#nr-hit-ratio').text(typeof d.hit_ratio!=='undefined' ? d.hit_ratio+'%' : '—');
            $('#nr-hits').text(typeof d.keyspace_hits!=='undefined' ? d.keyspace_hits.toLocaleString() : '—');
            $('#nr-misses').text(typeof d.keyspace_misses!=='undefined' ? d.keyspace_misses.toLocaleString() : '—');
            $('#nr-evicted').text(typeof d.evicted_keys!=='undefined' ? d.evicted_keys.toLocaleString() : '—');
            var uptime = parseInt(d.uptime_in_seconds||0);
            var h = Math.floor(uptime/3600), m = Math.floor((uptime%3600)/60);
            $('#nr-uptime').text(h+'h '+m+'m');
            // Donut
            var pct = parseFloat(d.hit_ratio||0);
            var circ = 2*Math.PI*38;
            var dash = (pct/100)*circ;
            $('#nitredis-donut-arc').attr('stroke-dasharray', dash.toFixed(1)+' '+circ.toFixed(1));
            $('#nitredis-donut-pct').text(pct+'%');
        });
    }
    loadMetrics();
    $('#nitredis-refresh-metrics').on('click', loadMetrics);

    function showMsg(msg, ok) {
        var $m = $('#nitredis-action-msg');
        $m.text(msg).removeClass('nitredis-msg--ok nitredis-msg--err')
          .addClass(ok ? 'nitredis-msg--ok' : 'nitredis-msg--err').show();
        setTimeout(function(){ $m.fadeOut(); }, 4000);
    }

    $('#nitredis-flush-btn').on('click', function(){
        $(this).prop('disabled',true).text('Flushing…');
        var btn = this;
        $.post(NitRedis.ajax_url, { action:'nitredis_flush', nonce:NitRedis.nonce }, function(res){
            showMsg(res.data.message, res.success);
            $(btn).prop('disabled',false).text('🗑 Flush Cache');
            loadMetrics();
        });
    });

    $('#nitredis-install-dropin-btn').on('click', function(){
        $(this).prop('disabled',true).text('Installing…');
        $.post(NitRedis.ajax_url, { action:'nitredis_install_dropin', nonce:NitRedis.nonce }, function(res){
            showMsg(res.data.message, res.success);
            if(res.success) location.reload();
        });
    });

    $('#nitredis-remove-dropin-btn').on('click', function(){
        if(!confirm('Disable NitRedis drop-in? Object caching will stop.')) return;
        $(this).prop('disabled',true).text('Removing…');
        $.post(NitRedis.ajax_url, { action:'nitredis_remove_dropin', nonce:NitRedis.nonce }, function(res){
            showMsg(res.data.message, res.success);
            if(res.success) location.reload();
        });
    });
});
</script>
