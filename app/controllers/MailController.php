<?php
class MailController extends Controller {
    public function inbox() {
        $page = max(1, (int)($_GET['p'] ?? 1));
        $result = $this->imap->getEmails('INBOX', $page);
        $folders = $this->imap->getFolders();
        $this->view('mail/inbox', ['title' => 'Odebrane', 'content_view' => 'mail/inbox', 'emails' => $result['emails'], 'total' => $result['total'], 'pages' => $result['pages'], 'current_page' => $result['current_page'], 'folders' => $folders, 'current_folder' => 'INBOX']);
    }

    public function folder() {
        $folderName = urldecode($_GET['folder'] ?? 'INBOX');
        $page = max(1, (int)($_GET['p'] ?? 1));
        $result = $this->imap->getEmails($folderName, $page);
        $folders = $this->imap->getFolders();
        $title = $this->imap->isTrashFolder($folderName) ? 'Kosz' : ($this->imap->isSentFolder($folderName) ? 'Wysłane' : $folderName);
        $this->view('mail/inbox', ['title' => $title, 'content_view' => 'mail/inbox', 'emails' => $result['emails'], 'total' => $result['total'], 'pages' => $result['pages'], 'current_page' => $result['current_page'], 'folders' => $folders, 'current_folder' => $folderName]);
    }

    public function read() {
        $uid = (int)($_GET['uid'] ?? 0);
        $folder = urldecode($_GET['folder'] ?? 'INBOX');
        if (!$uid) { $this->redirect('index.php?page=inbox'); }
        $this->imap->selectFolder($folder);
        $email = $this->imap->getEmailByUid($uid);
        if (!$email) { $this->setFlash('error', 'Nie znaleziono wiadomości.'); $this->redirect('index.php?page=folder&folder=' . urlencode($folder)); }
        $this->imap->markAsReadByUid($uid);
        $this->view('mail/read', ['title' => $email['subject'], 'content_view' => 'mail/read', 'email' => $email, 'uid' => $uid, 'current_folder' => $folder]);
    }

    public function compose() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->handleSend(); return; }
        $this->view('mail/compose', ['title' => 'Nowa wiadomość', 'content_view' => 'mail/compose', 'to' => '', 'subject' => '', 'body' => '', 'csrf_token' => $this->generateCsrfToken(), 'reply_to' => '', 'in_reply_to_id' => '', 'forward_msgno' => '', 'forward_folder' => '', 'attachments' => []]);
    }

    public function reply() {
        $uid = (int)($_GET['uid'] ?? 0);
        $folder = urldecode($_GET['folder'] ?? 'INBOX');
        if (!$uid) { $this->redirect('index.php?page=inbox'); }
        $this->imap->selectFolder($folder);
        $email = $this->imap->getEmailByUid($uid);
        if (!$email) { $this->redirect('index.php?page=inbox'); }
        $quotedBody = "\n\n----- Original Message -----\nOd: " . $email['from'] . "\nData: " . $email['date'] . "\n\n" . strip_tags($email['body_text'] ?: $email['body_html']);
        $subject = (stripos($email['subject'], 'Re:') !== 0 ? 'Re: ' : '') . $email['subject'];
        $this->view('mail/compose', ['title' => 'Odpowiedz', 'content_view' => 'mail/compose', 'to' => $email['from_address'], 'subject' => $subject, 'body' => $quotedBody, 'csrf_token' => $this->generateCsrfToken(), 'reply_to' => $email['from_address'], 'in_reply_to_id' => '<' . $uid . '@' . MAIL_SERVER . '>', 'forward_msgno' => '', 'forward_folder' => '', 'attachments' => []]);
    }

    public function forward() {
        $uid = (int)($_GET['uid'] ?? 0);
        $folder = urldecode($_GET['folder'] ?? 'INBOX');
        if (!$uid) { $this->redirect('index.php?page=inbox'); }
        $this->imap->selectFolder($folder);
        $email = $this->imap->getEmailByUid($uid);
        if (!$email) { $this->redirect('index.php?page=inbox'); }
        $forwardBody = "\n\n----- Forwarded Message -----\nOd: " . $email['from'] . "\nData: " . $email['date'] . "\n\n" . strip_tags($email['body_text'] ?: $email['body_html']);
        $subject = (stripos($email['subject'], 'Fwd:') !== 0 ? 'Fwd: ' : '') . $email['subject'];
        $this->view('mail/compose', ['title' => 'Przekaż', 'content_view' => 'mail/compose', 'to' => '', 'subject' => $subject, 'body' => $forwardBody, 'csrf_token' => $this->generateCsrfToken(), 'reply_to' => '', 'in_reply_to_id' => '', 'forward_msgno' => $uid, 'forward_folder' => $folder, 'attachments' => $email['attachments']]);
    }

    private function handleSend() {
        if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) { 
            $this->setFlash('error', 'Błąd bezpieczeństwa.'); 
            $this->redirect('index.php?page=compose'); 
        }
        
        $to = trim($_POST['to'] ?? ''); 
        $subject = trim($_POST['subject'] ?? ''); 
        $body = $_POST['body'] ?? '';
        $cc = trim($_POST['cc'] ?? ''); 
        $inReplyTo = $_POST['in_reply_to_id'] ?? '';
        
        if (empty($to) || empty($subject)) { 
            $this->setFlash('error', 'Podaj odbiorcę i temat.'); 
            $this->redirect('index.php?page=compose'); 
        }

        // --- BEZPIECZNA OBSŁUGA ZAŁĄCZNIKÓW ---
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $i => $name) {
                if (empty($name)) continue;
                
                $error = $_FILES['attachments']['error'][$i];
                $tmpName = $_FILES['attachments']['tmp_name'][$i];
                $size = $_FILES['attachments']['size'][$i];
                
                if ($error !== UPLOAD_ERR_OK) {
                    $errors = [
                        UPLOAD_ERR_INI_SIZE => 'Plik przekracza limit upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'Plik przekracza limit MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'Plik został częściowo przesłany',
                        UPLOAD_ERR_NO_FILE => 'Nie wybrano pliku',
                        UPLOAD_ERR_NO_TMP_DIR => 'Brak folderu tmp',
                        UPLOAD_ERR_CANT_WRITE => 'Nie można zapisać pliku',
                        UPLOAD_ERR_EXTENSION => 'Rozszerzenie zablokowane'
                    ];
                    $errorMsg = $errors[$error] ?? "Nieznany błąd ($error)";
                    $this->setFlash('error', "Błąd przesyłania '$name': $errorMsg");
                    $this->redirect('index.php?page=compose');
                }
                
                // Użyj move_uploaded_file zamiast file_get_contents
                $safeTmp = tempnam(sys_get_temp_dir(), 'mail_attach_');
                if (!move_uploaded_file($tmpName, $safeTmp)) {
                    $this->setFlash('error', "Nie można przenieść pliku '$name' do folderu tymczasowego.");
                    $this->redirect('index.php?page=compose');
                }
                
                $data = file_get_contents($safeTmp);
                unlink($safeTmp);
                
                if ($data === false || strlen($data) === 0) {
                    $this->setFlash('error', "Plik '$name' jest pusty lub uszkodzony.");
                    $this->redirect('index.php?page=compose');
                }
                
                $attachments[] = [
                    'name' => $name,
                    'data' => $data,
                    'size' => $size
                ];
            }
        }
        
        // Załączniki z przekazywanej wiadomości
        if (!empty($_POST['forward_attachments']) && !empty($_POST['forward_msgno'])) {
            $fwdParts = is_array($_POST['forward_attachments']) ? $_POST['forward_attachments'] : explode(',', $_POST['forward_attachments']);
            $fwdFolder = $_POST['forward_folder'] ?? 'INBOX';
            $this->imap->selectFolder($fwdFolder);
            foreach ($fwdParts as $part) {
                $att = $this->imap->getAttachmentByUid((int)$_POST['forward_msgno'], trim($part));
                if ($att && strlen($att['data']) > 0) {
                    $attachments[] = ['name' => $att['filename'], 'data' => $att['data']];
                }
            }
        }

        // Wysyłka przez SMTP
        $smtp = new SmtpModel();
        if (!$smtp->connect($_SESSION['email'], $_SESSION['password'])) { 
            $this->setFlash('error', 'Błąd połączenia SMTP: ' . $smtp->getLastError()); 
            $this->redirect('index.php?page=compose'); 
        }
        
        $fromName = explode('@', $_SESSION['email'])[0];
        $rawMessage = $smtp->send($to, $subject, $body, $fromName, $attachments, $cc, '', $inReplyTo);
        
        if ($rawMessage === false) {
            $this->setFlash('error', 'Nie udało się wysłać: ' . $smtp->getLastError());
            $smtp->disconnect();
            $this->redirect('index.php?page=compose');
        }
        
        // Zapisz w folderze Wysłane
        $sentFolder = $this->imap->findSentFolder();
        if ($sentFolder) {
            $this->imap->appendToFolder($sentFolder, $rawMessage, '\\Seen');
        }
        
        $smtp->disconnect();
        $this->setFlash('success', 'Wiadomość z ' . count($attachments) . ' załącznikami wysłana pomyślnie!');
        $this->redirect('index.php?page=inbox');
    }

    public function delete() {
        $uid = (int)($_GET['uid'] ?? 0);
        $folder = urldecode($_GET['folder'] ?? 'INBOX');
        if ($uid) {
            $this->imap->selectFolder($folder);
            if ($this->imap->isTrashFolder($folder)) {
                $this->imap->deleteEmailByUid($uid);
                $this->setFlash('success', 'Wiadomość trwale usunięta.');
            } else {
                $result = $this->imap->moveToTrash($uid);
                if ($result) {
                    $this->setFlash('success', 'Wiadomość przeniesiona do Kosza.');
                } else {
                    $this->setFlash('error', 'Nie udało się przenieść do Kosza (usunięto trwale).');
                }
            }
        }
        $this->redirect('index.php?page=folder&folder=' . urlencode($folder));
    }

    public function download() {
        $uid = (int)($_GET['uid'] ?? 0);
        $partNumber = $_GET['part'] ?? '';
        $folder = urldecode($_GET['folder'] ?? 'INBOX');
        if (!$uid || !$partNumber) { $this->redirect('index.php?page=inbox'); }
        $this->imap->selectFolder($folder);
        $att = $this->imap->getAttachmentByUid($uid, $partNumber);
        if (!$att || strlen($att['data']) === 0) { $this->redirect('index.php?page=inbox'); }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $att['filename'] . '"');
        header('Content-Length: ' . strlen($att['data']));
        echo $att['data'];
        exit;
    }

    public function markRead() {
        $uid = (int)($_GET['uid'] ?? 0);
        $folder = urldecode($_GET['folder'] ?? 'INBOX');
        if ($uid) { $this->imap->selectFolder($folder); $this->imap->markAsReadByUid($uid); }
        header('Content-Type: application/json'); echo json_encode(['success' => true]); exit;
    }

    public function markUnread() {
        $uid = (int)($_GET['uid'] ?? 0);
        $folder = urldecode($_GET['folder'] ?? 'INBOX');
        if ($uid) { $this->imap->selectFolder($folder); $this->imap->markAsUnreadByUid($uid); }
        header('Content-Type: application/json'); echo json_encode(['success' => true]); exit;
    }
    
    public function debug() {
        if (!isset($_SESSION['logged_in'])) { echo "Brak dostępu"; exit; }
        $logFile = __DIR__ . '/../../tmp/smtp_debug.log';
        header('Content-Type: text/plain; charset=utf-8');
        if (file_exists($logFile)) echo file_get_contents($logFile);
        else echo "Brak logów.";
        exit;
    }
}
