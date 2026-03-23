<?php
defined( 'ABSPATH' ) || exit;
$s = NitRedis_Settings::get();
?>
<div class="nitredis-wrap">
    <?php require __DIR__ . '/partials/header.php'; ?>

    <!-- wp-config.php scanner banner -->
    <div class="nitredis-card nitredis-card--full nitredis-card--scan" id="nitredis-scan-card">
        <div class="nitredis-scan-header">
            <div>
                <h2 class="nitredis-card__title" style="margin-bottom:4px;">Auto-detect from wp-config.php</h2>
                <p class="nitredis-desc" style="margin:0;">NitRedis will scan your <code>wp-config.php</code> for Redis constants from any plugin or hosting platform and pre-fill the form below.</p>
            </div>
            <button class="nitredis-btn" id="nitredis-scan-btn" type="button">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Scan wp-config.php
            </button>
        </div>
        <div id="nitredis-scan-result" style="display:none;">
            <div class="nitredis-scan-result-inner">
                <div id="nitredis-scan-summary"></div>
                <div id="nitredis-scan-constants"></div>
                <div id="nitredis-scan-warnings"></div>
                <div id="nitredis-scan-actions" style="display:none; margin-top:14px;">
                    <button class="nitredis-btn" id="nitredis-apply-scan-btn" type="button">✓ Apply Detected Settings</button>
                    <span class="nitredis-hint" style="margin-left:10px; display:inline-block; padding-top:8px;">This will populate the form — you can review before saving.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="nitredis-card nitredis-card--full">
        <h2 class="nitredis-card__title">Connection Settings</h2>
        <p class="nitredis-desc">Settings can also be defined as constants in <code>wp-config.php</code> (e.g. <code>NITREDIS_HOST</code>). Constants take precedence over these values.</p>

        <form id="nitredis-settings-form" class="nitredis-form">

            <div class="nitredis-form-section">
                <h3>Connection</h3>
                <div class="nitredis-form-row">
                    <label>Scheme</label>
                    <select name="scheme">
                        <?php foreach ( ['tcp','unix','tls'] as $scheme ) : ?>
                        <option value="<?php echo esc_attr($scheme); ?>" <?php selected($s['scheme'],$scheme); ?>><?php echo esc_html(strtoupper($scheme)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="nitredis-form-row">
                    <label>Host</label>
                    <input type="text" name="host" value="<?php echo esc_attr($s['host']); ?>" placeholder="127.0.0.1">
                </div>
                <div class="nitredis-form-row">
                    <label>Port</label>
                    <input type="number" name="port" value="<?php echo esc_attr($s['port']); ?>" placeholder="6379" min="1" max="65535">
                </div>
                <div class="nitredis-form-row">
                    <label>Unix Socket Path</label>
                    <input type="text" name="path" value="<?php echo esc_attr($s['path']); ?>" placeholder="/tmp/redis.sock">
                    <span class="nitredis-hint">Only used when scheme is UNIX</span>
                </div>
                <div class="nitredis-form-row">
                    <label>Database</label>
                    <input type="number" name="database" value="<?php echo esc_attr($s['database']); ?>" placeholder="0" min="0" max="15">
                </div>
            </div>

            <div class="nitredis-form-section">
                <h3>Authentication</h3>
                <div class="nitredis-form-row">
                    <label>Username <span class="nitredis-hint">(Redis 6+ ACL)</span></label>
                    <input type="text" name="username" value="<?php echo esc_attr($s['username']); ?>" autocomplete="off">
                </div>
                <div class="nitredis-form-row">
                    <label>Password</label>
                    <input type="password" name="password" value="<?php echo esc_attr($s['password']); ?>" autocomplete="new-password">
                </div>
                <div class="nitredis-form-row">
                    <label>Enable SSL/TLS</label>
                    <label class="nitredis-toggle">
                        <input type="checkbox" name="ssl" value="1" <?php checked($s['ssl']); ?>>
                        <span class="nitredis-toggle__slider"></span>
                    </label>
                </div>
            </div>

            <div class="nitredis-form-section">
                <h3>Timeouts &amp; Keys</h3>
                <div class="nitredis-form-row">
                    <label>Connection Timeout (s)</label>
                    <input type="number" name="timeout" value="<?php echo esc_attr($s['timeout']); ?>" step="0.1" min="0">
                </div>
                <div class="nitredis-form-row">
                    <label>Read Timeout (s)</label>
                    <input type="number" name="read_timeout" value="<?php echo esc_attr($s['read_timeout']); ?>" step="0.1" min="0">
                </div>
                <div class="nitredis-form-row">
                    <label>Key Prefix</label>
                    <input type="text" name="prefix" value="<?php echo esc_attr($s['prefix']); ?>" placeholder="nitredis_">
                </div>
                <div class="nitredis-form-row">
                    <label>Max TTL (s) <span class="nitredis-hint">0 = unlimited</span></label>
                    <input type="number" name="max_ttl" value="<?php echo esc_attr($s['max_ttl']); ?>" min="0">
                </div>
            </div>

            <div class="nitredis-form-section">
                <h3>Automatic Updates</h3>
                <p class="nitredis-desc" style="margin-bottom:14px;">NitRedis checks your GitHub repository for new releases and notifies WordPress when an update is available. Set your repository once and all sites will update automatically.</p>
                <div class="nitredis-form-row">
                    <label>GitHub Repository
                        <span class="nitredis-hint">owner/repo</span>
                    </label>
                    <div>
                        <input type="text" name="github_repo"
                               value="<?php echo esc_attr($s['github_repo'] ?? 'nitlimited/nitredis'); ?>"
                               placeholder="nitlimited/nitredis" style="margin-bottom:8px;">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <button type="button" class="nitredis-btn nitredis-btn--sm nitredis-btn--secondary" id="nitredis-test-github-btn">
                                Test Connection
                            </button>
                            <span id="nitredis-github-test-msg" class="nitredis-msg" style="display:none;"></span>
                        </div>
                    </div>
                </div>
                <div class="nitredis-form-row">
                    <label>GitHub Token
                        <span class="nitredis-hint">Private repos only</span>
                    </label>
                    <input type="password" name="github_token"
                           value="<?php echo esc_attr($s['github_token'] ?? ''); ?>"
                           autocomplete="new-password"
                           placeholder="ghp_xxxx  (leave blank for public repos)">
                </div>
            </div>

            <div class="nitredis-form-section">
                <h3>Cache Groups</h3>
                <div class="nitredis-form-row nitredis-form-row--tall">
                    <label>Global Groups<br><span class="nitredis-hint">One per line</span></label>
                    <textarea name="global_groups_raw" rows="8"><?php echo esc_textarea( implode( "\n", (array) $s['global_groups'] ) ); ?></textarea>
                </div>
                <div class="nitredis-form-row nitredis-form-row--tall">
                    <label>Ignored Groups<br><span class="nitredis-hint">Not stored in Redis</span></label>
                    <textarea name="ignored_groups_raw" rows="4"><?php echo esc_textarea( implode( "\n", (array) $s['ignored_groups'] ) ); ?></textarea>
                </div>
            </div>

            <div class="nitredis-form-footer">
                <button type="submit" class="nitredis-btn" id="nitredis-save-btn">Save Settings</button>
                <span id="nitredis-save-msg" class="nitredis-msg" style="display:none;"></span>
            </div>

        </form>
    </div>
</div>

<script>
jQuery(function($){

    // ── Save settings ──────────────────────────────────────────────────────
    $('#nitredis-settings-form').on('submit', function(e){
        e.preventDefault();
        var $btn = $('#nitredis-save-btn').prop('disabled',true).text('Saving…');

        // Collect all named fields into a flat object
        var formData = {};
        $(this).find('[name]').each(function(){
            var el = $(this);
            var name = el.attr('name');
            if (el.attr('type') === 'checkbox') {
                formData[name] = el.is(':checked') ? '1' : '';
            } else {
                formData[name] = el.val();
            }
        });

        // Send each settings key as settings[key] so PHP sees $_POST['settings'] as an array
        var payload = { action: 'nitredis_save_settings', nonce: NitRedis.nonce };
        $.each(formData, function(k, v){ payload['settings[' + k + ']'] = v; });

        $.ajax({
            url: NitRedis.ajax_url,
            method: 'POST',
            data: payload,
            success: function(res) {
                var $m = $('#nitredis-save-msg');
                var msg = (res && res.data && res.data.message) ? res.data.message : (res.success ? 'Settings saved.' : 'Failed to save settings.');
                $m.text(msg).removeClass('nitredis-msg--ok nitredis-msg--err')
                  .addClass(res.success ? 'nitredis-msg--ok' : 'nitredis-msg--err').show();
                setTimeout(function(){ $m.fadeOut(); }, 5000);
                $btn.prop('disabled', false).text('Save Settings');
            },
            error: function(xhr) {
                var $m = $('#nitredis-save-msg');
                $m.text('Request failed (HTTP ' + xhr.status + '). Check browser console.')
                  .removeClass('nitredis-msg--ok').addClass('nitredis-msg--err').show();
                $btn.prop('disabled', false).text('Save Settings');
                console.error('NitRedis save error:', xhr.responseText);
            }
        });
    });

    // ── Test GitHub repo connection ───────────────────────────────────────
    $('#nitredis-test-github-btn').on('click', function(){
        var repo  = $('input[name=github_repo]').val().trim();
        var token = $('input[name=github_token]').val().trim();
        var $msg  = $('#nitredis-github-test-msg');
        var $btn  = $(this).prop('disabled', true).text('Testing…');

        $msg.hide();

        $.post(NitRedis.ajax_url, {
            action: 'nitredis_test_github',
            nonce:  NitRedis.nonce,
            repo:   repo,
            token:  token
        }, function(res) {
            $msg.text(res.data.message)
                .removeClass('nitredis-msg--ok nitredis-msg--err')
                .addClass(res.success ? 'nitredis-msg--ok' : 'nitredis-msg--err')
                .show();
            $btn.prop('disabled', false).text('Test Connection');
        }).fail(function() {
            $msg.text('Request failed.').addClass('nitredis-msg--err').show();
            $btn.prop('disabled', false).text('Test Connection');
        });
    });

    // ── wp-config.php scanner ──────────────────────────────────────────────
    var _scanData = null;

    $('#nitredis-scan-btn').on('click', function(){
        var $btn = $(this).prop('disabled', true).text('Scanning…');
        var $result = $('#nitredis-scan-result').hide();
        $('#nitredis-scan-summary, #nitredis-scan-constants, #nitredis-scan-warnings, #nitredis-scan-actions').empty().hide();

        $.post(NitRedis.ajax_url, { action:'nitredis_scan_config', nonce:NitRedis.nonce }, function(res){
            $btn.prop('disabled', false).html(
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Scan wp-config.php'
            );
            if (!res.success) {
                $('#nitredis-scan-summary').html('<span class="nitredis-badge nitredis-badge--error">Error</span> ' + res.data.message).show();
                $result.show(); return;
            }

            var d = res.data;
            _scanData = d;

            // Summary
            var $sum = $('#nitredis-scan-summary');
            if (d.found) {
                var keyCount = Object.keys(d.settings).length;
                $sum.html(
                    '<span class="nitredis-badge nitredis-badge--ok">Found</span> ' +
                    '<strong>' + keyCount + ' setting' + (keyCount !== 1 ? 's' : '') + ' detected</strong>' +
                    ' &nbsp;·&nbsp; Source: <code>' + escHtml(d.source) + '</code>' +
                    '<br><small style="color:var(--nr-text-muted);">Config file: <code>' + escHtml(d.path) + '</code></small>'
                );
            } else {
                $sum.html(
                    '<span class="nitredis-badge nitredis-badge--warn">Not found</span> ' +
                    'No Redis constants detected in <code>' + escHtml(d.path || 'wp-config.php') + '</code>.'
                );
            }
            $sum.show();

            // Constants table
            if (d.constants && Object.keys(d.constants).length) {
                var rows = '';
                $.each(d.constants, function(k, v){
                    var display = (typeof v === 'boolean') ? (v ? 'true' : 'false') : escHtml(String(v));
                    // Mask passwords
                    if (/password|pass/i.test(k)) {
                        display = v ? '••••••••' : '<em style="color:var(--nr-text-muted)">empty</em>';
                    }
                    rows += '<tr><td><code>' + escHtml(k) + '</code></td><td>' + display + '</td></tr>';
                });
                $('#nitredis-scan-constants').html(
                    '<h4 class="nitredis-scan-section-title">Detected Constants / Variables</h4>' +
                    '<table class="nitredis-diag-table nitredis-diag-table--compact">' +
                    '<thead><tr><th>Constant</th><th>Value</th></tr></thead><tbody>' + rows + '</tbody></table>'
                ).show();
            }

            // Warnings
            if (d.warnings && d.warnings.length) {
                var whtml = '<h4 class="nitredis-scan-section-title">Notes</h4><ul class="nitredis-scan-warnings">';
                $.each(d.warnings, function(_, w){ whtml += '<li>⚠ ' + escHtml(w) + '</li>'; });
                whtml += '</ul>';
                $('#nitredis-scan-warnings').html(whtml).show();
            }

            // Apply button — only if settings were found
            if (d.found) {
                $('#nitredis-scan-actions').show();
            }

            $result.slideDown(200);
        });
    });

    // Apply scanned settings into form
    $('#nitredis-apply-scan-btn').on('click', function(){
        if (!_scanData || !_scanData.settings) return;
        var s = _scanData.settings;
        var fieldMap = {
            scheme: 'select[name=scheme]', host: 'input[name=host]', port: 'input[name=port]',
            database: 'input[name=database]', password: 'input[name=password]',
            username: 'input[name=username]', timeout: 'input[name=timeout]',
            read_timeout: 'input[name=read_timeout]', prefix: 'input[name=prefix]',
            path: 'input[name=path]', max_ttl: 'input[name=max_ttl]'
        };
        var applied = 0;
        $.each(fieldMap, function(key, selector){
            if (typeof s[key] !== 'undefined') {
                $(selector).val(s[key]).addClass('nitredis-field--applied');
                applied++;
                setTimeout(function(){ $(selector).removeClass('nitredis-field--applied'); }, 2000);
            }
        });
        if (typeof s.ssl !== 'undefined') {
            $('input[name=ssl]').prop('checked', !!s.ssl).addClass('nitredis-field--applied');
        }

        // Flash confirmation
        $(this).text('✓ Applied ' + applied + ' field' + (applied !== 1 ? 's' : '') + '!').prop('disabled', true);
        var $btn = this;
        setTimeout(function(){ $($btn).text('✓ Apply Detected Settings').prop('disabled', false); }, 2500);

        // Scroll to form
        $('html,body').animate({ scrollTop: $('#nitredis-settings-form').offset().top - 80 }, 400);
    });

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});
</script>
