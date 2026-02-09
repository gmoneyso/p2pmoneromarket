<?php
require_once __DIR__ . '/ad_validator.php';

function create_ad(array $data, PDO $pdo, string $type): void
{
    $user_id = (int) $_SESSION['user_id'];

    // Validate input first
    $v = validate_ad($data);

    // 🔒 SAFETY: prevent overselling XMR
    if ($type === 'sell') {

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(
                CASE direction
                    WHEN 'credit' THEN amount
                    WHEN 'debit'  THEN -amount
                END
            ), 0)
            FROM balance_ledger
            WHERE user_id = :uid
        ");

        $stmt->execute([':uid' => $user_id]);
        $balance = (float) $stmt->fetchColumn();

        if ($v['max_xmr'] > $balance) {
            throw new Exception('Insufficient balance');
        }
    }

    // Insert listing
    $stmt = $pdo->prepare("
        INSERT INTO listings
        (
            user_id,
            type,
            crypto_pay,
            margin_percent,
            min_xmr,
            max_xmr,
            payment_time_limit,
            terms,
            payin_address,
            payin_network,
            payin_tag_memo
        )
        VALUES
        (
            :uid,
            :type,
            :coin,
            :margin,
            :min,
            :max,
            :time,
            :terms,
            :payin_address,
            :payin_network,
            :payin_tag_memo
        )
    ");

    $stmt->execute([
        ':uid'    => $user_id,
        ':type'   => $type,
        ':coin'   => $v['crypto_pay'],
        ':margin' => $v['margin_percent'],
        ':min'    => $v['min_xmr'],
        ':max'    => $v['max_xmr'],
        ':time'   => $v['payment_time_limit'],
        ':terms'  => $v['terms'],
        ':payin_address' => $v['payin_address'],
        ':payin_network' => $v['payin_network'],
        ':payin_tag_memo' => $v['payin_tag_memo'],
    ]);
}
