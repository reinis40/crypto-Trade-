<?php
require 'vendor/autoload.php';

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Carbon\Carbon;

$apiKey = "ccb58a8c-61b0-4c84-8289-5e562a8476a1";
$walletFile = 'wallet.json';
$transactionsFile = 'transactions.json';

function getApiData($apiKey, $url, $parameters): array
{
    $headers = [
          'Accepts: application/json',
          'X-CMC_PRO_API_KEY: ' . $apiKey
    ];
    $qs = http_build_query($parameters);
    $request = "$url?$qs";
    $curl = curl_init();
    curl_setopt_array($curl, array(
          CURLOPT_URL => $request,
          CURLOPT_HTTPHEADER => $headers,
          CURLOPT_RETURNTRANSFER => 1
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}

function showCrypto($apiKey)
{
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
    $parameters = [
          'start' => '1',
          'limit' => '10',
          'convert' => 'EUR'
    ];
    $data = getApiData($apiKey, $url, $parameters);

    $output = new ConsoleOutput();
    $table = new Table($output);
    $table->setHeaders(['Name', 'Symbol', 'Price per 1 coin (EUR)']);

    foreach ($data['data'] as $crypto) {
        $table->addRow([
              $crypto['name'],
              $crypto['symbol'],
              "€" . number_format($crypto['quote']['EUR']['price'], 2)
        ]);
    }
    $table->render();
}

function loadJsonFile($file)
{
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
    }
    return json_decode(file_get_contents($file), true);
}

function saveJsonFile($file, $data)
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function showWallet($walletFile)
{
    $wallet = loadJsonFile($walletFile);
    $output = new ConsoleOutput();
    $table = new Table($output);
    $table->setHeaders(['currency', 'amount', 'EUR']);
    foreach ($wallet as $symbol => $amount) {
        $table->addRow([
              $symbol,
              $amount,
              number_format(convertToEUR($symbol, $amount), 2)
        ]);
    }
    $table->render();
}

function showTransactions($walletLogs)
{
    $transactions = loadJsonFile($walletLogs);
    echo "Transactions:\n";
    foreach ($transactions as $transaction) {
        echo "{$transaction['type']} {$transaction['amount']} of {$transaction['symbol']} at {$transaction['price']} EUR each on {$transaction['date']}\n";
    }
}

function buyCrypto($apiKey, $walletFile, $transactionsFile, $symbol, $amountEUR)
{
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';
    $parameters = [
          'symbol' => $symbol,
          'convert' => 'EUR'
    ];
    $data = getApiData($apiKey, $url, $parameters);
    $price = $data['data'][$symbol]['quote']['EUR']['price'];

    $wallet = loadJsonFile($walletFile);
    $transactions = loadJsonFile($transactionsFile);

    if (!isset($wallet['EUR'])) {
        $wallet['EUR'] = 1000;
    }

    if ($wallet['EUR'] >= $amountEUR) {
        $wallet['EUR'] -= $amountEUR;
        $amountCrypto = $amountEUR / $price;
        if (!isset($wallet[$symbol])) {
            $wallet[$symbol] = 0;
        }
        $wallet[$symbol] += $amountCrypto;
        $transactions[] = [
              'type' => 'buy',
              'symbol' => $symbol,
              'amount' => $amountCrypto,
              'price' => $price,
              'date' => Carbon::now()->toDateTimeString()
        ];

        saveJsonFile($walletFile, $wallet);
        saveJsonFile($transactionsFile, $transactions);
        echo "Bought $amountCrypto of $symbol at €$price each.\n";
    } else {
        echo "Insufficient funds to buy €$amountEUR of $symbol.\n";
    }
}

function convertToEUR($symbol, $amount)
{
    if ($symbol == 'EUR') {
        return $amount;
    } else {
        $apiKey = "ccb58a8c-61b0-4c84-8289-5e562a8476a1";
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';
        $parameters = [
              'symbol' => $symbol,
              'convert' => 'EUR'
        ];

        $data = getApiData($apiKey, $url, $parameters);

        $priceInEUR = $data['data'][$symbol]['quote']['EUR']['price'];
        return $amount * $priceInEUR;
    }
}

function sellCrypto($apiKey, $walletFile, $transactionsFile, $symbol)
{
    $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';
    $parameters = [
          'symbol' => $symbol,
          'convert' => 'EUR'
    ];
    $data = getApiData($apiKey, $url, $parameters);
    $price = $data['data'][$symbol]['quote']['EUR']['price'];

    $wallet = loadJsonFile($walletFile);
    $transactions = loadJsonFile($transactionsFile);

    if (isset($wallet[$symbol]) && $wallet[$symbol] > 0) {
        $amountCrypto = $wallet[$symbol];
        $wallet['EUR'] += $amountCrypto * $price;
        unset($wallet[$symbol]);
        $transactions[] = [
              'type' => 'sell',
              'symbol' => $symbol,
              'amount' => $amountCrypto,
              'price' => $price,
              'date' => Carbon::now()->toDateTimeString()
        ];

        saveJsonFile($walletFile, $wallet);
        saveJsonFile($transactionsFile, $transactions);
        echo "Sold $amountCrypto of $symbol at €$price each.\n";
    } else {
        echo "Insufficient $symbol to sell.\n";
    }
}

while (true) {
    echo "\n1. list of crypto\n2. buy\n3. sell\n4. view wallet\n5. view logs\n6. exit\n";
    $input = readline("select an option: ");

    switch ($input) {
        case 1:
            showCrypto($apiKey);
            break;
        case 2:
            $symbol = readline("Enter cryptocurrency symbol: ");
            $amountEUR = readline("Enter amount in EUR to buy: ");
            buyCrypto($apiKey, $walletFile, $transactionsFile, strtoupper($symbol), (float)$amountEUR);
            break;
        case 3:
            $symbol = readline("Enter cryptocurrency symbol: ");
            sellCrypto($apiKey, $walletFile, $transactionsFile, strtoupper($symbol));
            break;
        case 4:
            showWallet($walletFile);
            break;
        case 5:
            showTransactions($transactionsFile);
            break;
        case 6:
            exit("Goodbye!\n");
        default:
            echo "Invalid option, please try again.\n";
    }
}
