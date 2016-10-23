<?php

/**
 * @package     JohnCMS
 * @link        http://johncms.com
 * @copyright   Copyright (C) 2008-2011 JohnCMS Community
 * @license     LICENSE.txt (see attached file)
 * @version     VERSION.txt (see attached file)
 * @author      http://johncms.com/about
 */

defined('_IN_JOHNCMS') or die('Restricted access');

class functions
{
    /**
     * Антифлуд
     *
     * Режимы работы:
     *   1 - Адаптивный
     *   2 - День / Ночь
     *   3 - День
     *   4 - Ночь
     *
     * @return int|bool
     */
    public static function antiflood(array $userData)
    {
        $config = unserialize(App::getContainer()->get('config')['johncms']['antiflood']);

        switch ($config['mode']) {
            case 1: // Адаптивный режим
                /** @var PDO $db */
                $db = App::getContainer()->get(PDO::class);

                $adm = $db->query('SELECT COUNT(*) FROM `users` WHERE `rights` > 0 AND `lastdate` > ' . (time() - 300))->fetchColumn();
                $limit = $adm > 0 ? $config['day'] : $config['night'];
                break;

            case 3: // День
                $limit = $config['day'];
                break;

            case 4: // Ночь
                $limit = $config['night'];
                break;

            default: // По умолчанию день / ночь
                $c_time = date('G', time());
                $limit = $c_time > $config['day'] && $c_time < $config['night'] ? $config['day'] : $config['night'];
        }

        // Для Администрации задаем лимит в 4 секунды
        if ($userData['rights'] > 0) {
            $limit = 4;
        }

        $flood = $userData['lastpost'] + $limit - time();

        return $flood > 0 ? $flood : false;
    }

    /**
     * Маскировка ссылок в тексте
     *
     * @param string $var
     * @return string
     */
    public static function antilink($var)
    {
        $var = preg_replace('~\\[url=(https?://.+?)\\](.+?)\\[/url\\]|(https?://(www.)?[0-9a-z\.-]+\.[0-9a-z]{2,6}[0-9a-zA-Z/\?\.\~&amp;_=/%-:#]*)~', '###', $var);
        $replace = [
            '.ru'   => '***',
            '.com'  => '***',
            '.biz'  => '***',
            '.cn'   => '***',
            '.in'   => '***',
            '.net'  => '***',
            '.org'  => '***',
            '.info' => '***',
            '.mobi' => '***',
            '.wen'  => '***',
            '.kmx'  => '***',
            '.h2m'  => '***',
        ];

        return strtr($var, $replace);
    }

    /**
     * Фильтрация строк
     *
     * @param string $str
     * @return string
     */
    public static function checkin($str)
    {
        if (function_exists('iconv')) {
            $str = iconv("UTF-8", "UTF-8", $str);
        }

        // Фильтруем невидимые символы
        $str = preg_replace('/[^\P{C}\n]+/u', '', $str);

        return trim($str);
    }

    /**
     * Обработка текстов перед выводом на экран
     *
     * @param string $str
     * @param int    $br   Параметр обработки переносов строк
     *                     0 - не обрабатывать (по умолчанию)
     *                     1 - обрабатывать
     *                     2 - вместо переносов строки вставляются пробелы
     * @param int    $tags Параметр обработки тэгов
     *                     0 - не обрабатывать (по умолчанию)
     *                     1 - обрабатывать
     *                     2 - вырезать тэги
     *
     * @return string
     */
    public static function checkout($str, $br = 0, $tags = 0)
    {
        $str = htmlentities(trim($str), ENT_QUOTES, 'UTF-8');
        if ($br == 1) {
            // Вставляем переносы строк
            $str = nl2br($str);
        } elseif ($br == 2) {
            $str = str_replace("\r\n", ' ', $str);
        }
        if ($tags == 1) {
            $str = bbcode::tags($str);
        } elseif ($tags == 2) {
            $str = bbcode::notags($str);
        }

        return trim($str);
    }

    /**
     * Показ различных счетчиков внизу страницы
     */
    public static function display_counters()
    {
        global $headmod;

        /** @var PDO $db */
        $db = App::getContainer()->get(PDO::class);
        $req = $db->query('SELECT * FROM `cms_counters` WHERE `switch` = 1 ORDER BY `sort` ASC');

        if ($req->rowCount()) {
            while ($res = $req->fetch()) {
                $link1 = ($res['mode'] == 1 || $res['mode'] == 2) ? $res['link1'] : $res['link2'];
                $link2 = $res['mode'] == 2 ? $res['link1'] : $res['link2'];
                $count = ($headmod == 'mainpage') ? $link1 : $link2;

                if (!empty($count)) {
                    echo $count;
                }
            }
        }
    }

    /**
     * Показываем дату с учетом сдвига времени
     *
     * @param int $var Время в Unix формате
     * @return string Отформатированное время
     */
    public static function display_date($var)
    {
        $shift = (App::getContainer()->get('config')['johncms']['timeshift'] + self::$user_set['timeshift']) * 3600;

        if (date('Y', $var) == date('Y', time())) {
            if (date('z', $var + $shift) == date('z', time() + $shift)) {
                return self::$lng['today'] . ', ' . date("H:i", $var + $shift);
            }
            if (date('z', $var + $shift) == date('z', time() + $shift) - 1) {
                return self::$lng['yesterday'] . ', ' . date("H:i", $var + $shift);
            }
        }

        return date("d.m.Y / H:i", $var + $shift);
    }

    /**
     * Сообщения об ошибках
     *
     * @param string|array $error Сообщение об ошибке (или массив с сообщениями)
     * @param string       $link  Необязательная ссылка перехода
     * @return bool|string
     */
    public static function display_error($error = '', $link = '')
    {
        if (!empty($error)) {
            return '<div class="rmenu"><p><b>' . self::$lng['error'] . '!</b><br />' .
            (is_array($error) ? implode('<br />', $error) : $error) . '</p>' .
            (!empty($link) ? '<p>' . $link . '</p>' : '') . '</div>';
        } else {
            return false;
        }
    }

    /**
     * Отображение различных меню
     *
     * @param array  $val
     * @param string $delimiter Разделитель между пунктами
     * @param string $end_space Выводится в конце
     * @return string
     */
    public static function display_menu($val = [], $delimiter = ' | ', $end_space = '')
    {
        return implode($delimiter, array_diff($val, [''])) . $end_space;
    }

    /**
     * Постраничная навигация
     * За основу взята доработанная функция от форума SMF 2.x.x
     *
     * @param string $url
     * @param int    $start
     * @param int    $total
     * @param int    $kmess
     * @return string
     */
    public static function display_pagination($url, $start, $total, $kmess)
    {
        $neighbors = 2;
        if ($start >= $total) {
            $start = max(0, $total - (($total % $kmess) == 0 ? $kmess : ($total % $kmess)));
        } else {
            $start = max(0, (int)$start - ((int)$start % (int)$kmess));
        }
        $base_link = '<a class="pagenav" href="' . strtr($url, ['%' => '%%']) . 'page=%d' . '">%s</a>';
        $out[] = $start == 0 ? '' : sprintf($base_link, $start / $kmess, '&lt;&lt;');
        if ($start > $kmess * $neighbors) {
            $out[] = sprintf($base_link, 1, '1');
        }
        if ($start > $kmess * ($neighbors + 1)) {
            $out[] = '<span style="font-weight: bold;">...</span>';
        }
        for ($nCont = $neighbors; $nCont >= 1; $nCont--) {
            if ($start >= $kmess * $nCont) {
                $tmpStart = $start - $kmess * $nCont;
                $out[] = sprintf($base_link, $tmpStart / $kmess + 1, $tmpStart / $kmess + 1);
            }
        }
        $out[] = '<span class="currentpage"><b>' . ($start / $kmess + 1) . '</b></span>';
        $tmpMaxPages = (int)(($total - 1) / $kmess) * $kmess;
        for ($nCont = 1; $nCont <= $neighbors; $nCont++) {
            if ($start + $kmess * $nCont <= $tmpMaxPages) {
                $tmpStart = $start + $kmess * $nCont;
                $out[] = sprintf($base_link, $tmpStart / $kmess + 1, $tmpStart / $kmess + 1);
            }
        }
        if ($start + $kmess * ($neighbors + 1) < $tmpMaxPages) {
            $out[] = '<span style="font-weight: bold;">...</span>';
        }
        if ($start + $kmess * $neighbors < $tmpMaxPages) {
            $out[] = sprintf($base_link, $tmpMaxPages / $kmess + 1, $tmpMaxPages / $kmess + 1);
        }
        if ($start + $kmess < $total) {
            $display_page = ($start + $kmess) > $total ? $total : ($start / $kmess + 2);
            $out[] = sprintf($base_link, $display_page, '&gt;&gt;');
        }

        return implode(' ', $out);
    }

    /**
     * Показываем местоположение пользователя
     *
     * @param int    $user_id
     * @param string $place
     * @return mixed|string
     */
    public static function display_place($user_id = 0, $place = '')
    {
        global $headmod;

        $homeurl = App::getContainer()->get('config')['johncms']['homeurl'];
        $place = explode(",", $place);
        $placelist = parent::load_lng('places');

        if (array_key_exists($place[0], $placelist)) {
            if ($place[0] == 'profile') {
                if ($place[1] == $user_id) {
                    return '<a href="' . $homeurl . '/profile/?user=' . $place[1] . '">' . $placelist['profile_personal'] . '</a>';
                } else {
                    $user = self::get_user($place[1]);

                    return $placelist['profile'] . ': <a href="' . $homeurl . '/profile/?user=' . $user['id'] . '">' . $user['name'] . '</a>';
                }
            } elseif ($place[0] == 'online' && isset($headmod) && $headmod == 'online') {
                return $placelist['here'];
            } else {
                return str_replace('#home#', $homeurl, $placelist[$place[0]]);
            }
        }

        return '<a href="' . $homeurl . '/index.php">' . $placelist['homepage'] . '</a>';
    }

    /**
     * Отображения личных данных пользователя
     *
     * @param int   $user Массив запроса в таблицу `users`
     * @param array $arg  Массив параметров отображения
     *                    [lastvisit] (boolean)   Дата и время последнего визита
     *                    [stshide]   (boolean)   Скрыть статус (если есть)
     *                    [iphide]    (boolean)   Скрыть (не показывать) IP и UserAgent
     *                    [iphist]    (boolean)   Показывать ссылку на историю IP
     *
     *                    [header]    (string)    Текст в строке после Ника пользователя
     *                    [body]      (string)    Основной текст, под ником пользователя
     *                    [sub]       (string)    Строка выводится вверху области "sub"
     *                    [footer]    (string)    Строка выводится внизу области "sub"
     *
     * @return string
     */
    public static function display_user($user = 0, $arg = [])
    {
        global $mod;
        $out = false;
        $homeurl = App::getContainer()->get('config')['johncms']['homeurl'];

        if (!$user['id']) {
            $out = '<b>' . self::$lng['guest'] . '</b>';

            if (!empty($user['name'])) {
                $out .= ': ' . $user['name'];
            }

            if (!empty($arg['header'])) {
                $out .= ' ' . $arg['header'];
            }
        } else {
            if (self::$user_set['avatar']) {
                $out .= '<table cellpadding="0" cellspacing="0"><tr><td>';

                if (file_exists((ROOTPATH . 'files/users/avatar/' . $user['id'] . '.png'))) {
                    $out .= '<img src="' . $homeurl . '/files/users/avatar/' . $user['id'] . '.png" width="32" height="32" alt="" />&#160;';
                } else {
                    $out .= '<img src="' . $homeurl . '/images/empty.png" width="32" height="32" alt="" />&#160;';
                }

                $out .= '</td><td>';
            }

            if ($user['sex']) {
                $out .= functions::image(($user['sex'] == 'm' ? 'm' : 'w') . ($user['datereg'] > time() - 86400 ? '_new' : '') . '.png', ['class' => 'icon-inline']);
            } else {
                $out .= functions::image('del.png');
            }

            $out .= !self::$user_id || self::$user_id == $user['id'] ? '<b>' . $user['name'] . '</b>' : '<a href="' . $homeurl . '/profile/?user=' . $user['id'] . '"><b>' . $user['name'] . '</b></a>';
            $rank = [
                0 => '',
                1 => '(GMod)',
                2 => '(CMod)',
                3 => '(FMod)',
                4 => '(DMod)',
                5 => '(LMod)',
                6 => '(Smd)',
                7 => '(Adm)',
                9 => '(SV!)',
            ];
            $rights = isset($user['rights']) ? $user['rights'] : 0;
            $out .= ' ' . $rank[$rights];
            $out .= (time() > $user['lastdate'] + 300 ? '<span class="red"> [Off]</span>' : '<span class="green"> [ON]</span>');

            if (!empty($arg['header'])) {
                $out .= ' ' . $arg['header'];
            }

            if (!isset($arg['stshide']) && !empty($user['status'])) {
                $out .= '<div class="status">' . functions::image('label.png', ['class' => 'icon-inline']) . $user['status'] . '</div>';
            }

            if (self::$user_set['avatar']) {
                $out .= '</td></tr></table>';
            }
        }

        if (isset($arg['body'])) {
            $out .= '<div>' . $arg['body'] . '</div>';
        }

        $ipinf = !isset($arg['iphide']) && self::$user_rights ? 1 : 0;
        $lastvisit = time() > $user['lastdate'] + 300 && isset($arg['lastvisit']) ? self::display_date($user['lastdate']) : false;

        if ($ipinf || $lastvisit || isset($arg['sub']) && !empty($arg['sub']) || isset($arg['footer'])) {
            $out .= '<div class="sub">';

            if (isset($arg['sub'])) {
                $out .= '<div>' . $arg['sub'] . '</div>';
            }

            if ($lastvisit) {
                $out .= '<div><span class="gray">' . self::$lng['last_visit'] . ':</span> ' . $lastvisit . '</div>';
            }

            $iphist = '';

            if ($ipinf) {
                $out .= '<div><span class="gray">' . self::$lng['browser'] . ':</span> ' . htmlspecialchars($user['browser']) . '</div>' .
                    '<div><span class="gray">' . self::$lng['ip_address'] . ':</span> ';
                $hist = $mod == 'history' ? '&amp;mod=history' : '';
                $ip = long2ip($user['ip']);

                if (self::$user_rights && isset($user['ip_via_proxy']) && $user['ip_via_proxy']) {
                    $out .= '<b class="red"><a href="' . $homeurl . '/admin/index.php?act=search_ip&amp;ip=' . $ip . $hist . '">' . $ip . '</a></b>';
                    $out .= '&#160;[<a href="' . $homeurl . '/admin/index.php?act=ip_whois&amp;ip=' . $ip . '">?</a>]';
                    $out .= ' / ';
                    $out .= '<a href="' . $homeurl . '/admin/index.php?act=search_ip&amp;ip=' . long2ip($user['ip_via_proxy']) . $hist . '">' . long2ip($user['ip_via_proxy']) . '</a>';
                    $out .= '&#160;[<a href="' . $homeurl . '/admin/index.php?act=ip_whois&amp;ip=' . long2ip($user['ip_via_proxy']) . '">?</a>]';
                } elseif (self::$user_rights) {
                    $out .= '<a href="' . $homeurl . '/admin/index.php?act=search_ip&amp;ip=' . $ip . $hist . '">' . $ip . '</a>';
                    $out .= '&#160;[<a href="' . $homeurl . '/admin/index.php?act=ip_whois&amp;ip=' . $ip . '">?</a>]';
                } else {
                    $out .= $ip . $iphist;
                }

                if (isset($arg['iphist'])) {
                    /** @var PDO $db */
                    $db = App::getContainer()->get(PDO::class);
                    $iptotal = $db->query("SELECT COUNT(*) FROM `cms_users_iphistory` WHERE `user_id` = '" . $user['id'] . "'")->fetchColumn();
                    $out .= '<div><span class="gray">' . self::$lng['ip_history'] . ':</span> <a href="' . $homeurl . '/profile/?act=ip&amp;user=' . $user['id'] . '">[' . $iptotal . ']</a></div>';
                }

                $out .= '</div>';
            }

            if (isset($arg['footer'])) {
                $out .= $arg['footer'];
            }
            $out .= '</div>';
        }

        return $out;
    }

    /**
     * Форматирование имени файла
     *
     * @param string $name
     * @return string
     */
    public static function format($name)
    {
        $f1 = strrpos($name, ".");
        $f2 = substr($name, $f1 + 1, 999);
        $fname = strtolower($f2);

        return $fname;
    }

    /**
     * Получаем данные пользователя
     *
     * @param int $id Идентификатор пользователя
     * @return array|bool
     */
    public static function get_user($id = 0)
    {
        if ($id && $id != self::$user_id) {
            /** @var PDO $db */
            $db = App::getContainer()->get(PDO::class);
            $req = $db->query("SELECT * FROM `users` WHERE `id` = '$id'");

            if ($req->rowCount()) {
                return $req->fetch();
            } else {
                return false;
            }
        } else {
            return self::$user_data;
        }
    }

    public static function image($name, $args = [])
    {
        $homeurl = App::getContainer()->get('config')['johncms']['homeurl'];

        if (is_file(ROOTPATH . 'theme/' . core::$user_set['skin'] . '/images/' . $name)) {
            $src = $homeurl . '/theme/' . core::$user_set['skin'] . '/images/' . $name;
        } elseif (is_file(ROOTPATH . 'images/' . $name)) {
            $src = $homeurl . '/images/' . $name;
        } else {
            return false;
        }

        return '<img src="' . $src . '" alt="' . (isset($args['alt']) ? $args['alt'] : '') . '"' .
        (isset($args['width']) ? ' width="' . $args['width'] . '"' : '') .
        (isset($args['height']) ? ' height="' . $args['height'] . '"' : '') .
        ' class="' . (isset($args['class']) ? $args['class'] : 'icon') . '"/>';
    }

    /**
     * Находится ли выбранный пользователь в контактах и игноре?
     *
     * @param int $id Идентификатор пользователя, которого проверяем
     * @return int Результат запроса:
     *                0 - не в контактах
     *                1 - в контактах
     *                2 - в игноре у меня
     */
    public static function is_contact($id = 0)
    {
        static $user_id = null;
        static $return = 0;

        if (!self::$user_id && !$id) {
            return 0;
        }

        if (is_null($user_id) || $id != $user_id) {
            /** @var PDO $db */
            $db = App::getContainer()->get(PDO::class);
            $user_id = $id;
            $req = $db->query("SELECT * FROM `cms_contact` WHERE `user_id` = '" . self::$user_id . "' AND `from_id` = '$id'");

            if ($req->rowCount()) {
                $res = $req->fetch();
                if ($res['ban'] == 1) {
                    $return = 2;
                } else {
                    $return = 1;
                }
            } else {
                $return = 0;
            }
        }

        return $return;
    }

    /**
     * Проверка на игнор у получателя
     *
     * @param $id
     * @return bool
     */
    public static function is_ignor($id)
    {
        static $user_id = null;
        static $return = false;

        if (!self::$user_id && !$id) {
            return false;
        }

        if (is_null($user_id) || $id != $user_id) {
            /** @var PDO $db */
            $db = App::getContainer()->get(PDO::class);
            $user_id = $id;
            $req = $db->query("SELECT * FROM `cms_contact` WHERE `user_id` = '$id' AND `from_id` = '" . self::$user_id . "'");

            if ($req->rowCount()) {
                $res = $req->fetch();
                if ($res['ban'] == 1) {
                    $return = true;
                }
            }
        }

        return $return;
    }

    /**
     * Транслитерация с Русского в латиницу
     *
     * @param string $str
     * @return string
     */
    public static function rus_lat($str)
    {
        $replace = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'j',
            'з' => 'z',
            'и' => 'i',
            'й' => 'i',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ъ' => "",
            'ы' => 'y',
            'ь' => "",
            'э' => 'ye',
            'ю' => 'yu',
            'я' => 'ya',
        ];

        return strtr($str, $replace);
    }

    /**
     * Обработка смайлов
     *
     * @param string $str
     * @param bool   $adm
     * @return string
     */
    public static function smileys($str, $adm = false)
    {
        static $smileys_cache = [];
        if (empty($smileys_cache)) {
            $file = ROOTPATH . 'files/cache/smileys.dat';
            if (file_exists($file) && ($smileys = file_get_contents($file)) !== false) {
                $smileys_cache = unserialize($smileys);

                return strtr($str, ($adm ? array_merge($smileys_cache['usr'], $smileys_cache['adm']) : $smileys_cache['usr']));
            } else {
                return $str;
            }
        } else {
            return strtr($str, ($adm ? array_merge($smileys_cache['usr'], $smileys_cache['adm']) : $smileys_cache['usr']));
        }
    }

    /**
     * Функция пересчета на дни, или часы
     *
     * @param int $var
     * @return bool|string
     */
    public static function timecount($var)
    {
        global $lng;
        if ($var < 0) {
            $var = 0;
        }
        $day = ceil($var / 86400);
        if ($var > 345600) {
            return $day . ' ' . $lng['timecount_days'];
        }
        if ($var >= 172800) {
            return $day . ' ' . $lng['timecount_days_r'];
        }
        if ($var >= 86400) {
            return '1 ' . $lng['timecount_day'];
        }

        return date("G:i:s", mktime(0, 0, $var));
    }

    // Транслитерация текста
    public static function trans($str)
    {
        $replace = [
            'a'  => 'а',
            'b'  => 'б',
            'v'  => 'в',
            'g'  => 'г',
            'd'  => 'д',
            'e'  => 'е',
            'yo' => 'ё',
            'zh' => 'ж',
            'z'  => 'з',
            'i'  => 'и',
            'j'  => 'й',
            'k'  => 'к',
            'l'  => 'л',
            'm'  => 'м',
            'n'  => 'н',
            'o'  => 'о',
            'p'  => 'п',
            'r'  => 'р',
            's'  => 'с',
            't'  => 'т',
            'u'  => 'у',
            'f'  => 'ф',
            'h'  => 'х',
            'c'  => 'ц',
            'ch' => 'ч',
            'w'  => 'ш',
            'sh' => 'щ',
            'q'  => 'ъ',
            'y'  => 'ы',
            'x'  => 'э',
            'yu' => 'ю',
            'ya' => 'я',
            'A'  => 'А',
            'B'  => 'Б',
            'V'  => 'В',
            'G'  => 'Г',
            'D'  => 'Д',
            'E'  => 'Е',
            'YO' => 'Ё',
            'ZH' => 'Ж',
            'Z'  => 'З',
            'I'  => 'И',
            'J'  => 'Й',
            'K'  => 'К',
            'L'  => 'Л',
            'M'  => 'М',
            'N'  => 'Н',
            'O'  => 'О',
            'P'  => 'П',
            'R'  => 'Р',
            'S'  => 'С',
            'T'  => 'Т',
            'U'  => 'У',
            'F'  => 'Ф',
            'H'  => 'Х',
            'C'  => 'Ц',
            'CH' => 'Ч',
            'W'  => 'Ш',
            'SH' => 'Щ',
            'Q'  => 'Ъ',
            'Y'  => 'Ы',
            'X'  => 'Э',
            'YU' => 'Ю',
            'YA' => 'Я',
        ];

        return strtr($str, $replace);
    }

    /*
    -----------------------------------------------------------------
    Старая функция проверки переменных.
    В новых разработках не применять!
    Вместо данной функции использовать checkin()
    -----------------------------------------------------------------
    */
    public static function check($str)//TODO: на удаление
    {
        /** @var PDO $db */
        $db = App::getContainer()->get(PDO::class);

        $str = htmlentities(trim($str), ENT_QUOTES, 'UTF-8');
        $str = self::checkin($str);
        $str = nl2br($str);
        $str = $db->quote($str);

        return $str;
    }
}
