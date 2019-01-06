<?php
use Mailgun\Mailgun;
// use Slim\Http\UploadedFile;
// require __DIR__ . '/../vendor/autoload.php';
use Slim\Http\Request;
// use textmagic\sdk\Services\TextmagicRestClient;
// use textmagic\sdk\Services\RestException;
use Textmagic\Services\TextmagicRestClient;
use Textmagic\Services\RestException;

// require_once "paragonie/random_compat/lib/random.php";
require_once "../vendor/paragonie/random_compat/lib/random.php";

Kint::$enabled_mode = true;
Kint::$enabled_mode = false;

class AuthMapper extends Mapper
{
    protected $auth_default;
    protected $couchdb;
    public function __construct($db, $logger, $couchdb)
    {
        parent::__construct($db, $logger);
        $this->auth_default = ["ip" => "", "state" => 0, "mId" => "", "identifier" => "", "verificationSeq" => 0, "authSeq" => 0, "limit" => "", "name" => "", "role" => ""];
        $this->couchdb = $couchdb;
        Kint::dump($db, $couchdb);
    }
    public function checkAuth($bodyAuth, $authReq)
    {
        if (!$bodyAuth) {
            return ['error' => "you are not authorized for this action"];
        }

        $auth = $this->getAuthFromFile();
        if ($auth['role'] < $authReq || $auth['mId'] !== $bodyAuth['id '] || $auth['authSeq'] !== $bodyAuth['authSeq ']) {
            return ['error' => "you are not authorized for this action"];
        }
        return true;
    }
    public function getAuthFromFile()
    {
        $ip = get_client_ip_env();
        $file = $this->db['host'] . "/ip-{$ip}.json";
        $contents = file_exists($file) ? file_get_contents($file) : false;
        if ($contents) {
            $result = json_decode($contents, true);
        } else {
            $result = $this->auth_default;
            $result["ip"] = $ip;
        }
        $result["file"] = $file;
        return $result;
    }
    public function saveAuthToFile($auth)
    {
        file_put_contents($auth["file"], json_encode($auth));
    }
    public function respond($response, $auth)
    {
        if (!Kint::$enabled_mode) {
            return $response->withJson($auth);
        } else {Kint::dump($auth);return $response;}

    }
    public function getAuth($request, $response, $params)
    {
        $auth = $this->getAuthFromFile();
        unset($auth['verificationSeq']);
        return $this->respond($response, $auth);
    }

    public function logout($request, $response, $params, $error=false)
    {
        $device = $this->getAuthFromFile();
        unlink($device['file']);

        $device = $this->auth_default;
        if ($error) {
            $device['error'] = $error;
        }

        return $this->respond($response, $device);
    }
    public function checkIdentifier($request, $response, $params)
    {
        // Check the identifier and send verification code
        try {
            $device = $this->getAuthFromFile();
            $ip = $device["ip"];
            $device['error'] = null;

            // if($device['state']!==0)return this-logout($request, $response);
            try {
                Kint::dump($this->couchdb);
                $client = new GuzzleHttp\Client();

                Kint::dump($client);
                $identifier = $params[0];
                $isEmail = strpos($identifier, '@');
                $ids = $this->expandIdentifier($identifier, $isEmail);
                $view = $isEmail ? 'allMailList' : 'byMobile';
                Kint::dump($isEmail, $view, $identifier, $ids);
                list($host, $db) = $this->couchdb;
                $ids = json_encode($ids);
                $url = "{$host}/{$db}/_design/members/_view/{$view}?limit=2&descending=true&keys={$ids}";
                Kint::dump($ids, $url);

                $result = $this->get_json_data($url);
                Kint::dump($result);
                $res = $client->request('GET', $url);
                Kint::dump($res->getStatusCode(), $res->getHeader('content-type'), $res->getBody());
                $body = json_decode((string) $res->getBody(), true);
                Kint::dump($isEmail, $view, $identifier, $body, $ids);
                $members = $body['rows'];
                if (count($members) === 0) {
                    $device['error'] = "No member with a "
                        . ($isEmail ? 'email address' : 'mobile phone number')
                        . ' of ' . $identifier . ' was found.';
                } else {
                    $mem = $members[0];
                    $this->logger->info("changeStatus", (array) $mem['value']);

                    $device['identifier'] = $identifier;
                    $device['via'] = $isEmail ? 'email' : 'text';
                    $device['name'] = $this->getName($members);
                    $device['verificationSeq'] = (string) random_int(100000, 999999);
                    $device['mId'] = $mem['id'];
                    $device['role'] = $mem['value']['role'];
                    $device['state'] = 1;

                    $isEmail ? $this->sendEmail($device) : $this->sendText($device);
                    $this->saveAuthToFile($device);
                }
                Kint::dump($device);
            } catch (Exception $e) {
                $device['error'] = 'Internal error: ' . $e->getMessage();
            }

            unset($device['verificationSeq']);
        } catch (PDOException $e) {
            $device['error'] = 'Internal error: ' . $e->getMessage();
            Kint::dump('error', $device);
        }
        return $this->respond($response, $device);

    }
    public function checkVerfication($request, $response, $params)
    {
        try {
            $device = $this->getAuthFromFile();
            $device['error'] = null;
            // logout resets everthing and can be called in any state

            if ($device['state'] !== 1) {
                return $this->logout($request, $response, $params, "Internal error - server not expecting verification code ({$device['state']})");
            }

            if ($device['verificationSeq'] == $params[0]) {
                $device['state'] = 2;
                $device['verificationSeq'] = '';
                $device['authSeq'] = bin2hex(random_bytes(12));
                Kint::dump($device);
                $this->saveAuthToFile($device);
            } else {
                $device['error'] = "verfification code does not match";
                $this->logger->error("changeStatus error:{$device['error']}", [$device['verificationSeq'], $params[0]]);
            }
            Kint::dump($device);

        } catch (PDOException $e) {
            $device['error'] = 'Internal error: ' . $e->getMessage();
            Kint::dump('error', $device);
        }
        if (!Kint::$enabled_mode) {
            return $response->withJson($device);
        } else {
            return $response;
        }

    }
    public function get_json_data($url)
    {
        $user_agent = 'Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';

        $options = array(

            CURLOPT_CUSTOMREQUEST => "GET", //set request type post or get
            CURLOPT_POST => false, //set to GET
            CURLOPT_USERAGENT => $user_agent, //set user agent
            CURLOPT_COOKIEFILE => "cookie.txt", //set cookie file
            CURLOPT_COOKIEJAR => "cookie.txt", //set cookie jar
            CURLOPT_RETURNTRANSFER => true, // return web page
            CURLOPT_HEADER => false, // don't return headers
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_ENCODING => "", // handle all encodings
            CURLOPT_AUTOREFERER => true, // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
            CURLOPT_TIMEOUT => 120, // timeout on response
            CURLOPT_MAXREDIRS => 10, // stop after 10 redirects
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        $err = curl_errno($ch);
        $errmsg = curl_error($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);

        $header['errno'] = $err;
        $header['errmsg'] = $errmsg;
        $header['content'] = $content;
        return $header;
    }
    public function expandIdentifier($id, $isEmail)
    {
        if ($isEmail) {
            return [$id];
        }

        $id = str_replace(' ', '', $id);
        if ($id[0] === '0') {
            $id = substr($id, 1);
        }

        if (substr($id, 0, 3) === '+44') {
            $id = substr($id, 3);
        }

        if (substr($id, 0, 2) === '44') {
            $id = substr($id, 2);
        }

        return ["0$id", "+44$id", "44$id"];
    }
    public function getName($rows)
    {
        $family = null;
        $names = null;
        foreach ($rows as $key => $row) {
            $first = $row['value']['firstName'];
            $last = $row['value']['lastName'];
            if (!$family) {
                $family = $last;
                $names = $first;
            } else {
                if ($last === $family) {
                    $names .= " & $first";
                } else {
                    $names .= " $family &";
                    $family = "$first $last";
                }
            }
        }
        return "$names $family";
    }
    public function sendEmail($device)
    {
        global $config;
        # Instantiate the client.
        $mgClient = new Mailgun($config['mailgun']['key']);
        $domain = $config['mailgun']['domain'];
        $to = "{$device['name']} <{$device['identifier']}>";
        // return;
        # Make the call to the client.
        $msg = array('from' => 'St.Edward\'s Fellwalkers <postmaster@mg.nicholware.co.uk>',
            'to' => $to,
            'subject' => 'Verification code',
            'text' => "Your Verification code for authenticated access to St. Edwards Fellwalkers is {$device['verificationSeq']}",
            'html' => "Your Verification code for authenticated access to St. Edwards Fellwalkers is <span style=\"font-size: larger; font-weight: bold;\">{$device['verificationSeq']}</span>");
        Kint::dump($msg, $device);
        $result = $mgClient->sendMessage($domain, $msg);

    }
    public function sendText($device)
    {
        global $config;
        $client = new TextmagicRestClient($config['textmagic']['name'], $config['textmagic']['password']);
        $number = $device['identifier'];
        if ($number[0] === '0') {
            $number = '+44' . substr($number, 1);
        }

        Kint::dump('text it', $number, $client);
        $result = ' ';
        try {
            $result = $client->messages->create(
                array(
                    'text' => "Your verification code for access is {$device['verificationSeq']}",
                    'phones' => implode(', ', array($number)),
                )
            );
        } catch (\Exception $e) {
            if ($e instanceof RestException) {
                Kint::dump($e);
                print '[ERROR] ' . $e->getMessage() . "\n";
                foreach ($e->getErrors() as $key => $value) {
                    print '[' . $key . '] ' . implode(',', $value) . "\n";
                }
            } else {
                print '[ERROR] ' . $e->getMessage() . "\n";
            }
            return;
        }
        Kint::dump($result);
    }
}
// Function to get the client ip address
function get_client_ip_server()
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

// Function to get the client ip address
function get_client_ip_env()
{
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP')) {
        $ipaddress = getenv('HTTP_CLIENT_IP');
    } else if (getenv('HTTP_X_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    } else if (getenv('HTTP_X_FORWARDED')) {
        $ipaddress = getenv('HTTP_X_FORWARDED');
    } else if (getenv('HTTP_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    } else if (getenv('HTTP_FORWARDED')) {
        $ipaddress = getenv('HTTP_FORWARDED');
    } else if (getenv('REMOTE_ADDR')) {
        $ipaddress = getenv('REMOTE_ADDR');
    } else {
        $ipaddress = 'UNKNOWN';
    }

    return $ipaddress;
}
