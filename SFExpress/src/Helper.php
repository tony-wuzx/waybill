<?php

namespace Yamato\SFExpress;

class Helper
{
    const API_BASE_URI_SANDBOX_OAUTH = 'https://sfapi-sbox.sf-express.com/oauth2/accessToken';
    const API_BASE_URI_PRODUCTION_OAUTH = 'https://sfapi.sf-express.com/oauth2/accessToken';
    const API_BASE_URI_SANDBOX_SERVICE = 'https://sfapi-sbox.sf-express.com/std/service';
    const API_BASE_URI_PRODUCTION_SERVICE = 'https://bspgw.sf-express.com/std/service';
    
    const SERVICE_WAYBILL = 'COM_RECE_CLOUD_PRINT_WAYBILLS';
    const SUCCESS = 'A1000';

    public function __construct(
        protected string $partnerID,
        protected string $checkWord,
        protected bool $isSandbox
    ) {
    }

    public function token()
    {
        $url = $this->isSandbox ? self::API_BASE_URI_SANDBOX_OAUTH : self::API_BASE_URI_PRODUCTION_OAUTH;
        $params = [
            'partnerID' => $this->partnerID,
            'secret' => $this->checkWord,
            'grantType' => 'password',
        ];
        $url .= '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($res['apiResultCode'] === self::SUCCESS) {
            return $res['accessToken'];
        }

        return '';
    }

    public function waybill(array $track_numbers,string $template_code)
    {
        foreach($track_numbers as $track_number) {
            $doc[]['masterWaybillNo'] = $track_number;
        }

        $url = $this->isSandbox ? self::API_BASE_URI_SANDBOX_SERVICE : self::API_BASE_URI_PRODUCTION_SERVICE;
        $params = [
            'partnerID' => $this->partnerID,
            'requestID' => uniqid(),
            'serviceCode' => self::SERVICE_WAYBILL,
            'timestamp' => time(),
            'accessToken' => $this->token(),
            'msgData' => json_encode([
                'templateCode' => $template_code,
                'version' => '2.0',
                'sync' => true,
                'extJson' => ['mergePdf' => true],
                'documents' => $doc
            ], 512)
        ];
        $url .= '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));

        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($res['apiResultCode'] === self::SUCCESS) {
            $obj = json_decode($res['apiResultData'], true);
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $obj['obj']['files'][0]['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'X-Auth-token: ' .$obj['obj']['files'][0]['token']
                ),
            ));
            $content = curl_exec($ch);
            curl_close($ch);
            return $content;
        }

        return '';
    }
}