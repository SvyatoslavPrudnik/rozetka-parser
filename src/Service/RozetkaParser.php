<?php

namespace App\Service;

use DOMDocument;
use DOMXPath;
use Exception;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RozetkaParser
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function parseCategory(string $categoryUrl, int $pageCount = 3): array
    {
        $products = [];

        for ($page = 1; $page <= $pageCount; $page++) {
            try {
                $url = $categoryUrl . "/page=$page";
                $response = $this->client->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
                        'Referer' => 'https://rozetka.com.ua/',
                    ],
                ]);

                // Проверяем статус ответа
                if ($response->getStatusCode() !== 200) {
                    throw new Exception("Не удалось загрузить страницу: $url, статус: " . $response->getStatusCode());
                }

                $content = $response->getContent();
            } catch (ClientExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $e) {
                echo "Ошибка запроса: " . $e->getMessage();
                continue; // Переходим к следующей странице
            }

            try {
                $dom = new DOMDocument();
                @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
                $xpath = new DOMXPath($dom);

                $titles = $xpath->query("//span[contains(@class, 'goods-tile__title')]");
                $prices = $xpath->query("//span[contains(@class, 'goods-tile__price-value')]");
                $images = $xpath->query("//a[contains(@class, 'goods-tile__picture')]/img[1]/@src | //a[contains(@class, 'goods-tile__picture')]/img[2]/@src");
                $links = $xpath->query("//a[contains(@class, 'goods-tile__picture')]/@href");

                // Проверка на равное количество элементов для избежания ошибок
                $itemsCount = min($titles->length, $prices->length, $images->length, $links->length);
                for ($i = 0; $i < $itemsCount; $i++) {
                    $title = trim($titles->item($i)->textContent);
                    $priceText = trim($prices->item($i)->textContent);
                    $price = (float) str_replace("\xc2\xa0", '', $priceText);
                    $imageUrl = $images->item($i)?->textContent;
                    $productUrl = $links->item($i)->textContent;

                    if (!$imageUrl || strpos($imageUrl, 'goods-stub.svg') !== false) {
                        $imageUrl = null; // Устанавливаем null для заглушек или пустых значений
                    }

                    $products[] = [
                        'name' => $title,
                        'price' => $price,
                        'imageUrl' => $imageUrl,
                        'productUrl' => $productUrl,
                    ];
                }
            } catch (Exception $e) {
                echo "Ошибка при парсинге HTML на странице $page: " . $e->getMessage();
                continue; // Переходим к следующей странице
            }
        }

        return $products;
    }
}


