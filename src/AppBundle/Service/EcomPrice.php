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
    public function getPrice($url, $tryLeft = 3)
    {
        $price = null;
        $hostname = parse_url($url, PHP_URL_HOST);
        $hostname = strtolower($hostname);
        switch($hostname)
        {
            case 'www.boulanger.fr':
            case 'www.boulanger.com':
                $price = $this->getPriceFromBoulanger($url);
                break;

            case 'www.darty.fr':
            case 'www.darty.com':
                $price = $this->getPriceFromDarty($url);
                break;

            case 'www.amazon.fr':
                $price = $this->getPriceFromAmazon($url);
                break;

            case 'www.rueducommerce.fr':
                $price = $this->getPriceFromRueDuCommerce($url);
                break;

            case 'www.cdiscount.com':
                $price = $this->getPriceFromCDiscount($url);
                break;

            case 'www.arredatutto.com':
                $price = $this->getPriceFromArredatutto($url);
                break;

            case 'www.priceminister.com':
                $price = $this->getPriceFromPriceMinister($url);
                break;

            case 'www.mistergooddeal.com':
                $price = $this->getPriceFromMisterGoodDeal($url);
                break;

            case 'www.electrodepot.fr':
                $price = $this->getPriceFromElectroDepot($url);
                break;

            case 'www.abribatelectromenager.fr':
                $price = $this->getPriceFromAbribatElectromenager($url);
                break;

            case 'www.conforama.fr':
                $price = $this->getPriceFromConforama($url);
                break;

            case 'www.backmarket.fr':
                $price = $this->getPriceFromBackMarket($url);
                break;

            case 'www.webdistrib.com':
                $price = $this->getPriceFromWebDistrib($url);
                break;

            case 'www.maginea.com':
                $price = $this->getPriceFromMaginea($url);
                break;

            case 'www.etrouvetout.com':
                $price = $this->getPriceFromETrouveTout($url);
                break;

            case 'www.allopneus.com':
                $price = $this->getPriceFromAlloPneus($url);
                break;

            case 'www.touspourunprix.fr':
                $price = $this->getPriceFromTousPourUnPrix($url);
                break;

            case 'www.tyrigo.com':
                $price = $this->getPriceFromTyrigo($url);
                break;

            case 'www.lacooplr.fr':
                $price = $this->getPriceFromLaCoopLr($url);
                break;

            case 'www.magasins-privilege.fr':
                $price = $this->getPriceFromMagasinsPrivilege($url);
                break;

            case 'www.idealprice.fr':
                $price = $this->getPriceFromIdealPrice($url);
                break;

            case 'www.villatech.fr':
                $price = $this->getPriceFromVillaTech($url);
                break;

            case 'www.ubaldi.com':
                $price = $this->getPriceFromUbaldi($url);
                break;

            case 'track.effiliation.com':
                $price = $this->getPriceFromEffiliation($url);
                break;

            case 'www.vieffetrade.eu':
                $price = $this->getPriceFromVieffetrade($url);
                break;

            case 'www.klarstein.fr':
                $price = $this->getPriceFromKlarstein($url);
                break;

            case 'www.centralepneus.fr':
                return $this->getPriceFromCentralePneus($url);
                

            case 'www.pixmania.fr':
                //On sait pas faire
                return null;
                
        }
        if(( empty($price) || $price < 0 ) && $tryLeft > 0)
        {
            $tryLeft--;
            return $this->getPrice($url, $tryLeft);
        }
        return $price;
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
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromAmazon($url, $tryLeft = 5)
    {
        $stack = new \GuzzleHttp\HandlerStack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        $stack->push(\GuzzleTor\Middleware::tor());
        $torGuzzleClient = new \GuzzleHttp\Client(['handler' => $stack]);
        //
        
        /*
        $response = $client->get('https://check.torproject.org/');
        var_dump((string)$response->getBody());die();
        */
        
        //sleep(rand(0,3));
        $price = 0;

        $client = new \Goutte\Client();
        $client->setClient($torGuzzleClient);
        $client->setHeader('User-Agent', "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.101 Safari/537.36");
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
        
        if($price == 0 && $tryLeft > 0)
        {
            sleep(6-$tryLeft);
            $tryLeft--;
            return $this->getPriceFromAmazon($url, $tryLeft);
        }

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

        $stack = new \GuzzleHttp\HandlerStack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        $stack->push(\GuzzleTor\Middleware::tor());
        $torGuzzleClient = new \GuzzleHttp\Client(['handler' => $stack]);

        $client = new \Goutte\Client();
        $client->setClient($torGuzzleClient);
        $crawler = $client->request('GET', $url);
        $crawler->filter('.gsRow .col-xs-6.prdInfos .price.spacerBottomXs')->eq(0)->each(function ($node) use (& $price){
            $price = ($node->text());
        });
        if(empty($price))
        {
            $crawler->filter('#prdRightCol .price')->eq(0)->each(function ($node) use (& $price)
            {
                $price = ($node->text());
            });
        }

        $price = $this->formatPrice($price);

        return $price;
    }
    
    protected function getPriceFromTousPourUnPrix($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('td.prix')->each(function ($node) use (& $price){
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

    protected function getPriceFromVieffetrade($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;
        $crawler->filter('meta[itemprop="price"]')->each(function ($node) use (& $price){
            $price = ($node->attr('content'));
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromKlarstein($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;
        $crawler->filter('#zoomwindow .zoxid-price')->each(function ($node) use (& $price, &$done){
            if( ! $done)
            {
                $price = ($node->text());
                $done = true;
            }
        });
        $price = $this->formatPrice($price);

        return $price;
    }


    protected function getPriceFromCentralePneus($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;
        $crawler->filter('meta[itemprop="price"]')->each(function ($node) use (& $price){
            $price = ($node->attr('content'));
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