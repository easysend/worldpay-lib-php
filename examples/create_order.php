
<?php
/**
 * PHP library version: v1.7
 */
require_once('../lib/Worldpay.php');

// Initialise Worldpay class with your SERVICE KEY
$worldpay = new Worldpay("your-service-key");

// Sometimes your SSL doesnt validate locally
// DONT USE IN PRODUCTION
$worldpay->disableSSLCheck(true);

$token = $_POST['token'];
$name = $_POST['name'];
$amount = $_POST['amount'];
$_3ds = (isset($_POST['3ds'])) ? $_POST['3ds'] : false;
$authoriseOnly = (isset($_POST['authoriseOnly'])) ? $_POST['authoriseOnly'] : false;
$customerIdentifiers = (!empty($_POST['customer-identifiers'])) ? json_decode($_POST['customer-identifiers']) : array();

include('header.php');

// Try catch
try {
    // Customers billing address
    $billing_address = array(
        "address1"=> $_POST['address1'],
        "address2"=> $_POST['address2'],
        "address3"=> $_POST['address3'],
        "postalCode"=> $_POST['postcode'],
        "city"=> $_POST['city'],
        "state"=> '',
        "countryCode"=> $_POST['countryCode']
    );

    // Customers delivery address
    $delivery_address = array(
        "firstName" => $_POST['delivery-firstName'],
        "lastName" => $_POST['delivery-lastName'],
        "address1"=> $_POST['delivery-address1'],
        "address2"=> $_POST['delivery-address2'],
        "address3"=> $_POST['delivery-address3'],
        "postalCode"=> $_POST['delivery-postcode'],
        "city"=> $_POST['delivery-city'],
        "state"=> '',
        "countryCode"=> $_POST['delivery-countryCode']
    );
    $response = $worldpay->createOrder(array(
        'token' => $token, // The token from WorldpayJS
        'orderDescription' => $_POST['description'], // Order description of your choice
        'amount' => $amount*100, // Amount in pence
        'is3DSOrder' => $_3ds, // 3DS
        'authoriseOnly' => $authoriseOnly,
        'orderType' => $_POST['order-type'], //Order Type: ECOM/MOTO/RECURRING
        'currencyCode' => $_POST['currency'], // Currency code
        'settlementCurrency' => $_POST['settlement-currency'], // Settlement currency code
        'name' => ($_3ds) ? '3D' : $name, // Customer name
        'billingAddress' => $billing_address, // Billing address array
        'deliveryAddress' => $delivery_address, // Delivery address array
        'customerIdentifiers' => (!is_null($customerIdentifiers)) ? $customerIdentifiers : array(), // Custom indentifiers
        'statementNarrative' => $_POST['statement-narrative'],
        'customerOrderCode' => 'A123' // Order code of your choice
    ));

    if ($response['paymentStatus'] === 'SUCCESS' ||  $response['paymentStatus'] === 'AUTHORIZED') {
        // Create order was successful!
        $worldpayOrderCode = $response['orderCode'];
        echo '<p>Order Code: <span id="order-code">' . $worldpayOrderCode . '</span></p>';
        echo '<p>Token: <span id="token">' . $response['token'] . '</span></p>';
        echo '<p>Payment Status: <span id="payment-status">' . $response['paymentStatus'] . '</span></p>';
        echo '<pre>' . print_r($response, true). '</pre>';
        // TODO: Store the order code somewhere..
    } elseif ($response['is3DSOrder']) {
        // Redirect to URL
        // STORE order code in session
        $_SESSION['orderCode'] = $response['orderCode'];
        ?>
        <form id="submitForm" method="post" action="<?php echo $response['redirectURL'] ?>">
            <input type="hidden" name="PaReq" value="<?php echo $response['oneTime3DsToken']; ?>"/>
            <input type="hidden" id="termUrl" name="TermUrl" value="http://localhost/3ds_redirect.php"/>
            <script>
                document.getElementById('termUrl').value = window.location.href.replace('create_order.php', '3ds_redirect.php');
                document.getElementById('submitForm').submit();
            </script>
        </form>
        <?php
    } else {
        // Something went wrong
        echo '<p id="payment-status">' . $response['paymentStatus'] . '</p>';
        throw new WorldpayException(print_r($response, true));
    }
} catch (WorldpayException $e) { // PHP 5.3+
    // Worldpay has thrown an exception
    echo 'Error code: ' . $e->getCustomCode() . '<br/>
    HTTP status code:' . $e->getHttpStatusCode() . '<br/>
    Error description: ' . $e->getDescription()  . ' <br/>
    Error message: ' . $e->getMessage();
} catch (Exception $e) {  // PHP 5.2
    echo 'Error message: '. $e->getMessage();
}
