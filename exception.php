<?php
class ExistedException extends Exception{
    public function __construct($message,$code = 0,Throwable $previous = null) {
        parent::__construct("existed_".$message,$code,$previous);
    }
}
class DeniedException extends Exception{
    public function __construct($message,$code = 0,Throwable $previous = null) {
        parent::__construct("denied_".$message,$code,$previous);
    }
}
class StatusException extends Exception{
    public function __construct($message,$code = 0,Throwable $previous = null) {
        parent::__construct("status_".$message,$code,$previous);
    }
}