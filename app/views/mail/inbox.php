<?php 
$isSentFolder = false;
$sentNames = ['Sent', 'Wysłane', 'Wyslane', 'Sent Messages', 'Sent Items'];
foreach ($sentNames as $sn) {
    if (strcasecmp($current_folder, $sn) === 0) { $isSentFolder = true; break; }
}
if (stripos($current_folder, 'sent') !== false) $isSentFolder = true;
?>
<div class="mail-layout">
    <aside class="sidebar">
        <a href="<?= APP_URL ?>/index.php?page=compose" class="btn btn-primary btn-block">✏️ Nowa wiadomość</a>
        <nav class="folders">
            <h3>Foldery</h3>
            <ul>
                <?php foreach ($folders as $folder): ?>
                    <li class="<?= $current_folder === $folder['name'] ? 'active' : '' ?>">
                        <a href="<?= APP_URL ?>/index.php?page=folder&folder=<?= urlencode($folder['name']) ?>">
                            <span class="folder-icon">
                                <?php
                                $n = strtoupper($folder['name']);
                                if ($n === 'INBOX') echo '📥';
                                elseif (strpos($n, 'SENT') !== false || strpos($n, 'WYSŁANE') !== false) echo '📤';
                                elseif (strpos($n, 'DRAFT') !== false) echo '📝';
                                elseif (strpos($n, 'TRASH') !== false || strpos($n, 'KOSZ') !== false || strpos($n, 'DELETED') !== false) echo '🗑️';
                                elseif (strpos($n, 'JUNK') !== false || strpos($n, 'SPAM') !== false) echo '⚠️';
                                else echo '📁';
                                ?>
                            </span>
                            <span class="folder-name"><?= htmlspecialchars($folder['displayName']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>
    <section class="mail-list-container">
        <div class="mail-list-header">
            <h2><?= htmlspecialchars($title) ?> <small>(<?= $total ?>)</small></h2>
        </div>
        <?php if (empty($emails)): ?>
            <div class="empty-state"><p>Brak wiadomości w tym folderze.</p></div>
        <?php else: ?>
            <div class="mail-list">
                <?php foreach ($emails as $email): ?>
                    <div class="mail-item <?= $email['unread'] ? 'unread' : '' ?>">
                        <div class="mail-item-indicators">
                            <?php if ($email['has_attachment']) echo '<span title="Załącznik">📎</span>'; ?>
                        </div>
                        <div class="mail-item-content">
                            <a href="<?= APP_URL ?>/index.php?page=read&uid=<?= $email['uid'] ?>&folder=<?= urlencode($current_folder) ?>" class="mail-link">
                                <div class="mail-from">
                                    <?php if ($isSentFolder): ?>
                                        <span style="color:#7f8c8d;font-size:12px;">Do:</span> 
                                        <?= htmlspecialchars($email['to'] ?: $email['to_address']) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($email['from']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="mail-subject"><?= htmlspecialchars($email['subject']) ?></div>
                            </a>
                        </div>
                        <div class="mail-item-date"><?= date('Y-m-d H:i', $email['timestamp']) ?></div>
                        <div class="mail-item-actions">
                            <?php if ($email['unread']): ?>
                                <button onclick="markRead(<?= $email['uid'] ?>, '<?= urlencode($current_folder) ?>')" class="btn-icon" title="Przeczytane">✓</button>
                            <?php else: ?>
                                <button onclick="markUnread(<?= $email['uid'] ?>, '<?= urlencode($current_folder) ?>')" class="btn-icon" title="Nieprzeczytane">○</button>
                            <?php endif; ?>
                            <a href="<?= APP_URL ?>/index.php?page=delete&uid=<?= $email['uid'] ?>&folder=<?= urlencode($current_folder) ?>" class="btn-icon" onclick="return confirm('Usunąć?')">🗑️</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= APP_URL ?>/index.php?page=folder&folder=<?= urlencode($current_folder) ?>&p=<?= $current_page - 1 ?>" class="btn btn-small">←</a>
                    <?php endif; ?>
                    <span class="page-info">Strona <?= $current_page ?> z <?= $pages ?></span>
                    <?php if ($current_page < $pages): ?>
                        <a href="<?= APP_URL ?>/index.php?page=folder&folder=<?= urlencode($current_folder) ?>&p=<?= $current_page + 1 ?>" class="btn btn-small">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<script>
function markRead(uid, folder) { 
    fetch('<?= APP_URL ?>/index.php?page=markRead&uid=' + uid + '&folder=' + folder)
        .then(() => location.reload()); 
}
function markUnread(uid, folder) { 
    fetch('<?= APP_URL ?>/index.php?page=markUnread&uid=' + uid + '&folder=' + folder)
        .then(() => location.reload()); 
}
</script>
