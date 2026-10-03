# TwittPay for Clientexec

Clientexec payment plugin

Part of the [TwittPay](https://twittpay.com) addon family.

## Quick start

1. Download the latest zip from the **Releases** page of this repository.
2. Install it on your Clientexec following the guide below.
3. Open the TwittPay settings and enter your **Brand Key**. You can get it from [your dashboard](https://twittpay.com/user/brands).
4. Make a small test payment to confirm everything works.

Payments are always re-verified on your server before an order or invoice is marked paid.

## Detailed installation guide

```text
===========================================================================
 TWITTPAY - Clientexec payment gateway
===========================================================================

 WHERE IT GOES
   Extract this zip at your Clientexec root - the folder that has plugins/ and
   index.php in it. Four files land in place:

     plugins/gateways/twittpay/PluginTwittpay.php          the gateway
     plugins/gateways/twittpay/PluginTwittpayCallback.php  the callback
     plugins/gateways/twittpay/TwittPayApi.php             shared API calls
     plugins/gateways/twittpay/callback.php                   entry point

   Keep the file names exactly as they are. Clientexec finds a plugin by matching
   the file name to the class name, and Linux servers are case sensitive.

 INSTALL
   1. Settings -> Plugins -> Gateways.
   2. Find "TwittPay", click Edit and fill in:

        Signup Name       what the customer sees at checkout

        Endpoint URL      your own gateway address, e.g.
                          https://checkout.twittpay.com
                          (the API host shown on your gateway's developer page)

        Brand Key           from your gateway dashboard, under Brands

        USD to BDT Rate   only used when the invoice is not already in BDT

        Store Currency    the currency your invoices are in. Only used when
                          Clientexec does not pass a currency with the payment.

   3. Turn the plugin on and pay a test invoice.

 HOW IT WORKS
   * Choosing this gateway on an invoice creates the payment and sends the
     customer straight to the gateway's checkout page.
   * Both the returning customer and the gateway's own webhook come back to
     plugins/gateways/twittpay/callback.php.
   * Nothing on that request is believed. The transaction id is the only thing
     read from it, and the payment is then verified against the API.
   * COMPLETED marks the invoice paid, but only while the invoice is still
     unpaid - so a webhook that arrives twice cannot pay it twice.
   * PENDING leaves the invoice open. The customer has sent the money and your
     merchant has not approved it. The gateway calls again with the answer, and
     that call settles the invoice. Do not ask the customer to pay twice.

 CURRENCY
   The gateway charges BDT.

   * A BDT invoice is sent as it is.
   * Any other currency is multiplied by the USD to BDT Rate, and the invoice's
     own amount and currency ride along in metadata - so the payment Clientexec
     records stays in the invoice's currency.

 WHAT TO WATCH
   * The Endpoint URL is your API host. Pasting the whole endpoint or a trailing
     /api is fine - only the scheme and host are used.
   * The callback URL must be reachable from the internet. Your gateway's server
     calls it directly.
   * Refunds are not done through the API. Refund on the gateway side, then
     record it in Clientexec by hand.

 FOUR FIXES OVER THE ORIGINAL
   * The PipraPay callback compared an Brand Key sent in a webhook header and
     rejected anything else. This gateway's webhook is not signed and sends no
     key, so that check would have refused every real call. It is gone -
     verification against the API does the same job properly, because a made-up
     transaction id simply does not verify.
   * The original called PaymentAccepted($amount, ...) with a variable that was
     never set, so the recorded amount was empty. Fixed.
   * On a failed payment the original called PaymentRejected on a plugin object
     built with an empty invoice id, so the rejection landed nowhere. Fixed.
   * The original printed the raw API response to the customer on an error. An
     error string can carry your Brand Key back out, so this port shows a plain
     message instead.

 CHECKED
   The PHP was checked with a lexer that balances braces only inside real PHP
   code. PHP itself was NOT run - there is no PHP binary on the machine this was
   built on, so php -l was never executed. Test it on a staging install first.
```
