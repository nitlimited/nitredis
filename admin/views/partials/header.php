<?php defined( 'ABSPATH' ) || exit; ?>
<div class="nitredis-header">
    <div class="nitredis-header__logo">
        <img src="<?php echo esc_url( NITREDIS_URL . 'assets/images/nusite-logo.png' ); ?>" alt="Nusite I.T Consulting" class="nitredis-logo">
    </div>
    <div class="nitredis-header__info">
        <h1 class="nitredis-title">NitRedis <span class="nitredis-version">v<?php echo NITREDIS_VERSION; ?></span></h1>
        <p class="nitredis-subtitle">Redis Object Cache for WordPress</p>
    </div>
    <nav class="nitredis-nav">
        <?php
        $pages = [
            'nitredis'             => 'Dashboard',
            'nitredis-settings'    => 'Settings',
            'nitredis-diagnostics' => 'Diagnostics',
        ];
        $current = $_GET['page'] ?? 'nitredis';
        foreach ( $pages as $slug => $label ) :
        ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"
           class="nitredis-nav__link <?php echo $current === $slug ? 'nitredis-nav__link--active' : ''; ?>">
            <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>
