<?php
    namespace App\Exceptions;

    trait ExceptionTrait{
        public function apiException($requesst, $e){
            if($e instanceof ModelNotFoundException){

            }
            if($e instanceof NotFoundHttpException){
                
            }

            if($e instanceof AuthenticationException){
                return response()->json([
                    'message' => 'custom error'
                ], Response::HTTP_NOT_FOUND);
            }

        }
    }


?>