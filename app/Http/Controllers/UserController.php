<?php
namespace App\Http\Controllers;

use App\AcceptDelivery;
use App\Address;
use App\User;
use App\LoginSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Ixudra\Curl\Facades\Curl;
use JWTAuth;
use JWTAuthException;
use Spatie\Permission\Models\Role;
use App\Sms;
use App\PushToken;

use ErrorCode;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;

class UserController extends Controller
{
    /**
     * @param $email
     * @param $password
     * @return mixed
     */
    private function getToken($email, $password)
    {
        $token = null;
        //$credentials = $request->only('email', 'password');
        try {
            if (!$token = JWTAuth::attempt(['email' => $email, 'password' => $password])) {
                return response()->json([
                    'response' => 'error',
                    'message' => 'Password or email is invalid..',
                    'token' => $token,
                ]);
            }
        } catch (JWTAuthException $e) {
            return response()->json([
                'response' => 'error',
                'message' => 'Token creation failed',
            ]);
        }
        return $token;
    }

    /**
     * @param $phone
     * @param $password
     * @return mixed
     */
    private function getTokenFromPhoneAndPassword($phone, $password)
    {
        $token = null;
        //$credentials = $request->only('email', 'password');
        try {
            if (!$token = JWTAuth::attempt(['phone' => $phone, 'password' => $password])) {
                return response()->json([
                    'response' => 'error',
                    'message' => 'Password or phone is invalid..',
                    'token' => $token,
                ]);
            }
        } catch (JWTAuthException $e) {
            return response()->json([
                'response' => 'error',
                'message' => 'Token creation failed',
            ]);
        }
        return $token;
    }


    
    /**
     * @param $user_id
     * @param $push_token
     */
    private function saveToken($user_id, $push_token)
    {
        $pushToken = PushToken::where('user_id', $user_id)->first();

        if ($pushToken) {
            //update the existing token
            $pushToken->token = $push_token;
            $pushToken->save();
        } else {
            //create new token for user
            $pushToken = new PushToken();
            $pushToken->token = $push_token;
            $pushToken->user_id = $user_id;
            $pushToken->save();
        }
        $success = $push_token;
        return response()->json($success);
    }

    /**
     * @param Request $request
     */
    public function login(Request $request)
    {

        $user = \App\User::where('email', $request->email)->get()->first();

        //check if it is coming from social login,
        if ($request->accessToken != null) {

            //check socialtoken validation
            $validation = $this->validateAccessToken($request->email, $request->provider, $request->accessToken);
            if ($validation) {
                if ($user) {
                    //user exists -> check if user has phone
                    if ($user->phone != null) {
                        // user has phone
                        //LOGIN USER
                        $token = JWTAuth::fromUser($user);
                        $user->auth_token = $token;

                        // Add address if address present
                        if ($request->address['lat'] != null) {
                            $address = new Address();
                            $address->user_id = $user->id;
                            $address->latitude = $request->address['lat'];
                            $address->longitude = $request->address['lng'];
                            $address->address = $request->address['address'];
                            $address->house = $request->address['house'];
                            $address->tag = $request->address['tag'];
                            $address->save();
                            $user->default_address_id = $address->id;
                        }

                        $user->save();
                        if ($user->default_address_id !== 0) {
                            $default_address = \App\Address::where('id', $user->default_address_id)->get(['address', 'house', 'latitude', 'longitude', 'tag'])->first();
                        } else {
                            $default_address = null;
                        }

                        $running_order = null;

                        $response = [
                            'success' => true,
                            'data' => [
                                'id' => $user->id,
                                'auth_token' => $token,
                                'name' => $user->name,
                                'email' => $user->email,
                                'phone' => $user->phone,
                                'default_address_id' => $user->default_address_id,
                                'default_address' => $default_address,
                                'delivery_pin' => $user->delivery_pin,
                                'wallet_balance' => $user->balanceFloat,
                            ],
                            'running_order' => $running_order,
                        ];
                        return response()->json($response);
                    }
                    if ($request->phone != null) {
                        $checkPhone = User::where('phone', $request->phone)->first();
                        if ($checkPhone) {
                            $response = [
                                'email_phone_already_used' => true,
                            ];
                            return response()->json($response);
                        } else {
                            try {
                                $user->phone = $request->phone;
                                $user->save();
                                $token = JWTAuth::fromUser($user);
                                $user->auth_token = $token;

                                // Add address if address present
                                if ($request->address['lat'] != null) {
                                    $address = new Address();
                                    $address->user_id = $user->id;
                                    $address->latitude = $request->address['lat'];
                                    $address->longitude = $request->address['lng'];
                                    $address->address = $request->address['address'];
                                    $address->house = $request->address['house'];
                                    $address->tag = $request->address['tag'];
                                    $address->save();
                                    $user->default_address_id = $address->id;
                                }

                                $user->save();
                            } catch (\Throwable $e) {
                                $response = ['success' => false, 'data' => 'Something went wrong. Please try again...'];
                                return response()->json($response, 201);
                            }

                            if ($user->default_address_id !== 0) {
                                $default_address = \App\Address::where('id', $user->default_address_id)->get(['address', 'house', 'latitude', 'longitude', 'tag'])->first();
                            } else {
                                $default_address = null;
                            }

                            $running_order = null;

                            $response = [
                                'success' => true,
                                'data' => [
                                    'id' => $user->id,
                                    'auth_token' => $token,
                                    'name' => $user->name,
                                    'email' => $user->email,
                                    'phone' => $user->phone,
                                    'default_address_id' => $user->default_address_id,
                                    'default_address' => $default_address,
                                    'delivery_pin' => $user->delivery_pin,
                                    'wallet_balance' => $user->balanceFloat,
                                ],
                                'running_order' => $running_order,
                            ];

                            return response()->json($response);
                        }

                    } else {
                        $response = [
                            'enter_phone_after_social_login' => true,
                        ];
                        return response()->json($response);
                    }
                } else {
                    // there is no user with this email..

                    if ($request->phone != null) {
                        $checkPhone = User::where('phone', $request->phone)->first();
                        if ($checkPhone) {
                            $response = [
                                'email_phone_already_used' => true,
                            ];
                            return response()->json($response);
                        } else {
                            //reg user
                            $user = new User();
                            $user->name = $request->name;
                            $user->email = $request->email;
                            $user->phone = $request->phone;
                            $user->password = \Hash::make(str_random(8));
                            $user->delivery_pin = strtoupper(str_random(5));

                            try {
                                $user->save();
                                $user->assignRole('Customer');
                                $token = JWTAuth::fromUser($user);
                                $user->auth_token = $token;

                                // Add address if address present
                                if ($request->address['lat'] != null) {
                                    $address = new Address();
                                    $address->user_id = $user->id;
                                    $address->latitude = $request->address['lat'];
                                    $address->longitude = $request->address['lng'];
                                    $address->address = $request->address['address'];
                                    $address->house = $request->address['house'];
                                    $address->tag = $request->address['tag'];
                                    $address->save();
                                    $user->default_address_id = $address->id;
                                }

                                $user->save();
                            } catch (\Throwable $e) {
                                $response = ['success' => false, 'data' => 'Something went wrong. Please try again...'];
                                return response()->json($response, 201);
                            }

                            if ($user->default_address_id !== 0) {
                                $default_address = \App\Address::where('id', $user->default_address_id)->get(['address', 'house', 'latitude', 'longitude', 'tag'])->first();
                            } else {
                                $default_address = null;
                            }

                            $running_order = null;

                            $response = [
                                'success' => true,
                                'data' => [
                                    'id' => $user->id,
                                    'auth_token' => $token,
                                    'name' => $user->name,
                                    'email' => $user->email,
                                    'phone' => $user->phone,
                                    'default_address_id' => $user->default_address_id,
                                    'default_address' => $default_address,
                                    'delivery_pin' => $user->delivery_pin,
                                    'wallet_balance' => $user->balanceFloat,
                                ],
                                'running_order' => $running_order,
                            ];
                            return response()->json($response);
                        }

                    } else {
                        // SHOW ENTER PHONE NUMBER
                        $response = [
                            'enter_phone_after_social_login' => true,
                        ];
                        return response()->json($response);
                    }
                    return response()->json($response);
                }
            } else {
                $response = false;
                return response()->json($response);
            }
        }

        // if user exists, check user

        if ($request->password != null) {
            if ($user && \Hash::check($request->password, $user->password)) // The passwords match...
            {
                $token = self::getToken($request->email, $request->password);
                $user->auth_token = $token;

                // Add address if address present
                if ($request->address['lat'] != null) {
                    $address = new Address();
                    $address->user_id = $user->id;
                    $address->latitude = $request->address['lat'];
                    $address->longitude = $request->address['lng'];
                    $address->address = $request->address['address'];
                    $address->house = $request->address['house'];
                    $address->tag = $request->address['tag'];
                    $address->save();
                    $user->default_address_id = $address->id;
                }

                $user->save();
                if ($user->default_address_id !== 0) {
                    $default_address = \App\Address::where('id', $user->default_address_id)->get(['address', 'house', 'latitude', 'longitude', 'tag'])->first();
                } else {
                    $default_address = null;
                }

                $running_order = null;

                $response = [
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'auth_token' => $token,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'default_address_id' => $user->default_address_id,
                        'default_address' => $default_address,
                        'delivery_pin' => $user->delivery_pin,
                        'wallet_balance' => $user->balanceFloat,
                    ],
                    'running_order' => $running_order,
                ];
                return response()->json($response, 201);
            } else {
                $response = ['success' => false, 'data' => 'DONOTMATCH'];
                return response()->json($response, 201);
            }
        }

    }


    /*---------------------------------------------------------------------------*/
    /*                  ARPAN[13-Jan-2021]
    /*---------------------------------------------------------------------------*/

    public function loginUsingOtp(Request $request){
        Log::info('#############################################################');
        Log::info('Inside loginUsingOtp()');
        Log::info('#############################################################');
        if($request->phone && $request->otp){
            //  First check phone is valid  or not
            $user = \App\User::where('phone', $request->phone)->get()->first();
            Log::info('IsRoleCustomer: ' .$user->hasRole('Customer'));
            if($user && $user->hasRole('Customer')){
                if($user->is_active == 1){
                    Log::info('IsActive: ' .$user->is_active);

                    $sms = new Sms();
                    Log::info('Calling Verify OTP.....: ');
                    $verifyResponse = $sms->verifyOtp($request->phone, $request->otp);
                    if($verifyResponse['valid_otp'] == true){
                        Log::info('OTP Verification: true');
                        $user->password = \Hash::make($request->otp);
                        $user->save();

                        Log::info('Saving push token......');
                        if($request->push_token){
                            $this->saveToken($user->id, $request->push_token);
                        }

                        $defaultAddress = null;
                        if ($user->default_address_id !== 0) {
                             $default_address = \App\Address::where('id', $user->default_address_id)->get()->first();
                         }


                        try{
                            $meta = $request->meta;
                            if($meta != null){
                                $loginSession =  LoginSession::where('user_id', $user->id)->get()->first();
                                if(!$loginSession){
                                    $loginSession = new LoginSession();
                                    $loginSession->user_id = $user->id;
                                }
                                $loginSession->login_at = Carbon::now();
                                $loginSession->mac_address = $meta['MAC'];
                                $loginSession->ip_address = $meta['wifiIP'];
                                $loginSession->manufacturer = $meta['manufacturer'];
                                $loginSession->model = $meta['model'];
                                $loginSession->sdk = $meta['sdk'];
                                $loginSession->brand = $meta['brand'];
                                $loginSession->save();

                            }
                        }catch (\Throwable $th) {
                            Log::error('ERROR inside login() during meta record insertion');
                            Log::error('ERROR: ' .$th->getMessage());
                        }

                        Log::info('Fetch Running Orders.....');
                        //$running_order = null;
                        $running_orders = \App\Order::where('user_id', $user->id)
                        ->whereIn('orderstatus_id', ['1', '2', '3', '4', '7', '8'])
                        ->with('orderitems', 'restaurant')
                        ->get();

                        $response = [
                            'success' => true,
                            'message' => "login success",
                            'data' => [
                                'id' => $user->id,
                                'auth_token' => $user->auth_token,
                                'name' => $user->name,
                                'photo' => $user->photo,
                                'email' => $user->email,
                                'email_verified_at' => $user->email_verified_at,
                                'phone' => $user->phone,
                                'default_address_id' => $user->default_address_id,
                                'default_address' => $defaultAddress,
                                'delivery_pin' => $user->delivery_pin,
                                'wallet_balance' => $user->balanceFloat,
                                'photo' => $user->photo,
                                'running_orders' => $running_orders,
                            ],                            
                        ];
                        return response()->json($response);

                    }else{
                        return response()->json(['success' => false,"message" => "Invalid OTP", ]);
                    }
                }else{
                    throw new AuthenticationException(ErrorCode::ACCOUNT_BLOCKED, "User blocked");
                }
            }else{
                if(!$user)throw new AuthenticationException(ErrorCode::PHONE_NOT_EXIST, "Customer not found for " .$request->phone);
                if(!$user->hasRole('Customer'))throw new AuthenticationException(ErrorCode::BAD_REQUEST, "Invalid Role ");
                throw new AuthenticationException(ErrorCode::BAD_RESPONSE, "Something error happened");
            }


        }else{
            if(!$request->phone)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "phone should not be null");
            if(!$request->otp)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "otp should not be null");
            return response()->json([
                'success' => false,
                "message" => "Invalid request body"
            ]);
        }
    }


    private function validateRegisterRequest($request){
        if($request->email && $request->phone && $request->name && $request->password){

            $checkEmail = User::where('email', $request->email)
                //->where('is_email_verified', 1)
                ->first();
            $checkPhone = User::where('phone', $request->phone)->first();
    
            if ($checkPhone || $checkEmail) {// if phone or email already exist
                if ($checkPhone)throw new ValidationException(ErrorCode::DUPLICATE_MOBILE_NUMBER, "phone already registered");
                if ($checkEmail)throw new ValidationException(ErrorCode::DUPLICATE_EMAIL_ID, "Email already registered");
            }
        }else{
            if(!$request->email)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "email should not be null");
            if(!$request->phone)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "phone should not be null");
            if(!$request->name)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "name should not be null");
        }

    }

    /**
    * @param Request $request
    */
    public function register(Request $request)
    {
        Log::info('#############################################################');
        Log::info('Inside register()');
        Log::info('#############################################################');

        $this->validateRegisterRequest($request);       


        $payload = [
            'password' => \Hash::make($request->password),
            'email' => $request->email,
            'name' => $request->name,
            'phone' => $request->phone,
            'delivery_pin' => strtoupper(str_random(5)),
            'auth_token' => '',
        ];

        try {
            Log::info('Validating request....: ');
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
                'phone' => ['required'],
            ]);

            $user = new \App\User($payload);
            if ($user->save()) {
                Log::info('User is Saved');

                //$token = self::getToken($request->email, $request->password); // generate user token
                Log::info('Generating Token....');
                $token = self::getTokenFromPhoneAndPassword($request->phone, $request->password);

                Log::info('TOKEN: ' .$token);
                if (!is_string($token)) {
                    //return response()->json(['success' => false, 'data' => 'Token generation failed'], 201);
                    throw new ValidationException(ErrorCode::TOKEN_GENERATION_FAILED, "Token generation failed");
                }

                $user = \App\User::where('phone', $request->phone)->get()->first();
                $user->auth_token = $token; // update user token

                // Add address if address present
                Log::info('Check if user provide address in registration request');
                if ($request->address != null && $request->address['lat'] != null) {
                    Log::info('ADDRESS: ' .$request->address);
                    $address = new Address();
                    $address->user_id = $user->id;
                    $address->latitude = $request->address['lat'];
                    $address->longitude = $request->address['lng'];
                    $address->address = $request->address['address'];
                    $address->house = $request->address['house'];
                    $address->tag = $request->address['tag'];
                    $address->save();
                    $user->default_address_id = $address->id;
                }

                try{
                    $meta = $request->meta;
                    if($meta != null)$user->meta = $meta;
                    $loginSession =  LoginSession::where('user_id', $user->id)->get()->first();
                    if(!$loginSession){
                        $loginSession = new LoginSession();
                        $loginSession->user_id = $user->id;
                    }
                    $loginSession->login_at = Carbon::now();
                    $loginSession->mac_address = $meta['MAC'];
                    $loginSession->ip_address = $meta['wifiIP'];
                    $loginSession->manufacturer = $meta['manufacturer'];
                    $loginSession->model = $meta['model'];
                    $loginSession->sdk = $meta['sdk'];
                    $loginSession->brand = $meta['brand'];
                    $loginSession->save();
                }catch (\Throwable $th) {
                    Log::error('ERROR inside register() during meta record insertion');
                    Log::error('ERROR: ' .$th->getMessage());
                }


                $user->save();
                $user->assignRole('Customer');
                Log::info('Assigned Role: Customer');

                Log::info('Sending OTP to PhoneNumber' .$request->phone);
                $sms = new Sms();
                $sms->processSmsAction('OTP', $request->phone);      

                if ($user->default_address_id !== 0) {
                    $default_address = \App\Address::where('id', $user->default_address_id)->get(['address', 'house', 'latitude', 'longitude', 'tag'])->first();
                } else {
                    $default_address = null;
                }

                Log::info('FIREBASE_PUSH_TOKEN' .$request->pushToken);
                if($request->pushToken){
                    $this->saveToken($user->id, $request->pushToken);
                    Log::info('Push Token Saved successfully');
                }


                $response = [
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'auth_token' => $token,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'default_address_id' => $user->default_address_id,
                        'default_address' => $default_address,
                        'delivery_pin' => $user->delivery_pin,
                        'wallet_balance' => $user->balanceFloat,
                    ],
                    'running_order' => null,
                ];
            } else {
                throw new AuthenticationException(ErrorCode::REGISTRATION_NOT_POSSIBLE, "Registration not possible");
            }
        } catch (\Throwable $th) {
            Log::error('ERROR inside register()');
            Log::error('ERROR: ' .$th->getMessage());
            $response = ['success' => false, 'message' => 'Couldnt register user.'];
            return response()->json($response, 400);
        }

        return response()->json($response, 201);
    }


    /**
     * @param Request $request
     */
    public function verifyPhone(Request $request){        
        if($request->phone != null && strlen($request->phone) == 10){
            $user = \App\User::where('phone', $request->phone)->get()->first();
            if($user){                
                // Check how many time request for login, if tried more then 5 times, automatically block the user for the day
                // think some better approach for the blocking logic
                // if($this->isMaximumAttempt($user)){
                //     throw new AuthenticationException(ErrorCode::MAXIMUM_ATTEMPT_REACH, "Maximum attempt reached, user is temporarily blocked");
                // }
                
                if($user->is_active){  
                    // Send the otp:
                    $sms = new Sms();
                    $sms->processSmsAction('OTP', $request->phone);      

                    return response()->json([
                        'success' => true,
                        'message' => " OTP send successfully to ##" .$request->phone. "##",
                        'code'=> '200',
                    ]);
                }else{
                    throw new AuthenticationException(ErrorCode::ACCOUNT_BLOCKED, "User blocked");
                }
            }else{
                throw new AuthenticationException(ErrorCode::PHONE_NOT_EXIST, "user not exist, please register first");
            }
        }else{
            if(!$request->phone){
                throw new AuthenticationException(ErrorCode::INVALID_REQUEST_BODY, "Invalid request body, phone number should not be null");
            } 
            if(strlen($request->phone) != 10){
                throw new AuthenticationException(ErrorCode::INVALID_REQUEST_BODY, "Invalid phone number, phone number must be of 10 digits");
            }
            throw new AuthenticationException(ErrorCode::INVALID_REQUEST_BODY, "Something error happened");           
        }

    }


    /**
    * @param Request $request
    */
    public function updateUserInfo(Request $request)
    {
        $user = auth()->user();

        if ($user) {            
            $user['wallet_balance'] = $user->balanceFloat;


            if ($user->default_address_id !== 0) {
                $default_address = \App\Address::where('id', $user->default_address_id)->get(['address', 'house', 'latitude', 'longitude', 'tag'])->first();
            } else {
                $default_address = null;
            }



            if($request->unique_order_id){
                $running_orders = \App\Order::where('user_id', $user->id)
                    ->whereIn('orderstatus_id', ['1', '2', '3', '4', '5', '6', '7', '8'])
                    ->where('unique_order_id', $request->unique_order_id)
                    ->with('orderitems', 'restaurant')
                    //->first();
                    ->get();
            }else{
                $running_orders = \App\Order::where('user_id', $user->id)
                ->whereIn('orderstatus_id', ['1', '2', '3', '4', '7', '8'])
                ->with('orderitems', 'restaurant')
                ->get();
            }

           


            if (count($running_orders) > 0){
                foreach($running_orders as $running_order){
                    $delivery_details = null;
                    if ($running_order) {
                        if ($running_order->orderstatus_id == 3 || $running_order->orderstatus_id == 4) {
                            //get assigned delivery guy and get the details to show to customers
                            $delivery_guy = AcceptDelivery::where('order_id', $running_order->id)->first();
                            if ($delivery_guy) {
                                $delivery_user = User::where('id', $delivery_guy->user_id)->first();
                                $delivery_details = $delivery_user->delivery_guy_detail;
                                if (!empty($delivery_details)) {
                                    $delivery_details = $delivery_details->toArray();
                                    $delivery_details['phone'] = $delivery_user->phone;
                                }
                            }
                            $running_order['delivery_details'] = $delivery_details;
                        }
                    }
               }
            }     


            $response = [
                'success' => true,
                'data' => [
                    'user'=>$user,
                    'running_orders' => $running_orders,
                ],
                
                //'delivery_details' => $delivery_details,
            ];

            return response()->json($response);
        }

    }

    /**
     * @param Request $request
     */
    public function checkRunningOrder(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            $running_order = \App\Order::where('user_id', $user->id)
                ->whereIn('orderstatus_id', ['1', '2', '3', '4', '7'])
                ->get();

            if (count($running_order) > 0) {
                $success = true;
                return response()->json($success);
            } else {
                $success = false;
                return response()->json($success);
            }
        }

    }

    /**
 * @param $provider
 * @param $accessToken
 */
    public function validateAccessToken($email, $provider, $accessToken)
    {
        if ($provider == 'facebook') {
            // validate facebook access token
            $curl = Curl::to('https://graph.facebook.com/app/?access_token=' . $accessToken)->get();
            $curl = json_decode($curl);

            if (isset($curl->id)) {
                if ($curl->id == config('settings.facebookAppId')) {
                    return true;
                }
                return false;
            }
            return false;

        }
        if ($provider == 'google') {
            // validate google access token
            $curl = Curl::to('https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=' . $accessToken)->get();
            $curl = json_decode($curl);

            if (isset($curl->email)) {
                if ($curl->email == $email) {
                    return true;
                }
                return false;
            }
            return false;
        }
    }

    /**
     * @param Request $request
     */
    public function getWalletTransactions(Request $request)
    {
        $user = auth()->user();
        // $user = auth()->user();
        if ($user) {
            // $balance = sprintf('%.2f', $user->balanceFloat);
            $balance = $user->balanceFloat;
            $transactions = $user->transactions()->orderBy('id', 'DESC')->get();

            $data = [
                'balance' => $balance,
                'transactions' => $transactions
            ];

            $response = [
                'success' => true,
                'data' => $data,
            ];
            return response()->json($response);
        } else {
            $response = [
                'success' => false,
            ];
            return response()->json($response);
        }
    }

    /**
     * @param Request $request
     */
    public function changeAvatar(Request $request)
    {
        $user = auth()->user();
        $user->avatar = $request->avatar;
        $user->save();
        return response()->json(['success' => true]);
    }

    /**
     * @param Request $request
     */
    public function checkBan(Request $request)
    {
        $user = auth()->user();
        if ($user->is_active) {
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }

    /**
     * @param Request $request
     */
    private function isMaximumAttempt($user){
        return true;
    }
};
