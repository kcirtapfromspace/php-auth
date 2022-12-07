// Copyright © 2022 Ory Corp
// SPDX-License-Identifier: Apache-2.0

<?php
  require 'vendor/autoload.php';
  require_once 'app.php';

  error_reporting(E_ERROR | E_PARSE);

  $proxyPort = getenv("PROXY_PORT");
  if ($proxyPort == "") $proxyPort = "4000";

  $app = new App;
  // register a new Ory client with the URL set to the Ory CLI Proxy
  // we can also read the URL from the env or a config file
  // $config = Ory\Client\Configuration::getDefaultConfiguration()->setHost(sprintf("http://ory.test.info/:%s/.ory", $proxyPort));
  // $app->ory = new Ory\Client\Api\FrontendApi(new GuzzleHttp\Client(), $config);
  $config = Ory\Client\Configuration::getDefaultConfiguration()->setHost("https://ory.test.info");
  // $app->ory = new Ory\Client\Api\FrontendApi(new GuzzleHttp\Client(), $config);
  $app->ory = new Ory\Client\Api\FrontendApi(new GuzzleHttp\Client(), $config);
  // $apiInstance = new Ory\Oathkeeper\Client\Api\ApiApi(new GuzzleHttp\Client());
  // try {
  //   $apiInstance->decisions();
  //   } catch (Exception $e) {
  //       echo 'Exception when calling ApiApi->decisions: ', $e->getMessage(), PHP_EOL;
  //   }

  $router = new \Bramus\Router\Router();
  $router->before('GET', '/php-auth', $app->validateSession());
  $router->get('/php-auth', $app->printDashboard());
  $router->run();
?>