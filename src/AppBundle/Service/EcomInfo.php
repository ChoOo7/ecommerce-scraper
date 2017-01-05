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


class EcomInfo
{

    /**
     * @var EcomPrice
     */
    protected $ecomPriceService;
    
    protected $ecomAffiSer;
    
    /**
     * EcomInfo constructor.
     * @param EcomPrice $ecomPrice
     */
    public function __construct(EcomPrice $ecomPrice, EcomAffiliation $ecomAffiSer)
    {
        $this->ecomPriceService = $ecomPrice;
        $this->ecomAffiSer = $ecomAffiSer;
    }

    /**
     * {@inheritDoc}
     */
    public function getInfos($ean, $productType)
    {
        switch($productType)
        {
            case 'VACUUM_CLEANERS':
            case 'aspirateur':
                return $this->getInfosOf($ean, 'aspirateur');

            case 'WASHING_MACHINES':
            case 'machinealaver':
                return $this->getInfosOf($ean, 'machinealaver');

            case 'DISHWASHERS':
            case 'lavevaisselle':
                return $this->getInfosOf($ean, 'lavevaisselle');
            case 'TYRES':
            case 'pneus':
            case 'pneu':
                return $this->getInfosOf($ean, 'pneu');
            
            default:
                return $this->getInfosOf($ean, $productType);
                
        }
        return null;
    }
    
    
    protected function getInfosOf($ean, $productType)
    {
        $infos = array();
        $providers = $this->getProvidersOfType($productType);
        foreach($providers as $provider)
        {
            $methodName = "getInfosOfFrom".ucfirst($provider);
            $infosBis = $this->$methodName($ean, $productType, $infos);
            if($infosBis !== null)
            {
                foreach ($infosBis as $k => $v)
                {
                    if (!array_key_exists($k, $infos))
                    {
                        $infos[$k] = array();
                    }
                    $infos[$k][$provider] = $v;
                }
            }
        }
        if(array_key_exists('uri', $infos))
        {
            foreach($infos['uri'] as $provider=>$url)
            {
                $infos['uri'][$provider] = $this->ecomAffiSer->getNewUrl($url);
            }
        }
        return $infos;
    }
    
    protected function getProvidersOfType($productType)
    {
        switch($productType)
        {
            case 'pneu':
                return array();
//                return array('AlloPneus');

            default:
                return array('Darty', 'EanFind', 'EanSearch', 'IdealPrice', 'VillaTech', 'Boulanger', 'EanFind', 'WebDistrib', /*'Amazon', */'ElectroDepot', 'RueDuCommerce', 'TousPourUnPrix', 'PriceMinister', 'MisterGoodDeal', 'CDiscount', 'Arredatutto', 'Conforama', 'ElectroMenagerCompare', 'Ubaldi');
        }
        return array();
    }

    protected function getInfosOfFromDarty($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = $searchUrl = 'http://www.darty.com/nav/recherche?text='.urlencode($ean);
        $searchUrl2 = 'http://www.darty.com/nav/recherche/'.urlencode($ean).'.html';

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        $crawler->filter('[data-flix-brand]')->each(function ($node) use (& $infos)
        {
            $infos['brand'] = $brand = $node->attr("data-flix-brand");
        });
        $crawler->filter('.product_name')->each(function ($node) use (& $infos)
        {
            $fullName = $node->text();
            if(array_key_exists('brand', $infos))
            {
                $tmp = explode($infos['brand'], $fullName);
                $tmp = $tmp[1];
                $tmp = trim($tmp);
                $tmp = trim($tmp, '-');
                $tmp = trim($tmp);
                $infos['model'] = $tmp;
            }
        });

        $crawler->filter('#darty_product_brand')->each(function ($node) use (& $infos)
        {
            $infos['brand'] = $brand = $node->text();
            $fullName = $node->parents()->eq(0)->text();

            $tmp = explode($brand, $fullName);
            $tmp = $tmp[1];
            $tmp = trim($tmp);
            $tmp = trim($tmp, '-');
            $tmp = trim($tmp);

            if(strpos($tmp, strtoupper($brand)) !== false)
            {
                $tmp = explode(strtoupper($brand), $fullName);
                $tmp = $tmp[1];
                $tmp = trim($tmp);
                $tmp = trim($tmp, '-');
                $tmp = trim($tmp);
            }

            $infos['model'] = $tmp;
        });

        $crawler->filter('.product_family')->each(function ($node) use (& $infos)
        {
            if(trim($node->text()) == 'Lave linge ouverture dessus')
            {
                $infos['ouverture']='dessus';
            }elseif(trim($node->text()) == 'Lave linge hublot')
            {
                $infos['ouverture']='hublot';
            }
            if(trim($node->text()) == 'Lave vaisselle encastrable')
            {
                $infos['pose']='Intégrable';
            }elseif(trim($node->text()) == 'Lave vaisselle')
            {
                $infos['pose']='Pose libre';
            }
        });

        $crawler->filter('.product_details_item')->each(function ($node) use (& $infos){
            $textNode = $node->text();
            $tmp = explode(':', $textNode);
            $tmp[0] = trim($tmp[0]);

            if(stripos($textNode, "Classe d'efficacité énergétique") !== false)
            {
                $value = trim($tmp[1]);
                $infos['energyClass'] = $this->cleanEnergyClass($value);
            }elseif(stripos($textNode, "aspiration sols durs") !== false)
            {
                $tmp2 = explode('-', $tmp[1]);
                $value = trim($tmp2[0]);
                $infos['solDur'] = $value;

                $value = trim($tmp[2]);
                $infos['tapis'] = $value;
            }elseif(stripos($textNode, "Niveau sonore") !== false)
            {
                $tmp2 = explode('-', $tmp[1]);
                $value = trim($tmp2[0]);
                $infos['bruit'] = $this->cleanBruit($value);

                if(array_key_exists(2, $tmp))
                {
                    $value = trim($tmp[2]);
                    $infos['filtration'] = $value;
                }
            }
        });
        $crawler->filter('.product_bloc_caracteristics tr')->each(function ($node) use (& $infos){
            $textNode = $node->text();
            $textLabel = trim($node->filter('th')->text());
            $value = trim($node->filter('td')->text());
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        if($searchUrl != $crawler->getUri() && $searchUrl2 != $crawler->getUri())
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }

    protected function getInfosOfFromVillaTech($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = $searchUrl = 'http://www.villatech.fr/catalogsearch/result/?q='.urlencode($ean);
        $newUrl = null;

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);


        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('.product-listing-ajax-container a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if($newUrl && $newUrl{0} == '/')
            {
                $newUrl = 'http://www.villatech.fr'.$newUrl;
            }
            if ($newUrl == null)
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }

        $crawler->filter('[itemprop="brand"] span')->eq(0)->each(function ($node) use (& $infos)
        {
            $infos['brand'] = $node->text();
        });
        $crawler->filter('h1 > span')->eq(1)->each(function ($node) use (& $infos)
        {
            $infos['model'] = $node->text();
        });



        $crawler->filter('#caracteristiques tr')->each(function ($node) use (& $infos, $productType){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td')->each(function($subNode) use (&$textLabel, & $value, &$index, $productType) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                }else{
                    $value = trim($subNode->text());
                }
                $textLabel = trim($textLabel);
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));

            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        if($newUrl)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }

    protected function getInfosOfFromEanFind($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = 'https://www.eanfind.fr/chercher/' . urlencode($ean);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        $crawler->filter('#brand')->each(function ($node) use (& $infos){
            $infos['brand'] = $node->attr('value');
        });
        $crawler->filter('a[itemprop="brand"]')->each(function ($node) use (& $infos){
            $infos['brand'] = $node->text();
        });
        return $infos;
    }
    
    protected function getInfosOfFromEanSearch($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;
        
        $brand = array_key_exists('brand', $parametersInfos) ? $parametersInfos['brand'] : null;
        if(empty($brand))
        {
            return null;
        }
        $brand = array_shift($brand);

        $url = 'https://www.ean-search.org/perl/ean-search.pl?q=' . urlencode($ean);

        $stack = new \GuzzleHttp\HandlerStack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        $stack->push(\GuzzleTor\Middleware::tor());
        $torGuzzleClient = new \GuzzleHttp\Client(['handler' => $stack]);

        $client = new \Goutte\Client();
        $client->setClient($torGuzzleClient);
        $client->setHeader('User-Agent', "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36".rand());
        $crawler = $client->request('GET', $url);
        
        $label = null;
        $crawler->filter('#main a')->eq(0)->each(function ($node) use (& $label){
            $label = $node->text();
        });
        $tmp = preg_split('!'.$brand.'!i', $label);
        if(array_key_exists(1, $tmp))
        {
            $infos['model'] = trim($tmp[1]);
        }else{
            return null;
        }
        return $infos;
    }

    protected function getInfosOfFromBoulanger($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = 'http://www.boulanger.com/resultats?tr='.urlencode($ean);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        $crawler->filter('span[itemprop="brand"]')->each(function ($node) use (& $infos){
            $infos['brand'] = $node->text();
        });

        $crawler->filter('img[itemprop="image"]')->each(function ($node) use (& $infos){
            $infos['model'] = trim(str_replace($infos['brand'], '', $node->attr("alt")));
        });

        $crawler->filter('table.characteristic tr')->each(function ($node) use (& $infos, $productType){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td')->each(function($subNode) use (&$textLabel, & $value, &$index, $productType) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                }else{
                    $value = trim($subNode->text());
                }
                $textLabel = trim($textLabel);
                if($textLabel == "Volume utile" && $productType == "frigo")
                {
                    $tbody = $subNode->parents('tbody')->eq(0);
                    $previousCatName = $tbody->parents()->previousAll()->text();
                    $previousCatName = trim($previousCatName);
                    $textLabel = "Volume utile ".$previousCatName;
                }
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });
        if($productType == 'machinealaver')
        {
            $crawler->filter('#filAriane span a')->each(function ($node) use (& $infos){
                if($node->text() == 'Lave-linge top')
                {
                    $infos['ouverture']='dessus';
                }elseif($node->text() == 'Lave-linge hublot')
                {
                    $infos['ouverture']='hublot';
                }
            });
        }

        if($crawler->getUri() != $url)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }


    protected function getInfosOfFromMisterGoodDeal($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = 'http://www.mistergooddeal.com/nav/recherche?srctype=list&text='.urlencode($ean);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        $crawler->filter('#carac_product li')->each(function ($node) use (& $infos, $parametersInfos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('div.label')->each(function($subNode) use (&$textLabel) {
                $textLabel = trim($subNode->text());
            });
            $node->filter('div.value')->each(function($subNode) use (&$value) {
                $value = trim($subNode->text());
            });
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value, $parametersInfos);
        });

        if($crawler->getUri() != $url)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }

    protected function getInfosOfFromConforama($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $model = array_key_exists('model', $parametersInfos) ? $parametersInfos['model'] : null;
        if( empty($model) )
        {
            return null;
        }
        $model = array_shift($model);

        $modelForSearch = str_replace('/', urlencode('/'), $model);
        $url = 'http://www.conforama.fr/recherche-conforama/'.rawurlencode($modelForSearch);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);


        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('#contentSegment .containerOneProdList h3.itemTitle a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if($newUrl && $newUrl{0} == '/')
            {
                $newUrl = 'http://www.conforama.fr'.$newUrl;
            }
            if ($newUrl == null)
            {
                return null;
            }

            if( ! $this->urlMatchModel($newUrl, $model))
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }


        $crawler->filter('.productSpecifications tr')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('.productSpecificationsLabel')->each(function($subNode) use (&$textLabel) {
                $textLabel = trim($subNode->text());
            });
            $node->filter('.productSpecificationsValue')->each(function($subNode) use (&$value) {
                $value = trim($subNode->text());
            });
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        if($crawler->getUri() != $url)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }

    protected function getInfosOfFromTousPourUnPrix($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $model = array_key_exists('model', $parametersInfos) ? $parametersInfos['model'] : null;
        if( empty($model) )
        {
            return null;
        }
        $model = array_shift($model);

        $url = 'https://www.touspourunprix.fr/recherche-resultats.php?search_in_description=1&ac_keywords='.urlencode($model);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);


        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('.module_centre_listeproduits_milieu a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if($newUrl && $newUrl{0} == '/')
            {
                $newUrl = 'https://www.touspourunprix.fr'.$newUrl;
            }
            if ($newUrl == null)
            {
                return null;
            }
            if( ! $this->urlMatchModel($newUrl, $model))
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }


        $crawler->filter('.productSpecifications tr')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('.productSpecificationsLabel')->each(function($subNode) use (&$textLabel) {
                $textLabel = trim($subNode->text());
            });
            $node->filter('.productSpecificationsValue')->each(function($subNode) use (&$value) {
                $value = trim($subNode->text());
            });
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        if($crawler->getUri() != $url)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }

    protected function getInfosOfFromPriceMinister($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = 'http://www.priceminister.com/s/'.urlencode($ean);


        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);


        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('#productCtn .navByList .productNav a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if($newUrl && $newUrl{0} == '/')
            {
                $newUrl = 'http://www.priceminister.com/'.$newUrl;
            }
            if ($newUrl == null)
            {
                return null;
            }

            if ($newUrl == null)
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }

        if($crawler->getUri() != $url)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }


    protected function getInfosOfFromElectroDepot($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = 'http://www.electrodepot.fr/Antidot/Front_Search/Suggest/?q='.urlencode($ean);


        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);


        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if($newUrl && $newUrl{0} == '/')
            {
                $newUrl = 'http://www.electrodepot.fr/'.$newUrl;
            }
            if ($newUrl == null)
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }


        $crawler->filter('#div-description tr')->each(function ($node) use (& $infos){
            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td.titre')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                $textLabel = trim($subNode->text());
            });
            $node->filter('td.valeur')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                $value = trim($subNode->text());
            });
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        if($crawler->getUri() != $url)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }


    protected function getInfosOfFromWebDistrib($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = 'https://www.webdistrib.com/autocomplete?Keywords='.urlencode($ean);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        $newUrl = null;
        $crawler->filter('li a')->each(function ($node) use (& $newUrl){
            $newUrl = $node->attr('href');
        });
        if($newUrl == null)
        {
            return null;
        }

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $newUrl);

        $crawler->filter('#newProductTechTab tr')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                }else{
                    $value = trim($subNode->text());
                }
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        if($crawler->getUri() != $url)
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($crawler->getUri());
            if ($price)
            {
                $infos['price'] = $price;
            }
        }

        return $infos;
    }


    protected function getInfosOfFromAmazon($ean, $productType, $parametersInfos, $tryLeft = 5)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $stack = new \GuzzleHttp\HandlerStack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        $stack->push(\GuzzleTor\Middleware::tor());
        $torGuzzleClient = new \GuzzleHttp\Client(['handler' => $stack]);

        $url = 'https://www.amazon.fr/s/ref=nb_sb_noss?__mk_fr_FR=%C3%85M%C3%85%C5%BD%C3%95%C3%91&url=search-alias%3Daps&field-keywords='.urlencode($ean);

        $client = new \Goutte\Client();
        $client->setClient($torGuzzleClient);
        $client->setHeader('User-Agent', "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36");
        $crawler = $client->request('GET', $url);
        $newUrl = null;
        $crawler->filter('#result_0 a')->eq(0)->each(function ($node) use (& $newUrl){
            $newUrl = $node->attr('href');
        });
        if($newUrl == null && $tryLeft > 0)
        {
            sleep(6-$tryLeft);
            $tryLeft--;
            return $this->getInfosOfFromAmazon($ean, $productType, $parametersInfos, $tryLeft);
        }
        if($newUrl == null)
        {
            return null;
        }

        sleep(rand(0,3));
        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $newUrl);

        $crawler->filter('#prodDetails table tr')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                }else{
                    $value = trim($subNode->text());
                }
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        $infos['uri'] = $crawler->getUri();
        $infos['price'] = $this->ecomPriceService->getPrice($infos['uri']);

        return $infos;
    }

    protected function getInfosOfFromElectroMenagerCompare($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $brands = array_key_exists('brand', $parametersInfos) ? $parametersInfos['brand'] : array();
        if(empty($brands))
        {
            return null;
        }
        $brand = array_shift($brands);

        $models = array_key_exists('model', $parametersInfos) ? $parametersInfos['model'] : array();
        if(empty($models))
        {
            return null;
        }
        $model = array_shift($models);
        $url = array_shift($parametersInfos['uri']);
        $url = 'http://www.electromenager-compare.com/recherche-'.urlencode($brand.'-'.$model).'.htm';

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        if($crawler->filter('.content-style-warning')->count() != 0)
        {
            return null;
        }

        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('.product-list-info a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if ($newUrl == null)
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }

        $crawler->filter('.product-tech-section-row')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('div')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                    $textLabel = trim($textLabel, ':');
                    $textLabel = trim($textLabel);
                    $textLabel = explode(':', $textLabel);
                    $textLabel = $textLabel[0];

                    $textLabel = trim($textLabel);
                    $textLabel = trim($textLabel, '•');
                    $textLabel = trim($textLabel);
                }else{
                    $value = trim($subNode->text());
                }
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        $infos['uri'] = $crawler->getUri();
        $price = $this->ecomPriceService->getPrice($infos['uri']);
        if($price)
        {
            $infos['price'] = $price;
        }
        
        return $infos;
    }

    protected function getInfosOfFromRueDuCommerce($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $brands = array_key_exists('brand', $parametersInfos) ? $parametersInfos['brand'] : array();
        if(empty($brands))
        {
            return null;
        }
        $brand = array_shift($brands);

        $models = array_key_exists('model', $parametersInfos) ? $parametersInfos['model'] : array();
        if(empty($models))
        {
            return null;
        }
        $model = array_shift($models);
        $url = array_shift($parametersInfos['uri']);
        $url = 'http://search.rueducommerce.fr/search?s='.urlencode($model).'';

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        if($crawler->filter('.noResult')->count() != 0)
        {
            return null;
        }

        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('#blcResRech a.prdLink.url')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if ($newUrl == null)
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
            
            $textContent = $crawler->text();
            if(stripos($textContent, $brand) === false || stripos($textContent, $model) === false)
            {
                return null;
            }
            if(stripos($crawler->getUri(), 'search_retry') !== false)
            {
                return null;
            }
        }

        $crawler->filter('#blocAttributesContent tr')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                    $textLabel = trim($textLabel, ':');
                    $textLabel = trim($textLabel);
                    $textLabel = explode(':', $textLabel);
                    $textLabel = $textLabel[0];

                    $textLabel = trim($textLabel);
                    $textLabel = trim($textLabel, '•');
                    $textLabel = trim($textLabel);
                }else{
                    $value = trim($subNode->text());
                }
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });


        $infos['uri'] = $crawler->getUri();
        $price = $this->ecomPriceService->getPrice($infos['uri']);
        if($price)
        {
            $infos['price'] = $price;
        }

        return $infos;
    }

    protected function getInfosOfFromCDiscount($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $brands = array_key_exists('brand', $parametersInfos) ? $parametersInfos['brand'] : array();
        if(empty($brands))
        {
            return null;
        }
        $brand = array_shift($brands);

        $models = array_key_exists('model', $parametersInfos) ? $parametersInfos['model'] : array();
        if(empty($models))
        {
            return null;
        }
        $model = array_shift($models);
        $url = array_shift($parametersInfos['uri']);
        $url = 'http://www.cdiscount.com/search/10/'.urlencode($model).'.html#_his_';

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        if($crawler->filter('.lrTryAgain')->count() != 0)
        {
            return null;
        }

        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('#lpBloc a.jsQs')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if ($newUrl == null || stripos($newUrl, $brand) === false  || stripos($newUrl, $model) === false)
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }


        $crawler->filter('table.fpDescTbPub tr')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                    $textLabel = trim($textLabel, ':');
                    $textLabel = trim($textLabel);
                    $textLabel = explode(':', $textLabel);
                    $textLabel = $textLabel[0];

                    $textLabel = trim($textLabel);
                    $textLabel = trim($textLabel, '•');
                    $textLabel = trim($textLabel);
                }else{
                    $value = trim($subNode->text());
                }
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        $infos['uri'] = $crawler->getUri();
        if($productType == 'four')
        {
            if(stripos($infos['uri'], 'encastrable') !== false)
            {
                $infos['fourType'] = 'encastrable';
            }
        }


        
        $price = $this->ecomPriceService->getPrice($infos['uri']);
        if($price)
        {
            $infos['price'] = $price;
        }

        return $infos;
    }

    protected function getInfosOfFromArredatutto($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $brands = array_key_exists('brand', $parametersInfos) ? $parametersInfos['brand'] : array();
        if(empty($brands))
        {
            return null;
        }
        $brand = array_shift($brands);

        $models = array_key_exists('model', $parametersInfos) ? $parametersInfos['model'] : array();
        if(empty($models))
        {
            return null;
        }
        $model = array_shift($models);
        $url = array_shift($parametersInfos['uri']);
        $url = 'http://www.arredatutto.com/fr/advanced_search_result.html?keyword='.urlencode($model).'';

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('ul.list-grid-products li h3 a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if ($newUrl == null)
            {
                return null;
            }

            if ( ! $this->urlMatchModel($newUrl, $model))
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }


        $crawler->filter('.liste-specs .ls-ligne')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $actualText = $node->text();
            $node->filter('span')->eq(0)->each(function($subNode) use (& $value)
            {
                $value = $subNode->text();
            });
            $textLabel = str_replace($value, '', $node->text());
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });


        $infos['uri'] = $crawler->getUri();
        $price = $this->ecomPriceService->getPrice($infos['uri']);
        if($price)
        {
            $infos['price'] = $price;
        }

        return $infos;
    }

    protected function getInfosOfFromIdealPrice($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $url = 'https://www.idealprice.fr/recherche?controller=search&orderby=position&orderway=desc&search_query='.urlencode($ean).'&submit_search=';

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        if($crawler->getUri() == $url)
        {
            $newUrl = null;
            $done = false;
            $crawler->filter('#product_list a')->eq(0)->each(function ($node) use (& $newUrl)
            {
                $newUrl = $node->attr('href');
            });
            if ($newUrl == null)
            {
                return null;
            }

            $client = new \Goutte\Client();
            $crawler = $client->request('GET', $newUrl);
        }


        $crawler->filter('span.editable')->each(function ($node) use (& $infos){
            $infos['model'] = $node->text();
        });

        $infos['uri'] = $crawler->getUri();
        $price = $this->ecomPriceService->getPrice($infos['uri']);
        if($price)
        {
            $infos['price'] = $price;
        }

        return $infos;
    }

    protected function getInfosOfFromUbaldi($ean, $productType, $parametersInfos)
    {
        $infos = array();
        $infos['ean'] = $ean;

        $models = array_key_exists('model', $parametersInfos) ? $parametersInfos['model'] : array();
        if(empty($models))
        {
            return null;
        }
        $model = array_shift($models);
        $url = 'http://www.ubaldi.com/recherche/four-encastrable/'.urlencode(str_replace(' ', '-', $model)).'.php';

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        
        $newUrl = null;
        $done = false;
        $crawler->filter('#main-liste-articles a')->eq(0)->each(function ($node) use (& $newUrl)
        {
            $newUrl = $node->attr('href');
        });
        if ($newUrl == null)
        {
            return null;
        }

        if($newUrl && $newUrl{0} == '/')
        {
            $newUrl = 'http://www.ubaldi.com'.$newUrl;
        }

        if ( ! $this->urlMatchModel($newUrl, $model))
        {
            return null;
        }

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $newUrl);


        $crawler->filter('.liste-specs .ls-ligne')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('.ls-titre')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                $textLabel = trim($subNode->text());
            });
            $node->filter('.ls-valeur')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                $value = trim($subNode->text());
            });
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);

            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        $infos['uri'] = $crawler->getUri();
        $price = $this->ecomPriceService->getPrice($infos['uri']);
        if($price)
        {
            $infos['price'] = $price;
        }

        return $infos;
    }


    protected function getInfosOfFromAlloPneus($ean, $productType, $parametersInfos)
    {
        $infos = array();

        $url = 'https://www.google.fr/search?q='.urlencode($ean);

        $client = new \Goutte\Client();
        $client->setHeader('User-Agent', "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.101 Safari/537.36");
        $crawler = $client->request('GET', $url);

        $newUrl = null;
        $crawler->filter('.jackpot-merchant a')->each(function ($node) use (& $infos, & $newUrl){
            if(stripos($node->text(), 'allopneu') !== false)
            {
                $newUrl = 'https://www.google.fr' . $node->attr('href');
            }
        });


        $client = new \Goutte\Client();
        $client->setHeader('User-Agent', "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.101 Safari/537.36");
        $crawler = $client->request('GET', $newUrl);

        var_dump($newUrl);
        
        var_dump($crawler->getUri());

        var_dump("fin");
        die();

        $crawler->filter('img[itemprop="image"]')->each(function ($node) use (& $infos){
            $infos['model'] = trim(str_replace($infos['brand'], '', $node->attr("alt")));
        });

        $crawler->filter('table.characteristic tr')->each(function ($node) use (& $infos){

            $textLabel = null;
            $value = null;
            $index = 0;
            $node->filter('td')->each(function($subNode) use (&$textLabel, & $value, &$index) {
                if($index == 0)
                {
                    $textLabel = trim($subNode->text());
                }else{
                    $value = trim($subNode->text());
                }
                $index++;
            });
            $textLabel = trim(str_replace('Aide', '', $textLabel));
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });
        if($productType == 'machinealaver')
        {

            $crawler->filter('#filAriane span a')->each(function ($node) use (& $infos){
                if($node->text() == 'Lave-linge top')
                {
                    $infos['ouverture']='dessus';
                }elseif($node->text() == 'Lave-linge hublot')
                {
                    $infos['ouverture']='hublot';
                }
            });

        }

        if( ! empty($infos))
        {
            $infos['uri'] = $crawler->getUri();
            $infos['price'] = $this->ecomPriceService->getPrice($infos['uri']);
        }


        return $infos;
    }


    protected function aspirateurGetInfosFromLabel($infos, $textLabel, $value, $otherInfos = array())
    {
        $textLabel = trim($textLabel);
        $textLabel = trim($textLabel, ':');
        $textLabel = trim($textLabel);
        $textLabel = trim($textLabel, ':');
        $textLabel = trim($textLabel);
        $textLabel = trim($textLabel, chr(160));
        $textLabel = trim($textLabel, chr(194));
        $textLabel = trim($textLabel, ':');
        $textLabel = trim($textLabel);
        $value = trim($value);
        
        if($textLabel == 'Essorage' && stripos($value, 'db')!== false)
        {
            $textLabel='BruitEssorage';
        }
        if($textLabel == 'Lavage' && stripos($value, 'db')!== false)
        {
            $textLabel='BruitLavage';
        }
        if($textLabel == 'Capacité' && (stripos($value, 'kilo')!== false || stripos($value, 'kg')!== false))
        {
            $textLabel='CapacitéKg';
        }
        if($textLabel == 'Capacité' && (stripos($value, 'couvert')!== false))
        {
            $textLabel='CapacitéCouvert';
        }
        if($textLabel == 'Capacité' && (stripos($value, 'litre')!== false || stripos($value, 'l')!== false))
        {
            $textLabel='CapacitéVolume';
        }
        if($textLabel == 'Code' && (array_key_exists('model', $infos) || array_key_exists('model', $otherInfos)))
        {
            $textLabel='InternalCode';
        }
        
        switch($textLabel)
        {
            case 'Efficacité sols durs':
            case 'Classe d\'éfficacité sol dur':
            case 'Efficacité aspiration sol dur':
            case 'Efficacité aspiration sols durs':
                $infos['solDur'] = $value;
                break;
            case 'Efficacité moquette':
            case 'Classe d\'éfficacité tapis':
            case 'Efficacité aspiration tapis / moquette':
                $infos['tapis'] = $value;
                break;
            case 'Coût annuel d\'utilisation':
            case 'Consommation annuelle':
            case 'Coût annuel':
            case 'Coût estimé d\'utilisation (eau + électricité)':
            case 'Coût annuel Lavage':
            case 'Coût annuel d\'utilisation (basé sur 220 cycles de lavage par an)':
            case 'Coût estimé d\'utilisation':
                $infos['annualCost'] = $this->cleanAnnualCost($value);
                break;
            case 'Consommation d\'énergie annuelle':
            case 'Consommation d\'energie annuelle':
            case 'Consommation d\'énergie en lavage':
            case 'Consommation  électrique annuelle':
            case 'Lavage annuelle':
            case 'Consommation électrique annuelle':
            case 'Consommation d\'énergie':
            case 'Consommation d\'énergie (Norme EN 153)':
            case 'Consommation d\'énergie annuelle (en kWh)':
                $infos['annualConsumtion'] = $this->cleanAnnualConsumtion($value);
                break;
            case 'Consommation d\'eau en lavage':
            case 'Consommation d\'eau annuelle':
            case 'Consommation d\'eau':
                $infos['annualWaterConsumtion'] = $this->cleanAnnualConsumtion($value);
                break;
            case 'Nombre de couverts':
            case 'CapacitéCouvert':
                $infos['nombreCouvert'] = $value;
                break;
                
            case 'Classe de qualité de filtration':
                $infos['filtration'] = $value;
                break;
            case 'Niveau sonore':
            case 'Niveau sonore (Norme EN 60704-3)':
            case 'Niveau Sonore (dB)':
                $infos['bruit'] = $this->cleanBruit($value);
                break;
            case 'Contenance':
            case 'Contenance':
            case 'Capacité':
                $infos['volume'] = $this->cleanVolume($value);
                break;
            case 'Volume utile du réfrigérateur':
            case 'Volume utile':
            case 'Volume utile Réfrigérateur':
            case 'Volume réfrigérateur':
            case 'Volume utile réfrigérateur (L)':
                $infos['volumeFridge'] = $this->cleanVolume($value);
                break;
            case 'Volume utile du congélateur':
            case 'Volume congélateur':
            case 'Volume utile Congélateur':
                $infos['volumeFreezer'] = $this->cleanVolume($value);
                break;
            case 'Largeur (cm)':
            case 'Largeur d\'encastrement (cm)': 
            case 'Type de lave vaisselle':
                $infos['largeur'] = $this->cleanLargeur($value);
                break;
            case 'Classe d\'efficacité énergétique':
            case 'Classe énergie':
            case 'Classe énergétique':
            case 'Efficacité énergétique (10 niveaux)':
            case 'Efficacité énergétique':
            case 'Ecolabel d\'énergie':
                $infos['energyClass'] = $this->cleanEnergyClass($value);
                break;

            case 'Classe efficacité Lavage':
            case 'Efficacité de lavage':
                $infos['washEnergyClass'] = $this->cleanEnergyClass($value);
                break;
            case 'Puissance maxi consommée':
            case 'Puissance normale':
            case 'Puissance':
                $infos['puissance'] = $this->cleanPuissance($value);
                break;
            
            case 'Système de nettoyage':
                $infos['fourNettoyage'] = $this->cleanFourNettoyage($value);
                break;
            
            case 'Modèle':
            case 'Code':
            //case 'Référence':
            case 'Numéro du modèle de l\'article':
            case 'Référence constructeur':
                if($value != $infos['ean'])
                {
                    $infos['model'] = $value;
                }
                break;
            case 'Gamme':
            case 'Marque':
                if(strtolower($value) != 'aucune')
                {
                    $infos['brand'] = $value;
                }
                break;
            case 'Vitesse d\'essorage':
            case 'Essorage':
                $infos['vitesseEssorage'] = $this->cleanSpeed($value);
                break;

            case 'Consommation en convection classique':
            case 'Chauffage classique':
                $infos['classicalConsumtion'] = $this->cleanPuissance($value);
                break;

            case 'Consommation en convection forcée':
            case 'Convection forcée':
                $infos['forcedConsumtion'] = $this->cleanPuissance($value);
                break;

            case 'Qualité d\'essorage':
            case 'Classe d\'efficacité à l\'essorage':
            case 'Classe efficacité Essorage':
            case 'Efficacité d\'essorage':
                $infos['qualiteEssorage'] = $this->cleanEnergyClass($value);
                break;
            
            case 'Qualité de séchage':
            case 'Efficacité de séchage':
            case 'Efficacité de séchage':
                $infos['qualiteSechage'] = $this->cleanEnergyClass($value);
                break;
            
            case 'Classe d\'efficacité fluidodynamique (efficacité de l\'aspiration)':
                $infos['energyClassAspiration'] = $this->cleanEnergyClass($value);
                break;

            case 'Classe d\'efficacité lumineuse':
                $infos['energyClassLight'] = $this->cleanEnergyClass($value);
                break;
            
            case 'Classe d\'efficacité de filtration des graisses':
                $infos['energyClassGraisse'] = $this->cleanEnergyClass($value);
                break;
                
            
            case 'Capacité de lavage':
            case 'Capacité maximale au lavage':
            case 'Capacité de chargement':
            case 'CapacitéKg':
                $infos['capacityKg'] = $this->cleanKg($value);
                break;
            case 'Volume du tambour':
            case 'Volume du tambour':
            case 'CapacitéVolume':
                $infos['capacityVolume'] = $this->cleanVolume($value);
                break;
            case 'Niveau sonore en lavage':
            case 'Niveau sonore au lavage':
            case 'BruitLavage':
                $infos['bruitLavage'] = $this->cleanBruit($value);
                break;
            case 'Niveau sonore en essorage':
            case 'Niveau sonore à l\'essorage':
            case 'BruitEssorage':
            case 'Niveau sonore en mode essorage':
                $infos['bruitEssorage'] = $this->cleanBruit($value);
                break;
            case '':
                $infos[''] = $value;
                break;
            case '':
                $infos[''] = $value;
                break;
                

            case 'Récupération poussière':
                if($value == 'Avec sac')
                {
                    $infos['sac'] = 'oui';
                }elseif($value == 'Sans sac')
                {
                    $infos['sac'] = 'non';
                }else{
                    $infos['sac'] = 'non';
                }
                break;
            default:
                //var_dump($textLabel.' : '.$value);
        }
        return $infos;
    }

    protected function cleanPuissance($value)
    {
        $value = str_ireplace('watts', '', $value);
        $value = str_ireplace('watt', '', $value);
        $value = str_ireplace('w', '', $value);
        
        return trim($value);
    }


    protected function cleanBruit($value)
    {
        $value = str_ireplace('db(a) re 1 pw', '', $value);
        $value = str_ireplace('db(a)', '', $value);
        $value = str_ireplace('db', '', $value);
        $value = str_ireplace('Maximum de ', '', $value);
        return trim($value);
    }

    protected function cleanEnergyClass($value)
    {
        $tmp = explode('(', $value);
        $value = $tmp[0];
        $tmp = explode('-', $value);
        $value = $tmp[0];
        $value = str_ireplace('to d', '', $value);
        $value = str_ireplace('to G', '', $value);
        $value = trim($value);
        $value = str_replace(' ', '', $value);
        $value = str_ireplace('toG', '', $value);
        return $value;
    }

    protected function cleanAnnualConsumtion($value)
    {
        $value = str_ireplace('kWh/an', '', $value);
        $value = str_ireplace(',', '.', $value);
        $value = trim($value);
        $value = str_ireplace(',', '.', $value);
        $value = (float)($value);
        return $value;
    }

    protected function cleanVolume($value)
    {
        $value = str_ireplace('litres', '', $value);
        $value = str_ireplace('litre', '', $value);
        $value = str_ireplace('l', '', $value);
        $value = trim($value);
        $value = str_ireplace(',', '.', $value);
        $value = (float)($value);
        return $value;
    }

    protected function cleanSpeed($value)
    {
        if(preg_match('!Entre [0-9]+ et ([0-9]+) tr.+!is', $value, $matches))
        {
            $value = $matches[1];
        }
        $value = str_ireplace('tr/min', '', $value);
        $value = trim($value);
        $value = (float)($value);
        return $value;
    }

    protected function cleanKg($value)
    {
        $value = str_ireplace('kg', '', $value);
        $value = str_ireplace('k', '', $value);
        $value = trim($value);
        $value = str_ireplace(',', '.', $value);
        $value = (float)($value);
        return $value;
    }

    protected function cleanAnnualCost($value)
    {
        $value = str_ireplace(' euros', '', $value);
        $value = str_ireplace(' € (approximatif)', '', $value);
        $value = trim($value);
        $value = str_ireplace(',', '.', $value);
        $value = (float)($value);
        return $value;
    }

    protected function cleanFourNettoyage($value)
    {
        $value = str_ireplace('a ', '', $value);
        $value = trim($value);
        $value = strtolower($value);
        return $value;
    }
    protected function cleanLargeur($value)
    {
        $value = str_ireplace('cm', '', $value);
        $value = trim($value);
        $value = (float)($value);
        if($value >= 59 && $value <= 61)
        {
            $value = 60;
        }
        return $value;
    }
    
    protected function urlMatchModel($newUrl, $model)
    {
        if(stripos($newUrl, $model) !== false)
        {
            return true;
        }
        if(stripos($newUrl, urlencode($model)) !== false)
        {
            return true;
        }
        if(stripos($newUrl, rawurlencode($model)) !== false)
        {
            return true;
        }
        if(stripos($newUrl, str_replace(' ', '-', $model)) !== false)
        {
            return true;
        }
        if(strpos($model, ' ') !== false)
        {
            $tmp = array_filter(explode(' ', $model));
            
            foreach($tmp as $term)
            {
                if( ! $this->urlMatchModel($newUrl, $term))
                {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
}