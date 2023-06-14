<?php
$a = [__ROOT__."/env",(isset($_SERVER['HTTP_HOST']) ? get_domain($_SERVER['HTTP_HOST']) : "localhost").".env"];
if(file_exists($a[0]."/".$a[1])){
    Dotenv\Dotenv::createImmutable($a[0],$a[1])->load();
}
Dotenv\Dotenv::createImmutable(__ROOT__)->load();
unset($a);
function get_domain(string $host){
    $myhost = strtolower(trim($host));
    $count = substr_count($myhost, '.');
    if($count === 2){
      if(strlen(explode('.', $myhost)[1]) > 3) $myhost = explode('.', $myhost, 2)[1];
    } else if($count > 2){
      $myhost = get_domain(explode('.', $myhost, 2)[1]);
    }
    return $myhost;
}