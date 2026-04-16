<?php
ob_start();
require("connectdb.php");

register_shutdown_function("notify_crash_handler");
//function to redirect starts here
function redirect($new_location)
{
    header("location: " . $new_location);
    exit;
}
function getCurrentFileFullURL(){
        $protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,strpos( $_SERVER["SERVER_PROTOCOL"],'/'))).'://';
        // Get the server name and port
        $servername = $_SERVER['SERVER_NAME'];
        $port = $_SERVER['SERVER_PORT'];
        // Get the path to the current script
        $path = $_SERVER['PHP_SELF'];
        // Combine the above to form the full URL
        $endpoint = $protocol . $servername . ":" . $port . $path;
        return $endpoint;
    }
    function setTimeZoneForUser($timeZoneToUse=''){
        if(strlen($timeZoneToUse)>2){
            date_default_timezone_set($timeZoneToUse);
        }else{
            date_default_timezone_set("Africa/Lagos");
        }
    }
     function system_notify_crash_handler($message,$from){
        $botidtouse=$chatId=0;
  


        $errstr  = preg_replace('/[^A-Za-z0-9\-]/', ' ',$message);

        $keyboard= [];

        $response="@habnarm1 \n*WORK FLOW CRASH*\n\nFrom: $from\nText:$errstr";

        replyuser($chatId, "0", $response, false, $keyboard, "markdown", $botidtouse);

 }
  function get_details_from_exception(Exception $e){
        $errorMessage = sprintf(
            "Error: %s in %s on line %d",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        return $errorMessage;
    }
function input_is_invalid($data)
    {
        // Check if data is null
        if (is_null($data)) {
            return true;
        }
    
        // Trim and check for string data
        if (is_string($data)) {
            $data = trim($data);
            if (strlen($data) == 0 || empty($data)) {
                return true;
            }
        }
    
        // Check for arrays
        if (is_array($data)) {
            if (empty($data)||count($data)==0) {
                return true;
            }
            // foreach ($data as $item) {
            //     if (self::input_is_invalid($item)) {
            //         return true;
            //     }
            // }
        }
    
        // Check for objects
        if (is_object($data)) {
            if ($data instanceof \stdClass) {
                // Convert stdClass to array and check if empty
                if (empty((array)$data)) {
                    return true;
                }
            } else {
                // Handle other object types
                foreach ($data as $key => $value) {
                    if (input_is_invalid($value)) {
                        return true;
                    }
                }
            }
        }

        
    
        // For other data types, consider them invalid if they are empty
        if (empty($data)) {
            return true;
        }
    
        return false;
    }
function cleanme($whoclean)
{
    global $connect;
    $whoclean = trim($whoclean);
    $whoclean = strip_tags($whoclean);
    $whoclean = mysqli_real_escape_string($connect, $whoclean);
    return $whoclean;
}
function notify_crash_handler()
{
    global $connect;
    $errfile = "unknown file";
    $errstr = "shutdown";
    $errno = E_CORE_ERROR;
    $errline = 0;
    $error = error_get_last();
    if ($error !== NULL) {
        $adminidis = 0;
        // https://www.php.net/manual/en/errorfunc.constants.php
        $errno = $error["type"];
        if ($errno == 1) {
            $errno = "Fatal and code has stopped";
        } else if ($errno == 2) {
            $errno = "Warning but code did not stop";
        }
        $errfile = str_replace("public_html", "", $error["file"]);
        $errline = $error["line"];

        $chatId = "";
        $botidtouse = "";
        $keyboard = [];
        $response = "@habnarm1 \n*CRASH NOTIFICATION*\n\nFile: $errfile\nType:$errno\nLine:$errline\nText:$errstr";
        replyuser($chatId, "0", $response, true, $keyboard, "markdown", $botidtouse);
    }
}
function validateEmail($email)
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return true;
    } else {
        return false;
    }
}
function weekOfMonth($date)
{
    $firstOfMonth = date("Y-m-01", strtotime($date));
    return (intval(date("W", strtotime($date))) - intval(date("W", strtotime($firstOfMonth)))) + 1;
}
function getDatetimethatPasssed($endday)
{
    $todayis = date("Y-m-d");
    $earlier = new DateTime("$endday");
    $later = new DateTime("$todayis");
    $abs_diff = $later->diff($earlier)->format("%a"); //3
    return $abs_diff;
}
function getDaysPassed($vendorsubendday)
{
    $datediff = time() - $vendorsubendday;
    // $datediff =$vendorsubendday-$vendorsubstartday;//getting total days btw
    //60 is for minute
    //60 by 60 is for hr
    //60 by 60 by 24 is for days
    //any number by 60 by 60 by 24 is for months
    $difference = round($datediff / (24 * 60 * 60)); //getting days
    return $difference;
}
function getMinBetweentimes($latesttime, $oldtime)
{
    $minbtwis = 0;
    $subtractit = $latesttime - $oldtime;
    $minbtwis = round($subtractit / (60));
    //60 is for minute
    //60 by 60 is for hr
    //60 by 60 by 24 is for days
    //any number by 60 by 60 by 24 is for months
    return $minbtwis;
}
function getthe24Time($time)
{
    $data = $time;
    $date = date('H:i', $data);
    return $date;
}
function roundToTheNearestAnything($value, $roundTo)
{
    $value = floor($value);
    $mod = $value % $roundTo;
    return $value + ($mod < ($roundTo / 2) ? -$mod : $roundTo - $mod);
}
function isStringHasEmojis($string)
{
    $emojis_regex =
        '/[\x{0080}-\x{02AF}'
        . '\x{0300}-\x{03FF}'
        . '\x{0600}-\x{06FF}'
        . '\x{0C00}-\x{0C7F}'
        . '\x{1DC0}-\x{1DFF}'
        . '\x{1E00}-\x{1EFF}'
        . '\x{2000}-\x{209F}'
        . '\x{20D0}-\x{214F}'
        . '\x{2190}-\x{23FF}'
        . '\x{2460}-\x{25FF}'
        . '\x{2600}-\x{27EF}'
        . '\x{2900}-\x{29FF}'
        . '\x{2B00}-\x{2BFF}'
        . '\x{2C60}-\x{2C7F}'
        . '\x{2E00}-\x{2E7F}'
        . '\x{3000}-\x{303F}'
        . '\x{A490}-\x{A4CF}'
        . '\x{E000}-\x{F8FF}'
        . '\x{FE00}-\x{FE0F}'
        . '\x{FE30}-\x{FE4F}'
        . '\x{1F000}-\x{1F02F}'
        . '\x{1F0A0}-\x{1F0FF}'
        . '\x{1F100}-\x{1F64F}'
        . '\x{1F680}-\x{1F6FF}'
        . '\x{1F910}-\x{1F96B}'
        . '\x{1F980}-\x{1F9E0}]/u';
    preg_match($emojis_regex, $string, $matches);
    return !empty($matches);
}
function thousandsCurrencyFormat($num)
{
    if ($num > 1000) {
        $x = round($num);
        $x_number_format = number_format($x);
        $x_array = explode(',', $x_number_format);
        $x_parts = array('k', 'm', 'b', 't');
        $x_count_parts = count($x_array) - 1;
        $x_display = $x;
        $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
        $x_display .= $x_parts[$x_count_parts - 1];
        return $x_display;
    }
    return $num;
}
function addDaysToTime($day, $time)
{
    $currentTime = $time;
    //The amount of hours that you want to add.
    $daysToAdd = $day;
    //Convert the hours into seconds.
    $secondsToAdd = $daysToAdd * (24 * 60 * 60);
    //Add the seconds onto the current Unix timestamp.
    $newTime = $currentTime + $secondsToAdd;
    return $newTime;
}
function reduce($text)
{
    $reduce = substr($text, 0, 100);
    $reduce = substr($reduce, 0, strrpos($reduce, " "));
    return $reduce . '...';
}
function gettheTimeAndDate($time)
{
    $data = $time;
    $date = date("d/M/Y h:ia", $data);
    return $date;
}
function cleantextseo($text)
{
    $text = html_entity_decode($text);
    $text = strip_tags($text);
    $text = str_replace("\\r\\n", "", $text);
    $text = str_replace("\\", "", $text);
    $text = str_replace("&nbsp;", '', $text);
    return $text;
}
function truncate_number($number, $precision = 2)
{
    // Zero causes issues, and no need to truncate
    if (0 == (int) $number) {
        return $number;
    }
    // Are we negative?
    $negative = $number / abs($number);
    // Cast the number to a positive to solve rounding
    $number = abs($number);
    // Calculate precision number for dividing / multiplying
    $precision = pow(10, $precision);
    // Run the math, re-applying the negative value to ensure
    // returns correctly negative / positive
    return floor($number * $precision) / $precision * $negative;
}
function gettheTime($time)
{
    $data = $time;
    $date = date('h:i A', $data);
    return $date;
}
function generate_string($input, $strength)
{
    $input_length = strlen($input);
    $random_string = '';
    for ($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }
    return $random_string;
}
function reducesm($text)
{
    $reduce = substr($text, 0, 150);
    $reduce = substr($reduce, 0, strrpos($reduce, "@"));
    return $reduce . '...';
}
function reducesmspace($text)
{
    $reduce = substr($text, 0, 150);
    $reduce = substr($reduce, 0, strrpos($reduce, " "));
    return $reduce . '...';
}
function deleteinFolder($name, $dir)
{
    $data = $name;
    $dir = $dir;
    $dirHandle = opendir($dir);
    while ($file = readdir($dirHandle)) {
        if ($file == $data) {
            unlink($dir . "/" . $file);
        }
    }
    closedir($dirHandle);
}
function convertTime($time)
{
    $data = $time;
    $date = strtotime($data);
    return $date;
}
function validatePassword($password)
{
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number = preg_match('@[0-9]@', $password);
    $specialChars = preg_match('@[^\w]@', $password);
    if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 6) {
        return false;
    } else {
        return true;
    }
}
function getIPAddress()
{
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}
function Password_encrypt($user_pass)
{
    $BlowFish_Format = "$2y$10$";
    $salt_len = 24;
    $salt = Get_Salt($salt_len);
    $the_format = $BlowFish_Format . $salt;
    $hash_pass = crypt($user_pass, $the_format);
    return $hash_pass;
}
function Get_Salt($size)
{
    $Random_string = md5(uniqid(mt_rand(), true));
    $Base64_String = base64_encode($Random_string);
    $change_string = str_replace('+', '.', $Base64_String);
    $salt = substr($change_string, 0, $size);
    return $salt;
}
function check_pass($pass, $storedPass)
{
    $Hash = crypt($pass, $storedPass);
    if ($Hash === $storedPass) {
        return (true);
    } else {
        return (false);
    }
}
function getIp()
{
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP')) {
        $ipaddress = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('HTTP_X_FORWARDED')) {
        $ipaddress = getenv('HTTP_X_FORWARDED');
    } elseif (getenv('HTTP_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    } elseif (getenv('HTTP_FORWARDED')) {
        $ipaddress = getenv('HTTP_FORWARDED');
    } elseif (getenv('REMOTE_ADDR')) {
        $ipaddress = getenv('REMOTE_ADDR');
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}
function sendtelemsg($chatid, $message, $botkey)
{
    $path = "https://api.telegram.org/bot$botkey";
    $message = str_replace("%0A", "\n", $message);
    $url = $path . "/sendmessage";
    // $encodedKeyboard = json_encode($keyboard);
    $parameters =
        array(
            'chat_id' => $chatid,
            'text' => $message,
            'reply_to_message_id' => 0,
            'parse_mode' => "html"
        );
    $curld = curl_init();
    curl_setopt($curld, CURLOPT_POST, true);
    curl_setopt($curld, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($curld, CURLOPT_URL, $url);
    curl_setopt($curld, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curld);
    curl_close($curld);
    // file_get_contents($path."/sendmessage?chat_id=".$chatid."&text=$message&parse_mode=html");
}
function replyuser($chatid, $message_id, $message, $buttonadded, $keyboard, $markdown, $botkey)
{
    if (empty($markdown) || $markdown == null) {
        $markdown = "html";
    }
    $path = "https://api.telegram.org/bot$botkey";
    // &parse_mode=html to mke html tag work
    //     <b>bold</b>, <strong>bold</strong>
    // <i>italic</i>, <em>italic</em>
    // <a href="http://www.example.com/">inline URL</a>
    // <code>inline fixed-width code</code>
    // <pre>pre-formatted fixed-width code block</pre>
    if (!$buttonadded) {
        //   file_get_contents($path."/sendmessage?chat_id=".$chatid."&reply_to_message_id=".$message_id."&text=$message&parse_mode=$markdown");
        $message = str_replace("%0A", "\n", $message);
        $url = $path . "/sendmessage";
        $encodedKeyboard = json_encode($keyboard);
        $parameters =
            array(
                'chat_id' => $chatid,
                'text' => $message,
                'reply_to_message_id' => $message_id,
                'parse_mode' => "$markdown"
            );
        $curld = curl_init();
        curl_setopt($curld, CURLOPT_POST, true);
        curl_setopt($curld, CURLOPT_POSTFIELDS, $parameters);
        curl_setopt($curld, CURLOPT_URL, $url);
        curl_setopt($curld, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($curld);
        curl_close($curld);
    } else {
        if (empty($keyboard)) {
            $url = $path . "/sendmessage";
            $encodedKeyboard = json_encode($keyboard);
            $parameters =
                array(
                    'chat_id' => $chatid,
                    'text' => $message,
                    'reply_to_message_id' => $message_id,
                    'parse_mode' => "$markdown"
                );
            $curld = curl_init();
            curl_setopt($curld, CURLOPT_POST, true);
            curl_setopt($curld, CURLOPT_POSTFIELDS, $parameters);
            curl_setopt($curld, CURLOPT_URL, $url);
            curl_setopt($curld, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($curld);
            curl_close($curld);
        } else {
            $url = $path . "/sendmessage";
            $encodedKeyboard = json_encode($keyboard);
            $parameters =
                array(
                    'chat_id' => $chatid,
                    'text' => $message,
                    'reply_to_message_id' => $message_id,
                    'reply_markup' => $encodedKeyboard,
                    'parse_mode' => "$markdown"
                );
            $curld = curl_init();
            curl_setopt($curld, CURLOPT_POST, true);
            curl_setopt($curld, CURLOPT_POSTFIELDS, $parameters);
            curl_setopt($curld, CURLOPT_URL, $url);
            curl_setopt($curld, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($curld);
            curl_close($curld);
            // return $output;
        }
    }
}
function getTotalDaysdifferent($dtimes)
{
    $datediff = $dtimes - strtotime(date("Y-m-d"));
    $differencedays = floor($datediff / (60 * 60 * 24));
    //0today,-1 =yesterday, -2= 3 days ago
    return $differencedays;
}
function createUniqueToken($length, $tablename, $tablecolname, $tokentag, $addnumbers, $addcapitalletters, $addsmalllletters)
{
    global $connect;
    $loopit = true;
    $input = "";
    if ($addnumbers) {
        $numbers = "1234567890";
        $input = $input . $numbers;
    }
    if ($addcapitalletters) {
        $capitalletters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $input = $input . $capitalletters;
    }
    if ($addsmalllletters) {
        $smallletters = "abcdefghijklmnopqrstuvwxyz";
        $input = $input . $smallletters;
    }
    $strength = $length;
    $tokenis = generate_string($input, $strength);
    while ($loopit) {
        // check field
        $query = "SELECT id FROM $tablename WHERE $tablecolname = ?";
        $stmt = $connect->prepare($query);
        $stmt->bind_param("s", $tokenis);
        $stmt->execute();
        $result = $stmt->get_result();
        $num_row = $result->num_rows;
        if ($num_row > 0) {
            $tokenis = generate_string($input, $strength);
        } else {
            $loopit = false;
        }
    }
    return $tokentag . $tokenis;
}





?>