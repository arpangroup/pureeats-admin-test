<?php

namespace App\Http\Controllers;

use App\Coupon;
use App\Order;
use App\Restaurant;
use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

use ErrorCode;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;

class CouponController extends Controller
{

    
    private function validateApplyCouponRequest($request){
        if($request->coupon && $request->restaurant_id && $request->subtotal && $request->payment_method){
            return true;
        }else{
            if(!$request->coupon)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "email should not be null");
            if(!$request->restaurant_id)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "phone should not be null");
            if(!$request->subtotal)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "name should not be null");
            if(!$request->payment_method)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "name should not be null");
        }

    }


    /**
     * @param Request $request
     */
    public function applyCoupon(Request $request)//coupon,restaurant_id,subtotal
    {
        $user = Auth::user();
        if (!$user) throw new AuthenticationException(ErrorCode::INVALID_AUTH_TOKEN, "User is not LoggedIn");
        $this->validateApplyCouponRequest($request);
        

        $coupon = Coupon::where('code', $request->coupon)->first();

        if ($coupon && $coupon->is_active) {

            //check if coupon belongs to the restaurant
            if (in_array($request->restaurant_id, $coupon->restaurants()->pluck('restaurant_id')->toArray())) {
                //check if expirty date is correct
                if ($coupon->expiry_date->gt(Carbon::now()) && $coupon->count < $coupon->max_count) {
                    //check if min-subtotal is proper
                    
                    if ($request->subtotal >= $coupon->min_subtotal) {
                        //get user orders
                        $userOrderCount = count($user->orders);
                        
                        if ($coupon->user_type == 'ONCE') {
                            $orderAlreadyPlacedWithCoupon = Order::where('user_id', $user->id)->where('coupon_name', $coupon->code)->first();
                            if ($orderAlreadyPlacedWithCoupon) {
                                throw new ValidationException(ErrorCode::INVALID_COUPON, "This coupon can only be used once per one user");
                            }
                        }
                        if ($coupon->user_type == 'ONCENEW') {
                            if ($userOrderCount != 0) {
                                throw new ValidationException(ErrorCode::INVALID_COUPON, "This coupon can only be used for first order");                                
                            }
                        }
                        if ($coupon->user_type == 'CUSTOM') {
                            $orderAlreadyPlacedWithCoupon = Order::where('user_id', $user->id)->where('coupon_name', $coupon->code)->get()->count();
                            if ($orderAlreadyPlacedWithCoupon >= $coupon->max_count_per_user) {
                                throw new ValidationException(ErrorCode::INVALID_COUPON, "Max limit reached for this coupon");                                
                            }
                        }
                        
                        $response = [
                            'success' => true,
                            'message' => $request->coupon .' is applicable',
                            'data' => $coupon,
                        ];
                        return response()->json($response);
                    } else {
                        throw new ValidationException(ErrorCode::INVALID_COUPON, $coupon->subtotal_message);
                    }
                } else {
                    if(!$coupon->expiry_date->gt(Carbon::now()))throw new ValidationException(ErrorCode::INVALID_COUPON, "Coupon Expired");
                    if($coupon->count >= $coupon->max_count)throw new ValidationException(ErrorCode::INVALID_COUPON, "Maximum count reached");
                    throw new ValidationException(ErrorCode::INVALID_COUPON, "Invalid Coupon");
                }
            } else {
                throw new ValidationException(ErrorCode::INVALID_COUPON, $request->coupon ."not exist in this restaurantID: ".$request->restaurant_id);
            }
        } else {
            if(!$coupon)throw new ValidationException(ErrorCode::INVALID_COUPON, "Invalid Coupon " .$request->coupon);
            if($coupon->is_active == 0)throw new ValidationException(ErrorCode::INVALID_COUPON,  $request->coupon." not active");
        }
    }






    /**
     * @param Request $request
     */
    public function applyCouponOriginal(Request $request)//coupon,restaurant_id,subtotal
    {
        $user = Auth::user();
        if (!$user) {
            $response = [
                'success' => false,
                'type' => 'NOTLOGGEDIN',
            ];
            return response()->json($response);
        }

        $coupon = Coupon::where('code', $request->coupon)->first();

        if ($coupon && $coupon->is_active) {

            //check if coupon belongs to the restaurant
            if (in_array($request->restaurant_id, $coupon->restaurants()->pluck('restaurant_id')->toArray())) {
                //check if expirty date is correct
                if ($coupon->expiry_date->gt(Carbon::now()) && $coupon->count < $coupon->max_count) {
                    //check if min-subtotal is proper
                    if ($request->subtotal >= $coupon->min_subtotal) {
                        //get user orders
                        $userOrderCount = count($user->orders);

                        if ($coupon->user_type == 'ONCE') {
                            $orderAlreadyPlacedWithCoupon = Order::where('user_id', $user->id)->where('coupon_name', $coupon->code)->first();
                            if ($orderAlreadyPlacedWithCoupon) {
                                $response = [
                                    'success' => false,
                                    'type' => 'ALREADYUSEDONCE',
                                    'message' => 'This coupon can only be used once per one user',
                                ];
                                return response()->json($response);
                            }
                        }
                        if ($coupon->user_type == 'ONCENEW') {
                            if ($userOrderCount != 0) {
                                $response = [
                                    'success' => false,
                                    'type' => 'FORNEWUSER',
                                    'message' => 'This coupon can only be used for first order',
                                ];
                                return response()->json($response);
                            }
                        }
                        if ($coupon->user_type == 'CUSTOM') {
                            $orderAlreadyPlacedWithCoupon = Order::where('user_id', $user->id)->where('coupon_name', $coupon->code)->get()->count();
                            if ($orderAlreadyPlacedWithCoupon >= $coupon->max_count_per_user) {
                                $response = [
                                    'success' => false,
                                    'type' => 'MAXLIMITREACHEDPERUSER',
                                    'message' => 'Max limit reached for this coupon',
                                ];
                                return response()->json($response);
                            }
                        }
                        $coupon->success = true;
                        return response()->json($coupon);
                    } else {
                        $response = [
                            'success' => false,
                            'type' => 'MINSUBTOTAL',
                            'message' => $coupon->subtotal_message,
                        ];
                        return response()->json($response);
                    }

                } else {
                    $response = [
                        'success' => false,
                    ];
                    return response()->json($response);
                }
            } else {
                $response = [
                    'success' => false,
                ];
                return response()->json($response);
            }
        } else {
            $response = [
                'success' => false,
            ];
            return response()->json($response);
        }
    }

    public function coupons()
    {
        $coupons = Coupon::orderBy('id', 'DESC')->get();
        $restaurants = Restaurant::all();
        $todaysDate = Carbon::now()->format('m-d-Y');
        return view('admin.coupons', array(
            'coupons' => $coupons,
            'restaurants' => $restaurants,
            'todaysDate' => $todaysDate,
        ));
    }


    /**
     * @param Request $request
     */
    public function getAllCoupons(Request $request)
    {
        if($request->restaurant_id){
            $coupons = Restaurant::where('id', $request->restaurant_id)->first()->coupons;
        }else{
            $coupons = Coupon::orderBy('id', 'DESC')->with('restaurants')->get();
        }
        return $coupons;
    }

    
    /**
     * @param Request $request
     */
    public function saveNewCoupon(Request $request)
    {
        // dd($request->all());
        $coupon = new Coupon();

        $coupon->name = $request->name;
        $coupon->description = $request->description;
        $coupon->code = $request->code;
        $coupon->discount_type = $request->discount_type;
        $coupon->discount = $request->discount;
        $coupon->expiry_date = Carbon::parse($request->expiry_date)->format('Y-m-d H:i:s');
        // $coupon->restaurant_id = $request->restaurant_id;

        $coupon->max_count = $request->max_count;

        $coupon->min_subtotal = $request->min_subtotal == null ? 0 : $request->min_subtotal;
        if ($request->discount_type == 'PERCENTAGE') {
            $coupon->max_discount = $request->max_discount;
        } else {
            $coupon->max_discount = null;
        }
        $coupon->subtotal_message = $request->subtotal_message;

        if ($request->is_active == 'true') {
            $coupon->is_active = true;
        } else {
            $coupon->is_active = false;
        }

        $coupon->user_type = $request->user_type;
        if ($request->user_type == 'CUSTOM') {
            $coupon->max_count_per_user = $request->max_count_per_user;
        }

        try {
            $coupon->save();
            $coupon->restaurants()->sync($request->restaurant_id);
            return redirect()->back()->with(['success' => 'Coupon Updated']);
        } catch (\Illuminate\Database\QueryException $qe) {
            return redirect()->back()->with(['message' => $qe->getMessage()]);
        } catch (Exception $e) {
            return redirect()->back()->with(['message' => $e->getMessage()]);
        } catch (\Throwable $th) {
            return redirect()->back()->with(['message' => $th]);
        }
    }

    /**
     * @param $id
     */
    public function getEditCoupon($id)
    {
        $coupon = Coupon::where('id', $id)->first();
        $restaurants = Restaurant::all();
        if ($coupon) {
            return view('admin.editCoupon', array(
                'coupon' => $coupon,
                'restaurants' => $restaurants,
            ));
        }
        return redirect()->route('admin.coupons');
    }

    /**
     * @param Request $request
     */
    public function updateCoupon(Request $request)
    {
        $coupon = Coupon::where('id', $request->id)->first();

        if ($coupon) {

            $coupon->name = $request->name;
            $coupon->description = $request->description;
            $coupon->code = $request->code;
            $coupon->discount_type = $request->discount_type;
            $coupon->discount = $request->discount;
            $coupon->expiry_date = Carbon::parse($request->expiry_date)->format('Y-m-d H:i:s');
            // $coupon->restaurant_id = $request->restaurant_id;
            $coupon->max_count = $request->max_count;

            $coupon->min_subtotal = $request->min_subtotal == null ? 0 : $request->min_subtotal;

            if ($request->discount_type == 'PERCENTAGE') {
                $coupon->max_discount = $request->max_discount;
            } else {
                $coupon->max_discount = null;
            }
            $coupon->subtotal_message = $request->subtotal_message;

            if ($request->is_active == 'true') {
                $coupon->is_active = true;
            } else {
                $coupon->is_active = false;
            }

            if ($request->is_exclusive == 'true') {
                $coupon->is_exclusive = true;
            } else {
                $coupon->is_exclusive = false;
            }

            $coupon->user_type = $request->user_type;
            if ($request->user_type == 'CUSTOM') {
                $coupon->max_count_per_user = $request->max_count_per_user;
            }

            try {
                $coupon->save();
                $coupon->restaurants()->sync($request->restaurant_id);
                return redirect()->back()->with(['success' => 'Coupon Updated']);
            } catch (\Illuminate\Database\QueryException $qe) {
                return redirect()->back()->with(['message' => $qe->getMessage()]);
            } catch (Exception $e) {
                return redirect()->back()->with(['message' => $e->getMessage()]);
            } catch (\Throwable $th) {
                return redirect()->back()->with(['message' => $th]);
            }
        }
    }

    /**
     * @param $id
     */
    public function deleteCoupon($id)
    {
        $coupon = Coupon::where('id', $id)->first();

        if ($coupon) {
            $coupon->delete();
            return redirect()->back()->with(['success' => 'Coupon Deleted']);
        }
        return redirect()->route('admin.coupons');
    }
}
