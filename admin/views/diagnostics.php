<?php
defined( 'ABSPATH' ) || exit;
$checks = NitRedis_Diagnostics::run();
?>
<div class="nitredis-wrap">
    <?php require __DIR__ . '/partials/header.php'; ?>

    <div class="nitredis-card nitredis-card--full">
        <h2 class="nitredis-card__title">System Diagnostics</h2>
        <p class="nitredis-desc">Run time environment and configuration checks for NitRedis.</p>

        <table class="nitredis-diag-table">
            <thead>
                <tr><th>Check</th><th>Status</th><th>Details</th></tr>
            </thead>
            <tbody>
                <?php foreach ( $checks as $check ) : ?>
                <tr>
                    <td><?php echo esc_html( $check['label'] ); ?></td>
                    <td>
                        <span class="nitredis-badge nitredis-badge--<?php echo esc_attr( $check['status'] ); ?>">
                            <?php echo esc_html( ucfirst( $check['status'] ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $check['message'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr class="nitredis-divider">

        <h3 class="nitredis-card__subtitle">Drop-in Management</h3>
        <?php if ( NitRedis_Diagnostics::is_our_drop_in() ) : ?>
            <p>The NitRedis <code>object-cache.php</code> drop-in is currently <strong>installed</strong> in <code>wp-content/</code>.</p>
            <button class="nitredis-btn nitredis-btn--secondary" id="nitredis-remove-dropin-btn2">Remove Drop-in</button>
        <?php elseif ( file_exists( NITREDIS_DROP_IN ) ) : ?>
            <p>A different <code>object-cache.php</code> is installed. Remove it manually before enabling NitRedis.</p>
        <?php else : ?>
            <p>No <code>object-cache.php</code> drop-in is installed. Click below to enable Redis object caching.</p>
            <button class="nitredis-btn" id="nitredis-install-dropin-btn2">Install Drop-in</button>
        <?php endif; ?>
        <span id="nitredis-dropin-msg" class="nitredis-msg" style="display:none;"></span>

        <hr class="nitredis-divider">

        <h3 class="nitredis-card__subtitle">PHP Info</h3>
        <table class="nitredis-diag-table">
            <tr><td>PHP Version</td><td><?php echo PHP_VERSION; ?></td></tr>
            <tr><td>WordPress Version</td><td><?php echo get_bloginfo('version'); ?></td></tr>
            <tr><td>WP_CACHE</td><td><?php echo defined('WP_CACHE') && WP_CACHE ? '<span class="nitredis-badge nitredis-badge--ok">true</span>' : '<span class="nitredis-badge nitredis-badge--warn">false / not set</span>'; ?></td></tr>
            <tr><td>Multisite</td><td><?php echo is_multisite() ? 'Yes' : 'No'; ?></td></tr>
            <tr><td>NitRedis Plugin Dir</td><td><code><?php echo esc_html(NITREDIS_DIR); ?></code></td></tr>
            <tr><td>Drop-in Path</td><td><code><?php echo esc_html(NITREDIS_DROP_IN); ?></code></td></tr>
        </table>
    </div>
</div>

<script>
jQuery(function($){
    function showMsg(msg, ok) {
        var $m = $('#nitredis-dropin-msg');
        $m.text(msg).removeClass('nitredis-msg--ok nitredis-msg--err')
          .addClass(ok ? 'nitredis-msg--ok' : 'nitredis-msg--err').show();
        setTimeout(function(){ $m.fadeOut(); }, 4000);
    }
    $('#nitredis-install-dropin-btn2').on('click', function(){
        $(this).prop('disabled',true).text('Installing…');
        $.post(NitRedis.ajax_url,{action:'nitredis_install_dropin',nonce:NitRedis.nonce},function(res){
            showMsg(res.data.message, res.success);
            if(res.success) location.reload();
        });
    });
    $('#nitredis-remove-dropin-btn2').on('click', function(){
        if(!confirm('Remove the NitRedis drop-in?')) return;
        $(this).prop('disabled',true).text('Removing…');
        $.post(NitRedis.ajax_url,{action:'nitredis_remove_dropin',nonce:NitRedis.nonce},function(res){
            showMsg(res.data.message, res.success);
            if(res.success) location.reload();
        });
    });
});
</script>
