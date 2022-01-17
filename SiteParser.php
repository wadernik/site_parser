<?php

use PHPHtmlParser\Dom;
use Psr\Http\Client\ClientExceptionInterface;

class SiteParser
{
    private string $baseUrl;
    private string $mainDataUrlPath;
    private string $imagesFolder;

    private const DELIMITER = '/';
    private const RETRIEVE_DETAILS_PAUSE = '2'; // pause between loading detailed information per product; in seconds
    private const DEFAULT_IMAGE_EXT = 'jpg';
    private const DEFAULT_IMAGE_FOLDER = 'images';

    // class names
    private const MAIN_CONTENT_CLASS = '.cl-item-block';
    private const MAIN_DATA_NAME_CLASS = '.link-line';
    private const MAIN_DATA_CODE_CLASS = '.cl-item-article';
    private const PROPERTIES_CLASS = '.property';
    private const PROPERTY_NAME_CLASS = '.property__name';
    private const PROPERTY_VALUE_CLASS = '.property__val';

    // property keys
    private array $propertiesGeneralKeysDictionary = [
        'Код' => 'code',
        'Применяемость' => 'applicability',
        'Производитель' => 'manufacturer',
        'Вес' => 'weight',
        'Емкость' => 'capacity',
        'Пусковой ток' => 'inrush_current',
        'Полярность' => 'polarity',
        'Тип корпуса' => 'housing',
        'Тип клемм' => 'cleat_type',
        'Напряжение' => 'voltage',
        'Типоразмер' => 'size_type',
        'Крепление' => 'holder',
        'Технология' => 'technology',
        'Классификация АКБ' => 'type',
        'Длина' => 'length',
        'Ширина' => 'width',
        'Высота' => 'height',
    ];

    /**
     * @param string $baseUrl
     * @param string $mainDataUrlPath
     * @param string $imagesFolder
     */
    public function __construct(string $baseUrl, string $mainDataUrlPath, string $imagesFolder = '')
    {
        // Remove trailing slash if needed
        if (strlen($baseUrl) > 1 && substr($baseUrl, -1) === '/') {
            $baseUrl = rtrim($baseUrl, '/');
        }
        $this->baseUrl = trim($baseUrl);

        // Remove slash on the beginning of the string if needed
        if ($mainDataUrlPath[0] === '/') {
            $mainDataUrlPath = ltrim($mainDataUrlPath, '/');
        }
        $this->mainDataUrlPath = $mainDataUrlPath;

        if ($imagesFolder === '') {
            $imagesFolder = self::DEFAULT_IMAGE_FOLDER;
        }
        $this->imagesFolder = $imagesFolder;

        // Creating folder for images if needed
        if (
            !is_dir($this->imagesFolder)
            && !mkdir($concurrentDirectory = $this->imagesFolder, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            $this->imagesFolder = '';
            echo sprintf('Directory "%s" was not created', $this->imagesFolder) . "\n";
        }
    }

    /**
     * @return array
     */
    public function parse(): ?array
    {
        $dom = new Dom();
        $mainContents = null;

        try {
            $dom->loadFromUrl($this->baseUrl . self::DELIMITER . $this->mainDataUrlPath);
            $mainContents = $dom->find(self::MAIN_CONTENT_CLASS);
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
        } catch (ClientExceptionInterface $e) {
            echo $e->getMessage() . "\n";
        }

        $elements = [];
        foreach ($mainContents as $content) {
            // Retrieve product's code
            $codeElement = $content->find(self::MAIN_DATA_CODE_CLASS);
            $productCode = $codeElement ? substr(trim($codeElement->text), 8) : '0';

            // Retrieve image URL
            $imgElement = $content->find('img');
            $productImageSourceUrl = $this->baseUrl
                . self::DELIMITER
                . ($imgElement ? $imgElement->getAttribute('src') : '');

            // Save image to folder if folder exists
            $fileFullPath = '';
            if ($this->imagesFolder !== '') {
                $pathInfo = pathinfo($productImageSourceUrl);
                $extension = $pathInfo['extension'] ?? self::DEFAULT_IMAGE_EXT;
                $fileName = ($pathInfo['filename'] ?? $codeElement) . ".$extension";
                $fileFullPath = $this->imagesFolder . "/$fileName";
                $file = file_get_contents($productImageSourceUrl);
                file_put_contents($fileFullPath, $file);
            }

            // Retrieve product's name and URL to detail's page
            $nameElement = $content->find(self::MAIN_DATA_NAME_CLASS);
            $productName = $nameElement ? trim($nameElement->text) : '';
            $productDetailsUrl = $this->baseUrl . $nameElement ? trim($nameElement->getAttribute('href')) : '';

            // Retrieve product's details
            $generalProductDetails = [];
            $additionalProductDetails = [];
            try {
                $detailsContents = $dom->loadFromUrl($this->baseUrl . $productDetailsUrl);
                $detailsHtml = $detailsContents->find(self::PROPERTIES_CLASS);

                foreach ($detailsHtml as $detail) {
                    $detailTitle = trim($detail->find(self::PROPERTY_NAME_CLASS)->text);
                    $detailValue = trim($detail->find(self::PROPERTY_VALUE_CLASS)->text);

                    $titleCode = $this->propertiesGeneralKeysDictionary[$detailTitle] ?? '';
                    if ($titleCode !== '') {
                        $generalProductDetails[$titleCode] = $detailValue;
                    } else {
                        $additionalProductDetails[$detailTitle] = $detailValue;
                    }
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . "\n";
            } catch (ClientExceptionInterface $e) {
                echo $e->getMessage() . "\n";
            }

            try {
                $elements[] = array_merge(
                    [
                        'name' => $productName,
                        'code' => $productCode,
                        'img' => $fileFullPath ?: $productImageSourceUrl,
                    ],
                    $generalProductDetails,
                    ['additional_properties' => json_encode($additionalProductDetails, JSON_UNESCAPED_UNICODE)]
                );
            } catch (\Exception $e) {
                echo $e->getMessage();
            }

            break;
            sleep(self::RETRIEVE_DETAILS_PAUSE);
        }

        return $elements;
    }
}
