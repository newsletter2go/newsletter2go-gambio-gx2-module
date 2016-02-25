<?php
// VERSION 3000 line:61

if (isset($SESSION) && $_SESSION['customers_status']['customers_status_id'] == '0') {
    define('SUPPRESS_REDIRECT', true);
}

require('includes/application_top.php');

AdminMenuControl::connect_with_page('admin.php?do=ModuleCenter');

defined('GM_HTTP_SERVER') or define('GM_HTTP_SERVER', HTTP_SERVER);
define('PAGE_URL', GM_HTTP_SERVER . DIR_WS_ADMIN . basename(__FILE__));

function replaceTextPlaceholders($content)
{
    $txt = new LanguageTextManager('newsletter2go', $_SESSION['languages_id']);
    while (preg_match('/##(\w+)\b/', $content, $matches) == 1) {
        $replacement = $txt->get_text($matches[1]);
        if (empty($replacement)) {
            $replacement = $matches[1];
        }

        $content = preg_replace('/##' . $matches[1] . '/', $replacement . '$1', $content, 1);
    }

    return $content;
}

function getLanguageCodes()
{
    $query = 'SELECT `code` FROM `' . TABLE_LANGUAGES . '` WHERE `status` = 1';
    $result = xtc_db_query($query);
    $languageCodes = array();
    while ($row = xtc_db_fetch_array($result)) {
        $languageCodes[] = $row['code'];
    }

    return $languageCodes;
}

// setup username, apikey (initial or from db)
$query = "SELECT `configuration_value` FROM `" . TABLE_CONFIGURATION . "` WHERE `configuration_key` = 'NEWSLETTER2GO_USERNAME'";
$username = xtc_db_fetch_array(xtc_db_query($query));
$username = $username['configuration_value'];

if (empty($username)) {
    $username = '';
    $apikey = '';
    $version = '';
} else {
    $query = "SELECT `configuration_value` FROM `" . TABLE_CONFIGURATION . "` WHERE `configuration_key` = 'NEWSLETTER2GO_APIKEY'";
    $apikey = xtc_db_fetch_array(xtc_db_query($query));
    $apikey = $apikey['configuration_value'];
}

if (!empty($_POST['n2g_username']) && !empty($_POST['n2g_apikey'])) {
    $inputUser = xtc_db_input($_POST['n2g_username']);
    $inputKey = xtc_db_input($_POST['n2g_apikey']);

    if (empty($username)) {
        $query = "INSERT INTO `" . TABLE_CONFIGURATION . "` (`configuration_key`, `configuration_value`)
                    VALUES ('NEWSLETTER2GO_USERNAME', '$inputUser'), ('NEWSLETTER2GO_APIKEY', '$inputKey'), ('NEWSLETTER2GO_VERSION', '3000')";
        xtc_db_query($query);
        $username = $inputUser;
        $apikey = $inputKey;
    } else {
        if ($username != $inputUser) {
            $username = $inputUser;
            $query = "UPDATE `" . TABLE_CONFIGURATION . "` SET `configuration_value` = '$inputUser' WHERE `configuration_key` = 'NEWSLETTER2GO_USERNAME'";
            xtc_db_query($query);
        }

        if ($apikey != $inputKey) {
            $apikey = $inputKey;
            $query = "UPDATE `" . TABLE_CONFIGURATION . "` SET `configuration_value` = '$inputKey' WHERE `configuration_key` = 'NEWSLETTER2GO_APIKEY'";
            xtc_db_query($query);
        }
    }
}

ob_start();
?>
    <!DOCTYPE html>
    <html <?php echo HTML_PARAMS; ?>>
    <head>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $_SESSION['language_charset']; ?>">
        <title><?php echo TITLE; ?></title>
        <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
        <style>
            td.boxCenter {
                font: 0.8em sans-serif;
                padding: 1em;
            }

            td.boxCenter h1 {
                color: #0264BB;
                margin: 1ex 0;
            }

            dl.form {
                overflow: auto;
                position: relative;
            }

            dl.form > dt, dl.form > dd {
                margin: 2px 0;
            }

            dl.form > dt {
                float: left;
                clear: left;
                width: 25%
            }

            dl.form > dd {
                float: left;
                width: 75%;
                margin: 0;
            }

            dl.form > dt label {
                display: inline-block; /*width: 200px*/
            }

            dl.form > dt label:after {
                content: ':';
            }

            dl.form input[type="text"] {
                width: 25em;
            }

            form.bluegray {
                font-size: 1.2em;
            }

            form.bluegray fieldset {
                border: none;
                padding: 0;
                margin: 1ex 0 0 0;
            }

            form.bluegray legend {
                font-weight: bolder;
                font-size: 1.4em;
                background: #585858;
                color: #FFFFFF;
                padding: .2ex 0.5%;
                width: 99%;
            }

            form.bluegray dl.adminform {
                margin: 0;
            }

            form.bluegray dl.adminform dt, form.bluegray dl.adminform dd {
                line-height: 20px;
                padding: 3px 0;
                margin: 0;
            }

            form.bluegray dl.adminform dt {
                width: 18%;
                float: left;
                font-weight: bold;
                padding: 2px;
            }

            form.bluegray dl.adminform dd {
                border-bottom: 1px dotted rgb(90, 90, 90);
                width: 80%;
                float: none;
                padding-left: 20%;
                background-color: #F7F7F7;
            }

            #generate {
                height: 28px;
                margin: 3px
            }

            /*form.bluegray dl.adminform dd:nth-child(4n) {background: #D6E6F3;*/
            }
        </style>
        <script type="text/javascript">
            function generateKey() {
                var key = '';
                var allowedChars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                var keyLength = 30;
                for (var i = keyLength; i > 0; --i) key += allowedChars[Math.round(Math.random() * (allowedChars.length - 1))];

                document.getElementById('n2g_apikey').value = key;
            }
        </script>
    </head>
    <body>
    <?php require(DIR_WS_INCLUDES . 'header.php'); ?>
    <table border="0" width="100%" cellspacing="2" cellpadding="2">
        <tr>
            <td class="columnLeft2" width="<?php echo BOX_WIDTH; ?>" valign="top">
                <?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
            </td>

            <td class="boxCenter" width="100%" valign="top">
                <h1>##title</h1>
                <form class="adminform bluegray" action="" method="POST">
                    <dl class="form adminform">
                        <dt><label for="n2g_username">##username</label>
                        </dt>
                        <dd>
                            <input id="n2g_username" name="n2g_username" type="text" value="<?php echo $username ?>"
                                   required>
                        </dd>
                        <dt><label for="n2g_apikey">##apikey</label></dt>
                        <dd>
                            <input id="n2g_apikey" name="n2g_apikey" type="text" value="<?php echo $apikey ?>" required>
                            <button id="generate" type="button" name="n2g_generate" onclick="generateKey()">Generate
                                key
                            </button>
                        </dd>
                    </dl>
                    <input type="submit" value="##save" class="button">
                    <input type="reset" value="##cancel" class="button">
                </form>
            </td>
        </tr>
    </table>
    </body>
    </html>

<?php
require(DIR_WS_INCLUDES . 'footer.php');
echo replaceTextPlaceholders(ob_get_clean());
require(DIR_WS_INCLUDES . 'application_bottom.php');