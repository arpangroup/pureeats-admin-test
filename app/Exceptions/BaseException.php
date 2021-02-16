<?php
    namespace App\Exceptions;
    use Exception;
    use Symfony\Component\HttpFoundation\Response;
    // Use Log;

    class BaseException extends Exception{

        public $isSuccess = false;
        public $message;
        public $code = 'E1001';

        function __construct($errorCode, $message) {
            $this->code = $errorCode;
            $this->message = $message;
        }


        public function render($exception)
        {
            return response()->json([
                'success' => $this->isSuccess,
                'message' => $this->getMessage(),
                'code'=> $this->code
            ], Response::HTTP_BAD_REQUEST);
        }


         // public function render(Throwable $exception){
        //     return response()->json([
        //         'he'=>$exception,
        //     ]);
	    // }

        // public function report(){
        //     //Log::critical("Hacker trying to access");
        //     $this->renderable(function (AuthenticationException $e, $request) {
        //         return response()->json([
        //             'message' => 'custom error inside AuthenticationException report',
        //         ], 500);
        //     });
        // }

        
    }
?>