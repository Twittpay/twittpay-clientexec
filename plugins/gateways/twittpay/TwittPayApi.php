<?php

/**
 * TwittPay - shared API helper for the Clientexec gateway
 * ---------------------------------------------------------------------------
 * Both the gateway plugin and the callback need to talk to the API, so the calls
 * live here once instead of twice.
 *
 * @version 1.0.0
 */
class TwittPayApi
{
    /**
     * Scheme and host of the configured endpoint. Pasting the whole endpoint or a
     * trailing /api still works.
     */
    public static function baseUrl($configured)
    {
        $raw    = rtrim(trim((string) $configured), '/');
        $scheme = parse_url($raw, PHP_URL_SCHEME);
        $host   = parse_url($raw, PHP_URL_HOST);

        if (empty($host)) {
            $host = strtok(ltrim(preg_replace('#^[a-z]+://#i', '', $raw), '/'), '/');
        }

        if (empty($scheme)) {
            $scheme = 'https';
        }

        return $scheme . '://' . $host;
    }

    /** One POST to the API. JSON in, array out. Returns array() on failure. */
    public static function call($configuredUrl, $apiKey, $endpoint, $payload)
    {
        // metadata has to arrive as a JSON object; a PHP list would encode as an
        // array and be rejected.
        if (isset($payload['metadata'])) {
            $payload['metadata'] = (object) $payload['metadata'];
        }

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL            => self::baseUrl($configuredUrl) . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'API-KEY: ' . trim((string) $apiKey),
            ),
        ));

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * The verify status. PENDING, COMPLETED or ERROR when the transaction is real,
     * and an empty string when it is not - a miss answers a number, not text.
     */
    public static function readStatus($verified)
    {
        if (!is_array($verified) || !isset($verified['status']) || !is_string($verified['status'])) {
            return '';
        }

        return strtoupper(trim($verified['status']));
    }

    /** metadata comes back from verify as a JSON string. */
    public static function metadata($verified)
    {
        if (!is_array($verified) || !isset($verified['metadata'])) {
            return array();
        }

        $meta = $verified['metadata'];

        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array();
    }

    /**
     * The transaction id, wherever it turns up: on the return URL, in the
     * webhook's form body, or in a JSON body.
     */
    public static function transactionId()
    {
        foreach (array($_GET, $_POST) as $bag) {
            foreach (array('transactionId', 'transaction_id') as $key) {
                if (!empty($bag[$key])) {
                    return trim((string) $bag[$key]);
                }
            }
        }

        $raw = file_get_contents('php://input');

        if (!empty($raw)) {
            $body = json_decode($raw, true);

            if (is_array($body)) {
                foreach (array('transactionId', 'transaction_id') as $key) {
                    if (!empty($body[$key])) {
                        return trim((string) $body[$key]);
                    }
                }
            }
        }

        return '';
    }

    /** The gateway charges BDT. Anything else is converted with the set rate. */
    public static function toBdt($amount, $currency, $rate)
    {
        if (strtoupper(trim((string) $currency)) === 'BDT') {
            return (float) $amount;
        }

        $rate = (float) $rate;

        if ($rate <= 0) {
            $rate = 1;
        }

        return (float) $amount * $rate;
    }
}
