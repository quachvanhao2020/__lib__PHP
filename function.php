<?php
function finde($data,string $key,$value){
    $e = find($data,$key,$value);
    if(empty($e)){
        throw new ExistedException($value);
    }
    return $e;
}
function getExceptionTraceAsString($exception) {
    $rtn = "";
    $count = 0;
    foreach ($exception->getTrace() as $frame) {
        $args = "";
        if (isset($frame['args'])) {
            $args = array();
            foreach ($frame['args'] as $arg) {
                if (is_string($arg)) {
                    $args[] = "'" . $arg . "'";
                } elseif (is_array($arg)) {
                    $args[] = "Array";
                } elseif (is_null($arg)) {
                    $args[] = 'NULL';
                } elseif (is_bool($arg)) {
                    $args[] = ($arg) ? "true" : "false";
                } elseif (is_object($arg)) {
                    $args[] = get_class($arg);
                } elseif (is_resource($arg)) {
                    $args[] = get_resource_type($arg);
                } else {
                    $args[] = $arg;
                }   
            }   
            $args = join(", ", $args);
        }
        $rtn .= sprintf(
            "#%s %s(%s): %s%s%s(%s)\n",
            $count,
            $frame['file'],
            $frame['line'],
            isset($frame['class']) ? $frame['class'] : '',
            isset($frame['type']) ? $frame['type'] : '',
            $frame['function'],
            $args
        );
        $count++;
    }
    return $rtn;
}
function reload(){
    header("Location: ".$_SERVER["REQUEST_URI"]);
}
function rd_token(){
    return md5(uniqid(rand(), true));
}
function include_var($filePath,$variables=[],$print=false)
{
    $output = NULL;
    if(file_exists($filePath)){
        extract($variables);
        ob_start();
        include $filePath;
        $output = ob_get_clean();
    }
    if ($print) {
        print $output;
    }
    return $output;
}