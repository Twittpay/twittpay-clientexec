<?php

/**
 * TwittPay - Clientexec gateway callback
 * ---------------------------------------------------------------------------
 * This one file answers both callers:
 *
 *   the customer coming back    GET, ends in a redirect to the invoice
 *   the gateway's webhook       POST, ends in a short JSON answer
 *
 * Nothing on the request is believed. The transaction id is the only thing read
 * from it, and the payment is then verified against the API before the invoice is
 * marked paid.
 *
 * @version 1.0.0
 */

require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Invoice.php';
require_once dirname(__FILE__) . '/TwittPayApi.php';

class PlugintwittpayCallback extends PluginCallback
{
    function processCallback()
    {
        $settings = new Plugin('', 'twittpay', $this->user);
        $apiUrl   = trim($settings->GetPluginVariable("plugin_twittpay_Endpoint URL"));
        $apiKey   = trim($settings->GetPluginVariable("plugin_twittpay_API Key"));

        // A POST with no browser behind it is the webhook.
        $isWebhook = (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');

        $transactionId = TwittPayApi::transactionId();

        if ($apiUrl === '' || $apiKey === '' || $transactionId === '') {
            $this->finish($isWebhook, false, 'No transaction id received.', '');
            return;
        }

        $verified = TwittPayApi::call($apiUrl, $apiKey, '/api/payment/verify', array('transaction_id' => $transactionId));
        $status   = TwittPayApi::readStatus($verified);

        if ($status === '') {
            $this->finish($isWebhook, false, 'The gateway does not know this transaction.', '');
            return;
        }

        $meta      = TwittPayApi::metadata($verified);
        $invoiceId = isset($meta['invoiceid']) ? $meta['invoiceid'] : '';

        if ($invoiceId === '') {
            $this->finish($isWebhook, false, 'This payment carries no invoice reference.', '');
            return;
        }

        // The invoice's own amount and currency, not the converted BDT.
        $amount   = isset($meta['invoice_amount']) ? $meta['invoice_amount'] : (isset($verified['amount']) ? $verified['amount'] : 0);
        $currency = !empty($meta['invoice_currency']) ? $meta['invoice_currency'] : 'BDT';
        $method   = !empty($verified['payment_method']) ? $verified['payment_method'] : 'TwittPay';
        $price    = $amount . ' ' . $currency;

        $cPlugin = new Plugin($invoiceId, 'twittpay', $this->user);
        $cPlugin->setAmount($amount);
        $cPlugin->setAction('charge');

        if ($status === 'PENDING') {
            // Sent, not approved by the merchant yet. The gateway calls this URL
            // again with the answer, so the invoice is left open rather than
            // rejected.
            $this->finish($isWebhook, true, 'The payment is being checked and will be applied once it clears.', $invoiceId);
            return;
        }

        if ($status !== 'COMPLETED') {
            $cPlugin->PaymentRejected($method . " payment of $price failed (Invoice: " . $invoiceId . ")");
            $this->finish($isWebhook, true, 'Payment not completed.', $invoiceId);
            return;
        }

        // A webhook that arrives twice hits this and does nothing the second time.
        if ($cPlugin->IsUnpaid() == 1) {
            $cPlugin->PaymentAccepted(
                $amount,
                $method . " payment of $price successful (Invoice: " . $invoiceId . ", Transaction ID: " . $transactionId . ")"
            );
        }

        $this->finish($isWebhook, true, 'Payment recorded.', $invoiceId, true);
    }

    /**
     * The webhook gets JSON, the customer gets sent back to their invoice.
     */
    private function finish($isWebhook, $ok, $message, $invoiceId, $paid = false)
    {
        if ($isWebhook) {
            header('Content-Type: application/json');
            echo json_encode(array('status' => (bool) $ok, 'message' => $message));
            exit;
        }

        $base = rtrim(CE_Lib::getSoftwareURL(), '/');

        if ($invoiceId !== '') {
            $url = $base . '/index.php?fuse=billing&controller=invoice&view=invoice&id=' . urlencode($invoiceId);

            if ($paid) {
                $url .= '&paid=1';
            }
        } else {
            $url = $base . '/index.php?fuse=billing&controller=invoice&view=allinvoices&filter=open';
        }

        header('Location: ' . $url);
        exit;
    }
}
