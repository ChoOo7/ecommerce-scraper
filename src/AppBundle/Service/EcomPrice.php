<?php

/*
 * This file is part of the SncRedisBundle package.
 *
 * (c) Henrik Westphal <henrik.westphal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Service;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;


class EcomPrice
{


    /**
     * {@inheritDoc}
     */
    public function getPrice($url)
    {
        $hostname = parse_url($url, PHP_URL_HOST);
        $hostname = strtolower($hostname);
        switch($hostname)
        {
            case 'www.boulanger.fr':
            case 'www.boulanger.com':
                return $this->getPriceFromBoulanger($url);

            case 'www.darty.fr':
            case 'www.darty.com':
                return $this->getPriceFromDarty($url);

            case 'www.amazon.fr':
                return $this->getPriceFromAmazon($url);

            case 'www.rueducommerce.fr':
                return $this->getPriceFromRueDuCommerce($url);

            case 'www.cdiscount.com':
                return $this->getPriceFromCDiscount($url);

            case 'www.arredatutto.com':
                return $this->getPriceFromArredatutto($url);

            case 'www.priceminister.com':
                return $this->getPriceFromPriceMinister($url);

            case 'www.mistergooddeal.com':
                return $this->getPriceFromMisterGoodDeal($url);

            case 'www.electrodepot.fr':
                return $this->getPriceFromElectroDepot($url);

            case 'www.abribatelectromenager.fr':
                return $this->getPriceFromAbribatElectromenager($url);
                
            case 'www.pixmania.fr':
                //On sait pas faire
                return null;
                
        }
        return null;
    }

    protected function getPriceFromBoulanger($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('.informations .price p .exponent')->each(function ($node) use (& $price){
            $price = ((int)$node->text());
        });
        $crawler->filter('.informations .price p sup .fraction')->each(function ($node) use (& $price){
            $price += ((int)$node->text())/100;
        });

        return $price;
    }

    protected function getPriceFromDarty($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('.product_metas  .darty_prix')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = str_replace('€', '', $price);
        $price = str_replace(',', '.', $price);
        $price = (float) $price;

        return $price;
    }

    protected function getPriceFromAmazon($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('#priceblock_ourprice')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = str_replace('EUR ', '', $price);
        $price = str_replace(',', '.', $price);
        $price = (float) $price;

        return $price;
    }

    protected function getPriceFromRueDuCommerce($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $moid = null;
        if(stripos($url, '#moid:') !== false)
        {
            $tmp = explode('#moid:', $url);
            $moid = $tmp[1];            
        }
        $crawler->filter('.price.main .price p')->each(function ($node) use (& $price, $moid){
            $price = $node->text();
        });
        $crawler->filter('li[data-moid="'.$moid.'"] .sellerPrice .price')->each(function ($node) use (& $price, $moid){
            $price = $node->text();
        });
        
        $crawler->filter('select.variantChanger option')->each(function ($node) use (& $price, $moid){
            
            $_price = (string)($node->attr("data-min-price"));
            $_moid = (string)($node->attr("data-moid"));
            if($moid)
            {
                if($_moid == $moid)
                {
                    $price = $_price;
                }
            }elseif($price == 0){
                $price = $_price;
            }
        });
        
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromCDiscount($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('.fTopPrice .price')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromArredatutto($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('meta[itemprop="price"]')->each(function ($node) use (& $price){
            $price = ($node->attr('content'));
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromPriceMinister($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('.gsRow .col-xs-6.prdInfos .price.spacerBottomXs')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromMisterGoodDeal($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('#darty_product_base_info .price.price_xxl')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromElectroDepot($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        //var_dump($crawler);
        $crawler->filter('.regular-price')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromAbribatElectromenager($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('meta[itemprop="price"]')->each(function ($node) use (& $price){
            $price = ($node->attr('content'));
        });
        $price = $this->formatPrice($price);

        return $price;
    }


    
    protected function formatPrice($price)
    {
        if(preg_match('!([0-9]+)€([0-9]+)!is', $price))
        {
            $price = str_replace('€', '.', $price);
        }
        $price = str_replace('€', '', $price);
        $price = str_replace('EUR ', '', $price);
        $price = str_replace(',', '.', $price);
        $price = (float) $price;
        return $price;
    }
}