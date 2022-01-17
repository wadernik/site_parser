<?php

require "vendor/autoload.php";
require "SiteParser.php";

$baseUrl = 'https://y-ola.unixmagazin.ru';
$mainDataPath = 'katalog/akb?per-page=all';
// $imagesFolder = 'images';

$parser = new SiteParser($baseUrl, $mainDataPath);

echo "Retrieving...\n";
$parsedData = $parser->parse();

echo "Retrieved and parsed!\n";

if (empty ($parsedData)) {
    echo "Data is empty, nothing to save. Probably, something went wrong";
    return;
}

echo "Saving...\n";
try {
    $encodedData = json_encode($parsedData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}

file_put_contents('data.json', $encodedData);

echo "Done.\n";
