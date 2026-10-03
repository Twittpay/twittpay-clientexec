<?php

/**
 * TwittPay - Clientexec gateway plugin
 * ---------------------------------------------------------------------------
 * Creates the payment and sends the customer to the gateway's checkout page.
 * The invoice itself is settled in PluginTwittpayCallback.php.
 *
 * @version 1.0.0
 */

require_once 'modules/admin/models/GatewayPlugin.php';
require_once dirname(__FILE__) . '/TwittPayApi.php';

class Plugintwittpay extends GatewayPlugin
{
    function getVariables()
    {
        $variables = array(
            lang("Plugin Name") => array(
                "type"        => "hidden",
                "description" => "",
                "value"       => "TwittPay"
            ),
            lang('Signup Name') => array(
                'type'        => 'text',
                'description' => lang('Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card.'),
                'value'       => 'TwittPay'
            ),
            lang("Endpoint URL") => array(
                "type"        => "text",
                "description" => "Your own gateway address, for example https://checkout.twittpay.com",
                "value"       => ""
            ),
            lang("Brand Key") => array(
                "type"        => "text",
                "description" => "From your gateway dashboard, under Brands",
                "value"       => ""
            ),
            lang("USD to BDT Rate") => array(
                "type"        => "text",
                "description" => "Used only when the invoice is not already in BDT. 1 USD = this many BDT.",
                "value"       => "120"
            ),
            lang("Store Currency") => array(
                "type"        => "text",
                "description" => "The currency your invoices are in, used when Clientexec does not pass one. BDT is sent straight through.",
                "value"       => "BDT"
            ),
        );

        return $variables;
    }

    function singlepayment($params)
    {
        $invoiceId = $params['invoiceNumber'];
        $amount    = sprintf("%01.2f", round($params["invoiceTotal"], 2));
        $firstname = isset($params['userFirstName']) ? $params['userFirstName'] : '';
        $lastname  = isset($params['userLastName']) ? $params['userLastName'] : '';
        $email     = isset($params['userEmail']) ? $params['userEmail'] : '';

        $apiUrl = trim($params['plugin_twittpay_Endpoint URL']);
        $apiKey = trim($params['plugin_twittpay_API Key']);
        $rate   = $params['plugin_twittpay_USD to BDT Rate'];

        if ($apiUrl === '' || $apiKey === '') {
            die("TwittPay is not fully configured. Please contact us.");
        }

        // Clientexec does not always hand a currency over, so the configured store
        // currency is the fallback.
        $currency = '';

        foreach (array('invoiceCurrency', 'currency', 'userCurrency') as $key) {
            if (!empty($params[$key])) {
                $currency = strtoupper(trim($params[$key]));
                break;
            }
        }

        if ($currency === '') {
            $currency = strtoupper(trim($params['plugin_twittpay_Store Currency']));
        }

        if ($currency === '') {
            $currency = 'BDT';
        }

        $baseURL     = rtrim(CE_Lib::getSoftwareURL(), '/') . '/';
        $callbackURL = $baseURL . "plugins/gateways/twittpay/callback.php";
        $cancelURL   = isset($params['invoiceviewURLCancel']) ? $params['invoiceviewURLCancel'] : $baseURL;

        $data = array(
            'cus_name'    => trim($firstname . ' ' . $lastname),
            'cus_email'   => $email !== '' ? $email : 'default@gmail.com',
            'amount'      => number_format(TwittPayApi::toBdt($amount, $currency, $rate), 2, '.', ''),
            'success_url' => $callbackURL,
            'cancel_url'  => $cancelURL,
            'webhook_url' => $callbackURL,
            'metadata'    => array(
                'invoiceid'        => (string) $invoiceId,
                'invoice_amount'   => $amount,
                'invoice_currency' => $currency,
                'source'           => 'clientexec',
            ),
        );

        $response = TwittPayApi::call($apiUrl, $apiKey, '/api/payment/create', $data);

        if (!empty($response['status']) && !empty($response['payment_url'])) {
            header('Location: ' . $response['payment_url']);
            exit();
        }

        // The raw response is never printed - an error string can carry the API
        // key back out to the customer.
        die("The payment could not be started. Please try again, or contact us if it keeps happening.");
    }

    function credit($params)
    {
    }
}
