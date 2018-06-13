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

    use AppBundle\Exception\Expirated;
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
    public function getPrice($url, $tryLeft = 3, $useTor = false)
    {
        $price = null;
        $hostname = parse_url($url, PHP_URL_HOST);
        $hostname = strtolower($hostname);
        try
        {
            switch ($hostname)
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
                    $price = $this->getPriceFromAmazon($url, $useTor);
                    break;

                case 'www.rueducommerce.fr':
                    $price = $this->getPriceFromRueDuCommerce($url);
                    break;

                case 'www.cdiscount.com':
                    $price = $this->getPriceFromCDiscount($url);
                    break;
/*                case 'www.arredatutto.com':
                    //$price = $this->getPriceFromArredatutto($url);
                    break;
*/
                case 'www.priceminister.com':
                    $price = $this->getPriceFromPriceMinister($url, $useTor);
                    break;
/*
                case 'www.mistergooddeal.com':
                    //$price = $this->getPriceFromMisterGoodDeal($url);
                    break;
*/
                case 'www.electrodepot.fr':
                    $price = $this->getPriceFromElectroDepot($url);
                    break;
/*
                case 'www.abribatelectromenager.fr':
                    //$price = $this->getPriceFromAbribatElectromenager($url);
                    break;

                case 'www.conforama.fr':
                    $price = $this->getPriceFromConforama($url);
                    break;

                case 'www.backmarket.fr':
                    //$price = $this->getPriceFromBackMarket($url);
                    break;
*/
                case 'www.webdistrib.com':
                    $price = $this->getPriceFromWebDistrib($url);
                    break;
/*
                case 'www.maginea.com':
                    //$price = $this->getPriceFromMaginea($url);
                    break;

                case 'www.etrouvetout.com':
                    //$price = $this->getPriceFromETrouveTout($url);
                    break;

                case 'www.allopneus.com':
                    //$price = $this->getPriceFromAlloPneus($url);
                    break;

                case 'www.touspourunprix.fr':
                    //$price = $this->getPriceFromTousPourUnPrix($url);
                    break;

                case 'www.tyrigo.com':
                    //$price = $this->getPriceFromTyrigo($url);
                    break;

                case 'www.lacooplr.fr':
                    //$price = $this->getPriceFromLaCoopLr($url);
                    break;
*/
                case 'www.magasins-privilege.fr':
                    $price = $this->getPriceFromMagasinsPrivilege($url);
                    break;
/*
                case 'www.idealprice.fr':
                    //$price = $this->getPriceFromIdealPrice($url);
                    break;
*/
                case 'www.villatech.fr':
                    $price = $this->getPriceFromVillaTech($url);
                    break;
/*
                case 'www.ubaldi.com':
                    //$price = $this->getPriceFromUbaldi($url);
                    break;
*/
                case 'track.effiliation.com':
                    $sourceUrl = $this->getSourceUrlFrom($url, "url");
                    return $this->getPrice($sourceUrl, $tryLeft);

                case 'clk.tradedoubler.com':
                    $sourceUrl = $this->getSourceUrlFrom($url, "url");
                    return $this->getPrice($sourceUrl, $tryLeft);

                case 'action.metaffiliation.com':
                    $sourceUrl = $this->getSourceUrlFrom($url, "redir");
                    return $this->getPrice($sourceUrl, $tryLeft);


                case 'www.awin1.com':
                    $sourceUrl = $this->getSourceUrlFrom($url, "p");
                    return $this->getPrice($sourceUrl, $tryLeft);

                case 'www.vieffetrade.eu':
                    //$price = $this->getPriceFromVieffetrade($url);
                    break;

                case 'www.klarstein.fr':
                    //$price = $this->getPriceFromKlarstein($url);
                    break;

                case 'www.centralepneus.fr':
                    //return $this->getPriceFromCentralePneus($url);


                case 'www.pixmania.fr':
                    //On sait pas faire
                    return null;


            }
        }
        catch(Expirated $e)
        {
            throw $e;
        }
        catch(\Exception $e)
        {
            $sleepTime = 3-$tryLeft;
            sleep(max(1, $sleepTime));
            $tryLeft++;
            echo "\nError getting price : ".$e->getMessage()." - sleeping ".($sleepTime);
        }
        if(( empty($price) || $price < 0 ) && $tryLeft > 0)
        {
            $tryLeft--;
            $tryLeft--;
            //retry with tor
            return $this->getPrice($url, $tryLeft, true);
        }
        return $price;
    }


    protected function getSourceUrlFrom($url, $paramName)
    {
        $params = array();
        parse_str($url, $params);
        return array_key_exists($paramName, $params) ? $params[$paramName] : null;
    }

    protected function getPriceFromBoulanger($url)
    {
        $price = 0;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);
        $done = false;

        $isExpirated = false;
        $crawler->filter('.stock.reappro')->each(function ($node) use (& $isExpirated, &$done){
            if(stripos((string)$node->text(), 'Non livrable') !== false)
            {
                $isExpirated = true;
            }
        });
        if($isExpirated)
        {
            throw new Expirated();
        }

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

    protected function getPriceFromAmazon($url, $tryLeft = 1, $useTor = true)
    {
        //return null;
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
        if($useTor)
        {
            $client->setClient($torGuzzleClient);
        }
        $client->setHeader('User-Agent', "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.101 Safari/537.36");
        $client->setHeader('Cookie', 'bid-acbfr=257-9352242-6483420; lc-acbfr=fr_FR; session-id=257-9523585-0377044; x-wl-uid=1HXkCw8xcj/0qAlcPCM6iET031oVRmQrnBv0eUJH1WgdsO2jrZYm7JWsxQSXTluboRc7SEv0CCRxagJQaPmeF2CvNPv3L5ZWPI0mXDyMrqQT0+IitBj1cEC7/PNXMGisKgucP2TXp/JI=; x-acbfr=PuFa7GioNLzyXOkQybnk3ikHwWQ0J92Qtp4wxRF8vUiTHYqwvkwAsut3VZPafOgF; at-acbfr=Atza|IwEBIGnDAB-5RDRHT2d6TZkl2PWMOT2HyEEhIUWh2SfEXpRYvccw0A_LBX3I-ommaxP-ef74TkdYvwJgbLWfKbQuFSjFr94l74yDQ-Bax51XYvVrDqQRwzSUTEIuLuTgLKX1oq_k0E1mzxpYndX3H4C-nx-3vscId-4KaRn_p4LwphyTHNPu2E-WUUo89FGEudpqeI4M-XZWpMfkofxZS2ycXIacQH2hiHLMaNlxf1lYJqBA-opf39Q0ulQMt_VZekfUJG16mB5JEKcuLoQf1ZkXnCWWQE8we0Efg0ktU383uYXppouIMhNGZUt5KvnOP-XJIMsL38nzvYPZIzeSWJ2P6kpJWRVk6MfIjpeelrIYeBrbZMUwze9y1Qkhvd073Q-1qjJwgCpkXsa3eRpm3HW8_Wi0; sess-at-acbfr="5fAbGqen8eDwEDNujAu6CkivuZkSfaeoQ5+GhsYDyak="; sst-acbfr=Sst1|PQFCJ4lDfH1LQ5OY32CKQleLFEjHCKJCTGaM4Qa0BEYlJT4aiuyy7yeECnnJlg3s7pGuDDcfdQwGf8cJOh8MGQGOqv7unx1qnP1Ln-2KB0ONXxBqfoWvIPGslvbdxrGl4YnGNPtdiS0gopz0hw-H5ub_J4YXOHZGa9Eepu-lywwLIcH-su7LQ9LxWdSkgJbsvY2cF_SakyCiYptsPFCqUpDQWcKuKQDGW83uO86lct9yhoRUd7yraw-lnp14mA9vV44ZRlyg4cg2Ack1Iq6lzNNxHsLoCs-9bqUR1457CKvr1efxcfeyHT9RZDrsAKuGc_IacdfvgUASJ5kLWHkufp2Wy_QdCLnpX3Gh8rTIWT8EnWQ9U2PxA-f-S0gJfJl0wU67_y1ALl1SnUDm5LFXecA8Q5HnhqA80CCSpa15SrTuVmkJe1yIyACOBfRbKjQcBVsLI8oacTO3s6Q-dkIYHvcRfncntQnZiHl2_boDQLU5hgPMuhMz_lT5DFzl4vSxtb36rJd4gcfjyvqv8qcsSP6Lhw; ca=AFJAAIAQAAQwAAACAAEAAAA=; session-token=vs9kl7pI1zyxJn4/exz0iAEksMVP7ETfsI9cPhcFCKhR12eqNuqRdDERRzvtLeGhUjgoUDfkUBGmjE/C9aJHQusGj4B0uZmsoaU5TX55YuRpx98L53GSIBUSd0PpJk2GzX+K/xBnLCZ+vrWRV1oVDoXtqE+nG+CdfMbKJ2FuOk06/5rWHIApIRNIfDAPq/CrUZJ59cdOVCZDO9LyIb/vl6YZQ3TsLwhTsKUX5xj9levYdCnsWyR93ap5OvFLsfphNWPwRmr5vM7JB0FKoucbcQ==; session-id-time=2082754801l; csm-hit=adb:adblk_yes&tb:9QSB6YSZVRTV10ZNR8T6+s-9QSB6YSZVRTV10ZNR8T6|1528904763303');
        //$url.='&_=sdfdsfs'.rand();
        $crawler = $client->request('GET', $url);

        //echo "\n".$crawler->text()."\n";
        if(stripos($crawler->text(), 'tes pas un robot.') !== false)
        {
            //TODO
            throw new \Exception("AmazonRobotException");
        }

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
            sleep(max(1, 2-$tryLeft));
            $tryLeft--;
            $useTor = $useTor && $tryLeft > 0;
            return $this->getPriceFromAmazon($url, $tryLeft, $useTor);
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



        $expirated = false;
        $crawler->filter('#right-column img')->each(function ($node) use (& $expirated){
            $alt = ($node->attr('alt'));
            if(strtolower($alt) == 'épuisé')
            {
                $expirated = true;
            }
        });
        if($expirated)
        {
            throw new Expirated();
        }

        $crawler->filter('meta[itemprop="price"]')->each(function ($node) use (& $price){
            $price = ($node->attr('content'));
        });
        $price = $this->formatPrice($price);

        return $price;
    }

    protected function getPriceFromPriceMinister($url, $useTor = false)
    {
        $price = 0;

        $stack = new \GuzzleHttp\HandlerStack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        $stack->push(\GuzzleTor\Middleware::tor());
        $torGuzzleClient = new \GuzzleHttp\Client(['handler' => $stack]);

        $client = new \Goutte\Client();
        if($useTor)
        {
            $client->setClient($torGuzzleClient);
        }
        $crawler = $client->request('GET', $url);

        if(stripos($crawler->text(), 'il faut que nous nous assurions que vous n\'êtes pas un robot') !== false)
        {
            //TODO : Custom exception
            throw new \Exception("RobotExceptionPriceMinister");
        }

        $expirated = $crawler->filter('#prdRightCol .col-xs-12.center-xs .badLevel')->count() && $crawler->filter('#prdRightCol .col-xs-12.center-xs #productAlertLink')->count();
        if($expirated)
        {
            throw new Expirated();
        }

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

        $expirated = $crawler->filter('body#error_404')->count();
        if($expirated)
        {
            throw new Expirated();
        }

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

        $expirated = $crawler->filter('#newProductInfos .availability .availability__status.availability__status--soldout')->count();
        if($expirated)
        {
            throw new Expirated();
        }

        $expirated = $crawler->filter('#product__main .availability__status--resupply')->count();
        if($expirated)
        {
            throw new Expirated();
        }

        $expirated = $crawler->filter('#product__main .availability__status--soldout')->count();
        if($expirated)
        {
            throw new Expirated();
        }


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

        $expirated = $crawler->filter('#productSection .stock.stockEnd')->count();
        if($expirated)
        {
            throw new Expirated();
        }


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
