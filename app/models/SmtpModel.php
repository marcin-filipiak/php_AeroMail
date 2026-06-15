<?php
class SmtpModel extends Model {
    private $connection;
    private $username;
    private $password;
    private $debugLog = [];
    private $lastError = '';
    private $debugEnabled = false;

    public function __construct() {
        // Czytaj ustawienie debugowania z configu
        $this->debugEnabled = defined('SMTP_DEBUG') ? SMTP_DEBUG : false;
    }

    public function connect($username, $password) {
        $this->username = $username;
        $this->password = $password;
        $this->debugLog = [];
        
        $this->log("🔌 Łączenie z " . MAIL_SERVER . ":" . MAIL_SMTP_PORT);
        $this->connection = @fsockopen(MAIL_SERVER, MAIL_SMTP_PORT, $errno, $errstr, 30);
        
        if (!$this->connection) {
            $this->lastError = "Nie można połączyć: $errstr ($errno)";
            return false;
        }

        $response = $this->getResponse();
        if (!$this->isSuccess($response)) {
            $this->lastError = "Brak powitania: $response";
            return false;
        }

        $this->sendCommand('EHLO ' . gethostname());
        $this->sendCommand('STARTTLS');
        
        $crypto = stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            $this->lastError = "TLS failed";
            return false;
        }

        $this->sendCommand('EHLO ' . gethostname());
        $this->sendCommand('AUTH LOGIN');
        $this->sendCommand(base64_encode($this->username));
        $response = $this->sendCommand(base64_encode($this->password));
        
        if (!$this->isSuccess($response)) {
            $this->lastError = "Auth failed: $response";
            return false;
        }
        return true;
    }

    private function log($msg) {
        if ($this->debugEnabled) {
            $this->debugLog[] = "[" . date('H:i:s') . "] $msg";
        }
    }

    public function getDebugLog() { 
        return $this->debugEnabled ? implode("\n", $this->debugLog) : '(debugowanie wyłączone)'; 
    }
    
    public function getLastError() { return $this->lastError; }

    private function sendCommand($command) {
        fwrite($this->connection, $command . "\r\n");
        return $this->getResponse();
    }

    private function getResponse() {
        $response = '';
        stream_set_timeout($this->connection, 10);
        while ($line = fgets($this->connection, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }

    private function isSuccess($response) { 
        return preg_match('/^[23]\d\d/', $response); 
    }

    public function buildRawMessage($to, $subject, $body, $fromName = '', $attachments = [], $cc = '', $bcc = '', $inReplyTo = '') {
        $from = $this->username;
        $boundary_mixed = 'mixed_' . md5(uniqid(time()));
        $boundary_alt = 'alt_' . md5(uniqid(time() . 'alt'));
        
        $textBody = strip_tags($body);
        $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');
        
        $headers = [];
        $headers[] = 'Return-Path: <' . $from . '>';
        $headers[] = 'Delivered-To: ' . $to;
        
        if ($fromName) {
            $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $headers[] = 'From: ' . $encodedName . ' <' . $from . '>';
        } else {
            $headers[] = 'From: <' . $from . '>';
        }
        
        $headers[] = 'To: ' . $to;
        if ($cc) $headers[] = 'Cc: ' . $cc;
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . uniqid() . '@' . MAIL_SERVER . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: KlientPocztyMVC/1.0';
        if ($inReplyTo) $headers[] = 'In-Reply-To: ' . $inReplyTo;
        if ($inReplyTo) $headers[] = 'References: ' . $inReplyTo;

        $hasAttachments = !empty($attachments);
        
        if ($hasAttachments) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary_mixed . '"';
            
            $messageBody = "This is a multi-part message in MIME format.\r\n\r\n";
            $messageBody .= '--' . $boundary_mixed . "\r\n";
            $messageBody .= 'Content-Type: multipart/alternative; boundary="' . $boundary_alt . '"' . "\r\n\r\n";
            
            $messageBody .= '--' . $boundary_alt . "\r\n";
            $messageBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $messageBody .= chunk_split(base64_encode($textBody)) . "\r\n";
            
            $messageBody .= '--' . $boundary_alt . "\r\n";
            $messageBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $messageBody .= chunk_split(base64_encode($body)) . "\r\n";
            
            $messageBody .= '--' . $boundary_alt . '--' . "\r\n";
            
            foreach ($attachments as $attachment) {
                $messageBody .= '--' . $boundary_mixed . "\r\n";
                $messageBody .= 'Content-Type: application/octet-stream; name="=?UTF-8?B?' . base64_encode($attachment['name']) . '?="' . "\r\n";
                $messageBody .= "Content-Transfer-Encoding: base64\r\n";
                $messageBody .= 'Content-Disposition: attachment; filename="=?UTF-8?B?' . base64_encode($attachment['name']) . '?="' . "\r\n\r\n";
                $messageBody .= chunk_split(base64_encode($attachment['data'])) . "\r\n";
            }
            $messageBody .= '--' . $boundary_mixed . '--';
        } else {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary_alt . '"';
            
            $messageBody = "This is a multi-part message in MIME format.\r\n\r\n";
            
            $messageBody .= '--' . $boundary_alt . "\r\n";
            $messageBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $messageBody .= chunk_split(base64_encode($textBody)) . "\r\n";
            
            $messageBody .= '--' . $boundary_alt . "\r\n";
            $messageBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $messageBody .= chunk_split(base64_encode($body)) . "\r\n";
            
            $messageBody .= '--' . $boundary_alt . '--';
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $messageBody;
    }

    public function send($to, $subject, $body, $fromName = '', $attachments = [], $cc = '', $bcc = '', $inReplyTo = '') {
        if (!$this->connection) {
            $this->lastError = "Brak połączenia SMTP";
            return false;
        }
        
        $from = $this->username;
        $rawMessage = $this->buildRawMessage($to, $subject, $body, $fromName, $attachments, $cc, $bcc, $inReplyTo);

        $response = $this->sendCommand('MAIL FROM:<' . $from . '>');
        $this->log("MAIL FROM: " . trim($response));
        if (!$this->isSuccess($response)) {
            $this->lastError = "MAIL FROM odrzucony: $response";
            return false;
        }

        $recipients = array_merge([$to], $cc ? explode(',', $cc) : [], $bcc ? explode(',', $bcc) : []);
        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) continue;
            $response = $this->sendCommand('RCPT TO:<' . $recipient . '>');
            $this->log("RCPT TO $recipient: " . trim($response));
            if (!$this->isSuccess($response)) {
                $this->lastError = "RCPT TO odrzucony: $response";
                return false;
            }
        }

        $response = $this->sendCommand('DATA');
        if (!$this->isSuccess($response)) {
            $this->lastError = "DATA odrzucony: $response";
            return false;
        }

        fwrite($this->connection, $rawMessage . "\r\n.\r\n");
        $response = $this->getResponse();
        $this->log("Treść: " . trim($response));
        
        if (!$this->isSuccess($response)) {
            $this->lastError = "Odrzucono: $response";
            return false;
        }
        
        return $rawMessage;
    }

    public function disconnect() {
        if ($this->connection) {
            $this->sendCommand('QUIT');
            fclose($this->connection);
            $this->connection = null;
        }
    }
    
    public function __destruct() { $this->disconnect(); }
}
