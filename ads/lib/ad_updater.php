<?php
declare(strict_types=1);

require_once __DIR__ . '/ad_edit_validator.php';

function update_ad(int $adId, array $data, PDO $pdo, string $type): void
{
    $user_id = (int)$_SESSION['user_id'];

    $v = validate_edit_ad($data);

    $stmt = $pdo->prepare("
        UPDATE listings
        SET
            crypto_pay = :coin,
            margin_percent = :margin,
            terms = :terms,
            payin_address = :payin_address,
            payin_network = :payin_network,
            payin_tag_memo = :payin_tag_memo
        WHERE id = :id
          AND user_id = :uid
          AND type = :type
    ");

    $stmt->execute([
        ':coin'   => $v['crypto_pay'],
        ':margin' => $v['margin_percent'],
        ':terms'  => $v['terms'],
        ':payin_address' => $v['payin_address'],
        ':payin_network' => $v['payin_network'],
        ':payin_tag_memo' => $v['payin_tag_memo'],
        ':id'     => $adId,
        ':uid'    => $user_id,
        ':type'   => $type
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Ad not updated');
    }
}
