<?php

class Flagship
{
    private $envId = '';
    private $apiKey = '';
    protected $decision = null;

    public function start($visitorID, $context)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://decision.flagship.io/v2/bk90qks1tlug042qsqng/flags',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>
                '{
                    "visitor_id": "' .
                $visitorID .
                '",
                    "context": ' .
                $context .
                ',
                    "trigger_hit": false
                }',
            CURLOPT_HTTPHEADER => [
                'Connection: keep-alive',
                'x-api-key: HwXaeJai242GCC0RGGOym57eSCEimA7A3tkDJbUG',
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        $this->decision = json_decode($response);
        return $this->decision;
    }

    public function getDecision()
    {
        return $this->decision;
    }

    public function getHashKey()
    {
        if ($this->decision == null) {
            return false;
        }
        $experiences = [];
        foreach ($this->decision as $flag) {
            $experiences[$flag->metadata->campaignId] =
                $flag->metadata->variationId;
        }

        return implode(
            '|',
            array_map(
                function ($v, $k) {
                    return sprintf('%s:%s', $k, $v);
                },
                $experiences,
                array_keys($experiences)
            )
        );
    }

    public function getFlag($key, $default)
    {
        if ($this->decision === null || !isset($this->decision->{$key})) {
            return $default;
        }
        return $this->decision->{$key}->value;
    }

    public function generateUID()
    {
        return 'varnish-v' . rand();
    }
}
