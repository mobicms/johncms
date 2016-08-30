<?php

define('_IN_JOHNCMS', 1);

$headmod = 'login';
require('incfiles/core.php');
require('incfiles/head.php');

if (core::$user_id) {
    echo '<div class="menu"><h2><a href="' . $set['homeurl'] . '">' . _t('Home', 'system') . '</a></h2></div>';
} else {
    echo '<div class="phdr"><b>' . _t('Login', 'system') . '</b></div>';
    $error = [];
    $captcha = false;
    $display_form = 1;
    $user_login = isset($_POST['n']) ? $_POST['n'] : null;
    $user_pass = isset($_POST['p']) ? $_POST['p'] : null;
    $user_mem = isset($_POST['mem']) ? 1 : 0;
    $user_code = isset($_POST['code']) ? trim($_POST['code']) : null;

    if ($user_pass && !$user_login) {
        $error[] = _t('You have not entered login', 'system');
    }

    if ($user_login && !$user_pass) {
        $error[] = _t('You have not entered password', 'system');
    }

    if ($user_login && (mb_strlen($user_login) < 2 || mb_strlen($user_login) > 20)) {
        $error[] = _t('Nickname', 'system') . ': ' . _t('Invalid length', 'system');
    }

    if ($user_pass && (mb_strlen($user_pass) < 1)) {
        $error[] = _t('Password', 'system') . ': ' . _t('Invalid length', 'system');
    }

    if (!$error && $user_pass && $user_login) {
        /** @var PDO $db */
        $db = App::getContainer()->get(PDO::class);

        // Запрос в базу на юзера
        $stmt = $db->prepare('SELECT * FROM `users` WHERE `name_lat` = ? LIMIT 1');
        $stmt->execute([functions::rus_lat(mb_strtolower($user_login))]);

        if ($stmt->rowCount()) {
            $user = $stmt->fetch();

            if ($user['failed_login'] > 2) {
                if ($user_code) {
                    if (mb_strlen($user_code) > 3 && $user_code == $_SESSION['code']) {
                        // Если введен правильный проверочный код
                        unset($_SESSION['code']);
                        $captcha = true;
                    } else {
                        // Если проверочный код указан неверно
                        unset($_SESSION['code']);
                        $error[] = _t('The security code is not correct', 'system');
                    }
                } else {
                    // Показываем CAPTCHA
                    $display_form = 0;
                    echo '<form action="login.php' . ($id ? '?id=' . $id : '') . '" method="post">' .
                        '<div class="menu"><p><img src="captcha.php?r=' . rand(1000, 9999) . '" alt="' . _t('Verification code', 'system') . '"/><br />' .
                        _t('Enter verification code', 'system') . ':<br><input type="text" size="5" maxlength="5"  name="code"/>' .
                        '<input type="hidden" name="n" value="' . htmlspecialchars($user_login) . '"/>' .
                        '<input type="hidden" name="p" value="' . $user_pass . '"/>' .
                        '<input type="hidden" name="mem" value="' . $user_mem . '"/>' .
                        '<input type="submit" name="submit" value="' . _t('Continue', 'system') . '"/></p></div></form>';
                }
            }

            if ($user['failed_login'] < 3 || $captcha) {
                if (md5(md5($user_pass)) == $user['password']) {
                    // Если логин удачный
                    $display_form = 0;
                    $db->exec("UPDATE `users` SET `failed_login` = '0' WHERE `id` = " . $user['id']);

                    if (!$user['preg']) {
                        // Если регистрация не подтверждена
                        echo '<div class="rmenu"><p>' . _t('Sorry, but your request for registration is not considered yet. Please, be patient.', 'system') . '</p></div>';
                    } else {
                        // Если все проверки прошли удачно, подготавливаем вход на сайт
                        if (isset($_POST['mem'])) {
                            // Установка данных COOKIE
                            $cuid = base64_encode($user['id']);
                            $cups = md5($user_pass);
                            setcookie("cuid", $cuid, time() + 3600 * 24 * 365);
                            setcookie("cups", $cups, time() + 3600 * 24 * 365);
                        }

                        // Установка данных сессии
                        $_SESSION['uid'] = $user['id'];
                        $_SESSION['ups'] = md5(md5($user_pass));

                        $db->exec("UPDATE `users` SET `sestime` = '" . time() . "' WHERE `id` = " . $user['id']);
                        $set_user = unserialize($user['set_user']);

                        if ($user['lastdate'] < (time() - 3600) && $set_user['digest']) {
                            header('Location: ' . $set['homeurl'] . '/index.php?act=digest&last=' . $user['lastdate']);
                        } else {
                            header('Location: ' . $set['homeurl'] . '/index.php');
                        }

                        echo '<div class="gmenu"><p><b><a href="index.php?act=digest">' . _t('Enter site', 'system') . '</a></b></p></div>';
                    }
                } else {
                    // Если логин неудачный
                    if ($user['failed_login'] < 3) {
                        // Прибавляем к счетчику неудачных логинов
                        $db->exec("UPDATE `users` SET `failed_login` = '" . ($user['failed_login'] + 1) . "' WHERE `id` = " . $user['id']);
                    }

                    $error[] = _t('Authorization failed', 'system');
                }
            }
        } else {
            $error[] = _t('Authorization failed', 'system');
        }
    }

    if ($display_form) {
        if ($error) {
            echo functions::display_error($error);
        }

        $info = '';
        if (core::$system_set['site_access'] == 0 || core::$system_set['site_access'] == 1) {
            if (core::$system_set['site_access'] == 0) {
                $info = '<div class="rmenu">' . _t('At the moment, access to the site is allowed only for SV!', 'system') . '</div>';
            } elseif (core::$system_set['site_access'] == 1) {
                $info = '<div class="rmenu">' . _t('At the moment, access to the site is allowed only for Administration of site.', 'system') . '</div>';
            }
        }

        echo $info;
        echo '<div class="gmenu"><form action="login.php" method="post"><p>' . _t('Username', 'system') . ':<br>' .
            '<input type="text" name="n" value="' . htmlentities($user_login, ENT_QUOTES, 'UTF-8') . '" maxlength="20"/>' .
            '<br>' . _t('Password', 'system') . ':<br>' .
            '<input type="password" name="p" maxlength="20"/></p>' .
            '<p><input type="checkbox" name="mem" value="1" checked="checked"/>' . _t('Remember', 'system') . '</p>' .
            '<p><input type="submit" value="' . _t('Login', 'system') . '"/></p>' .
            '</form></div>' .
            '<div class="menu"><p>' . functions::image('user.png') . '<a href="registration/">' . _t('Registration', 'system') . '</a></p></div>' .
            '<div class="bmenu"><p>' . functions::image('lock.png') . '<a href="profile/skl.php?continue">' . _t('Forgot password?', 'system') . '</a></p></div>';
    }
}

require('incfiles/end.php');
