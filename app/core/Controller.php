<?php
class Controller {
    protected $imap = null;

    public function __construct() {
        if ($this instanceof MailController && isset($_SESSION['logged_in'])) {
            $this->imap = new ImapModel();
            if (!$this->imap->connect($_SESSION['email'], $_SESSION['password'])) {
                $this->setFlash('error', 'Nie można połączyć z serwerem pocztowym.');
                $this->redirect('index.php?page=logout');
            }
        }
    }

    protected function view($view, $data = []) {
        $data['flash'] = $this->getFlash();
        $data['current_user'] = $_SESSION['email'] ?? null;
        $data['app_name'] = APP_NAME;
        $data['app_url'] = APP_URL;
        
        // WAŻNE: extract zamienia tablicę $data na zmienne PHP
        // Dzięki temu w widokach możemy używać $flash, $current_user itd.
        extract($data);
        
        require_once __DIR__ . '/../views/layout.php';
    }

    protected function redirect($url) {
        if (strpos($url, 'http') !== 0) {
            $url = APP_URL . '/' . ltrim($url, '/');
        }
        header('Location: ' . $url);
        exit;
    }

    protected function setFlash($type, $message) {
        $_SESSION['flash'][$type] = $message;
    }

    protected function getFlash() {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    protected function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
