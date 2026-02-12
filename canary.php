<?php

declare(strict_types=1);

$canaryFile = __DIR__ . '/content/canary.md';
$canaryRaw = is_file($canaryFile)
    ? (string)file_get_contents($canaryFile)
    : "# MoneroMarket Canary\n\nNo canary file found yet.";

function render_canary_markdown(string $raw): string
{
    $lines = preg_split('/\R/', trim($raw));
    if (!$lines) {
        return '<p class="canary-empty">No canary update available.</p>';
    }

    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $line = rtrim($line);

        if ($line === '') {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^##\s+(.+)$/', $line, $m)) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h2>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</h2>';
            continue;
        }

        if (preg_match('/^#\s+(.+)$/', $line, $m)) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h1>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</h1>';
            continue;
        }

        if (preg_match('/^-\s+(.+)$/', $line, $m)) {
            if (!$inList) {
                $html .= '<ul class="canary-list">';
                $inList = true;
            }
            $html .= '<li>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</li>';
            continue;
        }

        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }

        $html .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    if ($inList) {
        $html .= '</ul>';
    }

    return $html;
}

$canaryHtml = render_canary_markdown($canaryRaw);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Canary | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<style>
.canary-wrap { max-width: 900px; margin: 30px auto; }
.canary-card p { margin: 0 0 10px; }
.canary-list { margin: 0 0 14px; padding-left: 22px; }
.canary-list li { margin-bottom: 6px; }
</style>
</head>
<body>
<?php require __DIR__ . '/assets/header.php'; ?>

<div class="container canary-wrap">
    <div class="card canary-card">
        <?= $canaryHtml ?>
    </div>
</div>

</body>
</html>
