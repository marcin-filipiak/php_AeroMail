<div class="mail-read">
    <div class="mail-actions-bar">
        <a href="<?= APP_URL ?>/index.php?page=folder&folder=<?= urlencode($current_folder) ?>" class="btn btn-small">← Powrót</a>
        <a href="<?= APP_URL ?>/index.php?page=reply&uid=<?= $uid ?>&folder=<?= urlencode($current_folder) ?>" class="btn btn-small">↩️ Odpowiedz</a>
        <a href="<?= APP_URL ?>/index.php?page=forward&uid=<?= $uid ?>&folder=<?= urlencode($current_folder) ?>" class="btn btn-small">↪️ Przekaż</a>
        <a href="<?= APP_URL ?>/index.php?page=delete&uid=<?= $uid ?>&folder=<?= urlencode($current_folder) ?>" class="btn btn-small btn-danger" onclick="return confirm('Usunąć?')">🗑️ Usuń</a>
    </div>
    <div class="mail-header">
        <h1><?= htmlspecialchars($email['subject']) ?></h1>
        <div class="mail-meta">
            <div class="meta-row"><strong>Od:</strong> <?= htmlspecialchars($email['from']) ?></div>
            <div class="meta-row"><strong>Do:</strong> <?= htmlspecialchars($email['to']) ?></div>
            <?php if (!empty($email['cc'])): ?>
                <div class="meta-row"><strong>Dw:</strong> <?= htmlspecialchars($email['cc']) ?></div>
            <?php endif; ?>
            <div class="meta-row"><strong>Data:</strong> <?= htmlspecialchars($email['date']) ?></div>
        </div>
    </div>
    <?php if (!empty($email['attachments'])): ?>
        <div class="attachments">
            <h3>📎 Załączniki (<?= count($email['attachments']) ?>)</h3>
            <div class="attachment-list">
                <?php foreach ($email['attachments'] as $att): ?>
                    <a href="<?= APP_URL ?>/index.php?page=download&uid=<?= $uid ?>&part=<?= $att['part_number'] ?>&folder=<?= urlencode($current_folder) ?>" class="attachment-item">
                        <span>📄</span> 
                        <span><?= htmlspecialchars($att['filename']) ?></span> 
                        <span class="att-size"><?= round($att['size'] / 1024, 1) ?> KB</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="mail-body">
        <?= !empty($email['body_html']) ? $email['body_html'] : nl2br(htmlspecialchars($email['body_text'])) ?>
    </div>
</div>
