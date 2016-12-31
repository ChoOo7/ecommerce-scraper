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
    
    /**
     * EcomInfo constructor.
     * @param EcomPrice $ecomPrice
     */
    public function __construct(EcomPrice $ecomPrice)
    {
        $this->ecomPriceService = $ecomPrice;
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
        return $infos;
    }
    
    protected function getProvidersOfType($productType)
    {
        switch($productType)
        {
            case 'aspirateur':
                return array('Darty', 'Boulanger', 'WebDistrib', 'Amazon', 'ElectroMenagerCompare');
            
            case 'machinealaver':
                return array('Darty', 'Boulanger', 'WebDistrib', 'Amazon', 'ElectroMenagerCompare');
        }
        return array();
    }

    protected function getInfosOfFromDarty($ean, $productType, $parametersInfos)
    {
        $infos = array();

        $url = 'http://www.darty.com/nav/recherche?text='.urlencode($ean);

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

                $value = trim($tmp[2]);
                $infos['filtration'] = $value;
            }
        });
        $crawler->filter('.product_bloc_caracteristics tr')->each(function ($node) use (& $infos){
            $textNode = $node->text();
            $textLabel = trim($node->filter('th')->text());
            $value = trim($node->filter('td')->text());
            $infos = $this->aspirateurGetInfosFromLabel($infos, $textLabel, $value);
        });

        if( ! empty($infos))
        {
            $infos['uri'] = $crawler->getUri();
            $infos['price'] = $this->ecomPriceService->getPrice($infos['uri']);
        }

        return $infos;
    }

    protected function getInfosOfFromBoulanger($ean, $productType, $parametersInfos)
    {
        $infos = array();

        $url = 'http://www.boulanger.com/resultats?tr='.urlencode($ean);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        $crawler->filter('span[itemprop="brand"]')->each(function ($node) use (& $infos){
            $infos['brand'] = $node->text();
        });
        
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


    protected function getInfosOfFromWebDistrib($ean, $productType, $parametersInfos)
    {
        $infos = array();

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

        if( ! empty($infos))
        {
            $infos['uri'] = $crawler->getUri();
            $infos['price'] = $this->ecomPriceService->getPrice($infos['uri']);
        }

        return $infos;
    }


    protected function getInfosOfFromAmazon($ean, $productType, $parametersInfos)
    {
        $infos = array();

        $url = 'https://www.amazon.fr/s/ref=nb_sb_noss?__mk_fr_FR=%C3%85M%C3%85%C5%BD%C3%95%C3%91&url=search-alias%3Daps&field-keywords='.urlencode($ean);

        $client = new \Goutte\Client();
        $crawler = $client->request('GET', $url);

        $newUrl = null;
        $crawler->filter('#result_0 a')->eq(0)->each(function ($node) use (& $newUrl){
            $newUrl = $node->attr('href');
        });
        if($newUrl == null)
        {
            return null;
        }

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

        if( ! empty($infos))
        {
            $infos['uri'] = $crawler->getUri();
            $infos['price'] = $this->ecomPriceService->getPrice($infos['uri']);
        }

        return $infos;
    }

    protected function getInfosOfFromElectroMenagerCompare($ean, $productType, $parametersInfos)
    {
        $infos = array();

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

        if( ! empty($infos))
        {
            $infos['uri'] = $crawler->getUri();
            $price = $this->ecomPriceService->getPrice($infos['uri']);
            if($price)
            {
                $infos['price'] = $price;
            }
        }
        return $infos;
    }
    
    protected function aspirateurGetInfosFromLabel($infos, $textLabel, $value)
    {
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
        if($textLabel == 'Capacité' && (stripos($value, 'litre')!== false || stripos($value, 'l')!== false))
        {
            $textLabel='CapacitéVolume';
        }
        if($textLabel == 'Code' && array_key_exists('model', $infos))
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
                $infos['annualCost'] = $this->cleanAnnualCost($value);
                break;
            case 'Consommation d\'énergie annuelle':
            case 'Consommation d\'energie annuelle':
            case 'Consommation d\'énergie en lavage':
            case 'Consommation  électrique annuelle':
            case 'Lavage annuelle':
            case 'Consommation électrique annuelle':
            
                $infos['annualConsumtion'] = $this->cleanAnnualConsumtion($value);
                break;
            case 'Consommation d\'eau en lavage':
            case 'Consommation d\'eau annuelle':
                $infos['annualWaterConsumtion'] = $this->cleanAnnualConsumtion($value);
                break;
            
            case 'Classe de qualité de filtration':
                $infos['filtration'] = $value;
                break;
            case 'Niveau sonore':
                $infos['bruit'] = $this->cleanBruit($value);
                break;
            case 'Classe d\'efficacité énergétique':
            case 'Classe énergie':
            case 'Classe énergétique':
            case 'Efficacité énergétique (10 niveaux)':
            case 'Efficacité énergétique':
                $infos['energyClass'] = $this->cleanEnergyClass($value);
                break;

            case 'Classe efficacité Lavage':
                $infos['washEnergyClass'] = $this->cleanEnergyClass($value);
                break;
            case 'Puissance maxi consommée':
            case 'Puissance normale':
            case 'Puissance':
                $infos['puissance'] = $this->cleanPuissance($value);
                break;
            case 'Modèle':
            case 'Code':
                $infos['model'] = $value;
                break;
            case 'Gamme':
            case 'Marque':
                $infos['brand'] = $value;
                break;
            case 'Vitesse d\'essorage':
            case 'Essorage':
                $infos['vitesseEssorage'] = $this->cleanSpeed($value);
                break;

            case 'Qualité d\'essorage':
            case 'Classe d\'efficacité à l\'essorage':
            case 'Classe efficacité Essorage':
            case 'Efficacité d\'essorage':
                $infos['qualiteEssorage'] = $this->cleanEnergyClass($value);
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
        $value = trim($value);
        $value = str_replace(' ', '', $value);
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
}