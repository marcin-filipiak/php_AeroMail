<?php
class ImapModel extends Model {
    private $connection = null;
    private $currentFolder = 'INBOX';
    private $serverString = '';
    private $debugLog = [];

    public function connect($username, $password) {
        if (!function_exists('imap_open')) throw new Exception('Brak rozszerzenia IMAP.');
        $this->serverString = '{' . MAIL_SERVER . ':' . MAIL_IMAP_PORT . '/imap/' . MAIL_ENCRYPTION_IMAP . '/novalidate-cert}';
        $this->connection = @imap_open($this->serverString . 'INBOX', $username, $password, 0, 1);
        return $this->connection !== false;
    }

    public function disconnect() { if ($this->connection) @imap_close($this->connection, CL_EXPUNGE); }
    public function __destruct() { $this->disconnect(); }

    private function log($msg) {
        $this->debugLog[] = $msg;
        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            @file_put_contents(__DIR__ . '/../../tmp/imap_debug.log', date('H:i:s') . " $msg\n", FILE_APPEND);
        }
    }

    public function findTrashFolder() {
        $folders = $this->getFolders();
        foreach ($folders as $folder) {
            $name = $folder['name'];
            if (strcasecmp($name, 'Trash') === 0) return $name;
            if (strcasecmp($name, 'Kosz') === 0) return $name;
            if (strcasecmp($name, 'Deleted') === 0) return $name;
            if (stripos($name, 'Trash') !== false || stripos($name, 'Kosz') !== false) return $name;
        }
        return null;
    }

    public function isTrashFolder($folderName) {
        $trashNames = ['Trash', 'Kosz', 'Deleted', 'Deleted Items'];
        foreach ($trashNames as $tn) {
            if (strcasecmp($folderName, $tn) === 0) return true;
        }
        return (stripos($folderName, 'trash') !== false || stripos($folderName, 'kosz') !== false);
    }

    public function findSentFolder() {
        $folders = $this->getFolders();
        foreach ($folders as $folder) {
            $name = $folder['name'];
            if (strcasecmp($name, 'Sent') === 0) return $name;
            if (strcasecmp($name, 'Wysłane') === 0) return $name;
            if (stripos($name, 'Sent') !== false) return $name;
        }
        return null;
    }

    public function isSentFolder($folderName) {
        $sentNames = ['Sent', 'Wysłane', 'Wyslane', 'Sent Messages', 'Sent Items'];
        foreach ($sentNames as $sn) {
            if (strcasecmp($folderName, $sn) === 0) return true;
        }
        return (stripos($folderName, 'sent') !== false);
    }

    public function moveToTrash($uid) {
        $trashFolder = $this->findTrashFolder();
        $this->log("moveToTrash($uid) - szukam kosza...");
        
        if (!$trashFolder) {
            $this->log("Brak folderu Trash - usuwam trwale");
            return $this->deleteEmailByUid($uid);
        }
        
        $this->log("Znaleziony kosz: $trashFolder");
        
        $msgno = @imap_msgno($this->connection, $uid);
        if (!$msgno) {
            $this->log("Nie znaleziono msgno dla UID $uid");
            return $this->deleteEmailByUid($uid);
        }
        
        $rawMessage = @imap_fetchbody($this->connection, $msgno, '');
        if (!$rawMessage) {
            $this->log("Nie udało się pobrać wiadomości $msgno");
            return $this->deleteEmailByUid($uid);
        }
        
        $overview = @imap_fetch_overview($this->connection, $uid, FT_UID);
        $flags = '';
        if ($overview && isset($overview[0])) {
            if (isset($overview[0]->seen)) $flags .= '\\Seen ';
            if (isset($overview[0]->flagged)) $flags .= '\\Flagged ';
            if (isset($overview[0]->answered)) $flags .= '\\Answered ';
        }
        $flags = trim($flags);
        
        $this->log("Kopiuję do $trashFolder z flagami: $flags");
        
        $fullTrashPath = $this->serverString . $trashFolder;
        $appended = @imap_append($this->connection, $fullTrashPath, $rawMessage, $flags);
        
        if ($appended) {
            $this->log("Skopiowano do kosza, usuwam oryginał");
            @imap_delete($this->connection, $uid, ST_UID);
            @imap_expunge($this->connection);
            $this->log("Usunięto oryginał");
            return true;
        }
        
        $this->log("imap_append nie powiodło się: " . imap_last_error());
        return $this->deleteEmailByUid($uid);
    }

    public function appendToFolder($folder, $rawMessage, $flags = '\\Seen') {
        $fullFolder = $this->serverString . $folder;
        $result = @imap_append($this->connection, $fullFolder, $rawMessage, $flags);
        return $result !== false;
    }

    public function getFolders() {
        $folders = [];
        $list = @imap_getmailboxes($this->connection, $this->serverString, '*');
        if (is_array($list)) {
            foreach ($list as $mailbox) {
                $name = str_replace($this->serverString, '', $mailbox->name);
                $names = ['INBOX' => 'Odebrane', 'Sent' => 'Wysłane', 'Wysłane' => 'Wysłane', 'Drafts' => 'Szkice', 'Trash' => 'Kosz', 'Kosz' => 'Kosz', 'Junk' => 'Spam', 'Spam' => 'Spam'];
                $folders[] = ['name' => $name, 'displayName' => $names[$name] ?? $name, 'delimiter' => $mailbox->delimiter, 'attributes' => $mailbox->attributes];
            }
        }
        return $folders;
    }

    public function selectFolder($folder = 'INBOX') {
        $this->currentFolder = $folder;
        return @imap_reopen($this->connection, $this->serverString . $folder);
    }

    public function getEmailCount($folder = 'INBOX') {
        $this->selectFolder($folder);
        $info = @imap_check($this->connection);
        return $info ? $info->Nmsgs : 0;
    }

    public function getEmails($folder = 'INBOX', $page = 1, $perPage = EMAILS_PER_PAGE) {
        $this->selectFolder($folder);
        $total = $this->getEmailCount($folder);
        $emails = [];
        
        if ($total === 0) return ['emails' => [], 'total' => 0, 'pages' => 0, 'current_page' => $page];

        $sequence = "1:$total";
        $overview = @imap_fetch_overview($this->connection, $sequence, 0);
        
        if (!$overview) {
            return ['emails' => [], 'total' => 0, 'pages' => 0, 'current_page' => $page];
        }

        $overview = array_reverse($overview);
        $offset = ($page - 1) * $perPage;
        $overview = array_slice($overview, $offset, $perPage);

        foreach ($overview as $msg) {
            $emails[] = [
                'uid' => $msg->uid ?? $msg->msgno,
                'msgno' => $msg->msgno,
                'subject' => $this->decodeHeader($msg->subject ?? '(Brak tematu)'),
                'from' => $this->decodeHeader($msg->from ?? 'Nieznany'),
                'from_address' => $this->extractEmail($msg->from ?? ''),
                'to' => $this->decodeHeader($msg->to ?? ''),
                'to_address' => $this->extractEmail($msg->to ?? ''),
                'date' => $msg->date ?? '',
                'timestamp' => $msg->udate ?? strtotime($msg->date ?? 'now'),
                'size' => $msg->size ?? 0,
                'unread' => !isset($msg->seen) || !$msg->seen,
                'answered' => isset($msg->answered) && $msg->answered,
                'flagged' => isset($msg->flagged) && $msg->flagged,
                'has_attachment' => $this->checkAttachmentByUid($msg->uid ?? $msg->msgno)
            ];
        }
        
        return ['emails' => $emails, 'total' => $total, 'pages' => ceil($total / $perPage), 'current_page' => $page];
    }

    private function checkAttachmentByUid($uid) {
        $msgno = @imap_msgno($this->connection, $uid);
        if (!$msgno) return false;
        
        $structure = @imap_fetchstructure($this->connection, $msgno);
        if (!$structure || empty($structure->parts)) return false;
        return $this->hasAttachmentInParts($structure->parts);
    }
    
    private function hasAttachmentInParts($parts) {
        foreach ($parts as $part) {
            if ($part->ifdisposition && strtolower($part->disposition) === 'attachment') return true;
            if ($part->ifdparameters) {
                foreach ($part->dparameters as $p) {
                    if (strtolower($p->attribute) === 'filename') return true;
                }
            }
            if (!empty($part->parts) && $this->hasAttachmentInParts($part->parts)) return true;
        }
        return false;
    }

    public function getEmailByUid($uid) {
        $msgno = @imap_msgno($this->connection, $uid);
        if (!$msgno) return null;
        
        $header = @imap_headerinfo($this->connection, $msgno);
        $structure = @imap_fetchstructure($this->connection, $msgno);
        if (!$header || !$structure) return null;
        
        $body = $this->getBodyByMsgno($msgno, $structure);
        $fromAddress = '';
        if (!empty($header->from) && is_array($header->from) && isset($header->from[0])) {
            $fromAddress = ($header->from[0]->mailbox ?? '') . '@' . ($header->from[0]->host ?? '');
        } else {
            $fromAddress = $this->extractEmail($header->fromaddress ?? '');
        }
        
        return [
            'uid' => $uid,
            'msgno' => $msgno,
            'subject' => $this->decodeHeader($header->subject ?? '(Brak tematu)'),
            'from' => $this->decodeHeader($header->fromaddress ?? 'Nieznany'),
            'from_address' => $fromAddress,
            'to' => $this->decodeHeader($header->toaddress ?? ''),
            'cc' => isset($header->ccaddress) ? $this->decodeHeader($header->ccaddress) : '',
            'date' => $header->date ?? '',
            'timestamp' => $header->udate ?? strtotime($header->date ?? 'now'),
            'body_html' => $body['html'] ?? '',
            'body_text' => $body['text'] ?? '',
            'attachments' => $this->getAttachmentsByMsgno($msgno, $structure),
            'unread' => !isset($header->Unseen) || $header->Unseen !== 'U'
        ];
    }

    private function getBodyByMsgno($msgno, $structure) {
        $body = ['html' => '', 'text' => ''];
        if (empty($structure->parts)) {
            $raw = @imap_body($this->connection, $msgno);
            $decoded = $this->decodeBody($raw, $structure);
            if (strtolower($structure->subtype ?? '') === 'html') $body['html'] = $decoded;
            else $body['text'] = $decoded;
            if (empty($body['html']) && !empty($body['text'])) $body['html'] = nl2br(htmlspecialchars($body['text']));
            return $body;
        }
        $this->parsePartsByMsgno($msgno, $structure->parts, '', $body);
        if (empty($body['html']) && !empty($body['text'])) $body['html'] = nl2br(htmlspecialchars($body['text']));
        return $body;
    }
    
    private function parsePartsByMsgno($msgno, $parts, $prefix, &$body) {
        foreach ($parts as $index => $part) {
            $partNumber = $prefix ? ($prefix . '.' . ($index + 1)) : (string)($index + 1);
            if ($part->type === 1 && !empty($part->parts)) {
                $this->parsePartsByMsgno($msgno, $part->parts, $partNumber, $body);
                continue;
            }
            if ($part->type === 0) {
                $data = @imap_fetchbody($this->connection, $msgno, $partNumber);
                if ($data === false) continue;
                $decoded = $this->decodeBody($data, $part);
                $subtype = strtolower($part->subtype ?? 'plain');
                $isAttachment = false;
                if ($part->ifdisposition && strtolower($part->disposition) === 'attachment') $isAttachment = true;
                if ($part->ifdparameters) {
                    foreach ($part->dparameters as $p) {
                        if (strtolower($p->attribute) === 'filename') $isAttachment = true;
                    }
                }
                if (!$isAttachment) {
                    if ($subtype === 'html') { if (empty($body['html'])) $body['html'] = $decoded; } 
                    else { if (empty($body['text'])) $body['text'] = $decoded; }
                }
            }
        }
    }

    // POPRAWIONE POBIERANIE ZAŁĄCZNIKÓW
    public function getAttachmentsByMsgno($msgno, $structure = null) {
        if (!$structure) $structure = @imap_fetchstructure($this->connection, $msgno);
        $attachments = [];
        if (empty($structure->parts)) return $attachments;
        $this->findAttachmentsByMsgno($msgno, $structure->parts, '', $attachments);
        return $attachments;
    }
    
    private function findAttachmentsByMsgno($msgno, $parts, $prefix, &$attachments) {
        foreach ($parts as $index => $part) {
            $partNumber = $prefix ? ($prefix . '.' . ($index + 1)) : (string)($index + 1);
            if ($part->type === 1 && !empty($part->parts)) {
                $this->findAttachmentsByMsgno($msgno, $part->parts, $partNumber, $attachments);
                continue;
            }
            $isAttachment = false;
            $filename = 'attachment_' . $partNumber;
            
            if ($part->ifdisposition && strtolower($part->disposition) === 'attachment') $isAttachment = true;
            if ($part->ifparameters) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) === 'name') {
                        $filename = $this->decodeHeader($param->value);
                        $isAttachment = true;
                    }
                }
            }
            if ($part->ifdparameters) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) === 'filename') {
                        $filename = $this->decodeHeader($param->value);
                        $isAttachment = true;
                    }
                }
            }
            
            if ($isAttachment) {
                $this->log("Znaleziono załącznik: $filename (part $partNumber)");
                $data = @imap_fetchbody($this->connection, $msgno, $partNumber);
                
                if ($data === false || $data === '') {
                    $this->log("imap_fetchbody zwróciło false/empty dla part $partNumber");
                    continue;
                }
                
                $this->log("Pobrano " . strlen($data) . " bajtów surowych danych");
                
                // Dekodowanie
                if ($part->encoding == 3) { // BASE64
                    $data = base64_decode($data);
                    $this->log("Zdekodowano base64: " . strlen($data) . " bajtów");
                } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                    $data = quoted_printable_decode($data);
                    $this->log("Zdekodowano quoted-printable: " . strlen($data) . " bajtów");
                }
                
                if ($data === false || strlen($data) === 0) {
                    $this->log("Dane są puste po dekodowaniu!");
                    continue;
                }
                
                $attachments[] = [
                    'part_number' => $partNumber,
                    'filename' => $filename,
                    'size' => strlen($data),
                    'data' => $data,
                    'subtype' => strtolower($part->subtype ?? 'octet-stream')
                ];
                $this->log("Dodano załącznik: $filename (" . strlen($data) . " bajtów)");
            }
        }
    }

    // POPRAWIONE POBIERANIE JEDNEGO ZAŁĄCZNIKA
    public function getAttachmentByUid($uid, $partNumber) {
        $msgno = @imap_msgno($this->connection, $uid);
        if (!$msgno) {
            $this->log("getAttachmentByUid: nie znaleziono msgno dla UID $uid");
            return null;
        }
        
        $this->log("getAttachmentByUid: UID=$uid, msgno=$msgno, part=$partNumber");
        
        $structure = @imap_fetchstructure($this->connection, $msgno);
        if (!$structure) {
            $this->log("Nie udało się pobrać struktury dla msgno $msgno");
            return null;
        }
        
        $part = $this->findPartByNumber($structure->parts ?? [], $partNumber);
        if (!$part) {
            $this->log("Nie znaleziono części $partNumber w strukturze");
            return null;
        }
        
        $data = @imap_fetchbody($this->connection, $msgno, $partNumber);
        if ($data === false || $data === '') {
            $this->log("imap_fetchbody zwróciło false/empty dla part $partNumber");
            return null;
        }
        
        $this->log("Pobrano " . strlen($data) . " bajtów surowych dla part $partNumber");
        
        if ($part->encoding == 3) {
            $data = base64_decode($data);
            $this->log("Zdekodowano base64: " . strlen($data) . " bajtów");
        } elseif ($part->encoding == 4) {
            $data = quoted_printable_decode($data);
            $this->log("Zdekodowano quoted-printable: " . strlen($data) . " bajtów");
        }
        
        $filename = 'attachment';
        if ($part->ifdparameters) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename') {
                    $filename = $this->decodeHeader($param->value);
                }
            }
        }
        if ($part->ifparameters) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) === 'name') {
                    $filename = $this->decodeHeader($param->value);
                }
            }
        }
        
        $this->log("Zwracam załącznik: $filename (" . strlen($data) . " bajtów)");
        
        return [
            'filename' => $filename,
            'data' => $data,
            'subtype' => strtolower($part->subtype ?? 'octet-stream')
        ];
    }
    
    private function findPartByNumber($parts, $partNumber) {
        $indices = explode('.', $partNumber);
        $current = $parts;
        $part = null;
        
        foreach ($indices as $i => $idx) {
            $idx = (int)$idx - 1;
            if (!isset($current[$idx])) return null;
            $part = $current[$idx];
            if ($i < count($indices) - 1) {
                if (empty($part->parts)) return null;
                $current = $part->parts;
            }
        }
        return $part;
    }

    public function deleteEmailByUid($uid) { 
        $result = @imap_delete($this->connection, $uid, ST_UID);
        if ($result) @imap_expunge($this->connection);
        return $result;
    }
    
    public function markAsReadByUid($uid) { return @imap_setflag_full($this->connection, $uid, "\\Seen", ST_UID); }
    public function markAsUnreadByUid($uid) { return @imap_clearflag_full($this->connection, $uid, "\\Seen", ST_UID); }

    public function decodeHeader($string) {
        if (empty($string)) return '';
        $elements = @imap_mime_header_decode($string);
        if (!$elements) return $string;
        $decoded = '';
        foreach ($elements as $el) {
            $charset = strtolower($el->charset);
            if ($charset === 'default' || $charset === 'utf-8') $decoded .= $el->text;
            else $decoded .= @mb_convert_encoding($el->text, 'UTF-8', $el->charset) ?: $el->text;
        }
        return $decoded;
    }

    private function extractEmail($from) {
        if (preg_match('/<([^>]+)>/', $from, $matches)) return $matches[1];
        return trim($from);
    }

    private function decodeBody($data, $part) {
        if ($part->encoding == 3) $data = base64_decode($data);
        elseif ($part->encoding == 4) $data = quoted_printable_decode($data);
        $charset = 'UTF-8';
        if (!empty($part->charset)) $charset = strtoupper($part->charset);
        elseif (!empty($part->parameters)) {
            foreach ($part->parameters as $p) {
                if (strtolower($p->attribute) === 'charset') {
                    $charset = strtoupper($p->value);
                    break;
                }
            }
        }
        if ($charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $data = @mb_convert_encoding($data, 'UTF-8', $charset) ?: $data;
        }
        return $data;
    }
}
