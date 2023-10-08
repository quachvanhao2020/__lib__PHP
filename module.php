<?php
!defined("TYPE_LANG") && define("TYPE_LANG","json");
!defined("ACCEPT_LANG") && define("ACCEPT_LANG","vi,en");
!defined("DEFAULT_LANG") && define("DEFAULT_LANG","en");
!defined("DETECT_LANG") && define("DETECT_LANG",false);
global $_LANGUAGE;
global $_HL;
$_HL = DEFAULT_LANG;
if(DETECT_LANG){
    $_HL = detect_language();
}
function load_module(string $path,callable $callable = null){
    global $_LANGUAGE;
    global $_HL;
    $load = require $path."/load.php";
    $name = $load['name'];
    $is = strtoupper($name)."_ENABLED";
    if(defined($is)){
        if(!constant($is)){
            if(!$callable) $callable = function() use($name){ return [
                'content' => $name,
                'type' => "text",
                'params' => [],
            ];};
            $callable();
            return;
        }
    }
    $hl = $_HL;
    $i18n = $load['i18n'];
    $lang = [];
    switch (TYPE_LANG) {
        case 'json':
        $lang = json_decode(@file_get_contents($i18n."/{$hl}.json"),true);
            break;
        default:
        $lang = require $i18n."/{$hl}.php";
            break;
    }
    if(@empty($lang)){
        $_LANGUAGE = array_merge($_LANGUAGE,$lang);
    }
}
function __($key){
    global $_LANGUAGE;
    return isset($_LANGUAGE[$key]) ? $_LANGUAGE[$key] : $key;
}
function detect_language(){
    $lang = isset($_GET['hl']) ? $_GET['hl'] : (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2) : "en");
    $acceptLang = explode(",",ACCEPT_LANG);
    $lang = in_array($lang, $acceptLang) ? $lang : 'en';
    return $lang;
}
?>