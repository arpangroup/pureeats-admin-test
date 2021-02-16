<!-- <link href="{{substr(url("/"), 0, strrpos(url("/"), '/'))}}/Modules/DeliveryAreaPro/Resources/assets/app.css" rel="stylesheet" type="text/css"> -->
<link href="{{url("/")}}/assets/Modules/DeliveryAreaPro/Resources/assets/app.css" rel="stylesheet" type="text/css">
<script src="https://polyfill.io/v3/polyfill.min.js?features=default"></script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('settings.googleApiKeyNoRestriction') }}&callback=initMap&libraries=drawing,places&v=weekly" defer ></script>