<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


 function respondMethodNotAlowed()
    {
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();
        $errordata = [
            "code" => "", 
            "text" => "The request method used is not valid.",
            "link" => "https://", 
            "hint" => [
                "Ensure you use the method stated in the documentation.",
                "Check your environment variable",
                "Missing or Incorrect Headers"
            ]
        ];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" =>"Method Not Allowed", "data" => [], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header("HTTP/1.1 405 Method Not allowed");
        http_response_code(405);

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    function respondBadRequest($userErrMessage,$data=[])
    {
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();

        $errordata = ["code" => '', "text" => "The body request is not valid, missing compulsory parameter or invalid data sent.", "link" => "https://", "hint" => [
            "Ensure you use the request data stated in the documentation.",
            "Check your environment variable",
            "Missing or Incorrect Headers"
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" => $userErrMessage,"data" => $data, "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header("HTTP/1.1 400 Bad request");
        http_response_code(400);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    function respondUnauthorized()
    {
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();

        $errordata = ["code" => '', "text" => "Access token invalid or not sent", "link" => "https://", "hint" => [
            "Check your environment variable",
            "Missing or Incorrect Headers",
            "Change access token",
            "Ensure access token is sent and its valid",
            "Follow the format stated in the documentation", "All letters in upper case must be in upper case",
            "Ensure the correct method is used","Ensure authorization is sent with capital A"
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" => 'Unauthorized', "data" =>[], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header("HTTP/1.1 401 Unauthorized");
        http_response_code(401);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    function respondInternalError($errorText,$usererr = null) {
        // If user error response is not provided, use the default internal error message
        if ($usererr === null) {
            $usererr = "Internal Server Error";
        }
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();
 
        $errordata = ["code" => '', "text" => $errorText, "link" => "https://", "hint" => [
            "Check the code",
            "Make sure data type needed is sent",
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" =>$usererr, "data" =>[], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        
        
        $log = dirname(__DIR__) . '/logs/' . date('Y-m-d') . '.txt';
        ini_set('error_log', $log);
        if(strpos($errorText,"wwwcardifyaf2104")!==false){
        $message = "Uncaught exception: $errorText";
        //SEND TO ADMIN TG
        system_notify_crash_handler($message,"System");
        
        error_log($message);
            
        }
        
        header("HTTP/1.1 500 Internal Error");
        http_response_code(500);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    //  note the code 102 was replace with code 104
    //function respondOK($maindata, $text) 

    function respondOK($text = null, $data = null) {
    // Ensure $text is JSON-friendly
    if (is_array($text) || is_object($text)) {
        // leave as-is
    } elseif ($text === null) {
        $text = ""; // default to empty string if null
    } else {
        $text = strval($text); // convert other types to string
    }

    // Prepare the API response
    $response = [
        'status' => true,
        'text' => $text,
        'data' => $data,
        'time' => date('Y-m-d H:i:s'),  // full timestamp
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
        'error' => []
    ];

    // Set JSON header and return the response
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit; // stop further script execution
}




    function respondTooManyRequest(){
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();

        $errordata = ["code" => '', "text" => "Too Many Requests", "link" => "https://", "hint" => [
            "Your server is calling the API contineously",
            "Your server is calling the API contineously",
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" => "Too Many Requests", "data" =>[], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header("HTTP/1.1 429 Too Many Requests");
        http_response_code(429);
            // 405 Method Not Allowed
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        exit;
    }
    function respondNotCompleted(){
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();

        $errordata = ["code" => '', "text" => "Request did not get completed", "link" => "https://", "hint" => [
            "Check server",
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" => "Request did not get completed", "data" =>[], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header('HTTP/1.1 202 Not Completed');
        http_response_code(202);
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            // 202 Accepted Indicates that the request has been received but not completed yet.
        exit;
    }

    function respondURLChanged($data){
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();

        $errordata = ["code" => '', "text" => "URL changed", "link" => "https://", "hint" => [
            "Check documentation",
            "Invalid URL"
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" => "URL changed", "data" =>[], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header('HTTP/1.1 302 URL changed');
        http_response_code(302);
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
           // The URL of the requested resource has been changed temporarily
        exit;
    }
    function respondNotFound($data){
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();

        $errordata = ["code" => '', "text" => "Data not found", "link" => "https://", "hint" => [
            "URL not valid",
            "Check data sent",
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" => "Data not found", "data" =>[], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header('HTTP/1.1 404 Not found');
        http_response_code(404);
          //  Not found
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        exit;
    }
    function respondForbiddenAuthorized($data){
        $method = getenv('REQUEST_METHOD');
        $endpoint = getCurrentFileFullURL();

        $errordata = ["code" => '', "text" => "Authorization Not allowed", "link" => "https://", "hint" => [
            "Make sure to have the permission",
            "Read documentation",
        ]];
        setTimeZoneForUser('');
        $data = ["status" => false, "text" => "Authorization Not allowed", "data" =>[], "time" => date("d-m-y H:i:sA", time()), "method" => $method, "endpoint" => $endpoint, "error" => $errordata];
        header("HTTP/1.1 403 Forbidden");
        http_response_code(403);
            // 403 Forbidden
        // Unauthorized request. The client does not have access rights to the content. Unlike 401, the client’s identity is known to the server.
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        exit;
    }
    // Generate a signed JWT for a user or admin.
    // $role: 'admin' | 'user'  — stored in the token, checked on protected endpoints
    function getTokenToSendAPI($userId, $role = 'user')
    {
        try {
            $issuedAt = new \DateTimeImmutable();
            $expire   = $issuedAt->modify("+" . JWT_EXPIRY_MINUTES . " minutes")->getTimestamp();

            $payload = [
                'iat'       => $issuedAt->getTimestamp(),
                'iss'       => JWT_SERVER_NAME,
                'nbf'       => $issuedAt->getTimestamp(),
                'exp'       => $expire,
                'usertoken' => (string) $userId,
                'role'      => $role,
            ];

            return JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
        } catch (\Exception $e) {
            respondInternalError(get_details_from_exception($e));
        }
    }

    // Validate the Bearer JWT sent in the Authorization header.
    // $requiredRole: pass 'admin' or 'user' to enforce role-based access, null to skip role check.
    // Returns the decoded token object on success; calls the appropriate respond* and exits on failure.
    function ValidateAPITokenSentIN($requiredRole = null)
    {
        try {
            $headers    = getallheaders();
            $authHeader = $headers['Authorization']
                ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

            if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                respondUnauthorized();
            }

            $jwt = $matches[1];
            if (empty($jwt)) {
                respondUnauthorized();
            }

            $token = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
            $now   = new \DateTimeImmutable();

            if ($token->iss !== JWT_SERVER_NAME ||
                $token->nbf > $now->getTimestamp() ||
                $token->exp < $now->getTimestamp() ||
                input_is_invalid($token->usertoken)) {
                respondUnauthorized();
            }

            // Role-based access control
            if ($requiredRole !== null && (!isset($token->role) || $token->role !== $requiredRole)) {
                respondForbiddenAuthorized([]);
            }

            return $token;
        } catch (\Firebase\JWT\ExpiredException $e) {
            respondUnauthorized();
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            respondUnauthorized();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Expired') ||
                str_contains($msg, 'Signature') ||
                str_contains($msg, 'Wrong number')) {
                respondUnauthorized();
            }
            respondInternalError(get_details_from_exception($e));
        }
    }

    function userHasCalledAPIToMaxLimit($userId, $limit = 100, $interval = 3600) {
        try{
                    // preventing too many request at a time
            // Define the maximum number of requests allowed per minute
            $maxRequestsPerMinute =$limit;
            // Create a unique identifier for the client, e.g., based on the client's IP address
            $clientIdentifier = 'rate_limit_' .$userId;
            // Retrieve the current timestamp
            $currentTimestamp = time();
            $folderPath = dirname(__DIR__);
            $filename =  $folderPath . "/logs/cache_call/rate_limit_data.json";
            // Set the path to the rate limit data file
            $rateLimitDataFile =  $filename ;
            // Initialize an empty array for rate limit data
            $requestData = [];
            
            // Check if the rate limit data file exists
            if (file_exists($rateLimitDataFile)) {
                // Load existing data from the file
                $requestData = json_decode(file_get_contents($rateLimitDataFile), true);
            }
            
            // Check if the client identifier exists in the request data
            if (!isset($requestData[$clientIdentifier])) {
                $requestData[$clientIdentifier] = [
                    'timestamp' => $currentTimestamp,
                    'count' => 1,
                ];
            } else {
                $lastTimestamp = $requestData[$clientIdentifier]['timestamp'];
                
                // Check if the time window has elapsed (1 minute in this case)
                if (($currentTimestamp - $lastTimestamp) > $interval) {
                    $requestData[$clientIdentifier] = [
                        'timestamp' => $currentTimestamp,
                        'count' => 1,
                    ];
                } else {
                    // Increment the request count
                    $requestData[$clientIdentifier]['count']++;
                }
            }
            
            // Save the updated request data
            file_put_contents( $filename , json_encode($requestData));
            
            // Check if the client has exceeded the allowed number of requests
            if ($requestData[$clientIdentifier]['count'] > $maxRequestsPerMinute) {
                //reset
                $requestData[$clientIdentifier]['count']=0;
                return true;
            }
         return false;
        } catch (\Exception $e) {
            respondInternalError(get_details_from_exception($e));
        }
    }


    ?>