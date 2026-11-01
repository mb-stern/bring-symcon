<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace BringAccount {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace BringAccount {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/VariableHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/BringAPI.php';

/**
 * BringGateway
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class BringAccount extends IPSModuleStrict
{
    use \BringAccount\DebugHelper;
    use \BringAccount\VariableHelper;

    private static $http_error =
        [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Server error'
        ];

    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterAttributeString(\Bring\Attribute::Name, '');
        $this->RegisterAttributeString(\Bring\Attribute::EMail, '');
        $this->RegisterAttributeString(\Bring\Attribute::Password, '');
        $this->RegisterAttributeString(\Bring\Attribute::uuid, '');
        $this->RegisterAttributeString(\Bring\Attribute::publicUuid, '');
        $this->RegisterAttributeString(\Bring\Attribute::AccessToken, '');
        $this->RegisterAttributeInteger(\Bring\Attribute::AccessTokenExpiresIn, 0);
        $this->RegisterAttributeString(\Bring\Attribute::RefreshToken, '');
        $this->RegisterAttributeString(\Bring\Attribute::UserImage, '');
        $this->RegisterTimer(\Bring\Timer::RefreshToken, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \Bring\Timer::RefreshToken . '",true);');
    }

    /**
     * Destroy
     *
     * @return void
     */
    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        if ($this->ReadAttributeInteger(\Bring\Attribute::AccessTokenExpiresIn)) {
            $this->RefreshToken();
        } else { // login
            if ($this->ReadAttributeString(\Bring\Attribute::EMail)) {
                $this->SendLogin(
                    $this->ReadAttributeString(\Bring\Attribute::EMail),
                    $this->ReadAttributeString(\Bring\Attribute::Password)
                );
            } else {
                $this->SetStatus(IS_INACTIVE);
            }
        }
    }

    /**
     * RequestAction
     *
     * @param  string $Ident
     * @param  mixed $Value
     * @return void
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case \Bring\Timer::RefreshToken:
                $this->RefreshToken();
                break;
            case 'LoginPopup':
                $this->UpdateFormField('LoginPopup', 'visible', true);
                break;
            case 'ClearLogin':
                $this->WriteAttributeString(\Bring\Attribute::uuid, '');
                $this->UpdateFormField('Name', 'caption', '');
                $this->UpdateFormField('UserImage', 'image', '');
                $this->UpdateFormField('ClearLogin', 'visible', false);
                $this->UpdateFormField('Username', 'value', $this->ReadAttributeString(\Bring\Attribute::EMail));
                $this->UpdateFormField('Password', 'value', $this->ReadAttributeString(\Bring\Attribute::Password));
                $this->UpdateFormField('Login', 'visible', true);
                $this->UpdateFormField('LoginPopup', 'visible', true);
                $this->SetStatus(IS_INACTIVE);
                break;
        }
    }

    /**
     * SendLogin
     *
     * @param  string $Username
     * @param  string $Password
     * @return string
     */
    public function SendLogin(string $Username, string $Password): string
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $Result = $this->DoRequest(
            \Bring\Api\RequestMethod::POST,
            'bringauth',
            [
                \Bring\Attribute::EMail    => $Username,
                \Bring\Attribute::Password => $Password
            ],
            true
        );
        if ($Result === false) {
            $this->ClearTokenAttributes();
            return $this->Translate('Unauthorized');
        }
        $this->WriteAttributeString(\Bring\Attribute::EMail, $Username);
        $this->WriteAttributeString(\Bring\Attribute::Password, $Password);
        $this->WriteAttributeString(\Bring\Attribute::Name, $Result[\Bring\Attribute::Name]);
        $this->WriteAttributeString(\Bring\Attribute::uuid, $Result[\Bring\Attribute::uuid]);
        $this->WriteAttributeString(\Bring\Attribute::publicUuid, $Result[\Bring\Attribute::publicUuid]);
        $this->UpdateTokenData($Result);
        $this->LoadProfile($Result[\Bring\Attribute::uuid], $Result[\Bring\Attribute::publicUuid]);
        //$this->ConnectToWS();
        $this->ReloadForm();
        return '';
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if ($this->ReadAttributeString(\Bring\Attribute::uuid)) {
            $Form['elements'][0] = [
                'type'    => 'Label',
                'name'    => 'Name',
                'caption' => $this->Translate('Logged in user: ') . $this->ReadAttributeString(\Bring\Attribute::Name),
            ];
            $Form['elements'][1] = [
                'type'   => 'Image',
                'name'   => 'UserImage',
                'width'  => '200px',
                'image'  => $this->ReadAttributeString(\Bring\Attribute::UserImage)
            ];
            $Form['actions'][0]['visible'] = true;
            $Form['actions'][1]['visible'] = false;
            $Form['actions'][2]['visible'] = false;
        } else {
            $Form['actions'][0]['visible'] = false;
            $Form['actions'][1]['visible'] = true;
            $Form['actions'][1]['popup']['items'][1]['value'] = $this->ReadAttributeString(\Bring\Attribute::EMail);
            $Form['actions'][1]['popup']['items'][2]['value'] = $this->ReadAttributeString(\Bring\Attribute::Password);
            $Form['actions'][2]['visible'] = true;
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * ForwardData
     *
     * @param  string $JSONString
     * @return string
     */
    public function ForwardData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        $this->SendDebug(__FUNCTION__, $Data, 0);
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                break;
            case IS_INACTIVE:
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Instance is inactive'), E_USER_WARNING);
                restore_error_handler();
                return serialize(false);
            default:
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Instance is in error state'), E_USER_WARNING);
                restore_error_handler();
                return serialize(false);
        }

        $Data[\Bring\FlowToParent::Url] = str_replace('%%%UserUuid%%%', $this->ReadAttributeString(\Bring\Attribute::uuid), $Data[\Bring\FlowToParent::Url]);
        foreach ($Data[\Bring\FlowToParent::Payload] as $Key => &$Value) {
            $Value = str_replace('%%%UserUuid%%%', $this->ReadAttributeString(\Bring\Attribute::uuid), $Value);
            $Value = str_replace('%%%PublicUserUuid%%%', $this->ReadAttributeString(\Bring\Attribute::publicUuid), $Value);
        }
        $Result = $this->DoRequest(
            $Data[\Bring\FlowToParent::Method],
            '' . $Data[\Bring\FlowToParent::Url],
            $Data[\Bring\FlowToParent::Payload]
        );
        return serialize($Result);
    }

    /**
     * ModulErrorHandler
     *
     * @param  int $errno
     * @param  string $errstr
     * @return true
     */
    protected function ModulErrorHandler(int $errno, string $errstr): bool
    {
        echo $errstr . PHP_EOL;
        return true;
    }

    /**
     * RefrehToken
     *
     * @return void
     */
    private function RefreshToken(): void
    {
        $this->SetTimerInterval(\Bring\Timer::RefreshToken, 0);
        $this->SendDebug(__FUNCTION__, '', 0);
        $Result = $this->DoRequest(
            \Bring\Api\RequestMethod::POST,
            'bringauth/token',
            [
                \Bring\Attribute::AccessToken     => $this->ReadAttributeString(\Bring\Attribute::AccessToken),
                \Bring\Attribute::RefreshToken    => $this->ReadAttributeString(\Bring\Attribute::RefreshToken)
            ]
        );
        if ($Result === false) {
            $this->ClearTokenAttributes();
            return;
        }
        $this->UpdateTokenData($Result);
        $this->UpdateFormField('ClearLogin', 'visible', true);
        $this->UpdateFormField('Login', 'visible', false);
        if ($this->GetStatus() != IS_ACTIVE) {
            $this->SetStatus(IS_ACTIVE);
            //$this->ConnectToWS();
        }
    }

    /**
     * LoadProfile
     *
     * @param  mixed $publicUuid
     * @return void
     */
    private function LoadProfile(string $Uuid, string $publicUuid): void
    {
        $Result = $this->DoRequest(
            \Bring\Api\RequestMethod::GET,
            'bringusers/profilepictures/' . $publicUuid
        );
        $this->SendDebug(__FUNCTION__, $Result, 0);

        if (is_string($Result) && $Result !== '') {
            $this->WriteAttributeString(\Bring\Attribute::UserImage, $Result);
            $this->UpdateFormField('UserImage', 'image', $Result);
        }
    }

    /**
     *   Handles the request to the server
     *
     *   @param string   $RequestMethod   The HTTP request type.
     *   @param string   $RequestURL      contains the request URL
     *   @param array    $Payload    The parameters we send with the request
     *   @param bool     $IsLogin   True on login command, otherwise false (That is necessary because it sends the API-KEY with the request)
     *   @return array|string|bool  The answer string from the server
     */
    private function DoRequest(string $RequestMethod = \Bring\Api\RequestMethod::GET, string $RequestURL = '', array $Payload = [], bool $IsLogin = false): array|string|bool
    {
        $Parameter = '';
        $ContentType = '';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $RequestMethod);
        switch ($RequestMethod) {
            case \Bring\Api\RequestMethod::GET:
                if (count($Payload)) {
                    $Parameter = http_build_query($Payload);
                }
                curl_setopt($ch, CURLOPT_URL, \Bring\Api::BringRestURL . $RequestURL . ($Parameter ? '?' . $Parameter : ''));
                break;
            case \Bring\Api\RequestMethod::POST:
                curl_setopt($ch, CURLOPT_POST, true);
                // No break. Add additional comment above this line if intentional
            case \Bring\Api\RequestMethod::PUT:
                curl_setopt($ch, CURLOPT_URL, \Bring\Api::BringRestURL . $RequestURL);
                $Parameter = self::BuildPayload($Payload, $ContentType);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $Parameter);
                break;
        }
        $RequestHeader = $this->BuildHeader($IsLogin, $ContentType);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        /** @var array $_IPS */
        $this->SendDebug('RequestMethod:' . $_IPS['THREAD'], $RequestMethod, 0);
        $this->SendDebug('RequestURL:' . $_IPS['THREAD'], $RequestURL, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $RequestHeader);
        $this->SendDebug('RequestHeader:' . $_IPS['THREAD'], $RequestHeader, 0);
        $this->SendDebug('RequestData:' . $_IPS['THREAD'], $Payload, 0);
        $response = curl_exec($ch);

        $HttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($HttpCode != 0) {
            $this->SendDebug('Request Headers:' . $_IPS['THREAD'], curl_getinfo($ch)['request_header'], 0);
        }
        $HeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        $Header = '';
        $Result = '';
        if (!is_bool($response)) {
            $Header = substr($response, 0, $HeaderSize);
            $Result = substr($response, $HeaderSize);
        }
        $this->SendDebug('Result Headers:' . $_IPS['THREAD'], $Header, 0);
        $this->SendDebug('Result Body:' . $_IPS['THREAD'], $Result, 0);

        set_error_handler([$this, 'ModulErrorHandler']);
        switch ($HttpCode) {
            case 0:
                $this->SendDebug('CURL ERROR', \Bring\Api::$CURL_error_codes[$curl_errno], 0);
                trigger_error(\Bring\Api::$CURL_error_codes[$curl_errno], E_USER_WARNING);
                $Result = false;
                break;
            case 202:
            case 204:
                if ($RequestMethod == \Bring\Api\RequestMethod::PUT) {
                    $Result = true;
                }
                break;
            case 400:
            case 401:
            case 403:
            case 404:
            case 405:
            case 500:
                $this->SendDebug(self::$http_error[$HttpCode], $HttpCode, 0);
                trigger_error(self::$http_error[$HttpCode], E_USER_WARNING);
                $Result = false;
                break;
            default:
                if (($RequestMethod != \Bring\Api\RequestMethod::GET) && ($Result == '')) {
                    $Result = true;
                } else {
                    $Result = self::DecodeResponse($Header, $Result);
                }
                break;
        }
        restore_error_handler();
        $this->SendDebug('Result:' . $_IPS['THREAD'], $Result, 0);
        return $Result;
    }

    private static function DecodeResponse(string $Header, string $Body): array|string|bool
    {
        // Only the final response headers describe the body (e.g. after a proxy handshake).
        $HeaderBlocks = explode("\r\n\r\n", rtrim($Header, "\r\n"));
        $Headers = self::http_parse_headers(end($HeaderBlocks));
        $ContentType = strtolower(trim(explode(';', $Headers['content-type'] ?? '')[0]));
        if (in_array($ContentType, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            return 'data:' . $ContentType . ';base64,' . base64_encode($Body);
        }

        $Result = json_decode($Body, true);
        if (json_last_error() !== JSON_ERROR_NONE || (!is_array($Result) && !is_string($Result) && !is_bool($Result))) {
            return false;
        }
        return $Result;
    }

    /**
     * BuildPayload
     *
     * @param  array $Payload
     * @param  bool $isJSON
     * @return string
     */
    private static function BuildPayload(array $Payload, string &$ContentType): string
    {
        if (isset($Payload[0]) && ($Payload[0][0] == '{')) {
            $ContentType = 'application/json; charset=utf-8';
            return $Payload[0];
        }
        $ContentType = 'application/x-www-form-urlencoded;charset=UTF-8';
        return http_build_query($Payload);
    }

    /**
     * BuildHeader
     *
     * @param  bool $isLogin
     * @param  string $ContentType
     * @return array
     */
    private function BuildHeader(bool $isLogin, string $ContentType): array
    {
        $Header = \Bring\Api::GetApiHeader();
        if ($ContentType) {
            $Header[] = 'Content-Type: ' . $ContentType;
        }
        if (!$isLogin) {
            $Header[] = 'X-BRING-USER-UUID: ' . $this->ReadAttributeString(\Bring\Attribute::uuid);
            $Header[] = 'Authorization: Bearer ' . $this->ReadAttributeString(\Bring\Attribute::AccessToken);
            $Header[] = 'Cookie: refresh_token=' . $this->ReadAttributeString(\Bring\Attribute::RefreshToken);
        }
        return $Header;
    }

    /**
     * ClearAllAttributes
     *
     * @return void
     */
    private function ClearTokenAttributes(): void
    {
        $this->WriteAttributeString(\Bring\Attribute::AccessToken, '');
        $this->WriteAttributeString(\Bring\Attribute::RefreshToken, '');
        $this->WriteAttributeInteger(\Bring\Attribute::AccessTokenExpiresIn, 0);
        $this->SetStatus(IS_EBASE + 1);
    }

    /**
     * UpdateTokenData
     *
     * @param  array $TokenData
     * @return void
     */
    private function UpdateTokenData(array $TokenData): void
    {
        $this->WriteAttributeString(\Bring\Attribute::AccessToken, $TokenData[\Bring\Attribute::AccessToken]);
        $this->WriteAttributeString(\Bring\Attribute::RefreshToken, $TokenData[\Bring\Attribute::RefreshToken]);
        $Timestamp = time() + $TokenData[\Bring\Attribute::AccessTokenExpiresIn];
        $this->WriteAttributeInteger(\Bring\Attribute::AccessTokenExpiresIn, $Timestamp);
        $Interval = ($TokenData[\Bring\Attribute::AccessTokenExpiresIn] - 300) * 1000;
        $this->SetTimerInterval(\Bring\Timer::RefreshToken, $Interval);
        $this->SendDebug('TokenExpire', $Timestamp, 0);
        $this->SendDebug('Set Token Refresh Interval', $Interval, 0);
        $this->SetStatus(IS_ACTIVE);
    }

    /**
     * http_parse_headers
     *
     * @param  mixed $raw_headers
     * @return string
     */
    private static function http_parse_headers(string $raw_headers): array
    {
        $headers = [];
        $key = '';

        foreach (explode("\n", $raw_headers) as $i => $h) {
            $h = explode(':', $h, 2);

            if (isset($h[1])) {
                if (!isset($headers[$h[0]])) {
                    $headers[$h[0]] = trim($h[1]);
                } elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], [trim($h[1])]);
                } else {
                    $headers[$h[0]] = array_merge([$headers[$h[0]]], [trim($h[1])]);
                }

                $key = $h[0];
            } else {
                if (substr($h[0], 0, 1) == "\t") {
                    $headers[$key] .= "\r\n\t" . trim($h[0]);
                } elseif (!$key) {
                    $headers[0] = trim($h[0]);
                }
            }
        }
        $headers = array_change_key_case($headers, CASE_LOWER);
        return $headers;
    }

    /**
     * ReceiveData
     *
     * @todo WebSocket aktuell ohne Funktion seitens Bring
     * @param  mixed $JSONString
     * @return string
     */
    /*
    public function ReceiveData($JSONString): string
    {
        $Data = explode("\x0", hex2bin((json_decode($JSONString))->Buffer))[0];
        $this->DecodePacket($Data);
        return '';
    }
     */

    /**
     * SendToWS
     *
     * @todo WebSocket aktuell ohne Funktion seitens Bring
     * @param  string $Data
     * @return void
     */
    /*
    public function SendToWS(string $Data): void
    {
        $JSON = json_encode([
            \Bring\FlowToWebSocket::DataID     => \Bring\GUID::SendToWS,
            \Bring\FlowToWebSocket::Buffer     => bin2hex($Data)
        ]);
        @$this->SendDataToParent($JSON);
    }
     */

    /**
     * ConnectToWS
     *
     * @todo WebSocket aktuell ohne Funktion seitens Bring
     * @return void
     */
    /*
    private function ConnectToWS(): void
    {
        $this->SendToWS("CONNECT\nlogin:webapp\npasscode:RVFiufPWELR6\nhost:bringwebapp\naccept-version:1.2,1.1,1.0\nheart-beat:25000,25000\n\n\x0");
    }
     */

    /**
     * SubscribeToWS
     *
     * @todo WebSocket aktuell ohne Funktion seitens Bring
     * @return void
     */
    /*
    private function SubscribeToWS(): void
    {
        $Data = "SUBSCRIBE\n" .
                'id:sub-' . time() . "123-600\n" .
                'destination:/exchange/sync/' . $this->ReadAttributeString(\Bring\Attribute::uuid) . "\n" .
                "ack:client\n\n\x0";
        $this->SendToWS($Data);

        $Data = "SUBSCRIBE\n" .
                'id:sub-' . time() . "312-600\n" .
                'destination:/exchange/sync/' . $this->ReadAttributeString(\Bring\Attribute::publicUuid) . "\n" .
                "ack:client\n\n\x0";
        $this->SendToWS($Data);

        $Data = "SUBSCRIBE\n" .
                'id:sub-' . time() . "456-600\n" .
                'destination:/exchange/sync/527a5b13-88b9-4743-a693-3de79cabe093' . "\n" .
                "ack:client\n\n\x0";
        $this->SendToWS($Data);

    }
     */

    /**
     * DecodePacket
     *
     * @todo WebSocket aktuell ohne Funktion seitens Bring
     * @param  string $Data
     * @return void
     */
    /*
    private function DecodePacket(string $DataLines): void
    {
        $this->SendDebug(__FUNCTION__, $DataLines, 0);
        if ($DataLines == "\n") {
            $this->SendToWS("\n");
            return;
        }
        $Data = explode("\n", $DataLines);
        switch ($Data[0]) {
            case 'CONNECTED':
                $this->SubscribeToWS();
                break;
            case 'CLOSED':
                //reconnect
                break;
        }
        return;
    }
     */

}
