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

            case 'www.conforama.fr':
                return $this->getPriceFromConforama($url);

            case 'www.backmarket.fr':
                return $this->getPriceFromBackMarket($url);

            case 'www.webdistrib.com':
                return $this->getPriceFromWebDistrib($url);

            case 'www.maginea.com':
                return $this->getPriceFromMaginea($url);

            case 'www.etrouvetout.com':
                return $this->getPriceFromETrouveTout($url);

            case 'www.allopneus.com':
                return $this->getPriceFromAlloPneus($url);

            case 'www.tyrigo.com':
                return $this->getPriceFromTyrigo($url);

            case 'www.lacooplr.fr':
                return $this->getPriceFromLaCoopLr($url);

            case 'www.magasins-privilege.fr':
                return $this->getPriceFromMagasinsPrivilege($url);

            case 'www.idealprice.fr':
                return $this->getPriceFromIdealPrice($url);

            case 'www.villatech.fr':
                return $this->getPriceFromVillaTech($url);

            case 'www.ubaldi.com':
                return $this->getPriceFromUbaldi($url);

            case 'track.effiliation.com':
                return $this->getPriceFromEffiliation($url);

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
        $done = false;
        $crawler->filter('.informations .price p .exponent')->each(function ($node) use (& $price, &$done){
            if( ! $done)
            {
                $price = ((int)$node->text());
                $done = true;
            }
        });
        $done = false;
        $crawler->filter('.informations .price p sup .fraction')->each(function ($node) use (& $price, & $done){
            if( ! $done)
            {
                $price += ((int)$node->text()) / 100;
            }
        });

        return $price;
    }

    protected function getPriceFromDarty($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
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
        $crawler->filter('#priceblock_ourprice')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $crawler->filter('#unqualifiedBuyBox .a-color-price')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $crawler->filter('#soldByThirdParty .a-color-price')->each(function ($node) use (& $price){
            $price = ($node->text());
        });        
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromRueDuCommerce($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
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

    protected function getPriceFromConforama($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('.priceEco .currentPrice')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromWebDistrib($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('#newProductPrice')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromMaginea($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('.price.sale.currency_EUR')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromETrouveTout($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('.block-cart-prod .price')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromAlloPneus($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('span[itemprop="price"]')->each(function ($node) use (& $price){
            $price = ($node->attr('content'));
        });
        $price = $this->formatPrice($price);

        return $price;
    }


    protected function getPriceFromTyrigo($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('.price--content.content--default')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromLaCoopLr($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('#our_price_display')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromMagasinsPrivilege($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;
        $crawler->filter('form .regular-price .price')->each(function ($node) use (& $price, & $done){
            if( ! $done)
            {
                $price = ($node->text());
                $done = true;
            }
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromIdealPrice($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;
        $crawler->filter('#our_price_display')->each(function ($node) use (& $price, & $done){
            if( ! $done)
            {
                $price = ($node->text());
                $done = true;
            }
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromVillaTech($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;
        $crawler->filter('#pricing > div > p[itemprop="price"]')->each(function ($node) use (& $price, &$done){
            if( ! $done)
            {
                $price = ($node->text());
                $done = true;
            }
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromUbaldi($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;
        $crawler->filter('.fa-infos-principales .prix.rebours-prix')->each(function ($node) use (& $price, &$done){
            if( ! $done)
            {
                $price = ($node->attr('data-prix-vente'));
                $done = true;
            }
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromEffiliation($url)
    {
        $queryString = parse_url($url, PHP_URL_QUERY);
        $params = array();
        parse_str($queryString, $params);
        $insideUrl = $params['url']; 
        return $this->getPrice($insideUrl);
    }

    protected function getPriceFromBackMarket($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('.price-wo-currency')->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        $price = $this->formatPrice($price);

        return $price;
    }


    
    public function formatPrice($price)
    {
        if(preg_match('!([0-9]+)€([0-9]+)!is', $price))
        {
            $price = str_replace('€', '.', $price);
        }
        if(preg_match('!([0-9]+)\.([0-9]{3,})!is', $price))
        {
            $price = str_replace('.', '', $price);
        }
        if(preg_match('!([0-9]+),([0-9]{3,})!is', $price))
        {
            $price = str_replace(',', '', $price);
        }
        $price = str_replace('€', '', $price);
        $price = str_replace(' ', '', $price);
        $price = str_replace(' ', '', $price);        
        $price = str_replace('EUR', '', $price);
        $price = str_replace(',', '.', $price);
        $price = trim($price);
        
        $price = (float) $price;
        return $price;
    }
}