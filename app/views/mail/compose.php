<div class="compose-container">
    <h1><?= htmlspecialchars($title) ?></h1>
    <form method="POST" action="<?= APP_URL ?>/index.php?page=compose" enctype="multipart/form-data" class="compose-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <?php if (!empty($reply_to)): ?>
            <input type="hidden" name="reply_to" value="<?= htmlspecialchars($reply_to) ?>">
        <?php endif; ?>
        <?php if (!empty($in_reply_to_id)): ?>
            <input type="hidden" name="in_reply_to_id" value="<?= htmlspecialchars($in_reply_to_id) ?>">
        <?php endif; ?>
        <?php if (!empty($forward_msgno)): ?>
            <input type="hidden" name="forward_msgno" value="<?= $forward_msgno ?>">
            <input type="hidden" name="forward_folder" value="<?= htmlspecialchars($forward_folder ?? 'INBOX') ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label>Do:</label>
            <input type="text" name="to" required value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="form-group">
            <label>DW:</label>
            <input type="text" name="cc" value="">
        </div>
        <div class="form-group">
            <label>Temat:</label>
            <input type="text" name="subject" required value="<?= htmlspecialchars($subject) ?>">
        </div>
        <div class="form-group">
            <label>Treść:</label>
            <textarea name="body" rows="15" required><?= htmlspecialchars($body) ?></textarea>
        </div>
        <div class="form-group">
            <label>Załączniki:</label>
            <input type="file" name="attachments[]" multiple>
            <?php if (!empty($attachments)): ?>
                <div class="forward-attachments" style="margin-top:10px;">
                    <label style="font-weight:bold;">Dołącz z przekazywanej wiadomości:</label>
                    <?php foreach ($attachments as $att): ?>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="forward_attachments[]" value="<?= $att['part_number'] ?>" checked> 
                            <?= htmlspecialchars($att['filename']) ?> (<?= round($att['size']/1024,1) ?> KB)
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">📤 Wyślij</button>
            <a href="<?= APP_URL ?>/index.php?page=inbox" class="btn">Anuluj</a>
        </div>
    </form>
</div>
