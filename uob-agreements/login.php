<?php

declare(strict_types=1);

// Preserve old bookmarks while routing every public sign-in through the real
// PostgreSQL-backed workspace authentication flow.
header('Location: workspace/login.php', true, 302);
exit;
