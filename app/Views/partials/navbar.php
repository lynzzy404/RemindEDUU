<?php

declare(strict_types=1);

?>
<header class="navbar">
    <div class="navbar__inner container">
        <a class="navbar__brand" href="/">RemindEDU</a>
        <?php if (!$hasAuthenticatedUser): ?>
            <nav class="navbar__links" aria-label="Navigasi utama">
                <a href="/login">Masuk</a>
                <a href="/register">Daftar</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
