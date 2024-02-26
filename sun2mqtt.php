<?php

require __DIR__ . '/vendor/autoload.php';


use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

//Enable asynchronous signal handling
pcntl_async_signals(true);

//turn off output buffering
ob_end_flush();


function logger($message){
    echo (new DateTime())->format(DATE_ATOM).' : '.$message.PHP_EOL;
}

function publish(MqttClient $mqtt, $topic, $payload){
    logger('Publish => topic: "'.$topic.'"; payload: "'.$payload.'"');
    $mqtt->publish($topic,$payload);
}


function publishSunriseSunset(MqttClient $mqtt, $prefix, $latitude, $longitude) {

    $todaySunInfo = date_sun_info(time(), $latitude, $longitude);

    //TODO check for false value if date_sun_info didn't work

    $sunriseAstronomic=date("Hi", $todaySunInfo["astronomical_twilight_begin"]);
    $sunriseNautic=date("Hi", $todaySunInfo["nautical_twilight_begin"]);
    $sunriseCivil=date("Hi", $todaySunInfo["civil_twilight_begin"]);
    $sunrise=date("Hi", $todaySunInfo["sunrise"]);
    $sunset=date("Hi", $todaySunInfo["sunset"]);
    $sunsetCivil=date("Hi", $todaySunInfo["civil_twilight_end"]);
    $sunsetNautic=date("Hi", $todaySunInfo["nautical_twilight_end"]);
    $sunsetAstronomic=date("Hi", $todaySunInfo["astronomical_twilight_end"]);

    $transit = date("Hi", $todaySunInfo['transit']);

    $interval = $todaySunInfo["sunset"]-$todaySunInfo["sunrise"];
    $daylen = round($interval/60);
    

    publish($mqtt, $prefix.'/sun/astronomicsunrise',$sunriseAstronomic);
    publish($mqtt, $prefix.'/sun/nauticsunrise',$sunriseNautic);
    publish($mqtt, $prefix.'/sun/civilsunrise',$sunriseCivil);
    publish($mqtt, $prefix.'/sun/sunrise',$sunrise);
    publish($mqtt, $prefix.'/sun/sunset',$sunset);
    publish($mqtt, $prefix.'/sun/civilsunset',$sunsetCivil);
    publish($mqtt, $prefix.'/sun/nauticsunset',$sunsetNautic);
    publish($mqtt, $prefix.'/sun/astronomicsunset',$sunsetAstronomic);
    
    publish($mqtt, $prefix.'/sun/transit',$transit);
    publish($mqtt, $prefix.'/sun/daylength',$daylen);
}


// Return altitude correction for altitude due to atmospheric refraction.
// http://en.wikipedia.org/wiki/Atmospheric_refraction
function correctForRefraction($d) {
    if (!($d > -0.5))      $d = -0.5;  // Function goes ballistic when negative.
    return (0.017/tan(deg2rad($d + 10.3/($d+5.11))));
}

// Return the right ascension of the sun at Unix epoch t.
// http://bodmas.org/kepler/sun.html
function sunAbsolutePositionDeg($t) {
    $dSec = $t - 946728000;
    $meanLongitudeDeg = fmod((280.461 + 0.9856474 * $dSec/86400),360);
    $meanAnomalyDeg = fmod((357.528 + 0.9856003 * $dSec/86400),360);
    $eclipticLongitudeDeg = $meanLongitudeDeg + 1.915 * sin(deg2rad($meanAnomalyDeg)) + 0.020 * sin(2*deg2rad($meanAnomalyDeg));
    $eclipticObliquityDeg = 23.439 - 0.0000004 * $dSec/86400;
    $sunAbsY = cos(deg2rad($eclipticObliquityDeg)) * sin(deg2rad($eclipticLongitudeDeg));
    $sunAbsX = cos(deg2rad($eclipticLongitudeDeg));
    $rightAscensionRad = atan2($sunAbsY, $sunAbsX);
    $declinationRad = asin(sin(deg2rad($eclipticObliquityDeg))*sin(deg2rad($eclipticLongitudeDeg)));
    return array(rad2deg($rightAscensionRad), rad2deg($declinationRad));
}

// Convert an object's RA/Dec to altazimuth coordinates.
// http://answers.yahoo.com/question/index?qid=20070830185150AAoNT4i
// http://www.jgiesen.de/astro/astroJS/siderealClock/

function absoluteToRelativeDeg($t, $rightAscensionDeg, $declinationDeg, $latitude, $longitude){
    $longitude = (float) $longitude;
    $latitude = (float) $latitude;
    $dSec = $t - 946728000;
    $midnightUtc = $dSec - fmod($dSec,86400);
    $siderialUtcHours = fmod((18.697374558 + 0.06570982441908*$midnightUtc/86400 + (1.00273790935*(fmod($dSec,86400))/3600)),24);
    $siderialLocalDeg = fmod((($siderialUtcHours * 15) + $longitude),360);
    $hourAngleDeg = fmod(($siderialLocalDeg - $rightAscensionDeg),360);
    $altitudeRad = asin(sin(deg2rad($declinationDeg))*sin(deg2rad($latitude)) + cos(deg2rad($declinationDeg)) * cos(deg2rad($latitude)) * cos(deg2rad($hourAngleDeg)));
    $azimuthY = -cos(deg2rad($declinationDeg)) * cos(deg2rad($latitude)) * sin(deg2rad($hourAngleDeg));
    $azimuthX = sin(deg2rad($declinationDeg)) - sin(deg2rad($latitude)) * sin($altitudeRad);
    $azimuthRad = atan2($azimuthY, $azimuthX);
    return array(rad2deg($azimuthRad), rad2deg($altitudeRad));
}

function publishSunPosition(MqttClient $mqtt, $prefix, $latitude, $longitude) {

    $t = time();
    list($rightAscension,$declination)=sunAbsolutePositionDeg($t);
    list($azimuth, $elevation) = absoluteToRelativeDeg($t, $rightAscension, $declination, $latitude, $longitude);
    if ($azimuth < 0) $azimuth += 360;
    $elevation=$elevation+correctForRefraction($elevation);

    publish($mqtt, $prefix.'/sunposition/elevation', round($elevation, 1));
    publish($mqtt, $prefix.'/sunposition/azimuth', round($azimuth, 1));
    
    $todaySunInfo = date_sun_info($t, $latitude, $longitude);
    publish($mqtt, $prefix.'/sunposition/daytime', ($todaySunInfo["sunrise"] < $t && $t < $todaySunInfo["sunset"])? 1 : 0 );
}

//--------------------------------------------------------------------
//------------------------------- MAIN -------------------------------
//--------------------------------------------------------------------

$versionnumber='1.0.2';

echo sprintf('===== sun2mqtt v%s =====',$versionnumber).PHP_EOL;

$timezone = $_ENV["TZ"] ?? "Europe/Paris";
$latitude = $_ENV["LATITUDE"] ?? "48.85826";
$longitude = $_ENV["LONGITUDE"] ?? "2.29451";
// $altitude = $_ENV["ALTITUDE"] ?? "65";
$publishHour = $_ENV["PUBLISHHOUR"] ?? 3;


$mqttprefix = $_ENV["PREFIX"] ?? "sun2mqtt";
$mqtthost = $_ENV["HOST"] ?? "127.0.0.1";
$mqttport = $_ENV["PORT"] ?? 1883;
$mqttclientid = $_ENV["CLIENTID"] ?? "sun2mqtt";
$mqttuser = $_ENV["USER"] ?? null;
$mqttpassword = $_ENV["PASSWORD"] ?? null;

//Set up Timzezone for date/time php functions
if (!date_default_timezone_set($timezone)){
    echo 'Wrong TimeZone : '.$timezone.PHP_EOL.'Exit';
    return;
}

echo '===== Prepare MQTT Client ====='.PHP_EOL;
$mqtt = new MqttClient($mqtthost, $mqttport, $mqttclientid);

$shutdown = function (int $signal, $info) use ($mqtt, $mqttprefix) {
    echo PHP_EOL;
    logger('Exit');
    $mqtt->publish($mqttprefix.'/connected', '0', 0, true);
    $mqtt->interrupt();
};

pcntl_signal(SIGINT, $shutdown);
pcntl_signal(SIGTERM, $shutdown);


$connectionSettings = new ConnectionSettings();

//Configure Testament
$connectionSettings->setLastWillTopic($mqttprefix.'/connected');
$connectionSettings->setLastWillMessage('0');
$connectionSettings->setRetainLastWill(true);

//if there is username or password
if($mqttuser || $mqttpassword){
    if($mqttuser) $connectionSettings->setUsername($mqttuser);
    if($mqttpassword) $connectionSettings->setPassword($mqttpassword);
}

//Connect
$mqtt->connect($connectionSettings);

//Publish connection state
$mqtt->publish($mqttprefix.'/connected', '1', 0, true);

echo '===== MQTT Client Connected ====='.PHP_EOL;
echo 'Now waiting for the next daily publish time => ';
if (strtotime('today '.$publishHour.':00') > strtotime('now')) echo date(DATE_ATOM, strtotime('today '.$publishHour.':00')).PHP_EOL;
else echo date(DATE_ATOM, strtotime('tomorrow '.$publishHour.':00')).PHP_EOL;

$lastDailyPublishTime = time();
$lastMinutePublishTime = time();

//DEBUG
$lastDailyPublishTime = strtotime('last month');

$loopEventHandler = function (MqttClient $mqtt, float $elapsedTime) use ($publishHour, &$lastDailyPublishTime, &$lastMinutePublishTime, $mqttprefix, $latitude, $longitude) {
    $now = time();
    $todayPublishHour = strtotime('today '.$publishHour.':00');
    $currentPublishMinute = $now - date('s');

    //if today daily publish hour is between $lastDailyPublishTime and $now, then publish
    if($lastDailyPublishTime<$todayPublishHour && $todayPublishHour<=$now){

        logger('Daily Publish Time');
        publish($mqtt, $mqttprefix.'/executionTime', date(DATE_ATOM));

        publishSunriseSunset($mqtt, $mqttprefix, $latitude, $longitude);

        $lastDailyPublishTime = $todayPublishHour;
    }

    //if current minute publish time is between $lastMinutePublishTime and $now, then publish
    if($lastMinutePublishTime<$currentPublishMinute && $currentPublishMinute<=$now){

        logger('Minute Publish Time');
        publish($mqtt, $mqttprefix.'/executionTime', date(DATE_ATOM));

        publishSunPosition($mqtt, $mqttprefix, $latitude, $longitude);

        $lastMinutePublishTime = $currentPublishMinute;
    }
};

$mqtt->registerLoopEventHandler($loopEventHandler);

$mqtt->subscribe('php-mqtt/client/test', function ($topic, $message) {
    echo sprintf("Received message on topic [%s]: %s", $topic, $message).PHP_EOL;
}, 0);
$mqtt->loop(true);
$mqtt->disconnect();

?>