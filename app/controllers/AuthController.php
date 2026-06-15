<?php
class AuthController extends Controller {
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $this->setFlash('error', 'Podaj adres email i hasło.');
                $this->redirect('index.php?page=login');
            }

            $imap = new ImapModel();
            if ($imap->connect($email, $password)) {
                $imap->disconnect();
                $_SESSION['logged_in'] = true;
                $_SESSION['email'] = $email;
                $_SESSION['password'] = $password;
                $_SESSION['login_time'] = time();
                $this->setFlash('success', 'Zalogowano pomyślnie!');
                $this->redirect('index.php?page=inbox');
            } else {
                $this->setFlash('error', 'Nieprawidłowy login lub hasło.');
                $this->redirect('index.php?page=login');
            }
        }
        $this->view('auth/login', ['title' => 'Logowanie', 'content_view' => 'auth/login']);
    }

    public function logout() {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }
}
