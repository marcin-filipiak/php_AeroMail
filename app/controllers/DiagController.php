<?php
class DiagController extends Controller {
    private $diagImap = null;
    
    public function __construct() {
        // Sam tworzymy połączenie IMAP
        $this->diagImap = new ImapModel();
        if (!$this->diagImap->connect($_SESSION['email'] ?? '', $_SESSION['password'] ?? '')) {
            echo "Błąd połączenia IMAP";
            exit;
        }
    }
    
    public function folders() {
        if (!isset($_SESSION['logged_in'])) { echo "Brak dostępu"; exit; }
        
        header('Content-Type: text/plain; charset=utf-8');
        
        echo "=== DIAGNOSTYKA FOLDERÓW ===\n\n";
        
        $folders = $this->diagImap->getFolders();
        echo "Znalezione foldery (" . count($folders) . "):\n";
        foreach ($folders as $f) {
            $isTrash = $this->diagImap->isTrashFolder($f['name']) ? ' [KOSZ]' : '';
            $isSent = $this->diagImap->isSentFolder($f['name']) ? ' [WYSLANE]' : '';
            echo "  - '{$f['name']}' (display: {$f['displayName']})$isTrash$isSent\n";
        }
        
        echo "\n=== TEST KOSZA ===\n";
        $trash = $this->diagImap->findTrashFolder();
        echo "Znaleziony folder Kosz: " . ($trash ?? 'BRAK!') . "\n";
        
        echo "\n=== TEST SENT ===\n";
        $sent = $this->diagImap->findSentFolder();
        echo "Znaleziony folder Wysłane: " . ($sent ?? 'BRAK!') . "\n";
        
        echo "\n=== TEST INBOX ===\n";
        $this->diagImap->selectFolder('INBOX');
        $count = $this->diagImap->getEmailCount('INBOX');
        echo "Liczba maili w INBOX: $count\n";
        
        $emails = $this->diagImap->getEmails('INBOX', 1);
        echo "Pobrane maile: " . count($emails['emails']) . "\n";
        foreach ($emails['emails'] as $e) {
            echo "  - UID: {$e['uid']}, msgno: {$e['msgno']}, Temat: {$e['subject']}\n";
        }
        
        if (count($emails['emails']) > 0) {
            echo "\n=== TEST KASOWANIA (na ostatnim mailu) ===\n";
            $testEmail = end($emails['emails']);
            $testUid = $testEmail['uid'];
            echo "Test na UID: $testUid (temat: {$testEmail['subject']})\n";
            
            $trashFolder = $this->diagImap->findTrashFolder();
            echo "Trash folder: " . ($trashFolder ?? 'BRAK') . "\n";
            
            if ($trashFolder) {
                $serverString = '{' . MAIL_SERVER . ':' . MAIL_IMAP_PORT . '/imap/' . MAIL_ENCRYPTION_IMAP . '/novalidate-cert}';
                $fullTrashPath = $serverString . $trashFolder;
                echo "Pełna ścieżka do kosza: $fullTrashPath\n";
                
                // Test 1: imap_mail_copy
                echo "\n--- Test imap_mail_copy ---\n";
                $copyResult = @imap_mail_copy($this->diagImap->connection, (string)$testUid, $fullTrashPath, CP_UID);
                echo "imap_mail_copy($testUid -> $fullTrashPath): " . ($copyResult ? 'OK' : 'FAIL') . "\n";
                if (!$copyResult) {
                    echo "Error: " . imap_last_error() . "\n";
                    
                    // Spróbuj bez CP_UID
                    echo "\n--- Test imap_mail_copy bez CP_UID ---\n";
                    $copyResult2 = @imap_mail_copy($this->diagImap->connection, (string)$testUid, $trashFolder, CP_UID);
                    echo "imap_mail_copy (tylko nazwa): " . ($copyResult2 ? 'OK' : 'FAIL') . "\n";
                    if (!$copyResult2) echo "Error: " . imap_last_error() . "\n";
                }
                
                // Test 2: imap_append
                echo "\n--- Test imap_append ---\n";
                $rawMsg = @imap_fetchbody($this->diagImap->connection, $testUid, '', FT_UID | FT_PEEK);
                if ($rawMsg) {
                    echo "Pobrano surową wiadomość: " . strlen($rawMsg) . " bajtów\n";
                    $appendResult = @imap_append($this->diagImap->connection, $fullTrashPath, $rawMsg);
                    echo "imap_append: " . ($appendResult ? 'OK' : 'FAIL') . "\n";
                    if (!$appendResult) echo "Error: " . imap_last_error() . "\n";
                } else {
                    echo "Nie udało się pobrać wiadomości: " . imap_last_error() . "\n";
                }
            }
        }
        
        echo "\n=== KONIEC DIAGNOSTYKI ===\n";
        exit;
    }
}
