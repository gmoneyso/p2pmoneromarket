<?php
declare(strict_types=1);

const MESSAGES_UNLOCK_HOURS = 72;
const MESSAGES_MAX_FAILED_ATTEMPTS = 5;
const MESSAGES_LOCK_MINUTES = 30;

require_once __DIR__ . '/helpers/core.php';
require_once __DIR__ . '/helpers/unlock.php';
require_once __DIR__ . '/helpers/crypto.php';
require_once __DIR__ . '/helpers/threads.php';
require_once __DIR__ . '/helpers/send.php';
