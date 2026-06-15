<div class="login-container">
    <div class="login-box">
        <h1>📧 <?= APP_NAME ?></h1>
        <p class="subtitle">Zaloguj się do swojej skrzynki pocztowej</p>
        <form method="POST" action="<?= APP_URL ?>/index.php?page=login" class="login-form">
            <div class="form-group">
                <label for="email">Adres email</label>
                <input type="email" id="email" name="email" required placeholder="twoja.nazwa@norbert104.mikr.dev" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Hasło</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Zaloguj się</button>
        </form>
        <div class="server-info"><small>Serwer: <?= MAIL_SERVER ?></small></div>
    </div>
</div>
