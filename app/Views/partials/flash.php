<?php

$flash = $data['flash'] ?? null;
if (is_array($flash)):
    foreach ($flash as $type => $message):
        if (!is_string($message)) {
            continue;
        }
        ?>
        <div class="flash flash--<?= e((string) $type) ?>" role="status">
            <?= e($message) ?>
        </div>
        <?php
    endforeach;
endif;
