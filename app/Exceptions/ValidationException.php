<?php
    namespace App\Exceptions;
    use Exception;
    use Symfony\Component\HttpFoundation\Response;
    // Use Log;
    class ValidationException extends BaseException{

        function __construct($errorCode, $message) {
            parent::__construct($errorCode, $message);
        }
    }
?>