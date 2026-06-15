<?php
class App {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // Pobierz stronę z parametru GET
        $page = $_GET['page'] ?? '';
        
        // Routing
        switch ($page) {
            case '':
            case 'index':
                if (isset($_SESSION['logged_in'])) {
                    $this->redirect('index.php?page=inbox');
                } else {
                    $this->redirect('index.php?page=login');
                }
                break;
                
            case 'login':
                require_once __DIR__ . '/../controllers/AuthController.php';
                $controller = new AuthController();
                $controller->login();
                break;
                
            case 'logout':
                require_once __DIR__ . '/../controllers/AuthController.php';
                $controller = new AuthController();
                $controller->logout();
                break;
                
            case 'inbox':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->inbox();
                break;
                
            case 'folder':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->folder();
                break;
                
            case 'read':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->read();
                break;
                
            case 'compose':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->compose();
                break;
                
            case 'reply':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->reply();
                break;
                
            case 'forward':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->forward();
                break;
                
            case 'delete':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->delete();
                break;
                
            case 'download':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->download();
                break;
                
            case 'markRead':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->markRead();
                break;
                
            case 'markUnread':
            
            case 'diag':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/DiagController.php';
                $controller = new DiagController();
                $controller->folders();
                break;
            case 'debug':
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->debug();
                break;
                $this->requireLogin();
                require_once __DIR__ . '/../controllers/MailController.php';
                $controller = new MailController();
                $controller->markUnread();
                break;
                
            default:
                http_response_code(404);
                echo "<h1>404 - Strona nie znaleziona</h1>";
                echo "<p><a href='" . APP_URL . "/index.php'>Powrót do strony głównej</a></p>";
                break;
        }
    }
    
    private function requireLogin() {
        if (!isset($_SESSION['logged_in'])) {
            $this->redirect('index.php?page=login');
        }
    }
    
    public function redirect($url) {
        if (strpos($url, 'http') !== 0) {
            $url = APP_URL . '/' . ltrim($url, '/');
        }
        header('Location: ' . $url);
        exit;
    }
}
