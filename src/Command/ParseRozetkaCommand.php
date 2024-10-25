<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\RozetkaParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:parse-rozetka')]
class ParseRozetkaCommand extends Command
{
    private RozetkaParser $parser;
    private EntityManagerInterface $entityManager;

    public function __construct(RozetkaParser $parser, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->parser = $parser;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->addArgument('categoryUrl', InputArgument::OPTIONAL, 'URL категории товаров на Rozetka', 'https://rozetka.com.ua/ua/notebooks/c80004')
            ->addArgument('pageCount', InputArgument::OPTIONAL, 'Количество страниц для парсинга', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $categoryUrl = (string) $input->getArgument('categoryUrl');
        $pageCount = (int) $input->getArgument('pageCount');

        $products = $this->parser->parseCategory($categoryUrl, $pageCount);

        foreach ($products as $data) {
            $product = new Product();
            $product->setName($data['name']);
            $product->setPrice($data['price']);
            $product->setImageUrl($data['imageUrl']);
            $product->setProductUrl($data['productUrl']);

            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();

        $output->writeln('Products saved successfully.');
        return Command::SUCCESS;
    }
}

