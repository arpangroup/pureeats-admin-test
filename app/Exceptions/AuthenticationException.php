<?php
    namespace App\Exceptions;

    class AuthenticationException extends BaseException{

        function __construct($errorCode, $message) {
            parent::__construct($errorCode, $message);
        }
        
    }
?>