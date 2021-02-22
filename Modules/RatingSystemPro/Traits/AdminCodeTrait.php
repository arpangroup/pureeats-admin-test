<?php


namespace Modules\RatingSystemPro\Traits;

use App\Setting;
use App\Translation;
use Modules\SuperCache\SuperCache;
use Nwidart\Modules\Facades\Module;
use Cache;

trait AdminCodeTrait
{
    /**
     * Run Admin Script
     * @return []
     */
    public static function runAdminScript()
    {
        self::addRelationshipCode();
        self::addisRatingCode();
        self::addisRatingStoreCode();
        self::runTranslateKey();
        self::addCodeInTranslate();
        self::replaceCode();
        self::addMenuAdminOption();
        self::addCodeInRating();
        self::addCodeInRatingLabel();
        self::addCodeInRatingLabelEdit();
        self::addCodeInRatingEdit();
    }

    /**
     * Add Code In Label Rating Edit
     * @return []
     */
    private static function addCodeInRatingLabelEdit()
    {
        $file = base_path('resources/views/admin/editRestaurant.blade.php');
        $replacement = ' <label class="col-lg-3 col-form-label">Rating:</label>
        <!-- EndRatingAdminLabelEdit -->';

        if (strpos(file_get_contents($file), '<!-- EndRatingAdminLabelEdit -->') === false) {
            self::replaceContentByText($file, $replacement, '<label class="col-lg-3 col-form-label"><span class="text-danger">*</span>Rating:</label>', true);
        }
    }

    /**
     * Add Code In Translate
     * @return []
     */
    private static function addCodeInRatingEdit()
    {
        $file = base_path('resources/views/admin/editRestaurant.blade.php');
        $replacement = ' placeholder="0" readonly>
        <!-- EndRatingAdminEdit -->';

        if (strpos(file_get_contents($file), '<!-- EndRatingAdminEdit -->') === false) {
            self::replaceContentByText($file, $replacement, 'placeholder="Rating from 1-5" required>', true);
        }
    }


    /**
     * Add Code In Label Rating
     * @return []
     */
    private static function addCodeInRatingLabel()
    {
        $file = base_path('resources/views/admin/restaurants.blade.php');
        $replacement = '
        <label class="col-lg-3 col-form-label">Rating:</label>
        <!-- EndRatingAdminLabel -->';

        if (strpos(file_get_contents($file), '<!-- EndRatingAdminLabel -->') === false) {
            self::replaceContentByText($file, $replacement, '<label class="col-lg-3 col-form-label"><span class="text-danger">*</span>Rating:</label>', true);
        }
    }

    /**
     * Add Code In Translate
     * @return []
     */
    private static function addCodeInRating()
    {
        $file = base_path('resources/views/admin/restaurants.blade.php');
        $replacement = '
        placeholder="0" readonly>
        <!-- EndRatingAdmin -->';

        if (strpos(file_get_contents($file), '<!-- EndRatingAdmin -->') === false) {
            self::replaceContentByText($file, $replacement, 'placeholder="Rating from 1-5" required>', true);
        }
    }

    /**
     * Add Menu Admin Option
     * Adicionar o menu reports na área do loja
     *
     * @return []
     */
    private static function addMenuAdminOption()
    {
        $file       = base_path('resources/views/admin/includes/header.blade.php');
        $pattern    = '/<a\shref="{{\sroute\("admin.viewTopItems"\)\s}}"\s/i';
        $replace    = '<!-- menuadminRatingSystemPro -->
                @if(\Module::find("RatingSystemPro")->isEnabled())
                    <a href="{{ url("ratingsystempro/reports") }}" class="dropdown-item {{ Request::is(\'ratingsystempro/reports\') ? \'active\' : \'\' }}"> 
                    <i class="icon-graph mr-2"></i>
                        {{ @trans(\'ratingsystempro::default.reports\') }} 
                    </a>
                @endif<!-- endmenuadminRatingSystemPro -->  
            <a href="{{ route("admin.viewTopItems") }}" ';

        if(strpos(file_get_contents($file),'<!-- menuadminRatingSystemPro -->') === false) {
            self::replaceContent($file, $pattern, $replace);
        }
    }

    /**
     * Add Code In Translate
     * @return []
     */
    private static function addCodeInTranslate()
    {
        $file = base_path('resources/views/admin/editTranslation.blade.php');
        $replacement = '<!-- END OfflineMode Screen Setting --> 
        <!-- moduleAdminTranslateRatingSystemPro --> 
        <div class="module">
                    <button class="btn btn-primary translation-section-btn mt-4" type="button"> <i class="icon-mobile mr-1"></i>Rating System Pro</button>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label"><strong>Restaurant Rating</strong></label>
                        <div class="col-lg-9">
                            <input type="text" class="form-control form-control-lg" name="RestaurantRating"
                                   value="@if (!empty($data->RestaurantRating)) {{ $data->RestaurantRating }}@else{{ config(\'settings.RestaurantRating\') }}@endif"
                                   placeholder="Restaurant Rating">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label"><strong>Text qualification for restaurant</strong></label>
                        <div class="col-lg-9">
                            <input type="text" class="form-control form-control-lg" name="LeaveQualification"
                                   value="@if (!empty($data->LeaveQualification)) {{ $data->LeaveQualification }}@else{{ config(\'settings.LeaveQualification\') }}@endif"
                                   placeholder="Leave a qualification for the restaurant below">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label"><strong>Text qualification for Delivery Guy</strong></label>
                        <div class="col-lg-9">
                            <input type="text" class="form-control form-control-lg" name="LeaveQualificationDeliveryGuy"
                                   value="@if (!empty($data->LeaveQualificationDeliveryGuy)) {{ $data->LeaveQualificationDeliveryGuy }}@else{{ config(\'settings.LeaveQualificationDeliveryGuy\') }}@endif"
                                   placeholder="Leave a qualification for the Delivery Guy below">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label"><strong>Delivery Guy Rating</strong></label>
                        <div class="col-lg-9">
                            <input type="text" class="form-control form-control-lg" name="DeliveryGuyRating"
                                   value="@if (!empty($data->DeliveryGuyRating)) {{ $data->DeliveryGuyRating }}@else{{ config(\'settings.DeliveryGuyRating\') }}@endif"
                                   placeholder="Delivery Guy Rating">
                        </div>
                    </div>       
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label"><strong>SAVE</strong></label>
                        <div class="col-lg-9">
                            <input type="text" class="form-control form-control-lg" name="SAVE"
                                   value="@if (!empty($data->SAVE)) {{ $data->SAVE }}@else{{ config(\'settings.SAVE\') }}@endif"
                                   placeholder="SAVE">
                        </div>
                    </div>                 
                </div>
        <!-- endmoduleAdminControllerRatingSystemPro --> ';

        if (strpos(file_get_contents($file), '<!-- moduleAdminTranslateRatingSystemPro -->') === false) {
            self::replaceContentByText($file, $replacement, '<!-- END OfflineMode Screen Setting -->', true);
        }
    }

    /**
     * Add Code Admin Controller
     * Adicionando o relacionamento de Reting
     * @return []
     */
    private static function addisRatingCode()
    {
        $file = base_path('app/Order.php');
        $replacement = 'return $this->hasOne(\'App\AcceptDelivery\');
        }
                
        /*addisRatingCode*/
        protected $appends = [\'isRating\', \'isRatingDeliveryGuy\'];
    
        public function getIsRatingAttribute()
        {
            if(!auth()->check()) {
                return false;
            }
    
            return !empty(\Modules\RatingSystemPro\Entities\RatingStore::where(\'order_id\', $this->id)->where(\'restaurant_id\', $this->restaurant_id)->where(\'user_id\', auth()->user()->id)->first());
        }
    
        public function getIsRatingDeliveryGuyAttribute()
        {
            if(!auth()->check()) {
                return false;
            }
    
            if(isset($this->accept_delivery->user_id)) {
                return !empty(\Modules\RatingSystemPro\Entities\RatingDeliveryGuy::where(\'order_id\', $this->id)->where(\'delivery_guy_id\', $this->accept_delivery->user_id)->where(\'user_id\', auth()->user()->id)->first());
            }';

        if(strpos(file_get_contents($file),'/*addisRatingCode*/') === false) {
            self::replaceContentByText($file,  $replacement, 'return $this->hasOne(\'App\AcceptDelivery\');', true);
        }
    }

    /**
     * Add Code Admin Controller
     * Adicionando o relacionamento de Reting
     * @return []
     */
    private static function addRelationshipCode()
    {
        $file = base_path('app/Restaurant.php');
        $replacement = '/*addRelationshipCode*/ 
        public function ratings()
        {
            return $this->belongsToMany(\Modules\RatingSystemPro\Entities\RatingStore::class);
        }
        /*endaddRelationshipCode*/';

        if(strpos(file_get_contents($file),'/*addRelationshipCode*/') === false) {
            self::replaceContentByText($file,  $replacement, 'public function payment_gateways()');
        }
    }

    /**
     * Add Code Admin Controller
     * Adicionando o relacionamento de Reting
     * @return []
     */
    private static function addisRatingStoreCode()
    {
        $file = base_path('app/Restaurant.php');
        $replacement = ' protected $hidden = array(\'created_at\', \'updated_at\');
                
        /*addisRatingStoreCode*/
        public function getRatingAttribute()
        {
            return \Modules\RatingSystemPro\Entities\RatingStore::where(\'restaurant_id\', $this->id)->average(\'rating\');
        }
        /*endaddisRatingStoreCode*/';

        if(strpos(file_get_contents($file),'/*addisRatingStoreCode*/') === false) {
            self::replaceContentByText($file,  $replacement, 'protected $hidden = array(\'created_at\', \'updated_at\');', true);
        }
    }

    /**
     * Run Translate Key
     * @return []
     */
    private static function runTranslateKey()
    {
        $translations = Translation::get();
        if(count($translations) > 0) {
            foreach($translations as $translation) {
                $data = json_decode($translation->data, true);
                foreach (self::getKeys() as $key => $value) {
                    if(!array_key_exists( $key, $data)) {
                        array_push($data, [$key => $value]);
                    }
                }
                $translation->data = json_encode($data);
                $translation->save();
            }
        }
    }

    /**
     * Get Keys
     * @return string[]
     */
    private static function getKeys()
    {
        return [
            'RestaurantRating' => 'Restaurant Rating',
            'LeaveQualification' => 'Leave a qualification for the restaurant below',
            'SAVE' => 'SAVE',
            'LeaveQualificationDeliveryGuy' => 'Leave a qualification for the Delivery Guy below',
            'DeliveryGuyRating' => 'Delivery Guy Rating',
        ];
    }

    /**
     * Replace Code
     * @return []
     */
    private static function replaceCode()
    {
        $file = base_path('app/Http/Controllers/SettingController.php');
        $replacement = ',
            /*moduleSettingControllerRatingSystemPro*/ 
            \'RestaurantRating\',
            \'LeaveQualification\',
            \'SAVE\',
            \'LeaveQualificationDeliveryGuy\',
            \'DeliveryGuyRating\',
            /*endmoduleSettingControllerRatingSystemPro*/
        ])->get([\'key\', \'value\']);';

        if (strpos(file_get_contents($file), '/*moduleSettingControllerRatingSystemPro*/') === false) {
            self::replaceContentByText($file, $replacement, '])->get([\'key\', \'value\']);', true);
        }

        Cache::forget('app-settings');
    }
}
