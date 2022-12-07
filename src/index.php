<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: oryAccessToken
$config = Ory\Kratos\Client\Configuration::getDefaultConfiguration()->setHost("http://ory.test.info/.ory/kratos/private");
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = Ory\Kratos\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Authorization', 'Bearer');


$apiInstance = new Ory\Kratos\Client\Api\IdentityApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->listIdentities();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling IdentityApi->listIdentities: ', $e->getMessage(), PHP_EOL;
}

?>