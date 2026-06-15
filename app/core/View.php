<?php
class View {
    public static function render($view, $data = []) {
        extract($data);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            ob_start();
            require $viewFile;
            return ob_get_clean();
        }
        return '';
    }
}
